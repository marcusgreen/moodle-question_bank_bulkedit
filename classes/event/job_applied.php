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

namespace qbank_bulkedit\event;

/**
 * Event triggered when a bulk edit job has been applied.
 *
 * @property-read array $other {
 *      - string field: the field that was edited
 *      - int updated: number of questions updated
 *      - int failed: number of questions that failed
 *      - string|null aibackend: the AI backend used to build the rule, if any
 * }
 *
 * @package    qbank_bulkedit
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class job_applied extends \core\event\base {
    #[\Override]
    protected function init() {
        $this->data['objecttable'] = 'qbank_bulkedit_job';
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
    }

    #[\Override]
    public static function get_name() {
        return get_string('eventjobapplied', 'qbank_bulkedit');
    }

    #[\Override]
    public function get_description() {
        return "The user with id '{$this->userid}' applied bulk edit job '{$this->objectid}' to the field " .
            "'{$this->other['field']}', updating {$this->other['updated']} question(s).";
    }

    #[\Override]
    public function get_url() {
        return new \moodle_url('/question/bank/bulkedit/result.php', ['jobid' => $this->objectid]);
    }

    #[\Override]
    protected function validate_data() {
        parent::validate_data();
        foreach (['field', 'updated', 'failed'] as $key) {
            if (!isset($this->other[$key])) {
                throw new \coding_exception("The '{$key}' value must be set in other.");
            }
        }
    }

    #[\Override]
    public static function get_objectid_mapping() {
        return ['db' => 'qbank_bulkedit_job', 'restore' => \core\event\base::NOT_MAPPED];
    }

    #[\Override]
    public static function get_other_mapping() {
        return false;
    }
}
