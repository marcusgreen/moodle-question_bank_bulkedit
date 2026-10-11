# qbank_bulkedit v2 plan

Rule-based bulk edit of **selected** questions. AI (optional) writes the rule, never the field text.
Dry run before apply. Every change is a new question version.

## Flow
1. Question bank → tick questions → *With selected → Bulk edit* → `setup.php` (creates a job).
2. Choose question types, field, and a rule (simple / regex / append / prepend / set),
   or describe the change and press *Build rule with AI*.
3. `preview.php`: diff per question, tick boxes. Nothing is written.
4. `apply.php`: re-checks each question, saves new versions (adhoc task above `synclimit`).
5. `result.php`: updated / skipped / failed, links to question history.

## Components
| Area | Where |
|---|---|
| Bulk action | `classes/plugin_feature.php`, `classes/bulk_edit_action.php` |
| Field registry + hook | `classes/local/field/`, `classes/hook/collect_fields.php` |
| Rule engine | `classes/local/rule/` (`rule`, `simple_pattern`, `engine`, `validator`) |
| AI | `classes/local/ai/request_provider.php` (core AI default, local_ai_manager, tool_aiconnect), `rule_builder.php`, `classes/testing/fake_request_provider.php` |
| Jobs | `classes/local/job.php`, tables `qbank_bulkedit_job`, `qbank_bulkedit_item` |
| Saving versions | `classes/local/version_writer.php` (edit form round trip, then `save_question()`) |
| Tasks | `classes/task/apply_job.php`, `classes/task/purge_jobs.php` |
| Privacy, event | `classes/privacy/provider.php`, `classes/event/job_applied.php` |

## Decisions
* Name `qbank_bulkedit` (`qbank_bulkupdate` is taken in the plugins directory).
* Scope: only selected questions.
* AI builds the rule; one request per build/refine; samples are opt-in.
* Default backend: Moodle core AI subsystem.
* Raw regex is manager-only by default; a regex the AI wrote may be used without that capability.
* Staleness: an item is skipped if a newer version exists or the value changed since the preview.

## Saving a new version
`version_writer` fills the qtype's editing form exactly like the edit page, changes one element,
submits it (validation runs), then calls `save_question()`, re-applies tags and custom fields,
and checks the saved value inside a transaction. Editor fields keep their files via draft areas.

Risk: forms that depend on the request or wizard steps (calculated*, multianswer) are not
supported. A question type is only listed once `version_writer_test` proves a clean round trip.

## Still to do
* Revert job (re-save old values as a new version) using `questionid` / `newquestionid`.
* More question types (multichoice answer feedback, shortanswer feedback, ordering, ddimageortext).
* Merge `qbank_bulksearch` highlighting into the preview.
* Move the AI backend switch shared with qtype_aitext and assignfeedback_aif into one helper.
* Open: confirm the simple syntax token set and the default background threshold (50).
