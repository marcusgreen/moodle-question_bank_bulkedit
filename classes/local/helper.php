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

/**
 * Helpers for the plugin's pages.
 *
 * @package    qbank_bulkedit
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {
    /**
     * Log in to the question bank, check capabilities and set up $PAGE.
     *
     * @param int $cmid course module of the question bank
     * @param \moodle_url $url page URL
     * @return \core\context\module
     */
    public static function setup_page(int $cmid, \moodle_url $url): \core\context\module {
        global $CFG, $PAGE;
        require_once($CFG->dirroot . '/question/editlib.php');
        \core_question\local\bank\helper::require_plugin_enabled('qbank_bulkedit');
        [, $cm] = get_module_from_cmid($cmid);
        require_login($cm->course, false, $cm);
        $context = \core\context\module::instance($cmid);
        require_capability('qbank/bulkedit:use', $context);

        $PAGE->set_url($url);
        $PAGE->set_context($context);
        $PAGE->set_title(get_string('pluginname', 'qbank_bulkedit'));
        $PAGE->set_heading($PAGE->course->fullname);
        $PAGE->activityheader->disable();
        $PAGE->set_secondary_active_tab('questionbank');
        return $context;
    }

    /**
     * Load a job the current user owns and set up the page for it.
     *
     * @param int $jobid
     * @param \moodle_url $url page URL
     * @return job
     */
    public static function setup_job_page(int $jobid, \moodle_url $url): job {
        $job = job::load($jobid);
        self::setup_page($job->get_cmid(), $url);
        $job->require_owner();
        return $job;
    }

    /**
     * Get the ids of the questions ticked in the question bank.
     *
     * @param array $submitted submitted form data
     * @return int[]
     */
    public static function get_selected_question_ids(array $submitted): array {
        $ids = [];
        foreach (array_keys($submitted) as $key) {
            if (preg_match('!^q([0-9]+)$!', $key, $matches)) {
                $ids[] = (int) $matches[1];
            }
        }
        return $ids;
    }

    /**
     * Split a before and after value into the unchanged start, the removed and
     * added middle, and the unchanged end, shortening long unchanged parts.
     *
     * @param string $old
     * @param string $new
     * @param int $context characters of unchanged text to keep on each side
     * @return array with keys before, removed, added, after
     */
    public static function diff(string $old, string $new, int $context = 80): array {
        $oldchars = mb_str_split($old);
        $newchars = mb_str_split($new);
        $oldlen = count($oldchars);
        $newlen = count($newchars);
        $start = 0;
        while ($start < $oldlen && $start < $newlen && $oldchars[$start] === $newchars[$start]) {
            $start++;
        }
        $end = 0;
        while (
            $end < $oldlen - $start && $end < $newlen - $start
                && $oldchars[$oldlen - 1 - $end] === $newchars[$newlen - 1 - $end]
        ) {
            $end++;
        }
        $before = implode('', array_slice($oldchars, 0, $start));
        $after = implode('', array_slice($oldchars, $oldlen - $end));
        if (\core_text::strlen($before) > $context) {
            $before = '…' . \core_text::substr($before, -$context);
        }
        if (\core_text::strlen($after) > $context) {
            $after = \core_text::substr($after, 0, $context) . '…';
        }
        return [
            'before' => $before,
            'removed' => implode('', array_slice($oldchars, $start, $oldlen - $start - $end)),
            'added' => implode('', array_slice($newchars, $start, $newlen - $start - $end)),
            'after' => $after,
        ];
    }
}
