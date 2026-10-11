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

namespace qbank_bulkedit\local\field;

/**
 * A question field that can be bulk edited.
 *
 * A field knows how to read its stored value from question data (as returned by
 * question_bank::load_question_data()) and which element of the question type's
 * editing form holds it, so a new version can be saved through that form.
 *
 * @package    qbank_bulkedit
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class field {
    /** @var string Single line plain text form element. */
    public const TYPE_TEXT = 'text';
    /** @var string Multi line plain text form element. */
    public const TYPE_TEXTAREA = 'textarea';
    /** @var string HTML editor form element. */
    public const TYPE_EDITOR = 'editor';

    /** @var string[] Question types this field can be edited for. */
    private array $qtypes;

    /**
     * Constructor.
     *
     * @param string $name unique name of the field
     * @param string $label human readable label
     * @param string[] $qtypes question types this field can be edited for
     * @param string $formelement name of the element in the question editing form
     * @param string $type one of the TYPE_ constants
     * @param \Closure $getter function(\stdClass $questiondata): ?string returning the stored value
     */
    public function __construct(
        /** @var string unique name of the field */
        private readonly string $name,
        /** @var string human readable label */
        private readonly string $label,
        array $qtypes,
        /** @var string name of the element in the question editing form */
        private readonly string $formelement,
        /** @var string one of the TYPE_ constants */
        private readonly string $type,
        /** @var \Closure returns the stored value */
        private readonly \Closure $getter,
    ) {
        if (!in_array($type, [self::TYPE_TEXT, self::TYPE_TEXTAREA, self::TYPE_EDITOR])) {
            throw new \coding_exception('Unknown field type ' . $type);
        }
        $this->qtypes = array_values(array_unique($qtypes));
    }

    /**
     * Unique name of the field.
     *
     * @return string
     */
    public function get_name(): string {
        return $this->name;
    }

    /**
     * Human readable label.
     *
     * @return string
     */
    public function get_label(): string {
        return $this->label;
    }

    /**
     * Question types this field can be edited for.
     *
     * @return string[]
     */
    public function get_qtypes(): array {
        return $this->qtypes;
    }

    /**
     * Add supported question types.
     *
     * @param string[] $qtypes
     */
    public function add_qtypes(array $qtypes): void {
        $this->qtypes = array_values(array_unique(array_merge($this->qtypes, $qtypes)));
    }

    /**
     * Whether this field can be edited for the question type.
     *
     * @param string $qtype
     * @return bool
     */
    public function supports_qtype(string $qtype): bool {
        return in_array($qtype, $this->qtypes);
    }

    /**
     * Name of the element in the question editing form.
     *
     * @return string
     */
    public function get_form_element(): string {
        return $this->formelement;
    }

    /**
     * Whether the field is an HTML editor field.
     *
     * @return bool
     */
    public function is_editor(): bool {
        return $this->type === self::TYPE_EDITOR;
    }

    /**
     * Get the stored value of the field.
     *
     * @param \stdClass $questiondata as returned by question_bank::load_question_data()
     * @return string|null null if the question has no such value
     */
    public function get_value(\stdClass $questiondata): ?string {
        $value = ($this->getter)($questiondata);
        return $value === null ? null : (string) $value;
    }
}
