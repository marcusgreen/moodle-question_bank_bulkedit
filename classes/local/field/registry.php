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

use qbank_bulkedit\hook\collect_fields;

/**
 * Registry of the fields that can be bulk edited.
 *
 * @package    qbank_bulkedit
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class registry {
    /**
     * Question types whose editing form has been tested to round-trip
     * the core fields without changing anything else.
     */
    public const CORE_QTYPES = [
        'aitext',
        'ddwtos',
        'description',
        'essay',
        'gapfill',
        'gapselect',
        'match',
        'multichoice',
        'numerical',
        'shortanswer',
        'truefalse',
    ];

    /** @var field[]|null Fields keyed by name, cached for the request. */
    private static ?array $fields = null;

    /**
     * Get all fields, keyed by name.
     *
     * @return field[]
     */
    public static function get_fields(): array {
        if (self::$fields === null) {
            $hook = new collect_fields();
            \core\di::get(\core\hook\manager::class)->dispatch($hook);
            self::$fields = $hook->get_fields();
        }
        return self::$fields;
    }

    /**
     * Get one field.
     *
     * @param string $name
     * @return field|null
     */
    public static function get_field(string $name): ?field {
        return self::get_fields()[$name] ?? null;
    }

    /**
     * Get the fields that support at least one of the question types.
     *
     * @param string[] $qtypes
     * @return field[] keyed by name
     */
    public static function get_fields_for_qtypes(array $qtypes): array {
        return array_filter(self::get_fields(), function (field $field) use ($qtypes): bool {
            return (bool) array_filter($qtypes, [$field, 'supports_qtype']);
        });
    }

    /**
     * Get every question type that at least one field supports.
     *
     * @return string[]
     */
    public static function get_supported_qtypes(): array {
        $qtypes = [];
        foreach (self::get_fields() as $field) {
            $qtypes = array_merge($qtypes, $field->get_qtypes());
        }
        return array_values(array_unique($qtypes));
    }

    /**
     * The default fields, registered by this plugin's own hook callback.
     *
     * @return field[]
     */
    public static function get_default_fields(): array {
        $graderqtypes = ['essay', 'aitext'];
        return [
            new field(
                'name',
                get_string('questionname', 'question'),
                self::CORE_QTYPES,
                'name',
                field::TYPE_TEXT,
                fn($q) => $q->name
            ),
            new field(
                'questiontext',
                get_string('questiontext', 'question'),
                self::CORE_QTYPES,
                'questiontext',
                field::TYPE_EDITOR,
                fn($q) => $q->questiontext
            ),
            new field(
                'generalfeedback',
                get_string('generalfeedback', 'question'),
                self::CORE_QTYPES,
                'generalfeedback',
                field::TYPE_EDITOR,
                fn($q) => $q->generalfeedback
            ),
            new field(
                'aiprompt',
                get_string('field_aiprompt', 'qbank_bulkedit'),
                ['aitext'],
                'aiprompt',
                field::TYPE_TEXTAREA,
                fn($q) => $q->options->aiprompt ?? null
            ),
            new field(
                'markscheme',
                get_string('field_markscheme', 'qbank_bulkedit'),
                ['aitext'],
                'markscheme',
                field::TYPE_TEXTAREA,
                fn($q) => $q->options->markscheme ?? null
            ),
            new field(
                'graderinfo',
                get_string('field_graderinfo', 'qbank_bulkedit'),
                $graderqtypes,
                'graderinfo',
                field::TYPE_EDITOR,
                fn($q) => $q->options->graderinfo ?? null
            ),
            new field(
                'responsetemplate',
                get_string('field_responsetemplate', 'qbank_bulkedit'),
                $graderqtypes,
                'responsetemplate',
                field::TYPE_EDITOR,
                fn($q) => $q->options->responsetemplate ?? null
            ),
        ];
    }

    /**
     * Clear the cached fields. For unit tests.
     */
    public static function reset_caches(): void {
        self::$fields = null;
    }
}
