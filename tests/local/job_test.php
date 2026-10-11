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

use PHPUnit\Framework\Attributes\CoversClass;
use qbank_bulkedit\local\rule\rule;

/**
 * Tests for bulk edit jobs: preview, apply and the checks between them.
 *
 * @package    qbank_bulkedit
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \qbank_bulkedit\local\job
 * @covers     \qbank_bulkedit\local\helper
 */
#[CoversClass(\qbank_bulkedit\local\job::class)]
#[CoversClass(\qbank_bulkedit\local\helper::class)]
final class job_test extends \advanced_testcase {
    /** @var \core_question_generator */
    private \core_question_generator $generator;

    /** @var \stdClass question bank course module */
    private \stdClass $qbank;

    /** @var \stdClass question category */
    private \stdClass $category;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $this->qbank = $this->getDataGenerator()->create_module('qbank', ['course' => $course->id]);
        $this->generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $this->category = $this->generator->create_question_category([
            'contextid' => \core\context\module::instance($this->qbank->cmid)->id,
        ]);
    }

    /**
     * Create a short answer question with the given text.
     *
     * @param string $text
     * @param string $qtype
     * @return \stdClass
     */
    private function create_question(string $text, string $qtype = 'shortanswer'): \stdClass {
        $which = $qtype === 'essay' ? 'editor' : 'frogtoad';
        return $this->generator->create_question($qtype, $which, [
            'category' => $this->category->id,
            'questiontext' => ['text' => $text, 'format' => FORMAT_HTML],
        ]);
    }

    /**
     * Create a job that replaces "frog" with "toad" in the question text of short answer questions.
     *
     * @param \stdClass[] $questions
     * @return job
     */
    private function create_job(array $questions): job {
        $job = job::create(
            $this->qbank->cmid,
            '/question/edit.php?cmid=' . $this->qbank->cmid,
            array_map(fn($q) => $q->id, $questions)
        );
        $job->save_settings(['shortanswer'], 'questiontext', new rule(rule::MODE_SIMPLE, 'frog', 'toad'));
        return $job;
    }

    /**
     * Preview reports what would happen and changes nothing.
     */
    public function test_preview(): void {
        global $DB;
        $match = $this->create_question('A frog');
        $nomatch = $this->create_question('A newt');
        $essay = $this->create_question('Essay frog', 'essay');
        $job = $this->create_job([$match, $nomatch, $essay]);
        $versions = $DB->count_records('question_versions');

        $rows = $job->preview();

        $statuses = array_column(array_map(fn($r) => (array) $r, $rows), 'status', 'questionid');
        $this->assertSame(job::ITEM_CHANGE, $statuses[$match->id]);
        $this->assertSame(job::ITEM_NOMATCH, $statuses[$nomatch->id]);
        $this->assertSame(job::ITEM_WRONGQTYPE, $statuses[$essay->id]);
        $changed = array_values(array_filter($rows, fn($r) => $r->status === job::ITEM_CHANGE))[0];
        $this->assertSame('A frog', $changed->old);
        $this->assertSame('A toad', $changed->new);
        $this->assertSame($versions, $DB->count_records('question_versions'));
        $this->assertSame(job::STATUS_PREVIEWED, $job->get_status());
    }

    /**
     * Apply saves new versions of the ticked questions only.
     */
    public function test_apply(): void {
        $one = $this->create_question('One frog');
        $two = $this->create_question('Two frogs');
        $job = $this->create_job([$one, $two]);
        $rows = $job->preview();
        $itemone = array_values(array_filter($rows, fn($r) => $r->questionid == $one->id))[0]->itemid;

        $sink = $this->redirectEvents();
        $this->assertSame(1, $job->set_included([$itemone]));
        $job->apply();
        $events = array_filter($sink->get_events(), fn($e) => $e instanceof \qbank_bulkedit\event\job_applied);
        $sink->close();

        $results = [];
        foreach ($job->get_result_rows() as $row) {
            $results[$row->questionid] = $row;
        }
        $this->assertSame(job::ITEM_UPDATED, $results[$one->id]->status);
        $this->assertSame(job::ITEM_EXCLUDED, $results[$two->id]->status);
        $new = \question_bank::load_question_data($results[$one->id]->newquestionid);
        $this->assertSame('One toad', $new->questiontext);
        $this->assertSame(job::STATUS_DONE, $job->get_status());
        $this->assertEquals(1, $job->get_record()->countupdated);
        $this->assertEquals(1, $job->get_record()->countskipped);
        $this->assertCount(1, $events);
    }

    /**
     * A question edited after the preview is skipped.
     */
    public function test_apply_skips_stale(): void {
        $question = $this->create_question('A frog');
        $job = $this->create_job([$question]);
        $rows = $job->preview();
        $job->set_included([$rows[0]->itemid]);

        // Someone else saves a new version in the meantime.
        $this->generator->update_question($question, null, ['questiontext' => ['text' => 'A frog!', 'format' => 1]]);
        $job->apply();

        $this->assertSame(job::ITEM_STALE, $job->get_result_rows()[0]->status);
    }

    /**
     * Questions the user cannot edit, or from another bank, are not changed.
     */
    public function test_no_permission(): void {
        $question = $this->create_question('A frog');
        $othercourse = $this->getDataGenerator()->create_course();
        $otherbank = $this->getDataGenerator()->create_module('qbank', ['course' => $othercourse->id]);
        $othercategory = $this->generator->create_question_category([
            'contextid' => \core\context\module::instance($otherbank->cmid)->id,
        ]);
        $other = $this->generator->create_question('shortanswer', 'frogtoad', [
            'category' => $othercategory->id,
            'questiontext' => ['text' => 'Other frog', 'format' => FORMAT_HTML],
        ]);
        $job = $this->create_job([$question, $other]);

        $statuses = array_column(array_map(fn($r) => (array) $r, $job->preview()), 'status', 'questionid');

        $this->assertSame(job::ITEM_CHANGE, $statuses[$question->id]);
        $this->assertSame(job::ITEM_NOPERMISSION, $statuses[$other->id]);
    }

    /**
     * Only the owner may use a job.
     */
    public function test_require_owner(): void {
        $job = $this->create_job([$this->create_question('A frog')]);
        $this->setUser($this->getDataGenerator()->create_user());
        $this->expectException(\moodle_exception::class);
        $job->require_owner();
    }

    /**
     * Large jobs run in an adhoc task.
     */
    public function test_apply_task(): void {
        $question = $this->create_question('A frog');
        $job = $this->create_job([$question]);
        $rows = $job->preview();
        $job->set_included([$rows[0]->itemid]);
        $job->queue_apply();
        $this->assertSame(job::STATUS_APPLYING, $job->get_status());

        $this->expectOutputRegex('/1 updated/');
        $this->runAdhocTasks(\qbank_bulkedit\task\apply_job::class);

        $job = job::load($job->get_id());
        $this->assertSame(job::STATUS_DONE, $job->get_status());
        $this->assertSame(job::ITEM_UPDATED, $job->get_result_rows()[0]->status);
    }

    /**
     * Old jobs are purged.
     */
    public function test_purge(): void {
        global $DB;
        $job = $this->create_job([$this->create_question('A frog')]);
        $DB->set_field('qbank_bulkedit_job', 'timemodified', time() - 40 * DAYSECS, ['id' => $job->get_id()]);
        $this->create_job([$this->create_question('A frog')]);

        $this->assertSame(1, job::purge(time() - 30 * DAYSECS));
        $this->assertFalse($DB->record_exists('qbank_bulkedit_job', ['id' => $job->get_id()]));
        $this->assertFalse($DB->record_exists('qbank_bulkedit_item', ['jobid' => $job->get_id()]));
        $this->assertSame(1, $DB->count_records('qbank_bulkedit_job'));
    }

    /**
     * Sample values come only from questions the job can edit.
     */
    public function test_sample_values(): void {
        $job = $this->create_job([
            $this->create_question('First frog'),
            $this->create_question('Second frog'),
            $this->create_question('Essay', 'essay'),
        ]);
        $this->assertSame(['First frog', 'Second frog'], $job->get_sample_values(5));
        $this->assertCount(1, $job->get_sample_values(1));
    }

    /**
     * The diff helper finds the changed part.

     */
    public function test_diff(): void {
        $this->assertSame(
            ['before' => 'A ', 'removed' => 'frog', 'added' => 'toad', 'after' => ' sat'],
            helper::diff('A frog sat', 'A toad sat')
        );
        $this->assertSame(
            ['before' => 'Text', 'removed' => '', 'added' => ' more', 'after' => ''],
            helper::diff('Text', 'Text more')
        );
    }
}
