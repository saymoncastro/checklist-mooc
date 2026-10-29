@local @local_mooc_audit
Feature: Audit history, evolution, batches and follow-up dashboard
  In order to follow the resolution of the problems of many MOOC rooms
  As a manager or an editing teacher
  I need every audit to be recorded, compared with the previous one and summarised on a dashboard

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | Gestora   | Um       | manager1@example.com |
      | teacher1 | Docente   | Um       | teacher1@example.com |
    And the following "role assigns" exist:
      | user     | role    | contextlevel | reference |
      | manager1 | manager | System       |           |
    And the following "courses" exist:
      | fullname        | shortname | enablecompletion |
      | Sala Histórico  | HIST1     | 1                |
      | Sala Sem Acesso | HIST2     | 1                |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | HIST1  | editingteacher |
    And the following "activities" exist:
      | activity | name        | course | attempts | intro                     |
      | quiz     | Avaliação 1 | HIST1  | 1        | Tentativas permitidas: 1. |

  @javascript @accessibility
  Scenario: An editing teacher audits twice and reads the history and the evolution of the course
    Given I log in as "teacher1"
    And I am on "Sala Histórico" course homepage
    And I navigate to "Technical audit" in current page administration
    And I press "Run audit"
    Then I should see "First recorded audit of this course" in the "[data-region='run-summary']" "css_element"
    When I press "Run again"
    Then I should see "Persistent:" in the "[data-region='run-summary']" "css_element"
    When I click on "See the evolution" "link" in the "[data-region='run-summary']" "css_element"
    Then I should see "stored result, not recalculated"
    And I should see "Persistent" in the "[data-region='evolution']" "css_element"
    And I should see "Listed by date only"
    And the page should meet accessibility standards
    When I click on "Audit history" "link" in the ".local_mooc_audit-run" "css_element"
    Then I should see "Audit page" in the "[data-region='history-runs']" "css_element"
    And I should see "Complete" in the "[data-region='history-runs']" "css_element"
    And the page should meet accessibility standards

  @javascript @accessibility
  Scenario: A manager queues a batch from the dashboard and follows its progress
    Given I log in as "manager1"
    And I navigate to "Courses > Audit follow-up" in site administration
    Then I should see "is not an approval of the course" in the "[data-region='scope-notice']" "css_element"
    And I should see "Not audited" in the "[data-courseid] [data-situation]" "css_element"
    When I click on "input[aria-label='HIST1']" "css_element"
    And I click on "input[aria-label='HIST2']" "css_element"
    And I press "Audit the selected courses"
    Then I should see "Batch created: 2 audits queued"
    And I should see "Queued: 2" in the "[data-region='progress']" "css_element"
    And I run all adhoc tasks
    And I reload the page
    Then I should see "Complete: 2" in the "[data-region='progress']" "css_element"
    When I navigate to "Courses > Audit follow-up" in site administration
    Then I should see "Selection on the dashboard" in the "[data-region='batches']" "css_element"
    And I should not see "Not audited" in the "[data-region='courses']" "css_element"
    And the page should meet accessibility standards

  Scenario: An editing teacher has neither the dashboard nor the batches
    Given I log in as "teacher1"
    When I am on "Sala Histórico" course homepage
    Then I should not see "Audit follow-up"
