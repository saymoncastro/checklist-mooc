@local @local_mooc_audit
Feature: Assisted correction of a technical audit finding
  In order to fix a MOOC safely
  As a manager
  I need to see what a correction would change, confirm it explicitly and see the result of a new audit

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | Gestora   | Um       | manager1@example.com |
      | teacher1 | Docente   | Um       | teacher1@example.com |
      | teacher2 | Tutora    | Dois     | teacher2@example.com |
    And the following "courses" exist:
      | fullname       | shortname | enablecompletion |
      | Curso de teste | CT1       | 1                |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | manager1 | CT1    | manager        |
      | teacher1 | CT1    | editingteacher |
      | teacher2 | CT1    | teacher        |
    And the following "activities" exist:
      | activity | name                  | course | attempts | intro                        |
      | quiz     | Avaliação do Tópico 1 | CT1    | 1        | Tentativas permitidas: 2.    |

  @javascript @accessibility
  Scenario: A manager previews, confirms and sees the correction resolved by the re-audit
    Given I am on the "Curso de teste" "Course" page logged in as "manager1"
    And I navigate to "Technical audit" in current page administration
    And I press "Run audit"
    When I click on "See correction proposal" "link" in the "CL-13" "table_row"
    Then I should see "Correction proposal"
    And I should see "Executable correction after confirmation"
    And I should see "1" in the "[data-region='current']" "css_element"
    And I should see "2" in the "[data-region='proposed']" "css_element"
    And I should see "Avaliação do Tópico 1"
    And the page should meet accessibility standards
    When I press "Confirm correction"
    Then I should see "Correction applied and confirmed: the re-audit no longer reports the non conformity."
    And I should see "Attempts allowed: 1 → 2"
    # The re-audit is recorded: back on the report, CL-13 is no longer listed as a finding.
    And I click on "Back to the report" "link"
    And I should see "Re-audit after an assisted correction"
    And "CL-13" "table_row" should not exist
    And I am on the "Avaliação do Tópico 1" "quiz activity editing" page
    And the field "Attempts allowed" matches value "2"

  @javascript
  Scenario: Cancelling a proposal changes nothing
    Given I am on the "Curso de teste" "Course" page logged in as "manager1"
    And I navigate to "Technical audit" in current page administration
    And I press "Run audit"
    And I click on "See correction proposal" "link" in the "CL-13" "table_row"
    When I click on "Cancel" "link"
    Then I should see "Technical audit: Curso de teste"
    And I am on the "Avaliação do Tópico 1" "quiz activity editing" page
    And the field "Attempts allowed" matches value "1"

  @javascript
  Scenario: An editing teacher opens the audit and applies a correction
    Given I am on the "Curso de teste" "Course" page logged in as "teacher1"
    And I navigate to "Technical audit" in current page administration
    And I press "Run audit"
    When I click on "See correction proposal" "link" in the "CL-13" "table_row"
    And I press "Confirm correction"
    Then I should see "Correction applied and confirmed: the re-audit no longer reports the non conformity."

  @javascript
  Scenario: A non-editing teacher allowed to see proposals cannot apply them
    Given the following "permission overrides" exist:
      | capability                         | permission | role    | contextlevel | reference |
      | local/mooc_audit:audit             | Allow      | teacher | Course       | CT1       |
      | local/mooc_audit:preparecorrection | Allow      | teacher | Course       | CT1       |
    And I am on the "Curso de teste" "Course" page logged in as "teacher2"
    And I navigate to "Technical audit" in current page administration
    And I press "Run audit"
    When I click on "See correction proposal" "link" in the "CL-13" "table_row"
    Then I should see "Correction proposal"
    And "Confirm correction" "button" should not exist
    And I should see "You can see this proposal but you are not allowed to apply it."
