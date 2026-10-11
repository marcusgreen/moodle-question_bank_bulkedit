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
 * Applies a rule to a value. Has no side effects, so preview and apply give the same result.
 *
 * @package    qbank_bulkedit
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class engine {
    /** @var int Backtrack limit while running a rule, to stop runaway expressions. */
    public const BACKTRACK_LIMIT = 100000;

    /**
     * Apply the rule.
     *
     * @param rule $rule
     * @param string $value the current field value
     * @return string|null the new value, or null if the rule does not change the value
     * @throws \moodle_exception if the expression fails at run time
     */
    public static function apply(rule $rule, string $value): ?string {
        if ($rule->guard !== '' && self::contains($value, $rule->guard, $rule->caseinsensitive)) {
            return null;
        }
        switch ($rule->mode) {
            case rule::MODE_APPEND:
                $new = $value . $rule->replace;
                break;
            case rule::MODE_PREPEND:
                $new = $rule->replace . $value;
                break;
            case rule::MODE_SET:
                $new = $rule->replace;
                break;
            default:
                $new = self::replace($rule, $value);
        }
        return ($new === null || $new === $value) ? null : $new;
    }

    /**
     * Get the complete regular expression for a pattern rule.
     *
     * @param rule $rule
     * @return string
     */
    public static function get_regex(rule $rule): string {
        if ($rule->mode === rule::MODE_SIMPLE) {
            return simple_pattern::to_regex($rule->find, $rule->caseinsensitive);
        }
        return simple_pattern::wrap_regex($rule->find, $rule->caseinsensitive);
    }

    /**
     * Run a find and replace rule.
     *
     * @param rule $rule
     * @param string $value
     * @return string|null null if nothing matched
     */
    private static function replace(rule $rule, string $value): ?string {
        $regex = self::get_regex($rule);
        $replacement = $rule->mode === rule::MODE_SIMPLE
            ? simple_pattern::to_replacement($rule->replace)
            : $rule->replace;
        $oldlimit = ini_get('pcre.backtrack_limit');
        ini_set('pcre.backtrack_limit', (string) self::BACKTRACK_LIMIT);
        try {
            $count = 0;
            $new = @preg_replace($regex, $replacement, $value, $rule->firstonly ? 1 : -1, $count);
            $error = preg_last_error();
        } finally {
            ini_set('pcre.backtrack_limit', $oldlimit);
        }
        if ($new === null || $error !== PREG_NO_ERROR) {
            throw new \moodle_exception('err_regexruntime', 'qbank_bulkedit', '', preg_last_error_msg());
        }
        return $count === 0 ? null : $new;
    }

    /**
     * Whether a value contains a string.
     *
     * @param string $haystack
     * @param string $needle
     * @param bool $caseinsensitive
     * @return bool
     */
    private static function contains(string $haystack, string $needle, bool $caseinsensitive): bool {
        if ($caseinsensitive) {
            return \core_text::strpos(\core_text::strtolower($haystack), \core_text::strtolower($needle)) !== false;
        }
        return str_contains($haystack, $needle);
    }
}
