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

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../../lib/behat/behat_base.php');

/**
 * Behat steps for qbank_bulkedit.
 *
 * @package    qbank_bulkedit
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_qbank_bulkedit extends behat_base {
    /**
     * Start a bulk edit of the ticked questions.
     *
     * Moodle 5.0 to 5.2 show the bulk actions in a "With selected" menu. From 5.3 they are
     * buttons shown directly, so the menu is only opened when it exists.
     *
     * @When I bulk edit the selected questions
     */
    public function i_bulk_edit_the_selected_questions(): void {
        $menu = $this->getSession()->getPage()->findButton('With selected');
        if ($menu && $menu->isVisible()) {
            $menu->click();
        }
        $this->execute('behat_general::i_click_on', ["#bulkactionsui-container [name='bulkedit']", 'css_element']);
    }
}
