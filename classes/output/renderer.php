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

namespace qbank_bulkedit\output;

use qbank_bulkedit\local\helper;
use qbank_bulkedit\local\job;
use qbank_bulkedit\local\rule\rule;

/**
 * Renderer for qbank_bulkedit.
 *
 * @package    qbank_bulkedit
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends \plugin_renderer_base {
    /**
     * Render the dry run preview.
     *
     * @param job $job
     * @param \stdClass[] $rows from job::preview()
     * @return string
     */
    public function render_preview(job $job, array $rows): string {
        $changes = [];
        $skipped = [];
        foreach ($rows as $row) {
            if ($row->status === job::ITEM_CHANGE) {
                $changes[] = array_merge([
                    'itemid' => $row->itemid,
                    'name' => format_string($row->name),
                    'qtype' => $row->qtype ? \question_bank::get_qtype_name($row->qtype) : '',
                ], helper::diff($row->old, $row->new));
            } else {
                $skipped[] = $this->get_status_row($row);
            }
        }
        $synclimit = (int) get_config('qbank_bulkedit', 'synclimit');
        $context = [
            'jobid' => $job->get_id(),
            'sesskey' => sesskey(),
            'applyurl' => (new \moodle_url('/question/bank/bulkedit/apply.php'))->out(false),
            'backurl' => (new \moodle_url('/question/bank/bulkedit/setup.php', ['jobid' => $job->get_id()]))->out(false),
            'field' => $job->get_field()->get_label(),
            'rule' => $this->get_rule_summary($job->get_rule()),
            'changes' => $changes,
            'haschanges' => !empty($changes),
            'changecount' => count($changes),
            'skipped' => $skipped,
            'hasskipped' => !empty($skipped),
            'skippedcount' => count($skipped),
            'willqueue' => count($changes) > $synclimit,
            'synclimit' => $synclimit,
        ];
        return $this->render_from_template('qbank_bulkedit/preview', $context);
    }

    /**
     * Render the result of applying a job.
     *
     * @param job $job
     * @return string
     */
    public function render_result(job $job): string {
        $record = $job->get_record();
        $returnurl = $job->get_return_url();
        $rows = [];
        foreach ($job->get_result_rows() as $row) {
            $data = $this->get_status_row($row);
            if ($row->status === job::ITEM_UPDATED && $row->entryid) {
                $data['historyurl'] = (new \moodle_url('/question/bank/history/history.php', [
                    'entryid' => $row->entryid,
                    'returnurl' => $returnurl->out_as_local_url(false),
                    'cmid' => $job->get_cmid(),
                ]))->out(false);
            }
            $rows[] = $data;
        }
        $context = [
            'applying' => $job->get_status() === job::STATUS_APPLYING,
            'failed' => $job->get_status() === job::STATUS_FAILED,
            'done' => $job->get_status() === job::STATUS_DONE,
            'updated' => (int) $record->countupdated,
            'skipped' => (int) $record->countskipped,
            'errors' => (int) $record->countfailed,
            'rows' => $rows,
            'returnurl' => $returnurl->out(false),
        ];
        return $this->render_from_template('qbank_bulkedit/result', $context);
    }

    /**
     * A one line summary of a rule.
     *
     * @param rule $rule
     * @return array for the template
     */
    private function get_rule_summary(rule $rule): array {
        return [
            'mode' => get_string('mode_' . $rule->mode, 'qbank_bulkedit'),
            'find' => $rule->find,
            'hasfind' => $rule->uses_pattern(),
            'replace' => $rule->replace,
            'caseinsensitive' => $rule->caseinsensitive,
            'firstonly' => $rule->firstonly && $rule->uses_pattern(),
            'guard' => $rule->guard,
            'explanation' => $rule->explanation,
        ];
    }

    /**
     * Template data for an item that is shown with its status.
     *
     * @param \stdClass $row
     * @return array
     */
    private function get_status_row(\stdClass $row): array {
        return [
            'name' => $row->name !== null ? format_string($row->name) : get_string('questiondoesnotexist', 'question'),
            'qtype' => $row->qtype ? \question_bank::get_qtype_name($row->qtype) : '',
            'status' => get_string('status_' . $row->status, 'qbank_bulkedit'),
            'statuskey' => $row->status,
            'error' => $row->error,
        ];
    }
}
