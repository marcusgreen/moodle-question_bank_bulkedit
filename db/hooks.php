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
 * Hook callbacks for qbank_bulkedit.
 *
 * @package    qbank_bulkedit
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$callbacks = [
    [
        'hook' => \qbank_bulkedit\hook\collect_fields::class,
        'callback' => \qbank_bulkedit\local\hook_callbacks::class . '::collect_fields',
        'priority' => 1000,
    ],
    [
        // String literal because local_ai_manager is an optional dependency.
        'hook' => 'local_ai_manager\hook\purpose_usage',
        'callback' => \qbank_bulkedit\local\hook_callbacks::class . '::handle_purpose_usage',
    ],
    [
        'hook' => \core\hook\di_configuration::class,
        'callback' => \qbank_bulkedit\local\hook_callbacks::class . '::configure_di',
    ],
];
