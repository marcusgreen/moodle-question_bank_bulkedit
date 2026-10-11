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

/**
 * Choose the question types, field and rule for a bulk edit.
 *
 * Called from the question bank bulk action with the selected questions,
 * which creates a job, then with the job id.
 *
 * @package    qbank_bulkedit
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php'); // phpcs:ignore moodle.Files.RequireLogin.Missing -- helper::setup_page() logs in.

use qbank_bulkedit\form\setup_form;
use qbank_bulkedit\local\ai\request_provider;
use qbank_bulkedit\local\ai\rule_builder;
use qbank_bulkedit\local\field\registry;
use qbank_bulkedit\local\helper;
use qbank_bulkedit\local\job;
use qbank_bulkedit\local\rule\validator;

$jobid = optional_param('jobid', 0, PARAM_INT);

if (!$jobid) {
    // Coming from the question bank with the selected questions.
    $cmid = required_param('cmid', PARAM_INT);
    $returnurl = optional_param('returnurl', '', PARAM_LOCALURL);
    helper::setup_page($cmid, new moodle_url('/question/bank/bulkedit/setup.php', ['cmid' => $cmid]));
    require_sesskey();
    $questionids = helper::get_selected_question_ids((array) data_submitted());
    if (!$questionids) {
        redirect(
            $returnurl ? new moodle_url($returnurl) : new moodle_url('/question/edit.php', ['cmid' => $cmid]),
            get_string('noquestionsselected', 'qbank_bulkedit'),
            null,
            \core\output\notification::NOTIFY_WARNING
        );
    }
    $job = job::create($cmid, (string) $returnurl, $questionids);
    redirect(new moodle_url('/question/bank/bulkedit/setup.php', ['jobid' => $job->get_id()]));
}

$url = new moodle_url('/question/bank/bulkedit/setup.php', ['jobid' => $jobid]);
$job = helper::setup_job_page($jobid, $url);
$context = $job->get_context();
if (!in_array($job->get_status(), [job::STATUS_DRAFT, job::STATUS_PREVIEWED])) {
    redirect(new moodle_url('/question/bank/bulkedit/result.php', ['jobid' => $jobid]));
}

$selection = $job->get_selection_qtypes();
$supported = registry::get_supported_qtypes();

// AI is offered when the user may use it and a backend is installed.
$ai = null;
$provider = \core\di::get(request_provider::class);
$backends = request_provider::get_installed_backends();
if (has_capability('qbank/bulkedit:useai', $context) && isset($backends[$provider->get_backend()])) {
    $ai = [
        'reason' => $provider->get_unavailability_reason($context->id),
        'needspolicy' => $provider->needs_policy_acceptance($USER->id),
        'maxsamples' => (int) get_config('qbank_bulkedit', 'maxsamples'),
    ];
}

$form = new setup_form($url, [
    'selection' => $selection,
    'canregex' => has_capability('qbank/bulkedit:useregex', $context),
    'airule' => $job->get_ai_rule(),
    'ai' => $ai,
]);

$defaults = ['jobid' => $jobid, 'aiinstruction' => $job->get_record()->aiinstruction];
$defaults['qtypes'] = $job->get_qtypes() ?: array_values(array_intersect(array_keys($selection), $supported));
if ($job->get_record()->field) {
    $defaults['field'] = $job->get_record()->field;
}
if ($rule = $job->get_rule()) {
    $defaults += $rule->to_array();
}
$form->set_data($defaults);

$aierror = null;
if ($form->is_cancelled()) {
    $returnurl = $job->get_return_url();
    $job->delete();
    redirect($returnurl);
} else if (
    $ai !== null && $ai['reason'] === null && $form->no_submit_button_pressed()
        && optional_param('buildrule', '', PARAM_RAW) !== ''
) {
    require_sesskey();
    $data = $form->get_submitted_data();
    $qtypes = array_values(array_intersect((array) ($data->qtypes ?? []), array_keys($selection)));
    $field = registry::get_field($data->field ?? '');
    $instruction = trim($data->aiinstruction ?? '');
    if (!$qtypes || !$field || !array_filter($qtypes, [$field, 'supports_qtype'])) {
        $aierror = get_string('err_fieldqtypes', 'qbank_bulkedit');
    } else if ($instruction === '') {
        $aierror = get_string('err_noinstruction', 'qbank_bulkedit');
    } else if ($ai['needspolicy'] && empty($data->acceptpolicy)) {
        $aierror = get_string('err_policy', 'qbank_bulkedit');
    } else {
        if ($ai['needspolicy']) {
            $provider->accept_policy($USER->id, $context->id);
        }
        // Refine the rule on the form if it is a valid one.
        $current = null;
        try {
            $current = setup_form::rule_from_data($data);
            if (validator::validate($current) !== null) {
                $current = null;
            }
        } catch (moodle_exception $e) {
            $current = null;
        }
        $job->save_settings($qtypes, $field->get_name(), $current, $instruction);
        $samples = !empty($data->sendsamples) ? $job->get_sample_values($ai['maxsamples']) : [];
        try {
            $result = (new rule_builder())->build(
                $instruction,
                $field,
                $qtypes,
                $current,
                $samples,
                $context->id,
                $USER->id
            );
            $job->save_ai_rule($result['rule'], $provider->get_backend(), $result['model']);
            redirect($url, get_string('airulebuilt', 'qbank_bulkedit'), null, \core\output\notification::NOTIFY_SUCCESS);
        } catch (moodle_exception $e) {
            $aierror = $e->getMessage();
        }
    }
} else if ($data = $form->get_data()) {
    $airule = $job->get_ai_rule();
    $rule = setup_form::rule_from_data($data);
    // Keep the AI's explanation while the rule is still the one it built.
    if ($airule && $airule->same_effect($rule)) {
        $rule = $airule;
    }
    $qtypes = array_values(array_intersect($data->qtypes, array_keys($selection)));
    $job->save_settings($qtypes, $data->field, $rule, $data->aiinstruction ?? null);
    redirect(new moodle_url('/question/bank/bulkedit/preview.php', ['jobid' => $jobid]));
}

$unsupported = array_diff(array_keys($selection), $supported);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('setupheading', 'qbank_bulkedit', array_sum($selection)));
if ($unsupported) {
    echo $OUTPUT->notification(get_string(
        'unsupportedqtypes',
        'qbank_bulkedit',
        implode(', ', array_map(['question_bank', 'get_qtype_name'], $unsupported))
    ), 'info');
}
if ($aierror !== null) {
    echo $OUTPUT->notification(s($aierror), 'error');
}
$form->display();
echo $OUTPUT->render_from_template('qbank_bulkedit/syntax_help', []);
echo $OUTPUT->footer();
