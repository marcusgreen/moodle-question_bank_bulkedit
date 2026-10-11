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

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for qbank_bulkedit.
 *
 * @package    qbank_bulkedit
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    #[\Override]
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('qbank_bulkedit_job', [
            'userid' => 'privacy:metadata:job:userid',
            'field' => 'privacy:metadata:job:field',
            'rule' => 'privacy:metadata:job:rule',
            'aiinstruction' => 'privacy:metadata:job:aiinstruction',
            'aibackend' => 'privacy:metadata:job:aibackend',
            'timecreated' => 'privacy:metadata:job:timecreated',
            'timemodified' => 'privacy:metadata:job:timemodified',
        ], 'privacy:metadata:job');
        $collection->add_subsystem_link('core_ai', [], 'privacy:metadata:core_ai');
        $collection->add_external_location_link('aibackend', [
            'aiinstruction' => 'privacy:metadata:aibackend:aiinstruction',
            'samples' => 'privacy:metadata:aibackend:samples',
        ], 'privacy:metadata:aibackend');
        return $collection;
    }

    #[\Override]
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $contextlist->add_from_sql(
            "SELECT contextid FROM {qbank_bulkedit_job} WHERE userid = :userid",
            ['userid' => $userid]
        );
        return $contextlist;
    }

    #[\Override]
    public static function get_users_in_context(userlist $userlist) {
        $userlist->add_from_sql(
            'userid',
            "SELECT userid FROM {qbank_bulkedit_job} WHERE contextid = :contextid",
            ['contextid' => $userlist->get_context()->id]
        );
    }

    #[\Override]
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $jobs = $DB->get_records('qbank_bulkedit_job', ['userid' => $userid, 'contextid' => $context->id], 'id');
            if (!$jobs) {
                continue;
            }
            $data = [];
            foreach ($jobs as $job) {
                $data[] = (object) [
                    'field' => $job->field,
                    'qtypes' => $job->qtypes,
                    'rule' => $job->rule,
                    'aiinstruction' => $job->aiinstruction,
                    'aibackend' => $job->aibackend,
                    'status' => $job->status,
                    'countupdated' => $job->countupdated,
                    'timecreated' => transform::datetime($job->timecreated),
                    'timemodified' => transform::datetime($job->timemodified),
                ];
            }
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'qbank_bulkedit')],
                (object) ['jobs' => $data]
            );
        }
    }

    #[\Override]
    public static function delete_data_for_all_users_in_context(\context $context) {
        self::delete_jobs('contextid = :contextid', ['contextid' => $context->id]);
    }

    #[\Override]
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            self::delete_jobs(
                'contextid = :contextid AND userid = :userid',
                ['contextid' => $context->id, 'userid' => $userid]
            );
        }
    }

    #[\Override]
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        $userids = $userlist->get_userids();
        if (!$userids) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params['contextid'] = $userlist->get_context()->id;
        self::delete_jobs("contextid = :contextid AND userid $insql", $params);
    }

    /**
     * Delete jobs and their items.
     *
     * @param string $select
     * @param array $params
     */
    private static function delete_jobs(string $select, array $params): void {
        global $DB;
        $jobids = $DB->get_fieldset_select('qbank_bulkedit_job', 'id', $select, $params);
        foreach (array_chunk($jobids, 500) as $chunk) {
            [$insql, $inparams] = $DB->get_in_or_equal($chunk);
            $DB->delete_records_select('qbank_bulkedit_item', "jobid $insql", $inparams);
            $DB->delete_records_select('qbank_bulkedit_job', "id $insql", $inparams);
        }
    }
}
