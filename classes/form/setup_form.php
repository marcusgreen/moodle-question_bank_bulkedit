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

namespace qbank_bulkedit\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

use qbank_bulkedit\local\field\registry;
use qbank_bulkedit\local\rule\rule;
use qbank_bulkedit\local\rule\validator;

/**
 * Form to choose the question types, the field and the rule.
 *
 * Custom data:
 *  - selection: int[] qtype => count of selected questions
 *  - canregex: bool whether the user can write regular expressions
 *  - airule: rule|null the rule last built by the AI
 *  - ai: array|null null if AI is not offered, otherwise
 *        ['reason' => ?string, 'needspolicy' => bool, 'maxsamples' => int]
 *
 * @package    qbank_bulkedit
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class setup_form extends \moodleform {
    #[\Override]
    protected function definition() {
        $mform = $this->_form;
        $selection = $this->_customdata['selection'];
        $ai = $this->_customdata['ai'];

        $mform->addElement('hidden', 'jobid');
        $mform->setType('jobid', PARAM_INT);

        $mform->addElement('header', 'selectionheader', get_string('selection', 'qbank_bulkedit'));
        $mform->setExpanded('selectionheader');

        $qtypeoptions = [];
        foreach ($selection as $qtype => $count) {
            $qtypeoptions[$qtype] = get_string(
                'qtypecount',
                'qbank_bulkedit',
                ['name' => \question_bank::get_qtype_name($qtype), 'count' => $count]
            );
        }
        $mform->addElement(
            'autocomplete',
            'qtypes',
            get_string('qtypes', 'qbank_bulkedit'),
            $qtypeoptions,
            ['multiple' => true, 'noselectionstring' => get_string('noqtypes', 'qbank_bulkedit')]
        );
        $mform->addHelpButton('qtypes', 'qtypes', 'qbank_bulkedit');
        $mform->addRule('qtypes', null, 'required', null, 'client');

        $fieldoptions = [];
        foreach (registry::get_fields_for_qtypes(array_keys($selection)) as $name => $field) {
            $names = array_map(
                ['\question_bank', 'get_qtype_name'],
                array_values(array_intersect($field->get_qtypes(), array_keys($selection)))
            );
            $fieldoptions[$name] = get_string(
                'fieldoption',
                'qbank_bulkedit',
                ['label' => $field->get_label(), 'qtypes' => implode(', ', $names)]
            );
        }
        $mform->addElement('select', 'field', get_string('field', 'qbank_bulkedit'), $fieldoptions);
        $mform->addHelpButton('field', 'field', 'qbank_bulkedit');

        if ($ai !== null) {
            $this->add_ai_elements($ai);
        }

        $mform->addElement('header', 'ruleheader', get_string('rule', 'qbank_bulkedit'));
        $mform->setExpanded('ruleheader');

        $airule = $this->_customdata['airule'];
        if ($airule && $airule->explanation !== '') {
            $mform->addElement(
                'static',
                'aiexplanation',
                get_string('aiexplanation', 'qbank_bulkedit'),
                s($airule->explanation)
            );
        }

        $mform->addElement('select', 'mode', get_string('mode', 'qbank_bulkedit'), $this->get_mode_options());
        $mform->addHelpButton('mode', 'mode', 'qbank_bulkedit');

        $mform->addElement('textarea', 'find', get_string('find', 'qbank_bulkedit'), ['rows' => 3, 'cols' => 60]);
        $mform->setType('find', PARAM_RAW);
        $mform->addHelpButton('find', 'find', 'qbank_bulkedit');

        $mform->addElement('textarea', 'replace', get_string('replace', 'qbank_bulkedit'), ['rows' => 4, 'cols' => 60]);
        $mform->setType('replace', PARAM_RAW);
        $mform->addHelpButton('replace', 'replace', 'qbank_bulkedit');

        $mform->addElement('advcheckbox', 'caseinsensitive', get_string('caseinsensitive', 'qbank_bulkedit'));
        $mform->addElement('advcheckbox', 'firstonly', get_string('firstonly', 'qbank_bulkedit'));

        $mform->addElement('text', 'guard', get_string('guard', 'qbank_bulkedit'), ['size' => 60]);
        $mform->setType('guard', PARAM_RAW);
        $mform->addHelpButton('guard', 'guard', 'qbank_bulkedit');

        foreach ([rule::MODE_APPEND, rule::MODE_PREPEND, rule::MODE_SET] as $mode) {
            $mform->hideIf('find', 'mode', 'eq', $mode);
            $mform->hideIf('firstonly', 'mode', 'eq', $mode);
        }

        $this->add_action_buttons(true, get_string('preview', 'qbank_bulkedit'));
    }

    /**
     * Add the AI rule builder elements.
     *
     * @param array $ai
     */
    private function add_ai_elements(array $ai): void {
        $mform = $this->_form;
        $mform->addElement('header', 'aiheader', get_string('aiheader', 'qbank_bulkedit'));
        $mform->setExpanded('aiheader');
        if ($ai['reason'] !== null) {
            $mform->addElement(
                'static',
                'aiunavailable',
                '',
                get_string('aiunavailable', 'qbank_bulkedit', s($ai['reason']))
            );
            return;
        }
        $mform->addElement(
            'textarea',
            'aiinstruction',
            get_string('aiinstruction', 'qbank_bulkedit'),
            ['rows' => 3, 'cols' => 60, 'placeholder' => get_string('aiinstruction_placeholder', 'qbank_bulkedit')]
        );
        $mform->setType('aiinstruction', PARAM_TEXT);
        $mform->addHelpButton('aiinstruction', 'aiinstruction', 'qbank_bulkedit');
        if ($ai['maxsamples'] > 0) {
            $mform->addElement(
                'advcheckbox',
                'sendsamples',
                get_string('sendsamples', 'qbank_bulkedit'),
                get_string('sendsamples_desc', 'qbank_bulkedit', $ai['maxsamples'])
            );
        }
        if ($ai['needspolicy']) {
            $mform->addElement(
                'static',
                'aipolicy',
                get_string('aiusagepolicy', 'ai'),
                format_text(get_string('userpolicy', 'ai'), FORMAT_HTML)
            );
            $mform->addElement('advcheckbox', 'acceptpolicy', '', get_string('acceptpolicy', 'qbank_bulkedit'));
        }
        $mform->addElement('submit', 'buildrule', get_string('buildrule', 'qbank_bulkedit'));
        $mform->registerNoSubmitButton('buildrule');
    }

    /**
     * The modes the user may choose.
     *
     * @return string[]
     */
    private function get_mode_options(): array {
        $modes = [];
        foreach (rule::get_modes() as $mode) {
            $modes[$mode] = get_string('mode_' . $mode, 'qbank_bulkedit');
        }
        // A regular expression the AI wrote can be kept without the regex capability, but not written.
        $airule = $this->_customdata['airule'];
        if (!$this->_customdata['canregex'] && (!$airule || $airule->mode !== rule::MODE_REGEX)) {
            unset($modes[rule::MODE_REGEX]);
        }
        return $modes;
    }

    /**
     * Build a rule from submitted data.
     *
     * @param \stdClass|array $data
     * @param string $explanation
     * @return rule
     */
    public static function rule_from_data(\stdClass|array $data, string $explanation = ''): rule {
        $data = (array) $data;
        $data['explanation'] = $explanation;
        return rule::from_array($data);
    }

    #[\Override]
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $qtypes = $data['qtypes'] ?? [];
        if (empty($qtypes)) {
            $errors['qtypes'] = get_string('required');
        }
        $field = registry::get_field($data['field'] ?? '');
        if (!$field || !array_filter($qtypes, [$field, 'supports_qtype'])) {
            $errors['field'] = get_string('err_fieldqtypes', 'qbank_bulkedit');
        }
        try {
            $rule = self::rule_from_data($data);
        } catch (\moodle_exception $e) {
            $errors['mode'] = $e->getMessage();
            return $errors;
        }
        $airule = $this->_customdata['airule'];
        if (
            $rule->mode === rule::MODE_REGEX && !$this->_customdata['canregex']
                && (!$airule || !$airule->same_effect($rule))
        ) {
            $errors['find'] = get_string('err_regexpermission', 'qbank_bulkedit');
            return $errors;
        }
        $error = validator::validate($rule);
        if ($error !== null) {
            $errors[$rule->uses_pattern() ? 'find' : 'replace'] = $error;
        }
        return $errors;
    }
}
