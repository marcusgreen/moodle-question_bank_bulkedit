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

namespace qbank_bulkedit\local\ai;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use qbank_bulkedit\local\field\registry;
use qbank_bulkedit\local\rule\rule;

/**
 * Tests for the AI rule builder and request provider.
 *
 * @package    qbank_bulkedit
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \qbank_bulkedit\local\ai\rule_builder
 * @covers     \qbank_bulkedit\local\ai\request_provider
 */
#[CoversClass(\qbank_bulkedit\local\ai\rule_builder::class)]
#[CoversClass(\qbank_bulkedit\local\ai\request_provider::class)]
final class rule_builder_test extends \advanced_testcase {
    /**
     * Replace the provider with a mock that returns the given content.
     *
     * @param string $content
     * @param string|null $prompt receives the prompt that was sent
     */
    private function mock_provider(string $content, ?string &$prompt = null): void {
        $mock = $this->getMockBuilder(request_provider::class)->onlyMethods(['perform_request'])->getMock();
        $mock->method('perform_request')->willReturnCallback(
            function (string $sent) use ($content, &$prompt): array {
                $prompt = $sent;
                return ['content' => $content, 'model' => 'test-model'];
            }
        );
        \core\di::set(request_provider::class, $mock);
    }

    /**
     * A valid response becomes a rule, and the prompt contains the context.
     */
    public function test_build(): void {
        $this->resetAfterTest();
        $prompt = null;
        $fence = str_repeat(chr(96), 3);
        $this->mock_provider($fence . "json\n" . '{"mode":"simple","find":"Marks: {n}","replace":"Marks: 10",'
            . '"flags":"i","firstonly":false,"guard":"","explanation":"Sets the marks to 10."}' . "\n" . $fence, $prompt);
        $field = registry::get_field('markscheme');
        $current = new rule(rule::MODE_APPEND, '', 'x');

        $result = (new rule_builder())->build(
            'Change marks to 10',
            $field,
            ['aitext'],
            $current,
            ['Sample scheme Marks: 5'],
            1,
            2
        );

        $this->assertSame(rule::MODE_SIMPLE, $result['rule']->mode);
        $this->assertSame('Marks: {n}', $result['rule']->find);
        $this->assertTrue($result['rule']->caseinsensitive);
        $this->assertSame('Sets the marks to 10.', $result['rule']->explanation);
        $this->assertSame('test-model', $result['model']);
        $this->assertStringContainsString('Change marks to 10', $prompt);
        $this->assertStringContainsString('Sample scheme Marks: 5', $prompt);
        $this->assertStringContainsString($current->to_json(), $prompt);
        $this->assertStringContainsString('"mode"', $prompt);
    }

    /**
     * The admin can replace the system prompt.
     */
    public function test_custom_system_prompt(): void {
        $this->resetAfterTest();
        set_config('systemprompt', 'CUSTOM INSTRUCTIONS', 'qbank_bulkedit');
        $prompt = (new rule_builder())->get_prompt('x', registry::get_field('name'), ['essay'], null, []);
        $this->assertStringStartsWith('CUSTOM INSTRUCTIONS', $prompt);
        $this->assertStringNotContainsString('SAMPLE VALUE', $prompt);
    }

    /**
     * Responses that are not rules are rejected.
     *
     * @dataProvider invalid_response_provider
     * @param string $content
     */
    #[DataProvider('invalid_response_provider')]
    public function test_invalid_response(string $content): void {
        $this->resetAfterTest();
        $this->mock_provider($content);
        $this->expectException(\moodle_exception::class);
        (new rule_builder())->build('x', registry::get_field('name'), ['essay'], null, [], 1, 2);
    }

    /**
     * Invalid AI responses.
     *
     * @return array
     */
    public static function invalid_response_provider(): array {
        return [
            'not json' => ['I cannot help with that.'],
            'unknown mode' => ['{"mode":"delete","find":"a"}'],
            'invalid regex' => ['{"mode":"regex","find":"[a-","replace":"b"}'],
            'nested quantifier' => ['{"mode":"regex","find":"(a+)+","replace":"b"}'],
            'empty find' => ['{"mode":"simple","find":"","replace":"b"}'],
            'array value' => ['{"mode":"simple","find":["a"],"replace":"b"}'],
        ];
    }

    /**
     * Requests are refused for a user other than the current one.
     */
    public function test_wrong_user(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->expectException(\moodle_exception::class);
        (new request_provider())->perform_request('x', \core\context\system::instance()->id, 12345);
    }

    /**
     * The core backend is the default and is reported unavailable without a provider.
     */
    public function test_core_backend_unavailable(): void {
        $this->resetAfterTest();
        $provider = new request_provider();
        $this->assertSame(request_provider::BACKEND_CORE, $provider->get_backend());
        $this->assertArrayHasKey(request_provider::BACKEND_CORE, request_provider::get_installed_backends());
        $this->assertIsString($provider->get_unavailability_reason(\core\context\system::instance()->id));
    }
}
