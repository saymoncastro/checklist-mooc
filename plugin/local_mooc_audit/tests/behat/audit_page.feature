@local @local_mooc_audit
Feature: Technical audit of a course
  In order to fix a MOOC before it is offered again
  As a manager
  I need to open the technical audit from the course and read a structured, navigable report

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | Gestora   | Um       | manager1@example.com |
      | teacher1 | Docente   | Um       | teacher1@example.com |
      | teacher2 | Tutora    | Dois     | teacher2@example.com |
      | student1 | Estudante | Um       | student1@example.com |
    And the following "courses" exist:
      | fullname       | shortname | enablecompletion |
      | Curso de teste | CT1       | 1                |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | manager1 | CT1    | manager        |
      | teacher1 | CT1    | editingteacher |
      | teacher2 | CT1    | teacher        |
      | student1 | CT1    | student        |
    And the following "activities" exist:
      | activity | name           | course | attempts |
      | quiz     | Questionário 1 | CT1    | 1        |

  @javascript @accessibility
  Scenario: A manager opens the report from the course menu and runs it again
    Given I am on the "Curso de teste" "Course" page logged in as "manager1"
    When I navigate to "Technical audit" in current page administration
    Then I should see "Technical audit: Curso de teste"
    And "Overall status" "heading" should exist
    And I should see "Blocked" in the ".local_mooc_audit-overall" "css_element"
    And "h4#local_mooc_audit-nonconformities" "css_element" should exist
    And "h4#local_mooc_audit-warnings" "css_element" should exist
    And "h4#local_mooc_audit-unverifiable" "css_element" should exist
    And "h4#local_mooc_audit-conformant" "css_element" should exist
    And "h4#local_mooc_audit-notices" "css_element" should exist
    # Locations with a destination are links whose text names the resource (FR-023).
    And I should see "Questionário 1" in the "a[title='Open: Module settings']" "css_element"
    And "JSON" "link" should exist
    And the page should meet accessibility standards
    When I press "Run again"
    Then I should see "Technical audit: Curso de teste"
    And I should see "Blocked" in the ".local_mooc_audit-overall" "css_element"

  @javascript
  Scenario: Editing teachers open the audit; non-editing teachers and students do not see the entry
    Given I am on the "Curso de teste" "Course" page logged in as "teacher1"
    When I navigate to "Technical audit" in current page administration
    Then I should see "Technical audit: Curso de teste"
    And I log out
    And I am on the "Curso de teste" "Course" page logged in as "teacher2"
    And "Technical audit" "link" should not exist
    And I log out
    And I am on the "Curso de teste" "Course" page logged in as "student1"
    And "Technical audit" "link" should not exist
