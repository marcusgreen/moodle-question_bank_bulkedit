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
 * Tests for the rule engine and the simple pattern syntax.
 *
 * @package    qbank_bulkedit
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \qbank_bulkedit\local\rule\engine
 * @covers     \qbank_bulkedit\local\rule\simple_pattern
 * @covers     \qbank_bulkedit\local\rule\rule
 */
#[CoversClass(\qbank_bulkedit\local\rule\engine::class)]
#[CoversClass(\qbank_bulkedit\local\rule\simple_pattern::class)]
#[CoversClass(\qbank_bulkedit\local\rule\rule::class)]
final class engine_test extends \advanced_testcase {
    /**
     * Rules and their expected results.
     *
     * @return array
     */
    public static function apply_provider(): array {
        return [
            'number placeholder' => [
                ['mode' => 'simple', 'find' => 'Marks: {n}', 'replace' => 'Marks: 10'],
                'Total. Marks: 5 overall', 'Total. Marks: 10 overall',
            ],
            'decimal number' => [
                ['mode' => 'simple', 'find' => '{n}%', 'replace' => 'half'],
                'Score 2.5%', 'Score half',
            ],
            'star at end is greedy' => [
                ['mode' => 'simple', 'find' => 'Note: *', 'replace' => 'Note: none'],
                "Intro\nNote: old text", "Intro\nNote: none",
            ],
            'star in middle is lazy' => [
                ['mode' => 'simple', 'find' => '<b>*</b>', 'replace' => '<strong>x</strong>'],
                '<b>a</b> and <b>c</b>', '<strong>x</strong> and <strong>x</strong>',
            ],
            'captures' => [
                ['mode' => 'simple', 'find' => '({word}) is a ({word})', 'replace' => '$2 is a $1'],
                'Paris is a city', 'city is a Paris',
            ],
            'literal dot and brackets' => [
                ['mode' => 'simple', 'find' => 'a.b \(c\)', 'replace' => 'ok'],
                'axb (c) a.b (c)', 'axb (c) ok',
            ],
            'escaped star' => [
                ['mode' => 'simple', 'find' => '2\*3', 'replace' => '6'],
                '2*3 and 223', '6 and 223',
            ],
            'question mark' => [
                ['mode' => 'simple', 'find' => 'c?t', 'replace' => 'dog'],
                'cat cot cart', 'dog dog cart',
            ],
            'start anchor' => [
                ['mode' => 'simple', 'find' => '^Hello', 'replace' => 'Hi'],
                'Hello Hello', 'Hi Hello',
            ],
            'end anchor' => [
                ['mode' => 'simple', 'find' => 'end$', 'replace' => 'END'],
                'end end', 'end END',
            ],
            'caret in middle is literal' => [
                ['mode' => 'simple', 'find' => 'x^2', 'replace' => 'x²'],
                'Find x^2', 'Find x²',
            ],
            'line placeholder' => [
                ['mode' => 'simple', 'find' => 'Rubric:{line}', 'replace' => 'Rubric: see below'],
                "Rubric: old\nKeep", "Rubric: see below\nKeep",
            ],
            'unicode word' => [
                ['mode' => 'simple', 'find' => '{word} crème', 'replace' => 'cream'],
                'Le café crème', 'Le cream',
            ],
            'replacement keeps backslash and dollar' => [
                ['mode' => 'simple', 'find' => 'path', 'replace' => 'C:\\dir $x \\1'],
                'the path', 'the C:\\dir $x \\1',
            ],
            'case insensitive' => [
                ['mode' => 'simple', 'find' => 'MARKS', 'replace' => 'points', 'caseinsensitive' => true],
                'Marks and marks', 'points and points',
            ],
            'first only' => [
                ['mode' => 'simple', 'find' => 'a', 'replace' => 'b', 'firstonly' => true],
                'aaa', 'baa',
            ],
            'regex' => [
                ['mode' => 'regex', 'find' => '(\d+) marks', 'replace' => '[$1 points]'],
                'Worth 5 marks', 'Worth [5 points]',
            ],
            'regex with delimiter' => [
                ['mode' => 'regex', 'find' => 'a~b', 'replace' => 'c'],
                'xa~by', 'xcy',
            ],
            'append' => [
                ['mode' => 'append', 'replace' => "\nMarks: 10"],
                'Scheme', "Scheme\nMarks: 10",
            ],
            'prepend' => [
                ['mode' => 'prepend', 'replace' => 'Note: '],
                'text', 'Note: text',
            ],
            'set' => [
                ['mode' => 'set', 'replace' => 'new'],
                'old', 'new',
            ],
            'no match' => [
                ['mode' => 'simple', 'find' => 'missing', 'replace' => 'x'],
                'text', null,
            ],
            'replacement same as before' => [
                ['mode' => 'simple', 'find' => 'same', 'replace' => 'same'],
                'same', null,
            ],
            'set to the same value' => [
                ['mode' => 'set', 'replace' => 'same'],
                'same', null,
            ],
            'guard skips' => [
                ['mode' => 'append', 'replace' => ' Marks: 10', 'guard' => 'Marks: 10'],
                'Scheme Marks: 10', null,
            ],
            'guard case insensitive' => [
                ['mode' => 'append', 'replace' => ' Marks', 'guard' => 'MARKS', 'caseinsensitive' => true],
                'has marks', null,
            ],
            'guard does not match' => [
                ['mode' => 'append', 'replace' => '!', 'guard' => 'xyz'],
                'abc', 'abc!',
            ],
        ];
    }

    /**
     * Test applying rules.
     *
     * @dataProvider apply_provider
     * @param array $rule
     * @param string $value
     * @param string|null $expected
     */
    #[DataProvider('apply_provider')]
    public function test_apply(array $rule, string $value, ?string $expected): void {
        $this->assertSame($expected, engine::apply(rule::from_array($rule), $value));
    }

    /**
     * A runaway expression is stopped with an exception.
     */
    public function test_backtrack_limit(): void {
        $rule = new rule(rule::MODE_REGEX, '(*NO_START_OPT)(x+x+)+y', 'z');
        $this->expectException(\moodle_exception::class);
        engine::apply($rule, str_repeat('x', 30));
    }

    /**
     * Unbalanced brackets in a simple pattern are an error.
     */
    public function test_unbalanced(): void {
        $this->expectException(\moodle_exception::class);
        simple_pattern::to_regex('(abc');
    }

    /**
     * Rules convert to and from JSON, and compare without the explanation.
     */
    public function test_rule_json(): void {
        $rule = new rule(rule::MODE_SIMPLE, 'a', 'b', true, false, 'g', 'Explains');
        $copy = rule::from_json($rule->to_json());
        $this->assertSame($rule->to_array(), $copy->to_array());
        $this->assertTrue($rule->same_effect(new rule(rule::MODE_SIMPLE, 'a', 'b', true, false, 'g')));
        $this->assertFalse($rule->same_effect(new rule(rule::MODE_SIMPLE, 'a', 'c', true, false, 'g')));
    }

    /**
     * An unknown mode is rejected.
     */
    public function test_invalid_mode(): void {
        $this->expectException(\moodle_exception::class);
        rule::from_array(['mode' => 'delete']);
    }
}
