# Bulk edit questions (`qbank_bulkedit`)

A Moodle question bank plugin for changing the same text in many questions at once.

Select questions in the question bank, choose **With selected → Bulk edit**, then:

1. Choose which question types to change and which field (question name, question text,
   general feedback, or question type specific fields such as the AI Text prompt and mark scheme).
2. Write a rule:
   * **Find and replace** with a simple pattern syntax (see below)
   * **Regular expression** (PCRE), for users with the `qbank/bulkedit:useregex` capability
   * **Add to the end**, **Add to the start** or **Replace whole field**
   * or **describe the change in plain language** and let AI write the rule.

   Options: **Ignore case**, **Replace only the first match**, and
   **Skip if the field already contains** some text, so running the same change twice does
   not add text twice.
3. **Preview** the changes. Nothing is changed yet. Untick any question you want to leave alone.
4. **Apply**. Each changed question is saved as a **new version** through its question type's
   editing form, so nothing is lost and quizzes that use an older version are not affected.

When applying, each question is checked again. A question is skipped if it has changed since
the preview, the user can no longer edit it, or it is not in the question bank the bulk edit
was started from. Bulk edits that change more questions than the *Background threshold*
(default 50) are applied by an adhoc task. The result page shows how many questions were
updated, skipped or failed, with a link to each question's version history.

## Simple pattern syntax

| Pattern | Matches |
|---|---|
| `*` | any text, as little as possible (as much as possible at the end of the pattern) |
| `?` | any single character |
| `{word}` | one word |
| `{n}` | a number, optionally with a decimal part |
| `{line}` | the rest of the line |
| `^` / `$` | at the start / end of the pattern: the start / end of the field |
| `(...)` | capture, used as `$1`, `$2` … in the replacement |
| `\` | makes the next character literal |

Everything else is literal. In the replacement only `$1` to `$99` are special.

Rules are validated before they run: patterns and replacements have a maximum length,
expressions that can backtrack catastrophically (such as `(a+)+`) are rejected, and a
backtrack limit applies while running.

## The AI only writes the rule

The AI turns an instruction such as *"Add 'Marks: 10' to the end of the mark scheme unless it
is already there"* into a rule. The rule is shown, can be edited, and is then run by ordinary
code. This means:

* one AI request per rule, not one per question
* the preview and the applied change are identical
* question content is only sent to the AI if the teacher ticks **Send samples**
  (up to *Maximum sample values*, default 3; 0 never sends question content)

### AI backends

| Backend | Notes |
|---|---|
| Moodle AI subsystem (default) | Uses the site's *Generate text* provider and the Moodle AI usage policy. |
| `local_ai_manager` | Uses the purpose set in the plugin settings (default `singleprompt`), with its quotas and terms of use. |
| `tool_aiconnect` (legacy) | Listed only when installed. |

Only installed backends are offered. None is required: without AI the plugin works with
hand-written rules.

## Settings

*Site administration → Plugins → Question bank plugins → Bulk edit questions*:

| Setting | Default | Purpose |
|---|---|---|
| Background threshold | 50 | Bulk edits changing more questions than this run as an adhoc task |
| Keep bulk edit records for (days) | 30 | Older bulk edit records are deleted by a scheduled task. 0 keeps them for ever. Question versions are never deleted |
| AI backend | Moodle AI subsystem | Which AI system sends requests |
| AI manager purpose | `singleprompt` | Shown only when `local_ai_manager` is installed |
| AI instructions | empty | System prompt sent before the teacher's request. Empty uses the built-in default, which describes the rule format |
| Maximum sample values | 3 | How many field values a teacher may send to the AI |

## Supported question types

Core fields (name, question text, general feedback) are supported for question types whose
editing form has been tested to save a new version without changing anything else:
`aitext`, `ddwtos`, `description`, `essay`, `gapfill`, `gapselect`, `match`, `multichoice`,
`numerical`, `shortanswer`, `truefalse`.

Extra fields: `aiprompt` and `markscheme` (AI Text), `graderinfo` and `responsetemplate`
(Essay and AI Text).

Other plugins can add fields or question types by listening to the
`\qbank_bulkedit\hook\collect_fields` hook. A field with the same name as an existing one
replaces it.

```php
public static function collect_fields(\qbank_bulkedit\hook\collect_fields $hook): void {
    $hook->add_qtypes_to_field('questiontext', ['myqtype']);
    $hook->add_field(new \qbank_bulkedit\local\field\field(
        'myfield', get_string('myfield', 'qtype_myqtype'), ['myqtype'],
        'myfield', \qbank_bulkedit\local\field\field::TYPE_TEXTAREA,
        fn($q) => $q->options->myfield ?? null,
    ));
}
```

Field types are `TYPE_TEXT`, `TYPE_TEXTAREA` and `TYPE_EDITOR`. The fourth argument is the
name of the element in the question type's editing form.

Add a question type only once a test shows that saving through its editing form leaves every
other field unchanged (see `tests/local/version_writer_test.php`).

## Capabilities

| Capability | Default | Purpose |
|---|---|---|
| `qbank/bulkedit:use` | Editing teacher, manager | Use bulk edit |
| `qbank/bulkedit:useregex` | Manager | Write regular expressions (a regex the AI wrote can still be used) |
| `qbank/bulkedit:useai` | Editing teacher, manager | Use the AI rule builder |

Each question is also checked with the normal question edit capability, and must be in the
question bank the bulk edit was started from.

## Privacy and logging

Bulk edit records (user, field, rule, AI instruction and backend) are stored in
`qbank_bulkedit_job` and covered by the privacy provider, which also declares the AI
instruction and any samples sent to the AI backend. A `job_applied` event is logged when a
bulk edit is applied.

## Requirements

Moodle 5.0 or later.

## Installation

```
git clone https://github.com/marcusgreen/moodle-qbank_bulkedit.git question/bank/bulkedit
```

Then visit *Site administration → Notifications*.

## Licence

GNU GPL v3 or later.
