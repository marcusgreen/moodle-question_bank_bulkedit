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
 * Show the result of a bulk edit.
 *
 * @package    qbank_bulkedit
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php'); // phpcs:ignore moodle.Files.RequireLogin.Missing -- helper::setup_page() logs in.

use qbank_bulkedit\local\helper;
use qbank_bulkedit\local\job;

$jobid = required_param('jobid', PARAM_INT);
$url = new moodle_url('/question/bank/bulkedit/result.php', ['jobid' => $jobid]);
$job = helper::setup_job_page($jobid, $url);
if (in_array($job->get_status(), [job::STATUS_DRAFT, job::STATUS_PREVIEWED])) {
    redirect(new moodle_url('/question/bank/bulkedit/preview.php', ['jobid' => $jobid]));
}
if ($job->get_status() === job::STATUS_APPLYING) {
    $PAGE->set_periodic_refresh_delay(5);
}

/** @var \qbank_bulkedit\output\renderer $renderer */
$renderer = $PAGE->get_renderer('qbank_bulkedit');
echo $OUTPUT->header();
echo $renderer->render_result($job);
echo $OUTPUT->footer();
