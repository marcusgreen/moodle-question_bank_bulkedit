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

namespace qbank_bulkedit;

/**
 * The "Bulk edit" action in the question bank bulk actions menu.
 *
 * @package    qbank_bulkedit
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bulk_edit_action extends \core_question\local\bank\bulk_action_base {
    #[\Override]
    public function get_bulk_action_title(): string {
        return get_string('bulkaction', 'qbank_bulkedit');
    }

    #[\Override]
    public function get_key(): string {
        return 'bulkedit';
    }

    #[\Override]
    public function get_bulk_action_url(): \moodle_url {
        return new \moodle_url('/question/bank/bulkedit/setup.php');
    }

    #[\Override]
    public function get_bulk_action_capabilities(): ?array {
        return ['qbank/bulkedit:use'];
    }
}
