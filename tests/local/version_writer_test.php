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

namespace qbank_bulkedit\local;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use qbank_bulkedit\local\field\registry;

/**
 * Tests that saving a changed field through the editing form changes only that field.
 *
 * Every question type in registry::CORE_QTYPES should be covered here.
 *
 * @package    qbank_bulkedit
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \qbank_bulkedit\local\version_writer
 */
#[CoversClass(\qbank_bulkedit\local\version_writer::class)]
final class version_writer_test extends \advanced_testcase {
    /** @var string[] Keys that are expected to differ between versions. */
    private const VOLATILE_KEYS = [
        'id', 'question', 'questionid', 'stamp', 'timecreated', 'timemodified', 'createdby', 'modifiedby',
        'version', 'versionid', 'questionbankentryid', 'categoryobject', 'hints', 'answers',
        'trueanswer', 'falseanswer',
    ];

    /**
     * Question types, test question variants and fields to change.
     *
     * @return array
     */
    public static function qtype_provider(): array {
        return [
            'shortanswer questiontext' => ['shortanswer', 'frogtoad', 'questiontext'],
            'shortanswer name' => ['shortanswer', 'frogtoad', 'name'],
            'truefalse generalfeedback' => ['truefalse', 'true', 'generalfeedback'],
            'multichoice questiontext' => ['multichoice', 'two_of_four', 'questiontext'],
            'essay questiontext' => ['essay', 'editor', 'questiontext'],
            'essay graderinfo' => ['essay', 'editor', 'graderinfo'],
            'essay responsetemplate' => ['essay', 'editor', 'responsetemplate'],
            'numerical questiontext' => ['numerical', 'pi', 'questiontext'],
            'match generalfeedback' => ['match', 'foursubq', 'generalfeedback'],
            'description questiontext' => ['description', 'info', 'questiontext'],
            'ddwtos generalfeedback' => ['ddwtos', 'fox', 'generalfeedback'],
            'gapselect generalfeedback' => ['gapselect', 'missingchoiceno', 'generalfeedback'],
            'aitext markscheme' => ['aitext', 'editor', 'markscheme'],
            'aitext aiprompt' => ['aitext', 'editor', 'aiprompt'],
            'aitext graderinfo' => ['aitext', 'editor', 'graderinfo'],
            'gapfill generalfeedback' => ['gapfill', 'catmat', 'generalfeedback'],
        ];
    }

    /**
     * Create a question in a question bank module.
     *
     * @param string $qtype
     * @param string $which
     * @return \stdClass the question
     */
    private function create_question(string $qtype, string $which): \stdClass {
        $course = $this->getDataGenerator()->create_course();
        $qbank = $this->getDataGenerator()->create_module('qbank', ['course' => $course->id]);
        $context = \core\context\module::instance($qbank->cmid);
        /** @var \core_question_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category(['contextid' => $context->id]);
        return $generator->create_question($qtype, $which, ['category' => $category->id]);
    }

    /**
     * Strip the parts of question data that change with every version.
     *
     * @param mixed $data
     * @return mixed
     */
    private function normalise(mixed $data): mixed {
        if (is_object($data)) {
            $data = (array) $data;
        }
        // Some question types store records, with their ids, as JSON.
        if (is_string($data) && str_starts_with($data, '{') && is_array($decoded = json_decode($data, true))) {
            $data = $decoded;
        }
        if (!is_array($data)) {
            return $data;
        }
        foreach (self::VOLATILE_KEYS as $key) {
            unset($data[$key]);
        }
        foreach ($data as $key => $value) {
            $data[$key] = $this->normalise($value);
        }
        // Records keyed by their (new) ids are compared in order instead.
        if ($data && count(array_filter(array_keys($data), 'is_int')) === count($data)) {
            $data = array_values($data);
        }
        return $data;
    }

    /**
     * Answers and hints, with their ids removed, so they can be compared.
     *
     * @param \stdClass $questiondata
     * @return array
     */
    private function get_answers_and_hints(\stdClass $questiondata): array {
        return [
            array_values(array_map([$this, 'normalise'], $questiondata->options->answers ?? [])),
            array_values(array_map([$this, 'normalise'], $questiondata->hints ?? [])),
        ];
    }

    /**
     * Changing one field saves a new version where only that field differs.
     *
     * @dataProvider qtype_provider
     * @param string $qtype
     * @param string $which
     * @param string $fieldname
     */
    #[DataProvider('qtype_provider')]
    public function test_write(string $qtype, string $which, string $fieldname): void {
        if (!\question_bank::is_qtype_installed($qtype)) {
            $this->markTestSkipped("qtype_{$qtype} is not installed");
        }
        $this->resetAfterTest();
        $this->setAdminUser();
        $question = $this->create_question($qtype, $which);
        $field = registry::get_field($fieldname);
        $this->assertTrue($field->supports_qtype($qtype));

        $before = \question_bank::load_question_data($question->id);
        $newvalue = trim($field->get_value($before) . ' changed by bulk edit');
        $newid = version_writer::write($question->id, $field, $newvalue);

        $this->assertNotEquals($question->id, $newid);
        $after = \question_bank::load_question_data($newid);
        $this->assertSame($newvalue, $field->get_value($after));
        $this->assertEquals($before->version + 1, $after->version);
        $this->assertEquals($before->questionbankentryid, $after->questionbankentryid);

        // Everything else is unchanged.
        $expected = $this->normalise($before);
        $actual = $this->normalise($after);
        $path = $fieldname === 'name' || $fieldname === 'questiontext' || $fieldname === 'generalfeedback'
            ? null : 'options';
        if ($path) {
            unset($expected[$path][$fieldname], $actual[$path][$fieldname]);
        } else {
            unset($expected[$fieldname], $actual[$fieldname]);
        }
        $this->assertEquals($expected, $actual);
        $this->assertEquals($this->get_answers_and_hints($before), $this->get_answers_and_hints($after));
    }

    /**
     * Files embedded in an editor field are kept in the new version.
     */
    public function test_write_keeps_files(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $question = $this->create_question('essay', 'editor');
        $questiondata = \question_bank::load_question_data($question->id);
        $fs = get_file_storage();
        $fs->create_file_from_string([
            'contextid' => $questiondata->contextid,
            'component' => 'question',
            'filearea' => 'questiontext',
            'itemid' => $question->id,
            'filepath' => '/',
            'filename' => 'pic.png',
        ], 'not really a png');
        $text = '<p>Look <img src="@@PLUGINFILE@@/pic.png" alt="pic"></p>';
        $field = registry::get_field('questiontext');

        $newid = version_writer::write($question->id, $field, $text);

        $after = \question_bank::load_question_data($newid);
        $this->assertSame($text, $after->questiontext);
        $files = $fs->get_area_files($questiondata->contextid, 'question', 'questiontext', $newid, 'filename', false);
        $this->assertSame(['pic.png'], array_values(array_map(fn($f) => $f->get_filename(), $files)));
    }

    /**
     * Tags stay on the new version.
     */
    public function test_write_keeps_tags(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $question = $this->create_question('shortanswer', 'frogtoad');
        $questiondata = \question_bank::load_question_data($question->id);
        \core_tag_tag::set_item_tags(
            'core_question',
            'question',
            $question->id,
            \core\context::instance_by_id($questiondata->contextid),
            ['frogs', 'toads']
        );

        $newid = version_writer::write($question->id, registry::get_field('name'), 'Renamed');

        $tags = \core_tag_tag::get_item_tags_array('core_question', 'question', $newid);
        sort($tags);
        $this->assertSame(['frogs', 'toads'], array_values($tags));
    }

    /**
     * A value the form rejects is reported and nothing is saved.
     */
    public function test_write_validation_failure(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $question = $this->create_question('shortanswer', 'frogtoad');
        $versions = $DB->count_records('question_versions');

        try {
            version_writer::write($question->id, registry::get_field('questiontext'), '');
            $this->fail('Expected an exception');
        } catch (\moodle_exception $e) {
            $this->assertStringContainsString('editing form', $e->getMessage());
        }
        $this->assertSame($versions, $DB->count_records('question_versions'));
    }
}
