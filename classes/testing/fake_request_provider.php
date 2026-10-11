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

namespace qbank_bulkedit\testing;

use qbank_bulkedit\local\ai\request_provider;

/**
 * Fake AI request provider for behat.
 *
 * Swapped in through the DI configuration hook when BEHAT_SITE_RUNNING is defined.
 * Controlled with plugin config so it works across web and CLI processes:
 * - behat_mock_response: text returned by requests
 * - behat_mock_error: if set, requests fail with this message
 * - behat_mock_unavailable: if set, AI is reported unavailable with this message
 *
 * @package    qbank_bulkedit
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fake_request_provider extends request_provider {
    /** @var string Response when none is configured. */
    public const DEFAULT_RESPONSE = '{"mode":"append","find":"","replace":" (mock)","caseinsensitive":false,'
        . '"firstonly":false,"guard":"(mock)","explanation":"Adds (mock) to the end."}';

    #[\Override]
    public function get_unavailability_reason(int $contextid): ?string {
        $reason = get_config('qbank_bulkedit', 'behat_mock_unavailable');
        return empty($reason) ? null : $reason;
    }

    #[\Override]
    public function needs_policy_acceptance(int $userid): bool {
        return false;
    }

    #[\Override]
    public function perform_request(string $prompt, int $contextid, int $userid): array {
        $error = get_config('qbank_bulkedit', 'behat_mock_error');
        if (!empty($error)) {
            throw new \moodle_exception('err_airequest', 'qbank_bulkedit', '', $error);
        }
        $response = get_config('qbank_bulkedit', 'behat_mock_response');
        return [
            'content' => ($response !== false && $response !== '') ? $response : self::DEFAULT_RESPONSE,
            'model' => 'mock',
        ];
    }
}
