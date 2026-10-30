@local @local_mooc_audit
Feature: Fix a problem from the technical audit report
  In order to prepare my course without help
  As an editing teacher
  I need each problem to say what is wrong, why it matters, how to fix it and to take me to the right section

  Background:
    Given the following "courses" exist:
      | fullname          | shortname | enablecompletion |
      | Sala de correção  | MOOC_B    | 1                |
    And the course "MOOC_B" has a quiz that requires a page without completion
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Docente   | Um       | teacher1@example.com |
      | teacher2 | Tutora    | Dois     | teacher2@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | MOOC_B | editingteacher |
      | teacher2 | MOOC_B | teacher        |
    And the following "role capability" exists:
      | role                     | teacher |
      | local/mooc_audit:audit   | allow   |

  @javascript @accessibility
  Scenario: An editing teacher fixes a dependency on an activity without completion and confirms it
    Given I log in as "teacher1"
    And I am on the technical audit page of "MOOC_B"
    When I press "Run audit"
    Then I should see "will never be released" in the "tr[data-rule='X-DEP-02'][data-check='target_completion']" "css_element"
    And I should see "Aprofundamento" in the "tr[data-rule='X-DEP-02'][data-check='target_completion']" "css_element"
    And I should see "You can do this." in the "tr[data-rule='X-DEP-02'][data-check='target_completion']" "css_element"
    And the page should meet accessibility standards
    When I click on "Completion conditions: Aprofundamento" "link" in the "tr[data-rule='X-DEP-02'][data-check='target_completion']" "css_element"
    Then I should see "Completion conditions"
    And I should not see "Restrict access"
    And I set the field "Add requirements" to "1"
    And I set the field "completionview" to "1"
    And I press "Save and display"
    And I am on the technical audit page of "MOOC_B"
    And I press "Run again"
    Then I should not see "will never be released"

  Scenario: A teacher without editing rights reads the problem and who can fix it, without actions
    Given I log in as "teacher2"
    And I am on the technical audit page of "MOOC_B"
    When I press "Run audit"
    Then I should see "An editing teacher of the course." in the "tr[data-rule='X-DEP-02'][data-check='target_completion']" "css_element"
    And "Completion conditions: Aprofundamento" "link" should not exist
