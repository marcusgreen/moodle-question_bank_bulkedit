<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace qbank_bulkedit\local\ai;

/**
 * Sends prompts to the configured AI backend.
 *
 * Supports the Moodle core AI subsystem (default), local_ai_manager and the legacy
 * tool_aiconnect. Get it with \core\di::get() so tests can replace it.
 *
 * @package    qbank_bulkedit
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class request_provider {
    /** @var string Moodle core AI subsystem. */
    public const BACKEND_CORE = 'core_ai_subsystem';
    /** @var string The local_ai_manager plugin. */
    public const BACKEND_AIMANAGER = 'local_ai_manager';
    /** @var string The legacy tool_aiconnect plugin. */
    public const BACKEND_AICONNECT = 'tool_aimanager';

    /**
     * The backends that are installed on this site.
     *
     * @return string[] backend => display name
     */
    public static function get_installed_backends(): array {
        $backends = [];
        if (class_exists(\core_ai\manager::class)) {
            $backends[self::BACKEND_CORE] = get_string('backend_core', 'qbank_bulkedit');
        }
        if (class_exists('\local_ai_manager\manager')) {
            $backends[self::BACKEND_AIMANAGER] = get_string('backend_aimanager', 'qbank_bulkedit');
        }
        if (class_exists('\tool_aiconnect\ai\ai')) {
            $backends[self::BACKEND_AICONNECT] = get_string('backend_aiconnect', 'qbank_bulkedit');
        }
        return $backends;
    }

    /**
     * The configured backend.
     *
     * @return string
     */
    public function get_backend(): string {
        return get_config('qbank_bulkedit', 'backend') ?: self::BACKEND_CORE;
    }

    /**
     * The local_ai_manager purpose to use.
     *
     * @return string
     */
    protected function get_purpose(): string {
        return get_config('qbank_bulkedit', 'purpose') ?: 'singleprompt';
    }

    /**
     * Whether AI can be used in this context.
     *
     * @param int $contextid
     * @return bool
     */
    public function is_available(int $contextid): bool {
        return $this->get_unavailability_reason($contextid) === null;
    }

    /**
     * Why AI cannot be used in this context.
     *
     * @param int $contextid
     * @return string|null a message for the user, or null if AI is available
     */
    public function get_unavailability_reason(int $contextid): ?string {
        switch ($this->get_backend()) {
            case self::BACKEND_AIMANAGER:
                return $this->get_unavailability_reason_aimanager($contextid);
            case self::BACKEND_AICONNECT:
                return class_exists('\tool_aiconnect\ai\ai') ? null : get_string('ainotavailable', 'qbank_bulkedit');
            default:
                return $this->get_unavailability_reason_core($contextid);
        }
    }

    /**
     * Whether the user must accept the AI usage policy before a request.
     *
     * Only the core subsystem has a policy that this plugin has to ask for.
     * local_ai_manager handles its own terms of use and reports them as unavailability.
     *
     * @param int $userid
     * @return bool
     */
    public function needs_policy_acceptance(int $userid): bool {
        if ($this->get_backend() !== self::BACKEND_CORE) {
            return false;
        }
        return !\core_ai\manager::get_user_policy_status($userid);
    }

    /**
     * Record that the user accepted the AI usage policy.
     *
     * @param int $userid
     * @param int $contextid
     */
    public function accept_policy(int $userid, int $contextid): void {
        if ($this->get_backend() === self::BACKEND_CORE) {
            \core_ai\manager::user_policy_accepted($userid, $contextid);
        }
    }

    /**
     * Send a prompt and return the response.
     *
     * The user must be the current user: local_ai_manager always bills the logged in user.
     *
     * @param string $prompt
     * @param int $contextid
     * @param int $userid
     * @return array{content: string, model: ?string}
     * @throws \moodle_exception if the request fails
     */
    public function perform_request(string $prompt, int $contextid, int $userid): array {
        global $USER;
        if ((int) $USER->id !== $userid) {
            throw new \moodle_exception('err_wronguser', 'qbank_bulkedit');
        }
        $reason = $this->get_unavailability_reason($contextid);
        if ($reason !== null) {
            throw new \moodle_exception('err_airequest', 'qbank_bulkedit', '', $reason);
        }
        switch ($this->get_backend()) {
            case self::BACKEND_AIMANAGER:
                return $this->request_aimanager($prompt, $contextid);
            case self::BACKEND_AICONNECT:
                return $this->request_aiconnect($prompt);
            default:
                return $this->request_core($prompt, $contextid, $userid);
        }
    }

    /**
     * Why the core AI subsystem cannot be used.
     *
     * @param int $contextid
     * @return string|null
     */
    protected function get_unavailability_reason_core(int $contextid): ?string {
        if (!class_exists(\core_ai\manager::class)) {
            return get_string('ainotavailable', 'qbank_bulkedit');
        }
        $manager = \core\di::get(\core_ai\manager::class);
        if (!$manager->is_action_available(\core_ai\aiactions\generate_text::class)) {
            return get_string('ainotavailable', 'qbank_bulkedit');
        }
        if (method_exists($manager, 'is_action_enabled_in_context')) {
            $context = \core\context::instance_by_id($contextid);
            if (!$manager->is_action_enabled_in_context($context, \core_ai\aiactions\generate_text::class)) {
                return get_string('ainotenabledhere', 'qbank_bulkedit');
            }
        }
        return null;
    }

    /**
     * Why local_ai_manager cannot be used.
     *
     * @param int $contextid
     * @return string|null
     */
    protected function get_unavailability_reason_aimanager(int $contextid): ?string {
        global $USER;
        if (!class_exists('\local_ai_manager\ai_manager_utils')) {
            return get_string('ainotavailable', 'qbank_bulkedit');
        }
        $purpose = $this->get_purpose();
        $config = \local_ai_manager\ai_manager_utils::get_ai_config($USER, $contextid, null, [$purpose]);
        $available = \local_ai_manager\ai_manager_utils::AVAILABILITY_AVAILABLE;
        if (empty($config['availability']) || $config['availability']['available'] !== $available) {
            return ($config['availability']['errormessage'] ?? '') ?: get_string('ainotavailable', 'qbank_bulkedit');
        }
        foreach ($config['purposes'] as $purposeconfig) {
            if ($purposeconfig['purpose'] === $purpose) {
                if ($purposeconfig['available'] === $available) {
                    return null;
                }
                return $purposeconfig['errormessage'] ?: get_string('ainotavailable', 'qbank_bulkedit');
            }
        }
        return get_string('ainotavailable', 'qbank_bulkedit');
    }

    /**
     * Send the prompt to the core AI subsystem.
     *
     * @param string $prompt
     * @param int $contextid
     * @param int $userid
     * @return array{content: string, model: ?string}
     */
    protected function request_core(string $prompt, int $contextid, int $userid): array {
        $action = new \core_ai\aiactions\generate_text(
            contextid: $contextid,
            userid: $userid,
            prompttext: $prompt,
        );
        $response = \core\di::get(\core_ai\manager::class)->process_action($action);
        $data = $response->get_response_data();
        if (!$response->get_success() || !is_array($data) || !isset($data['generatedcontent'])) {
            $message = $response->get_errormessage() ?: get_string('ainotavailable', 'qbank_bulkedit');
            throw new \moodle_exception('err_airequest', 'qbank_bulkedit', '', $message);
        }
        return ['content' => (string) $data['generatedcontent'], 'model' => $response->get_model_used()];
    }

    /**
     * Send the prompt to local_ai_manager.
     *
     * @param string $prompt
     * @param int $contextid
     * @return array{content: string, model: ?string}
     */
    protected function request_aimanager(string $prompt, int $contextid): array {
        $manager = new \local_ai_manager\manager($this->get_purpose());
        $response = $manager->perform_request($prompt, 'qbank_bulkedit', $contextid);
        if ($response->get_code() !== 200) {
            throw new \moodle_exception(
                'err_airequest',
                'qbank_bulkedit',
                '',
                $response->get_errormessage(),
                $response->get_debuginfo()
            );
        }
        return ['content' => $response->get_content(), 'model' => $response->get_modelinfo() ?: null];
    }

    /**
     * Send the prompt to tool_aiconnect.
     *
     * @param string $prompt
     * @return array{content: string, model: ?string}
     */
    protected function request_aiconnect(string $prompt): array {
        $ai = new \tool_aiconnect\ai\ai();
        $response = $ai->prompt_completion($prompt);
        $content = $response['response']['choices'][0]['message']['content'] ?? null;
        if ($content === null) {
            throw new \moodle_exception('err_airequest', 'qbank_bulkedit', '', get_string('ainotavailable', 'qbank_bulkedit'));
        }
        return ['content' => (string) $content, 'model' => $response['response']['model'] ?? null];
    }
}
