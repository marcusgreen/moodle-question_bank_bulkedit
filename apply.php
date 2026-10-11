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

/**
 * Apply a previewed bulk edit, saving a new version of each ticked question.
 *
 * @package    qbank_bulkedit
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php'); // phpcs:ignore moodle.Files.RequireLogin.Missing -- helper::setup_page() logs in.

use qbank_bulkedit\local\helper;
use qbank_bulkedit\local\job;

$jobid = required_param('jobid', PARAM_INT);
$url = new moodle_url('/question/bank/bulkedit/apply.php', ['jobid' => $jobid]);
$job = helper::setup_job_page($jobid, $url);
require_sesskey();

$itemids = optional_param_array('apply', [], PARAM_INT);
$count = $job->set_included(array_values($itemids));
$previewurl = new moodle_url('/question/bank/bulkedit/preview.php', ['jobid' => $jobid]);
if ($count === 0) {
    redirect(
        $previewurl,
        get_string('nothingtoapply', 'qbank_bulkedit'),
        null,
        \core\output\notification::NOTIFY_WARNING
    );
}

$resulturl = new moodle_url('/question/bank/bulkedit/result.php', ['jobid' => $jobid]);
$synclimit = (int) get_config('qbank_bulkedit', 'synclimit');
if ($count > $synclimit) {
    $job->queue_apply();
    redirect($resulturl);
}

core_php_time_limit::raise();
$job->apply();
redirect($resulturl);
