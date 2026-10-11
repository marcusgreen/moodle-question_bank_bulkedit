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

namespace qbank_bulkedit\local;

use qbank_bulkedit\local\field\field;
use qbank_bulkedit\local\field\registry;
use qbank_bulkedit\local\rule\engine;
use qbank_bulkedit\local\rule\rule;

/**
 * A bulk edit job: selected questions, the field, the rule, the preview and the result.
 *
 * @package    qbank_bulkedit
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class job {
    /** @var string Being set up. */
    public const STATUS_DRAFT = 'draft';
    /** @var string Preview shown, ready to apply. */
    public const STATUS_PREVIEWED = 'previewed';
    /** @var string Queued or running as an adhoc task. */
    public const STATUS_APPLYING = 'applying';
    /** @var string Finished. */
    public const STATUS_DONE = 'done';
    /** @var string The task failed. */
    public const STATUS_FAILED = 'failed';

    /** @var string Not yet previewed. */
    public const ITEM_PENDING = 'pending';
    /** @var string The rule changes this question. */
    public const ITEM_CHANGE = 'change';
    /** @var string The rule does not change this question. */
    public const ITEM_NOMATCH = 'nomatch';
    /** @var string The question type was not selected. */
    public const ITEM_WRONGQTYPE = 'wrongqtype';
    /** @var string The question type does not have the field. */
    public const ITEM_NOFIELD = 'nofield';
    /** @var string The user cannot edit this question. */
    public const ITEM_NOPERMISSION = 'nopermission';
    /** @var string The question changed since it was selected or previewed. */
    public const ITEM_STALE = 'stale';
    /** @var string The user unticked the question in the preview. */
    public const ITEM_EXCLUDED = 'excluded';
    /** @var string A new version was saved. */
    public const ITEM_UPDATED = 'updated';
    /** @var string Saving failed. */
    public const ITEM_FAILED = 'failed';

    /**
     * Constructor.
     *
     * @param \stdClass $record the job record
     */
    private function __construct(
        /** @var \stdClass the job record */
        private \stdClass $record,
    ) {
        global $CFG;
        // The question bank and capability functions are not autoloaded, and jobs also run in tasks.
        require_once($CFG->libdir . '/questionlib.php');
    }

    /**
     * Create a job for the selected questions.
     *
     * @param int $cmid course module of the question bank
     * @param string $returnurl local URL to return to
     * @param int[] $questionids selected question ids
     * @return self
     */
    public static function create(int $cmid, string $returnurl, array $questionids): self {
        global $DB, $USER;
        $context = \core\context\module::instance($cmid);
        $now = time();
        $record = (object) [
            'contextid' => $context->id,
            'cmid' => $cmid,
            'userid' => $USER->id,
            'returnurl' => $returnurl,
            'status' => self::STATUS_DRAFT,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $transaction = $DB->start_delegated_transaction();
        $record->id = $DB->insert_record('qbank_bulkedit_job', $record);
        foreach (array_unique(array_map('intval', $questionids)) as $questionid) {
            $DB->insert_record('qbank_bulkedit_item', (object) [
                'jobid' => $record->id,
                'questionid' => $questionid,
                'included' => 1,
                'status' => self::ITEM_PENDING,
            ]);
        }
        $transaction->allow_commit();
        return new self($DB->get_record('qbank_bulkedit_job', ['id' => $record->id], '*', MUST_EXIST));
    }

    /**
     * Load a job.
     *
     * @param int $jobid
     * @return self
     */
    public static function load(int $jobid): self {
        global $DB;
        return new self($DB->get_record('qbank_bulkedit_job', ['id' => $jobid], '*', MUST_EXIST));
    }

    /**
     * Throw an exception unless the current user owns the job.
     */
    public function require_owner(): void {
        global $USER;
        if ((int) $this->record->userid !== (int) $USER->id) {
            throw new \moodle_exception('err_notowner', 'qbank_bulkedit');
        }
    }

    /**
     * Job id.
     *
     * @return int
     */
    public function get_id(): int {
        return (int) $this->record->id;
    }

    /**
     * The job record.
     *
     * @return \stdClass
     */
    public function get_record(): \stdClass {
        return $this->record;
    }

    /**
     * Context of the question bank.
     *
     * @return \core\context
     */
    public function get_context(): \core\context {
        return \core\context::instance_by_id($this->record->contextid);
    }

    /**
     * Course module id of the question bank.
     *
     * @return int
     */
    public function get_cmid(): int {
        return (int) $this->record->cmid;
    }

    /**
     * URL of the question bank page to return to.
     *
     * @return \moodle_url
     */
    public function get_return_url(): \moodle_url {
        if (!empty($this->record->returnurl)) {
            return new \moodle_url($this->record->returnurl);
        }
        return new \moodle_url('/question/edit.php', ['cmid' => $this->record->cmid]);
    }

    /**
     * The status.
     *
     * @return string
     */
    public function get_status(): string {
        return $this->record->status;
    }

    /**
     * The rule, if one has been set.
     *
     * @return rule|null
     */
    public function get_rule(): ?rule {
        return empty($this->record->rule) ? null : rule::from_json($this->record->rule);
    }

    /**
     * The rule last returned by the AI, if any.
     *
     * @return rule|null
     */
    public function get_ai_rule(): ?rule {
        return empty($this->record->airule) ? null : rule::from_json($this->record->airule);
    }

    /**
     * The field, if one has been chosen.
     *
     * @return field|null
     */
    public function get_field(): ?field {
        return empty($this->record->field) ? null : registry::get_field($this->record->field);
    }

    /**
     * The chosen question types.
     *
     * @return string[]
     */
    public function get_qtypes(): array {
        return empty($this->record->qtypes) ? [] : explode(',', $this->record->qtypes);
    }

    /**
     * The question types of the selected questions, with counts.
     *
     * @return int[] qtype => number of selected questions
     */
    public function get_selection_qtypes(): array {
        global $DB;
        $sql = "SELECT q.qtype, COUNT(1) AS total
                  FROM {qbank_bulkedit_item} i
                  JOIN {question} q ON q.id = i.questionid
                 WHERE i.jobid = :jobid
              GROUP BY q.qtype
              ORDER BY q.qtype";
        return array_map('intval', $DB->get_records_sql_menu($sql, ['jobid' => $this->get_id()]));
    }

    /**
     * Sample values of the field from the selected questions, for the AI.
     *
     * @param int $max
     * @return string[]
     */
    public function get_sample_values(int $max): array {
        $field = $this->get_field();
        if (!$field || $max <= 0) {
            return [];
        }
        $samples = [];
        foreach ($this->get_items() as $item) {
            $questiondata = $this->load_question_data($item->questionid);
            if (!$questiondata || !$this->is_editable($questiondata, $field)) {
                continue;
            }
            $value = $field->get_value($questiondata);
            if ($value !== null && trim($value) !== '') {
                $samples[] = $value;
            }
            if (count($samples) >= $max) {
                break;
            }
        }
        return $samples;
    }

    /**
     * Save the setup choices.
     *
     * @param string[] $qtypes
     * @param string $fieldname
     * @param rule|null $rule
     * @param string|null $instruction
     */
    public function save_settings(array $qtypes, string $fieldname, ?rule $rule, ?string $instruction = null): void {
        $this->update([
            'qtypes' => implode(',', $qtypes),
            'field' => $fieldname,
            'rule' => $rule?->to_json(),
            'aiinstruction' => $instruction ?? $this->record->aiinstruction,
            'status' => self::STATUS_DRAFT,
        ]);
    }

    /**
     * Save a rule built by the AI. It becomes the current rule.
     *
     * @param rule $rule
     * @param string $backend
     * @param string|null $model
     */
    public function save_ai_rule(rule $rule, string $backend, ?string $model): void {
        $this->update([
            'rule' => $rule->to_json(),
            'airule' => $rule->to_json(),
            'aibackend' => $backend,
            'aimodel' => $model === null ? null : \core_text::substr($model, 0, 100),
        ]);
    }

    /**
     * The items of this job.
     *
     * @return \stdClass[]
     */
    public function get_items(): array {
        global $DB;
        return $DB->get_records('qbank_bulkedit_item', ['jobid' => $this->get_id()], 'id');
    }

    /**
     * Work out what the rule does to each selected question, and store it.
     *
     * Nothing is written to the questions.
     *
     * @return \stdClass[] one row per item with itemid, questionid, name, qtype, status, error, old, new, included
     */
    public function preview(): array {
        global $DB;
        $rule = $this->get_rule();
        $field = $this->get_field();
        if (!$rule || !$field) {
            throw new \moodle_exception('err_notready', 'qbank_bulkedit');
        }
        $rows = [];
        foreach ($this->get_items() as $item) {
            $row = $this->evaluate($item, $rule, $field);
            $DB->update_record('qbank_bulkedit_item', (object) [
                'id' => $item->id,
                'status' => $row->status,
                'oldhash' => $row->old === null ? null : sha1($row->old),
                'error' => $row->error,
                'included' => 1,
            ]);
            $row->included = true;
            $rows[] = $row;
        }
        $this->update(['status' => self::STATUS_PREVIEWED]);
        return $rows;
    }

    /**
     * Rows for the result page.
     *
     * @return \stdClass[] one row per item with itemid, questionid, newquestionid, name, qtype, status, error
     */
    public function get_result_rows(): array {
        global $DB;
        $sql = "SELECT i.id AS itemid, i.questionid, i.newquestionid, i.status, i.error, q.name, q.qtype,
                       qv.questionbankentryid AS entryid
                  FROM {qbank_bulkedit_item} i
             LEFT JOIN {question} q ON q.id = i.questionid
             LEFT JOIN {question_versions} qv ON qv.questionid = i.questionid
                 WHERE i.jobid = :jobid
              ORDER BY i.id";
        return array_values($DB->get_records_sql($sql, ['jobid' => $this->get_id()]));
    }

    /**
     * Record which previewed changes the user wants applied.
     *
     * @param int[] $itemids items to apply
     * @return int number of items that will be applied
     */
    public function set_included(array $itemids): int {
        global $DB;
        if ($this->get_status() !== self::STATUS_PREVIEWED) {
            throw new \moodle_exception('err_notready', 'qbank_bulkedit');
        }
        $count = 0;
        foreach ($this->get_items() as $item) {
            if ($item->status !== self::ITEM_CHANGE) {
                continue;
            }
            $included = in_array((int) $item->id, $itemids) ? 1 : 0;
            $count += $included;
            $DB->set_field('qbank_bulkedit_item', 'included', $included, ['id' => $item->id]);
        }
        return $count;
    }

    /**
     * Queue the job to be applied by an adhoc task.
     */
    public function queue_apply(): void {
        $this->update(['status' => self::STATUS_APPLYING]);
        $task = new \qbank_bulkedit\task\apply_job();
        $task->set_custom_data(['jobid' => $this->get_id()]);
        $task->set_userid($this->record->userid);
        \core\task\manager::queue_adhoc_task($task);
    }

    /**
     * Apply the rule to the included questions, saving a new version of each.
     *
     * Each question is checked again first. If it changed since the preview it is skipped.
     */
    public function apply(): void {
        global $DB;
        if (!in_array($this->get_status(), [self::STATUS_PREVIEWED, self::STATUS_APPLYING])) {
            throw new \moodle_exception('err_notready', 'qbank_bulkedit');
        }
        $this->update(['status' => self::STATUS_APPLYING]);
        $rule = $this->get_rule();
        $field = $this->get_field();
        if (!$rule || !$field) {
            throw new \moodle_exception('err_notready', 'qbank_bulkedit');
        }
        foreach ($this->get_items() as $item) {
            if ($item->status !== self::ITEM_CHANGE) {
                continue;
            }
            $update = (object) ['id' => $item->id];
            if (!$item->included) {
                $update->status = self::ITEM_EXCLUDED;
                $DB->update_record('qbank_bulkedit_item', $update);
                continue;
            }
            $row = $this->evaluate($item, $rule, $field);
            if ($row->status === self::ITEM_CHANGE && sha1($row->old) !== $item->oldhash) {
                $row->status = self::ITEM_STALE;
            }
            $update->status = $row->status;
            $update->error = $row->error;
            if ($row->status === self::ITEM_CHANGE) {
                try {
                    $update->newquestionid = version_writer::write((int) $item->questionid, $field, $row->new);
                    $update->status = self::ITEM_UPDATED;
                } catch (\Throwable $e) {
                    $update->status = self::ITEM_FAILED;
                    $update->error = $e->getMessage();
                }
            }
            $DB->update_record('qbank_bulkedit_item', $update);
        }
        $this->finish();
    }

    /**
     * Mark the job failed, for example when the task throws.
     *
     * @param string $error
     */
    public function mark_failed(string $error): void {
        $this->update(['status' => self::STATUS_FAILED]);
        debugging('qbank_bulkedit job ' . $this->get_id() . ' failed: ' . $error, DEBUG_DEVELOPER);
    }

    /**
     * Delete the job and its items.
     */
    public function delete(): void {
        global $DB;
        $DB->delete_records('qbank_bulkedit_item', ['jobid' => $this->get_id()]);
        $DB->delete_records('qbank_bulkedit_job', ['id' => $this->get_id()]);
    }

    /**
     * Delete jobs last changed before a time.
     *
     * @param int $before timestamp
     * @return int number of jobs deleted
     */
    public static function purge(int $before): int {
        global $DB;
        $jobids = $DB->get_fieldset_select(
            'qbank_bulkedit_job',
            'id',
            'timemodified < ? AND status <> ?',
            [$before, self::STATUS_APPLYING]
        );
        foreach (array_chunk($jobids, 500) as $chunk) {
            [$insql, $params] = $DB->get_in_or_equal($chunk);
            $DB->delete_records_select('qbank_bulkedit_item', "jobid $insql", $params);
            $DB->delete_records_select('qbank_bulkedit_job', "id $insql", $params);
        }
        return count($jobids);
    }

    /**
     * Work out what the rule does to one item.
     *
     * @param \stdClass $item
     * @param rule $rule
     * @param field $field
     * @return \stdClass with itemid, questionid, name, qtype, status, error, old, new
     */
    private function evaluate(\stdClass $item, rule $rule, field $field): \stdClass {
        $row = (object) [
            'itemid' => (int) $item->id,
            'questionid' => (int) $item->questionid,
            'name' => '',
            'qtype' => '',
            'status' => self::ITEM_CHANGE,
            'error' => null,
            'old' => null,
            'new' => null,
        ];
        $questiondata = $this->load_question_data($item->questionid);
        if (!$questiondata) {
            $row->status = self::ITEM_FAILED;
            $row->error = get_string('questiondoesnotexist', 'question');
            return $row;
        }
        $row->name = $questiondata->name;
        $row->qtype = $questiondata->qtype;
        if (!in_array($questiondata->qtype, $this->get_qtypes())) {
            $row->status = self::ITEM_WRONGQTYPE;
            return $row;
        }
        if (!$field->supports_qtype($questiondata->qtype)) {
            $row->status = self::ITEM_NOFIELD;
            return $row;
        }
        if (
            (int) $questiondata->contextid !== (int) $this->record->contextid
                || !question_has_capability_on($questiondata, 'edit')
        ) {
            $row->status = self::ITEM_NOPERMISSION;
            return $row;
        }
        if ($this->get_latest_version_id((int) $item->questionid) !== (int) $item->questionid) {
            $row->status = self::ITEM_STALE;
            return $row;
        }
        $row->old = $field->get_value($questiondata);
        if ($row->old === null) {
            $row->status = self::ITEM_NOFIELD;
            return $row;
        }
        try {
            $row->new = engine::apply($rule, $row->old);
        } catch (\moodle_exception $e) {
            $row->status = self::ITEM_FAILED;
            $row->error = $e->getMessage();
            return $row;
        }
        if ($row->new === null) {
            $row->status = self::ITEM_NOMATCH;
        }
        return $row;
    }

    /**
     * Whether a question can be edited by this job.
     *
     * @param \stdClass $questiondata
     * @param field $field
     * @return bool
     */
    private function is_editable(\stdClass $questiondata, field $field): bool {
        return in_array($questiondata->qtype, $this->get_qtypes())
            && $field->supports_qtype($questiondata->qtype)
            && (int) $questiondata->contextid === (int) $this->record->contextid
            && question_has_capability_on($questiondata, 'edit');
    }

    /**
     * Load question data, or null if the question no longer exists.
     *
     * @param int $questionid
     * @return \stdClass|null
     */
    private function load_question_data(int $questionid): ?\stdClass {
        try {
            return \question_bank::load_question_data($questionid);
        } catch (\dml_exception $e) {
            return null;
        }
    }

    /**
     * The id of the latest version of the question's bank entry.
     *
     * @param int $questionid
     * @return int
     */
    private function get_latest_version_id(int $questionid): int {
        global $DB;
        $sql = "SELECT qv.questionid
                  FROM {question_versions} qv
                 WHERE qv.questionbankentryid = (
                           SELECT qv2.questionbankentryid
                             FROM {question_versions} qv2
                            WHERE qv2.questionid = :questionid
                       )
              ORDER BY qv.version DESC";
        $records = $DB->get_records_sql($sql, ['questionid' => $questionid], 0, 1);
        return $records ? (int) reset($records)->questionid : 0;
    }

    /**
     * Count the results, mark the job done and trigger the event.
     */
    private function finish(): void {
        global $DB;
        $counts = $DB->get_records_sql_menu(
            "SELECT status, COUNT(1) FROM {qbank_bulkedit_item} WHERE jobid = ? GROUP BY status",
            [$this->get_id()]
        );
        $updated = (int) ($counts[self::ITEM_UPDATED] ?? 0);
        $failed = (int) ($counts[self::ITEM_FAILED] ?? 0);
        $this->update([
            'status' => self::STATUS_DONE,
            'countupdated' => $updated,
            'countfailed' => $failed,
            'countskipped' => array_sum($counts) - $updated - $failed,
        ]);
        \qbank_bulkedit\event\job_applied::create([
            'context' => $this->get_context(),
            'objectid' => $this->get_id(),
            'relateduserid' => null,
            'other' => [
                'field' => $this->record->field,
                'updated' => $updated,
                'failed' => $failed,
                'aibackend' => $this->record->aibackend,
            ],
        ])->trigger();
    }

    /**
     * Update the job record.
     *
     * @param array $fields
     */
    private function update(array $fields): void {
        global $DB;
        foreach ($fields as $name => $value) {
            $this->record->$name = $value;
        }
        $this->record->timemodified = time();
        $DB->update_record('qbank_bulkedit_job', $this->record);
    }
}
