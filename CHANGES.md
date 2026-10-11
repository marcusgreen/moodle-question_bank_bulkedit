# Changes

## 2.0.0 (2026-10-11)

Complete rewrite for Moodle 5.0 and later. The 2022 proof of concept is tagged `v0.1-poc`.

* Bulk edit selected questions with find and replace (simple pattern syntax or regular
  expressions), add to start or end, or replace the whole field.
* Dry run preview with per-question tick boxes before anything is changed.
* Changes are saved as new question versions through each question type's editing form.
* Field registry with a hook so question types can add fields. Ships with core fields and
  AI Text / Essay fields.
* Optional AI rule builder: the AI writes the rule from a plain language instruction.
  Supports the Moodle AI subsystem (default), local_ai_manager and tool_aiconnect.
* Questions changed since the preview, questions the user cannot edit and questions from
  other banks are skipped.
* Large edits run as an adhoc task.
* Capabilities, privacy provider, event, purge task, PHPUnit and Behat tests.
