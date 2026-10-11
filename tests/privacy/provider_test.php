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

namespace qbank_bulkedit\privacy;

use PHPUnit\Framework\Attributes\CoversClass;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use qbank_bulkedit\local\job;

/**
 * Tests for the privacy provider.
 *
 * @package    qbank_bulkedit
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \qbank_bulkedit\privacy\provider
 */
#[CoversClass(\qbank_bulkedit\privacy\provider::class)]
final class provider_test extends \core_privacy\tests\provider_testcase {
    /**
     * Create a job for a user in a new question bank.
     *
     * @param \stdClass $user
     * @return array [job, context]
     */
    private function create_job(\stdClass $user): array {
        $this->setUser($user);
        $course = $this->getDataGenerator()->create_course();
        $qbank = $this->getDataGenerator()->create_module('qbank', ['course' => $course->id]);
        $job = job::create($qbank->cmid, '', [1, 2]);
        $job->save_settings(['essay'], 'questiontext', null, 'Make it shorter');
        return [$job, \core\context\module::instance($qbank->cmid)];
    }

    /**
     * Contexts, users, export and deletion.
     */
    public function test_provider(): void {
        global $DB;
        $this->resetAfterTest();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        [, $context1] = $this->create_job($user1);
        [, $context2] = $this->create_job($user2);

        $contexts = provider::get_contexts_for_userid($user1->id)->get_contextids();
        $this->assertEquals([$context1->id], $contexts);

        $userlist = new userlist($context2, 'qbank_bulkedit');
        provider::get_users_in_context($userlist);
        $this->assertEquals([$user2->id], $userlist->get_userids());

        provider::export_user_data(new approved_contextlist($user1, 'qbank_bulkedit', [$context1->id]));
        $data = writer::with_context($context1)->get_data([get_string('pluginname', 'qbank_bulkedit')]);
        $this->assertSame('Make it shorter', $data->jobs[0]->aiinstruction);

        provider::delete_data_for_user(new approved_contextlist($user1, 'qbank_bulkedit', [$context1->id]));
        $this->assertFalse($DB->record_exists('qbank_bulkedit_job', ['userid' => $user1->id]));
        $this->assertTrue($DB->record_exists('qbank_bulkedit_job', ['userid' => $user2->id]));

        provider::delete_data_for_users(new approved_userlist($context2, 'qbank_bulkedit', [$user2->id]));
        $this->assertSame(0, $DB->count_records('qbank_bulkedit_job'));
        $this->assertSame(0, $DB->count_records('qbank_bulkedit_item'));
    }
}
