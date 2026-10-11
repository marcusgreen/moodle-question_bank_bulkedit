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
 * Checks a rule is safe and valid before it is run.
 *
 * @package    qbank_bulkedit
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class validator {
    /** @var int Maximum length of a find pattern. */
    public const MAX_FIND_LENGTH = 1000;

    /** @var int Maximum length of a replacement. */
    public const MAX_REPLACE_LENGTH = 20000;

    /**
     * A quantified group that itself ends in a quantifier, such as (a+)+ or (a*){2,}.
     * These can backtrack catastrophically.
     */
    private const NESTED_QUANTIFIER = '/\((?:[^()\\\\]|\\\\.)*(?:[*+]|\{\d*,\d*\})\??\)(?:[*+]|\{\d*,\d*\})/';

    /**
     * Validate a rule.
     *
     * @param rule $rule
     * @return string|null an error message, or null if the rule is valid
     */
    public static function validate(rule $rule): ?string {
        if (\core_text::strlen($rule->find) > self::MAX_FIND_LENGTH) {
            return get_string('err_findtoolong', 'qbank_bulkedit', self::MAX_FIND_LENGTH);
        }
        if (\core_text::strlen($rule->replace) > self::MAX_REPLACE_LENGTH) {
            return get_string('err_replacetoolong', 'qbank_bulkedit', self::MAX_REPLACE_LENGTH);
        }
        if (!$rule->uses_pattern()) {
            if ($rule->mode !== rule::MODE_SET && $rule->replace === '') {
                return get_string('err_emptytext', 'qbank_bulkedit');
            }
            return null;
        }
        if ($rule->find === '') {
            return get_string('err_emptyfind', 'qbank_bulkedit');
        }
        if ($rule->mode === rule::MODE_REGEX && preg_match(self::NESTED_QUANTIFIER, $rule->find)) {
            return get_string('err_nestedquantifier', 'qbank_bulkedit');
        }
        try {
            $regex = engine::get_regex($rule);
        } catch (\moodle_exception $e) {
            return $e->getMessage();
        }
        error_clear_last();
        if (@preg_match($regex, '') === false) {
            $message = error_get_last()['message'] ?? preg_last_error_msg();
            $message = preg_replace('/^preg_match\(\): /', '', $message);
            return get_string('err_invalidregex', 'qbank_bulkedit', $message);
        }
        return null;
    }
}
