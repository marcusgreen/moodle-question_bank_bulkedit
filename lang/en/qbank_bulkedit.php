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
 * Strings for qbank_bulkedit, language 'en'.
 *
 * @package    qbank_bulkedit
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['acceptpolicy'] = 'I accept the AI usage policy';
$string['aiexplanation'] = 'What the AI rule does';
$string['aiheader'] = 'Describe the change (AI)';
$string['aiinstruction'] = 'Instruction';
$string['aiinstruction_help'] = 'Describe the change in plain language, for example "Add \'Marks: 10\' to the end of the mark scheme unless it is already there". The AI writes a find and replace rule, which fills in the rule below. Check the rule and the preview before applying it. The AI never changes the questions itself.

To refine a rule, change the instruction, for example "also ignore case", and build the rule again. The current rule is sent with the instruction.';
$string['aiinstruction_placeholder'] = 'e.g. Change "Marks: 5" to "Marks: 10"';
$string['ainotavailable'] = 'AI is not available. An administrator needs to set up an AI provider for generating text.';
$string['ainotenabledhere'] = 'AI is not enabled in this course.';
$string['airulebuilt'] = 'The AI built a rule. Check it, then preview the changes.';
$string['aisettings'] = 'AI rule builder';
$string['aisettings_desc'] = 'Teachers can describe a change in plain language and the AI writes the find and replace rule. The AI does not see or change questions unless the teacher chooses to send sample values.';
$string['aiunavailable'] = 'The AI rule builder cannot be used: {$a}';
$string['applychanges'] = 'Apply changes';
$string['applying'] = 'The changes are being applied in the background. This page refreshes until they are done.';
$string['backend'] = 'AI backend';
$string['backend_aiconnect'] = 'tool_aiconnect (legacy)';
$string['backend_aimanager'] = 'AI manager (local_ai_manager)';
$string['backend_core'] = 'Moodle AI subsystem';
$string['backend_desc'] = 'Which AI system sends the requests. Only installed backends are listed.';
$string['backtorule'] = 'Back to the rule';
$string['buildrule'] = 'Build rule with AI';
$string['bulkaction'] = 'Bulk edit';
$string['bulkedit:use'] = 'Bulk edit questions';
$string['bulkedit:useai'] = 'Use AI to build bulk edit rules';
$string['bulkedit:useregex'] = 'Write regular expressions for bulk edits';
$string['caseinsensitive'] = 'Ignore case';
$string['change'] = 'Change';
$string['changes'] = 'Changes ({$a})';
$string['err_airequest'] = 'The AI request failed: {$a}';
$string['err_airesponse'] = 'The AI did not return a valid rule. It returned: {$a}';
$string['err_airule'] = 'The AI returned a rule that cannot be used: {$a}';
$string['err_emptyfind'] = 'Enter the text to find.';
$string['err_emptytext'] = 'Enter the text to add.';
$string['err_fieldqtypes'] = 'Choose a field that at least one of the chosen question types has.';
$string['err_findtoolong'] = 'The text to find can be at most {$a} characters.';
$string['err_formvalidation'] = 'The question could not be saved because its editing form reported a problem: {$a}';
$string['err_invalidmode'] = 'Unknown rule mode: {$a}';
$string['err_invalidregex'] = 'The regular expression is not valid: {$a}';
$string['err_invalidrule'] = 'The rule is not valid.';
$string['err_nestedquantifier'] = 'The regular expression repeats a group that itself repeats, such as (a+)+. This can be extremely slow, so it is not allowed.';
$string['err_noformelement'] = 'The question editing form has no field called "{$a}".';
$string['err_noinstruction'] = 'Enter an instruction for the AI.';
$string['err_notowner'] = 'This bulk edit belongs to another user.';
$string['err_notready'] = 'This bulk edit is not ready for this step.';
$string['err_notsaved'] = 'The new version did not contain the new value, so it was not saved.';
$string['err_policy'] = 'You need to accept the AI usage policy first.';
$string['err_regexpermission'] = 'You do not have permission to write regular expressions. Use the simple pattern syntax instead.';
$string['err_regexruntime'] = 'The pattern failed while running: {$a}';
$string['err_replacetoolong'] = 'The replacement can be at most {$a} characters.';
$string['err_unbalanced'] = 'The pattern has unbalanced brackets. Use \\( and \\) for literal brackets.';
$string['err_wronguser'] = 'AI requests can only be made for the current user.';
$string['eventjobapplied'] = 'Bulk edit applied';
$string['field'] = 'Field';
$string['field_aiprompt'] = 'AI prompt';
$string['field_graderinfo'] = 'Information for graders';
$string['field_help'] = 'The part of each question to change. Only fields that the chosen question types have are listed.';
$string['field_markscheme'] = 'Mark scheme';
$string['field_responsetemplate'] = 'Response template';
$string['fieldoption'] = '{$a->label} ({$a->qtypes})';
$string['find'] = 'Find';
$string['find_help'] = 'What to look for. In "Find and replace" mode, use the simple pattern syntax shown at the bottom of the page. In "Regular expression" mode, enter a PCRE expression without delimiters.

HTML fields are searched as stored, including tags.';
$string['firstonly'] = 'Replace only the first match';
$string['guard'] = 'Skip if the field already contains';
$string['guard_help'] = 'Questions whose field already contains this text are left alone. Use this so that running the same change twice does not add text twice.';
$string['jobfailed'] = 'The bulk edit stopped with an error. Questions listed as updated were saved; the others were not changed.';
$string['maxsamples'] = 'Maximum sample values';
$string['maxsamples_desc'] = 'How many field values a teacher may choose to send to the AI to help it write the rule. 0 never sends question content.';
$string['mode'] = 'Rule';
$string['mode_append'] = 'Add to the end';
$string['mode_help'] = '* Find and replace: find text using the simple pattern syntax and replace it.
* Regular expression: find text with a PCRE regular expression and replace it. Use $1 for captured groups.
* Add to the end / Add to the start: add the text to every field.
* Replace whole field: set every field to the text.';
$string['mode_prepend'] = 'Add to the start';
$string['mode_regex'] = 'Regular expression';
$string['mode_set'] = 'Replace whole field';
$string['mode_simple'] = 'Find and replace';
$string['nochanges'] = 'The rule does not change any of the selected questions.';
$string['noqtypes'] = 'No question types chosen';
$string['noquestionsselected'] = 'No questions were selected.';
$string['nothingtoapply'] = 'No changes were ticked, so nothing was applied.';
$string['pluginname'] = 'Bulk edit questions';
$string['preview'] = 'Preview changes';
$string['previewheading'] = 'Preview';
$string['previewintro'] = 'Nothing has been changed yet. Untick any question you want to leave alone, then apply. Each changed question is saved as a new version.';
$string['privacy:metadata:aibackend'] = 'When a teacher uses the AI rule builder, their instruction, and any sample field values they choose to send, are sent to the configured AI backend.';
$string['privacy:metadata:aibackend:aiinstruction'] = 'The plain language instruction.';
$string['privacy:metadata:aibackend:samples'] = 'Sample values of the field being edited, only if the teacher chooses to send them.';
$string['privacy:metadata:core_ai'] = 'When the Moodle AI subsystem is the backend, it records the request.';
$string['privacy:metadata:job'] = 'Bulk edits set up by users.';
$string['privacy:metadata:job:aibackend'] = 'The AI backend used to build the rule.';
$string['privacy:metadata:job:aiinstruction'] = 'The instruction given to the AI rule builder.';
$string['privacy:metadata:job:field'] = 'The field being edited.';
$string['privacy:metadata:job:rule'] = 'The find and replace rule.';
$string['privacy:metadata:job:timecreated'] = 'When the bulk edit was created.';
$string['privacy:metadata:job:timemodified'] = 'When the bulk edit was last changed.';
$string['privacy:metadata:job:userid'] = 'The user who set up the bulk edit.';
$string['purpose'] = 'AI manager purpose';
$string['purpose_desc'] = 'The local_ai_manager purpose used for requests.';
$string['purposeplacedescription'] = 'Writes find and replace rules for bulk editing questions from a plain language instruction.';
$string['qtypecount'] = '{$a->name} ({$a->count})';
$string['qtypes'] = 'Question types';
$string['qtypes_help'] = 'Only selected questions of these types are changed. The number of selected questions of each type is shown in brackets.';
$string['replace'] = 'Text to use';
$string['replace_help'] = 'What this text does depends on the rule:

* Find and replace / Regular expression: it replaces each match. $1, $2 ... insert the text matched by the first, second ... bracketed part of the pattern.
* Add to the end / Add to the start: it is added to the field. Nothing is replaced.
* Replace whole field: it becomes the whole value of the field.';
$string['resultheading'] = 'Bulk edit result';
$string['resultsummary'] = '{$a->updated} updated, {$a->skipped} skipped, {$a->errors} failed.';
$string['retentiondays'] = 'Keep bulk edit records for (days)';
$string['retentiondays_desc'] = 'Bulk edit records older than this are deleted. 0 keeps them for ever. The question versions are never deleted.';
$string['returntobank'] = 'Return to the question bank';
$string['rule'] = 'Rule';
$string['selection'] = 'Selection';
$string['sendsamples'] = 'Send samples';
$string['sendsamples_desc'] = 'Send up to {$a} current values of the field to the AI to help it write the rule';
$string['setupheading'] = 'Bulk edit {$a} selected question(s)';
$string['status_change'] = 'Will change';
$string['status_excluded'] = 'Unticked';
$string['status_failed'] = 'Failed';
$string['status_nofield'] = 'Field not available for this question type';
$string['status_nomatch'] = 'No change';
$string['status_nopermission'] = 'You cannot edit this question here';
$string['status_pending'] = 'Not previewed';
$string['status_stale'] = 'Changed since it was selected or previewed';
$string['status_updated'] = 'Updated';
$string['status_wrongqtype'] = 'Question type not chosen';
$string['synclimit'] = 'Background threshold';
$string['synclimit_desc'] = 'Bulk edits that change more questions than this are applied by a background task.';
$string['syntax_anchor'] = 'At the very start or end of the pattern: the start or end of the field';
$string['syntax_capture'] = 'Brackets capture text, which $1, $2 ... insert in the replacement';
$string['syntax_escape'] = 'Makes the next character literal, for example \\* or \\(';
$string['syntax_example'] = 'Example: find <code>Marks: {n}</code> and replace with <code>Marks: 10</code>. Find <code>(*) is a (word)</code> ... replace with <code>$2 is a $1</code> swaps the words.';
$string['syntax_line'] = 'The rest of the line';
$string['syntax_n'] = 'A number, such as 5 or 2.5';
$string['syntax_question'] = 'Any single character';
$string['syntax_star'] = 'Any text';
$string['syntax_word'] = 'One word';
$string['syntaxhelp'] = 'Simple pattern syntax';
$string['syntaxmeaning'] = 'Meaning';
$string['syntaxtoken'] = 'Pattern';
$string['systemprompt'] = 'AI instructions';
$string['systemprompt_default'] = 'You write find and replace rules for a Moodle bulk question editor. You never edit text yourself: you return one rule that a program applies to every selected question.

Reply with a single JSON object and nothing else, with these keys:
- "mode": one of "simple", "regex", "append", "prepend", "set"
- "find": the pattern to find (simple and regex modes only, otherwise "")
- "replace": the replacement, or the text to add or set
- "caseinsensitive": true or false
- "firstonly": true to replace only the first match
- "guard": text that, if the field already contains it, means the field is skipped (use it so the rule is safe to run twice), or ""
- "explanation": one short sentence, for a teacher, saying what the rule does

Prefer "simple" mode. Its syntax: * any text, ? any one character, {word} one word, {n} a number, {line} the rest of the line, ^ at the start and $ at the end anchor to the start and end of the field, (...) captures text for $1, $2 in the replacement, \\ makes the next character literal. Everything else is literal.
Use "regex" (PCRE, no delimiters) only if simple mode cannot express the rule. Never use nested repetition such as (a+)+.
Use "append", "prepend" or "set" when the instruction adds text or replaces the whole field.
HTML fields are matched as stored, including tags; keep HTML valid.
If a current rule is given, change it as the instruction asks.';
$string['systemprompt_desc'] = 'Instructions sent to the AI before the teacher\'s request. Leave empty to use the default, which describes the rule format the plugin expects.';
$string['taskapplyjob'] = 'Apply a bulk question edit';
$string['taskpurgejobs'] = 'Delete old bulk question edit records';
$string['unchanged'] = 'Not changed ({$a})';
$string['unsupportedqtypes'] = 'Bulk edit does not support these question types yet, so they will not be changed: {$a}';
$string['viewhistory'] = 'View history';
$string['willqueue'] = 'More than {$a} questions will change, so the changes will be applied in the background.';
