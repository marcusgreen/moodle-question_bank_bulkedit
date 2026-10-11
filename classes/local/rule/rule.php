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

namespace qbank_bulkedit\local\rule;

/**
 * A rule describing how to change a field value.
 *
 * @package    qbank_bulkedit
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class rule {
    /** @var string Find with the simple wildcard syntax and replace. */
    public const MODE_SIMPLE = 'simple';
    /** @var string Find with a PCRE regular expression and replace. */
    public const MODE_REGEX = 'regex';
    /** @var string Add text to the end of the field. */
    public const MODE_APPEND = 'append';
    /** @var string Add text to the start of the field. */
    public const MODE_PREPEND = 'prepend';
    /** @var string Replace the whole field. */
    public const MODE_SET = 'set';

    /**
     * Constructor.
     *
     * @param string $mode one of the MODE_ constants
     * @param string $find the pattern to find (simple and regex modes)
     * @param string $replace the replacement, or the text to add or set
     * @param bool $caseinsensitive whether matching ignores case
     * @param bool $firstonly whether only the first match is replaced
     * @param string $guard skip the field if it already contains this text
     * @param string $explanation plain language description, usually from the AI
     */
    public function __construct(
        /** @var string one of the MODE_ constants */
        public readonly string $mode,
        /** @var string the pattern to find */
        public readonly string $find = '',
        /** @var string the replacement, or the text to add or set */
        public readonly string $replace = '',
        /** @var bool whether matching ignores case */
        public readonly bool $caseinsensitive = false,
        /** @var bool whether only the first match is replaced */
        public readonly bool $firstonly = false,
        /** @var string skip the field if it already contains this text */
        public readonly string $guard = '',
        /** @var string plain language description */
        public readonly string $explanation = '',
    ) {
        if (!in_array($mode, self::get_modes())) {
            throw new \moodle_exception('err_invalidmode', 'qbank_bulkedit', '', s($mode));
        }
    }

    /**
     * All modes.
     *
     * @return string[]
     */
    public static function get_modes(): array {
        return [self::MODE_SIMPLE, self::MODE_REGEX, self::MODE_APPEND, self::MODE_PREPEND, self::MODE_SET];
    }

    /**
     * Whether the mode uses the find pattern.
     *
     * @return bool
     */
    public function uses_pattern(): bool {
        return in_array($this->mode, [self::MODE_SIMPLE, self::MODE_REGEX]);
    }

    /**
     * Build a rule from an array, such as decoded JSON or form data.
     *
     * @param array $data
     * @return self
     */
    public static function from_array(array $data): self {
        foreach (['find', 'replace', 'guard', 'explanation'] as $key) {
            if (isset($data[$key]) && !is_scalar($data[$key])) {
                throw new \moodle_exception('err_invalidrule', 'qbank_bulkedit');
            }
        }
        return new self(
            (string) ($data['mode'] ?? ''),
            (string) ($data['find'] ?? ''),
            (string) ($data['replace'] ?? ''),
            !empty($data['caseinsensitive']),
            !empty($data['firstonly']),
            (string) ($data['guard'] ?? ''),
            (string) ($data['explanation'] ?? ''),
        );
    }

    /**
     * Build a rule from JSON.
     *
     * @param string $json
     * @return self
     */
    public static function from_json(string $json): self {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new \moodle_exception('err_invalidrule', 'qbank_bulkedit');
        }
        return self::from_array($data);
    }

    /**
     * Convert to an array.
     *
     * @return array
     */
    public function to_array(): array {
        return [
            'mode' => $this->mode,
            'find' => $this->find,
            'replace' => $this->replace,
            'caseinsensitive' => $this->caseinsensitive,
            'firstonly' => $this->firstonly,
            'guard' => $this->guard,
            'explanation' => $this->explanation,
        ];
    }

    /**
     * Convert to JSON.
     *
     * @return string
     */
    public function to_json(): string {
        return json_encode($this->to_array());
    }

    /**
     * Whether two rules do the same thing. The explanation is ignored.
     *
     * @param rule $other
     * @return bool
     */
    public function same_effect(rule $other): bool {
        $mine = $this->to_array();
        $theirs = $other->to_array();
        unset($mine['explanation'], $theirs['explanation']);
        return $mine === $theirs;
    }
}
