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
 * Admin settings for qbank_bulkedit.
 *
 * @package    qbank_bulkedit
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use qbank_bulkedit\local\ai\request_provider;

// The settings page is created by the qbank plugininfo class.
if ($settings) {
    if ($ADMIN->fulltree) {
        $settings->add(new admin_setting_configtext(
            'qbank_bulkedit/synclimit',
            new lang_string('synclimit', 'qbank_bulkedit'),
            new lang_string('synclimit_desc', 'qbank_bulkedit'),
            50,
            PARAM_INT
        ));

        $settings->add(new admin_setting_configtext(
            'qbank_bulkedit/retentiondays',
            new lang_string('retentiondays', 'qbank_bulkedit'),
            new lang_string('retentiondays_desc', 'qbank_bulkedit'),
            30,
            PARAM_INT
        ));

        $settings->add(new admin_setting_heading(
            'qbank_bulkedit/aiheading',
            new lang_string('aisettings', 'qbank_bulkedit'),
            new lang_string('aisettings_desc', 'qbank_bulkedit')
        ));

        $backends = request_provider::get_installed_backends();
        $settings->add(new admin_setting_configselect(
            'qbank_bulkedit/backend',
            new lang_string('backend', 'qbank_bulkedit'),
            new lang_string('backend_desc', 'qbank_bulkedit'),
            request_provider::BACKEND_CORE,
            $backends
        ));

        if (isset($backends[request_provider::BACKEND_AIMANAGER])) {
            $settings->add(new admin_setting_configtext(
                'qbank_bulkedit/purpose',
                new lang_string('purpose', 'qbank_bulkedit'),
                new lang_string('purpose_desc', 'qbank_bulkedit'),
                'singleprompt',
                PARAM_ALPHANUMEXT
            ));
        }

        $settings->add(new admin_setting_configtextarea(
            'qbank_bulkedit/systemprompt',
            new lang_string('systemprompt', 'qbank_bulkedit'),
            new lang_string('systemprompt_desc', 'qbank_bulkedit'),
            '',
            PARAM_RAW,
            80,
            12
        ));

        $settings->add(new admin_setting_configtext(
            'qbank_bulkedit/maxsamples',
            new lang_string('maxsamples', 'qbank_bulkedit'),
            new lang_string('maxsamples_desc', 'qbank_bulkedit'),
            3,
            PARAM_INT
        ));
    }
}
