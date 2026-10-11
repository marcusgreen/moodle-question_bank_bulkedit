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

use qbank_bulkedit\local\field\field;

/**
 * Saves a changed field as a new question version.
 *
 * The change goes through the question type's own editing form, as if a teacher
 * had opened the question, changed one field and pressed save. That way every
 * question type saves its own data, files are kept and the form validation runs.
 *
 * @package    qbank_bulkedit
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class version_writer {
    /**
     * Save a new version of the question with one field changed.
     *
     * The caller must check the user can edit the question.
     *
     * @param int $questionid the question version to start from
     * @param field $field the field to change
     * @param string $newvalue the new stored value of the field
     * @return int the id of the new question version
     * @throws \moodle_exception if the form does not validate or the saved value is wrong
     */
    public static function write(int $questionid, field $field, string $newvalue): int {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/question/editlib.php');
        require_once($CFG->libdir . '/formslib.php');

        $question = $DB->get_record('question', ['id' => $questionid], '*', MUST_EXIST);
        get_question_options($question, true);
        $qtypeobj = \question_bank::get_qtype($question->qtype);
        $category = $question->categoryobject;
        $context = \core\context::instance_by_id($category->contextid);
        $contexts = new \core_question\local\bank\question_edit_contexts($context);

        $question->contextid = $category->contextid;
        $question->formoptions = (object) [
            'canedit' => true,
            'canmove' => false,
            'cansaveasnew' => false,
            'repeatelements' => true,
            'mustbeusable' => false,
        ];
        $question->beingcopied = false;
        $toform = self::get_initial_form_data($question, $category, $context);

        $fromform = self::submit_form($qtypeobj, $question, $category, $contexts, $toform, $field, $newvalue);

        $transaction = $DB->start_delegated_transaction();
        try {
            $saved = $qtypeobj->save_question($question, $fromform);
            if (isset($fromform->tags)) {
                \core_tag_tag::set_item_tags('core_question', 'question', $saved->id, $context, $fromform->tags, 0);
            }
            if (isset($fromform->coursetags) && !empty($fromform->courseid)) {
                \core_tag_tag::set_item_tags(
                    'core_question',
                    'question',
                    $saved->id,
                    \core\context\course::instance($fromform->courseid),
                    $fromform->coursetags,
                    0
                );
            }
            if (class_exists(\qbank_customfields\customfield\question_handler::class)) {
                \qbank_customfields\customfield\question_handler::create()->instance_form_save($fromform);
            }
            \question_bank::notify_question_edited($saved->id);

            // Check the value really was saved, so a question type that ignores
            // or changes the value is reported instead of silently skipped.
            $check = \question_bank::load_question_data($saved->id);
            if (trim((string) $field->get_value($check)) !== trim($newvalue)) {
                throw new \moodle_exception('err_notsaved', 'qbank_bulkedit');
            }
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
        }
        return (int) $saved->id;
    }

    /**
     * Build the data the editing page passes to the form.
     *
     * @param \stdClass $question
     * @param \stdClass $category
     * @param \core\context $context
     * @return \stdClass
     */
    private static function get_initial_form_data(\stdClass $question, \stdClass $category, \core\context $context): \stdClass {
        $toform = fullclone($question);
        $toform->category = "{$category->id},{$category->contextid}";
        $toform->categorymoveto = $toform->category;
        $toform->makecopy = 0;
        $toform->appendqnumstring = '';
        $toform->returnurl = '';
        $toform->mdlscrollto = 0;
        $toform->inpopup = 0;
        $questionobject = \question_bank::load_question($question->id);
        $toform->status = $questionobject->status;
        $toform->idnumber = $questionobject->idnumber;
        if ($context->contextlevel == CONTEXT_MODULE) {
            $cm = get_coursemodule_from_id('', $context->instanceid, 0, false, MUST_EXIST);
            $toform->cmid = $cm->id;
            $toform->courseid = $cm->course;
        } else {
            $toform->courseid = SITEID;
        }
        if (class_exists(\qbank_customfields\customfield\question_handler::class)) {
            \qbank_customfields\customfield\question_handler::create()->instance_form_before_set_data($toform);
        }
        return $toform;
    }

    /**
     * Fill the editing form with the current data, change one field and validate it.
     *
     * The form is not submitted. Its exported values are exactly what get_data() returns
     * after a submission, and the form's validation and required rules are run on them.
     *
     * @param \question_type $qtypeobj
     * @param \stdClass $question
     * @param \stdClass $category
     * @param \core_question\local\bank\question_edit_contexts $contexts
     * @param \stdClass $toform
     * @param field $field
     * @param string $newvalue
     * @return \stdClass the validated form data
     */
    private static function submit_form(
        \question_type $qtypeobj,
        \stdClass $question,
        \stdClass $category,
        \core_question\local\bank\question_edit_contexts $contexts,
        \stdClass $toform,
        field $field,
        string $newvalue,
    ): \stdClass {
        global $PAGE, $USER;

        // Editing forms use the page URL. It is not set in a task.
        if (!$PAGE->has_set_url()) {
            $PAGE->set_url('/question/bank/editquestion/question.php', ['id' => $question->id]);
        }

        // Forms read the request, so isolate them from the request that is running this code.
        $saved = [$_GET, $_POST, $_FILES];
        try {
            $_GET = $_POST = $_FILES = [];
            $form = $qtypeobj->create_editing_form('question.php', $question, $category, $contexts, true);
            $form->set_data(fullclone($toform));
            $form->definition_after_data();
            $mform = self::get_quickform($form);
            $values = self::fill_from_defaults($mform, $mform->exportValues());
        } finally {
            [$_GET, $_POST, $_FILES] = $saved;
        }

        $element = $field->get_form_element();
        if ($field->is_editor()) {
            if (!isset($values[$element]) || !is_array($values[$element])) {
                throw new \moodle_exception('err_noformelement', 'qbank_bulkedit', '', s($element));
            }
            if (isset($values[$element]['itemid'])) {
                // The form shows embedded files from a draft area, so point the new text there too.
                $usercontext = \core\context\user::instance($USER->id);
                $newvalue = file_rewrite_pluginfile_urls(
                    $newvalue,
                    'draftfile.php',
                    $usercontext->id,
                    'user',
                    'draft',
                    $values[$element]['itemid']
                );
            }
            $values[$element]['text'] = $newvalue;
        } else {
            if (!array_key_exists($element, $values)) {
                throw new \moodle_exception('err_noformelement', 'qbank_bulkedit', '', s($element));
            }
            $values[$element] = $newvalue;
        }

        $validation = $form->validation($values, []);
        $errors = self::get_required_errors($mform, $values) + (is_array($validation) ? $validation : []);
        if ($errors) {
            $message = implode('; ', array_map(fn($e) => strip_tags((string) $e), $errors));
            throw new \moodle_exception('err_formvalidation', 'qbank_bulkedit', '', $message);
        }
        $fromform = (object) $values;

        // Same handling as the editing page: the question stays in its category.
        if (empty($fromform->usecurrentcat) && !empty($fromform->categorymoveto)) {
            $fromform->category = $fromform->categorymoveto;
        }
        [$newcategoryid] = explode(',', $fromform->category);
        if ((int) $newcategoryid !== (int) $category->id) {
            throw new \moodle_exception('err_formvalidation', 'qbank_bulkedit', '', 'category');
        }
        return $fromform;
    }

    /**
     * Fill in element values that the unsubmitted form does not export.
     *
     * A browser selects an option whose value loosely matches the default, for example
     * a fraction of 1 selects the option "1.0". QuickForm only exports exact matches,
     * so those values are taken from the form defaults instead.
     *
     * @param \MoodleQuickForm $mform
     * @param array $values exported values
     * @return array
     */
    private static function fill_from_defaults(\MoodleQuickForm $mform, array $values): array {
        foreach (self::get_element_names($mform->_elements) as $name) {
            $path = explode('[', str_replace(']', '', $name));
            if (self::get_path($values, $path) !== null) {
                continue;
            }
            $default = self::get_path($mform->_defaultValues, $path);
            if ($default === null) {
                $default = $mform->_defaultValues[$name] ?? null;
            }
            if (is_scalar($default)) {
                self::set_path($values, $path, $default);
            }
        }
        return $values;
    }

    /**
     * Check the form's required rules, which are normally checked on submission.
     *
     * @param \MoodleQuickForm $mform
     * @param array $values
     * @return string[] errors keyed by element name
     */
    private static function get_required_errors(\MoodleQuickForm $mform, array $values): array {
        $errors = [];
        foreach ($mform->_required as $name) {
            if (!$mform->elementExists($name)) {
                continue;
            }
            $value = self::get_path($values, explode('[', str_replace(']', '', $name)));
            if (is_array($value) && array_key_exists('text', $value)) {
                $value = $value['text'];
            }
            if (is_array($value) ? !$value : trim(strip_tags((string) $value)) === '') {
                $errors[$name] = get_string('requiredelement', 'form') . ': ' . $name;
            }
        }
        return $errors;
    }

    /**
     * Names of all elements, including those inside groups.
     *
     * @param \HTML_QuickForm_element[] $elements
     * @param string $prefix group name prefix
     * @return string[]
     */
    private static function get_element_names(array $elements, string $prefix = ''): array {
        $names = [];
        foreach ($elements as $element) {
            if ($element instanceof \HTML_QuickForm_group) {
                $groupname = $element->getName();
                $appendname = $element->_appendName ?? true;
                $names = array_merge($names, self::get_element_names(
                    $element->getElements(),
                    $appendname && $groupname ? $prefix . $groupname : $prefix
                ));
                continue;
            }
            $name = (string) $element->getName();
            // An unticked checkbox is not submitted by a browser, so it must not be filled in.
            $skip = ['submit', 'button', 'static', 'html', 'header', 'checkbox'];
            if ($name === '' || in_array($element->getType(), $skip)) {
                continue;
            }
            if ($prefix !== '') {
                $name = $prefix . '[' . str_replace('[', '][', preg_replace('/\]$/', '', $name)) . ']';
            }
            $names[] = $name;
        }
        return $names;
    }

    /**
     * Get a nested array value.
     *
     * @param array $array
     * @param string[] $path
     * @return mixed null if missing
     */
    private static function get_path(array $array, array $path): mixed {
        foreach ($path as $key) {
            if (!is_array($array) || !array_key_exists($key, $array)) {
                return null;
            }
            $array = $array[$key];
        }
        return $array;
    }

    /**
     * Set a nested array value.
     *
     * @param array $array
     * @param string[] $path
     * @param mixed $value
     */
    private static function set_path(array &$array, array $path, mixed $value): void {
        $ref = &$array;
        foreach ($path as $key) {
            if (!isset($ref[$key]) || !is_array($ref[$key])) {
                $ref[$key] = [];
            }
            $ref = &$ref[$key];
        }
        $ref = $value;
    }

    /**
     * Get the QuickForm inside a moodleform. It is protected, and there is no public accessor.
     *
     * @param \moodleform $form
     * @return \MoodleQuickForm
     */
    private static function get_quickform(\moodleform $form): \MoodleQuickForm {
        return \Closure::bind(fn(\moodleform $f) => $f->_form, null, \moodleform::class)($form);
    }
}
