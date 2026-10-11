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
 * Deletes old bulk edit jobs.
 *
 * @package    qbank_bulkedit
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class purge_jobs extends \core\task\scheduled_task {
    #[\Override]
    public function get_name(): string {
        return get_string('taskpurgejobs', 'qbank_bulkedit');
    }

    #[\Override]
    public function execute(): void {
        $days = (int) get_config('qbank_bulkedit', 'retentiondays');
        if ($days <= 0) {
            return;
        }
        $deleted = job::purge(time() - $days * DAYSECS);
        mtrace("Deleted {$deleted} bulk edit job(s).");
    }
}
