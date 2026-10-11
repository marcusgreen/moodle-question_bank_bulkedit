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

use qbank_bulkedit\local\field\field;
use qbank_bulkedit\local\rule\rule;
use qbank_bulkedit\local\rule\validator;

/**
 * Turns a plain language instruction into a rule, using AI.
 *
 * The AI only writes the rule. The rule is then run by the engine, so the
 * preview and the applied change are always the same.
 *
 * @package    qbank_bulkedit
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rule_builder {
    /** @var int Maximum characters of each sample value sent to the AI. */
    public const MAX_SAMPLE_LENGTH = 2000;

    /**
     * Build a rule.
     *
     * @param string $instruction what the user wants to change
     * @param field $field the field being edited
     * @param string[] $qtypes the question types being edited
     * @param rule|null $current the current rule, to refine
     * @param string[] $samples example field values the user agreed to send
     * @param int $contextid
     * @param int $userid
     * @return array{rule: rule, model: ?string}
     * @throws \moodle_exception if the request fails or the response is not a valid rule
     */
    public function build(
        string $instruction,
        field $field,
        array $qtypes,
        ?rule $current,
        array $samples,
        int $contextid,
        int $userid,
    ): array {
        $prompt = $this->get_prompt($instruction, $field, $qtypes, $current, $samples);
        $provider = \core\di::get(request_provider::class);
        $response = $provider->perform_request($prompt, $contextid, $userid);
        $rule = self::parse_response($response['content']);
        $error = validator::validate($rule);
        if ($error !== null) {
            throw new \moodle_exception('err_airule', 'qbank_bulkedit', '', $error);
        }
        return ['rule' => $rule, 'model' => $response['model']];
    }

    /**
     * Build the prompt.
     *
     * @param string $instruction
     * @param field $field
     * @param string[] $qtypes
     * @param rule|null $current
     * @param string[] $samples
     * @return string
     */
    public function get_prompt(string $instruction, field $field, array $qtypes, ?rule $current, array $samples): string {
        $systemprompt = get_config('qbank_bulkedit', 'systemprompt');
        if (empty(trim((string) $systemprompt))) {
            $systemprompt = get_string('systemprompt_default', 'qbank_bulkedit');
        }
        $parts = [trim($systemprompt)];
        $format = $field->is_editor() ? 'HTML' : 'plain text';
        $parts[] = "=== FIELD ===\n" . $field->get_label() . ' (' . $format . '), question types: ' . implode(', ', $qtypes);
        if ($current !== null) {
            $parts[] = "=== CURRENT RULE (refine this) ===\n" . $current->to_json();
        }
        foreach (array_values($samples) as $i => $sample) {
            $parts[] = '=== SAMPLE VALUE ' . ($i + 1) . " ===\n" . \core_text::substr($sample, 0, self::MAX_SAMPLE_LENGTH);
        }
        $parts[] = "=== INSTRUCTION ===\n" . trim($instruction);
        return implode("\n\n", $parts);
    }

    /**
     * Parse the AI response into a rule.
     *
     * @param string $content
     * @return rule
     * @throws \moodle_exception if the response is not a rule
     */
    public static function parse_response(string $content): rule {
        $start = strpos($content, '{');
        $end = strrpos($content, '}');
        if ($start === false || $end === false || $end < $start) {
            throw new \moodle_exception('err_airesponse', 'qbank_bulkedit', '', s(shorten_text($content, 200)));
        }
        $data = json_decode(substr($content, $start, $end - $start + 1), true);
        if (!is_array($data) || !isset($data['mode']) || !in_array($data['mode'], rule::get_modes(), true)) {
            throw new \moodle_exception('err_airesponse', 'qbank_bulkedit', '', s(shorten_text($content, 200)));
        }
        $data['caseinsensitive'] = !empty($data['caseinsensitive']) || str_contains((string) ($data['flags'] ?? ''), 'i');
        return rule::from_array($data);
    }
}
