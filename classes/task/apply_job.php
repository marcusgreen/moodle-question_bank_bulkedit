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

namespace qbank_bulkedit\task;

use qbank_bulkedit\local\job;

/**
 * Applies a large bulk edit job in the background.
 *
 * Runs as the user who created the job, so capability checks and version authorship are theirs.
 *
 * @package    qbank_bulkedit
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class apply_job extends \core\task\adhoc_task {
    #[\Override]
    public function get_name(): string {
        return get_string('taskapplyjob', 'qbank_bulkedit');
    }

    #[\Override]
    public function execute(): void {
        global $DB;
        $jobid = (int) $this->get_custom_data()->jobid;
        if (!$DB->record_exists('qbank_bulkedit_job', ['id' => $jobid])) {
            mtrace("Bulk edit job {$jobid} no longer exists.");
            return;
        }
        $job = job::load($jobid);
        if ($job->get_status() !== job::STATUS_APPLYING) {
            mtrace("Bulk edit job {$jobid} is not waiting to be applied.");
            return;
        }
        try {
            $job->apply();
        } catch (\Throwable $e) {
            $job->mark_failed($e->getMessage());
            throw $e;
        }
        $record = $job->get_record();
        mtrace("Bulk edit job {$jobid}: {$record->countupdated} updated, {$record->countskipped} skipped, " .
            "{$record->countfailed} failed.");
    }
}
