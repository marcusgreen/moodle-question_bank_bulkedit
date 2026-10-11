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
 * Compiles the simple wildcard syntax into a regular expression.
 *
 * Syntax:
 *  - `*` any text, as little as possible (as much as possible at the end of the pattern)
 *  - `?` any single character
 *  - `{word}` one word
 *  - `{n}` a number, optionally with a decimal part
 *  - `{line}` the rest of the line
 *  - `^` at the start / `$` at the end anchor to the start / end of the field
 *  - `(...)` capture, used as $1, $2 ... in the replacement
 *  - `\` makes the next character literal
 *  - everything else is literal
 *
 * @package    qbank_bulkedit
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class simple_pattern {
    /** @var string Regular expression delimiter. */
    public const DELIMITER = '~';

    /** @var array Placeholder tokens and their regular expressions. */
    private const PLACEHOLDERS = [
        '{word}' => '[\p{L}\p{N}_]+',
        '{n}' => '\d+(?:[.,]\d+)?',
        '{line}' => '[^\r\n]*',
    ];

    /**
     * Compile a simple pattern to a complete regular expression with delimiters and flags.
     *
     * @param string $pattern
     * @param bool $caseinsensitive
     * @return string
     */
    public static function to_regex(string $pattern, bool $caseinsensitive = false): string {
        $chars = mb_str_split($pattern);
        $count = count($chars);
        $regex = '';
        $depth = 0;
        for ($i = 0; $i < $count; $i++) {
            $char = $chars[$i];
            if ($char === '\\') {
                $i++;
                if ($i < $count) {
                    $regex .= preg_quote($chars[$i], self::DELIMITER);
                } else {
                    $regex .= preg_quote('\\', self::DELIMITER);
                }
                continue;
            }
            if ($char === '{') {
                $matched = false;
                foreach (self::PLACEHOLDERS as $token => $tokenregex) {
                    if (implode('', array_slice($chars, $i, mb_strlen($token))) === $token) {
                        $regex .= $tokenregex;
                        $i += mb_strlen($token) - 1;
                        $matched = true;
                        break;
                    }
                }
                if (!$matched) {
                    $regex .= preg_quote($char, self::DELIMITER);
                }
                continue;
            }
            switch ($char) {
                case '*':
                    $regex .= ($i === $count - 1 || ($i === $count - 2 && $chars[$i + 1] === '$')) ? '.*' : '.*?';
                    break;
                case '?':
                    $regex .= '.';
                    break;
                case '(':
                    $depth++;
                    $regex .= '(';
                    break;
                case ')':
                    $depth--;
                    if ($depth < 0) {
                        throw new \moodle_exception('err_unbalanced', 'qbank_bulkedit');
                    }
                    $regex .= ')';
                    break;
                case '^':
                    $regex .= $i === 0 ? '\A' : preg_quote($char, self::DELIMITER);
                    break;
                case '$':
                    $regex .= $i === $count - 1 ? '\z' : preg_quote($char, self::DELIMITER);
                    break;
                default:
                    $regex .= preg_quote($char, self::DELIMITER);
            }
        }
        if ($depth !== 0) {
            throw new \moodle_exception('err_unbalanced', 'qbank_bulkedit');
        }
        return self::DELIMITER . $regex . self::DELIMITER . 'us' . ($caseinsensitive ? 'i' : '');
    }

    /**
     * Convert a simple replacement to a preg_replace() replacement.
     *
     * Only $1 to $99 are special. Every other character, including backslash, is literal.
     *
     * @param string $replace
     * @return string
     */
    public static function to_replacement(string $replace): string {
        $replace = str_replace('\\', '\\\\', $replace);
        return preg_replace('/\$(?!\d)/', '\\\\$', $replace);
    }

    /**
     * Wrap a user supplied regular expression in delimiters and add flags.
     *
     * @param string $pattern the expression without delimiters
     * @param bool $caseinsensitive
     * @return string
     */
    public static function wrap_regex(string $pattern, bool $caseinsensitive = false): string {
        // Escape any delimiter that is not already escaped.
        $pattern = preg_replace('/(?<!\\\\)((?:\\\\\\\\)*)' . self::DELIMITER . '/', '$1\\\\' . self::DELIMITER, $pattern);
        return self::DELIMITER . $pattern . self::DELIMITER . 'u' . ($caseinsensitive ? 'i' : '');
    }
}
