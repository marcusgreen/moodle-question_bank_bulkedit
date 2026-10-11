@qbank @qbank_bulkedit
Feature: Bulk edit selected questions with a find and replace rule
  In order to fix the same text in many questions
  As a teacher
  I need to preview a find and replace across selected questions and apply it

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And the following "activities" exist:
      | activity | name            | course | idnumber |
      | qbank    | Question bank 1 | C1     | qbank1   |
    And the following "question categories" exist:
      | contextlevel    | reference | name           |
      | Activity module | qbank1    | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype     | name   | questiontext       |
      | Test questions   | truefalse | First  | The frog is green  |
      | Test questions   | truefalse | Second | The frog is brown  |
      | Test questions   | truefalse | Third  | The newt is orange |

  @javascript
  Scenario: Preview and apply a simple find and replace
    Given I am on the "Question bank 1" "core_question > question bank" page logged in as "teacher1"
    And I apply question bank filter "Category" with value "Test questions"
    And I click on "First" "checkbox"
    And I click on "Second" "checkbox"
    And I click on "Third" "checkbox"
    And I bulk edit the selected questions
    And I should see "Bulk edit 3 selected question(s)"
    And I set the following fields to these values:
      | Field          | Question text    |
      | Rule           | Find and replace |
      | Find           | frog             |
      | Text to use    | toad             |
    When I press "Preview changes"
    Then I should see "Changes (2)"
    And I should see "Not changed (1)"
    And I click on "Second" "checkbox"
    And I press "Apply changes"
    And I should see "1 updated, 2 skipped, 0 failed."
    And I should see "Updated" in the "First" "table_row"
    And I should see "Unticked" in the "Second" "table_row"
    And I should see "No change" in the "Third" "table_row"
    And I click on "View history" "link" in the "First" "table_row"
    And I should see "Question history"
    And I should see "First"

  @javascript
  Scenario: Build a rule with the AI rule builder
    Given the following config values are set as admin:
      | config               | value                                                                                                   | plugin         |
      | behat_mock_response  | {"mode":"simple","find":"frog","replace":"toad","explanation":"Changes frog to toad."}                    | qbank_bulkedit |
    And I am on the "Question bank 1" "core_question > question bank" page logged in as "teacher1"
    And I apply question bank filter "Category" with value "Test questions"
    And I click on "First" "checkbox"
    And I bulk edit the selected questions
    And I set the following fields to these values:
      | Field       | Question text        |
      | Instruction | Change frog to toad  |
    When I press "Build rule with AI"
    Then I should see "The AI built a rule."
    And I should see "Changes frog to toad."
    And the field "Find" matches value "frog"
    And I press "Preview changes"
    And I should see "Changes (1)"

  @javascript
  Scenario: The AI rule builder explains why it cannot be used
    Given the following config values are set as admin:
      | config                  | value                 | plugin         |
      | behat_mock_unavailable  | Quota reached         | qbank_bulkedit |
    And I am on the "Question bank 1" "core_question > question bank" page logged in as "teacher1"
    And I apply question bank filter "Category" with value "Test questions"
    And I click on "First" "checkbox"
    When I bulk edit the selected questions
    Then I should see "The AI rule builder cannot be used: Quota reached"
    And "Build rule with AI" "button" should not exist
