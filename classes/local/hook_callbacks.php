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

use qbank_bulkedit\local\field\registry;

/**
 * Hook callbacks for qbank_bulkedit.
 *
 * @package    qbank_bulkedit
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Register the default fields.
     *
     * Runs with a high priority so that other plugins can extend or replace them.
     *
     * @param \qbank_bulkedit\hook\collect_fields $hook
     */
    public static function collect_fields(\qbank_bulkedit\hook\collect_fields $hook): void {
        foreach (registry::get_default_fields() as $field) {
            $hook->add_field($field);
        }
    }

    /**
     * Describe the AI purpose this plugin uses to local_ai_manager.
     *
     * @param \local_ai_manager\hook\purpose_usage $hook
     */
    public static function handle_purpose_usage(\local_ai_manager\hook\purpose_usage $hook): void {
        $hook->set_component_displayname('qbank_bulkedit', get_string('pluginname', 'qbank_bulkedit'));
        $purpose = get_config('qbank_bulkedit', 'purpose') ?: 'singleprompt';
        $hook->add_purpose_usage_description(
            $purpose,
            'qbank_bulkedit',
            get_string('purposeplacedescription', 'qbank_bulkedit')
        );
    }

    /**
     * Replace the AI request provider with a fake when running behat.
     *
     * @param \core\hook\di_configuration $hook
     */
    public static function configure_di(\core\hook\di_configuration $hook): void {
        if (!defined('BEHAT_SITE_RUNNING')) {
            return;
        }
        $hook->add_definition(
            \qbank_bulkedit\local\ai\request_provider::class,
            \DI\create(\qbank_bulkedit\testing\fake_request_provider::class),
        );
    }
}
