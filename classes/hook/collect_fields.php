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

namespace qbank_bulkedit\hook;

use qbank_bulkedit\local\field\field;

/**
 * Hook that collects the question fields qbank_bulkedit can edit.
 *
 * Question type plugins can listen to this hook to make their own fields editable,
 * or to declare that their question type works with an existing field.
 *
 * A question type should only be added once a test shows that saving a new version
 * through its editing form leaves every other field unchanged.
 *
 * @package    qbank_bulkedit
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Collect the question fields that can be changed by qbank_bulkedit')]
#[\core\attribute\tags('question')]
final class collect_fields {
    /** @var field[] Fields keyed by name. */
    private array $fields = [];

    /**
     * Add a field. A field with the same name replaces an earlier one.
     *
     * @param field $field
     */
    public function add_field(field $field): void {
        $this->fields[$field->get_name()] = $field;
    }

    /**
     * Declare that more question types support an already registered field.
     *
     * @param string $fieldname
     * @param string[] $qtypes
     */
    public function add_qtypes_to_field(string $fieldname, array $qtypes): void {
        if (isset($this->fields[$fieldname])) {
            $this->fields[$fieldname]->add_qtypes($qtypes);
        }
    }

    /**
     * Get the collected fields.
     *
     * @return field[] keyed by name
     */
    public function get_fields(): array {
        return $this->fields;
    }
}
