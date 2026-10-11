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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for the rule validator.
 *
 * @package    qbank_bulkedit
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \qbank_bulkedit\local\rule\validator
 */
#[CoversClass(\qbank_bulkedit\local\rule\validator::class)]
final class validator_test extends \advanced_testcase {
    /**
     * Rules and whether they are valid.
     *
     * @return array
     */
    public static function validate_provider(): array {
        return [
            'simple' => [['mode' => 'simple', 'find' => 'a*b', 'replace' => 'c'], true],
            'simple empty find' => [['mode' => 'simple', 'find' => '', 'replace' => 'c'], false],
            'simple unbalanced' => [['mode' => 'simple', 'find' => 'a)', 'replace' => 'c'], false],
            'regex' => [['mode' => 'regex', 'find' => '\d+', 'replace' => 'n'], true],
            'regex invalid' => [['mode' => 'regex', 'find' => '[a-', 'replace' => 'n'], false],
            'regex nested plus' => [['mode' => 'regex', 'find' => '(a+)+', 'replace' => 'n'], false],
            'regex nested star' => [['mode' => 'regex', 'find' => '(\w*)*x', 'replace' => 'n'], false],
            'regex nested range' => [['mode' => 'regex', 'find' => '(a{1,})+', 'replace' => 'n'], false],
            'regex group quantified once' => [['mode' => 'regex', 'find' => '(ab)+', 'replace' => 'n'], true],
            'append' => [['mode' => 'append', 'replace' => 'x'], true],
            'append empty' => [['mode' => 'append', 'replace' => ''], false],
            'set empty clears' => [['mode' => 'set', 'replace' => ''], true],
            'find too long' => [['mode' => 'simple', 'find' => str_repeat('a', 1001), 'replace' => ''], false],
        ];
    }

    /**
     * Test validation.
     *
     * @dataProvider validate_provider
     * @param array $rule
     * @param bool $valid
     */
    #[DataProvider('validate_provider')]
    public function test_validate(array $rule, bool $valid): void {
        $error = validator::validate(rule::from_array($rule));
        if ($valid) {
            $this->assertNull($error);
        } else {
            $this->assertIsString($error);
        }
    }
}
