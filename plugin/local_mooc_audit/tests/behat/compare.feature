@local @local_mooc_audit
Feature: Comparison between two offers of a MOOC
  In order to prepare the room of a new offer
  As a manager
  I need to compare the previous offer with the new one and see what must be reviewed, without changing either

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | Gestora   | Um       | manager1@example.com |
      | teacher1 | Docente   | Um       | teacher1@example.com |
    And the following "role assigns" exist:
      | user     | role    | contextlevel | reference |
      | manager1 | manager | System       |           |
    And the following "courses" exist:
      | fullname            | shortname | enablecompletion |
      | Acessibilidade 2026 | AT26      | 1                |
      | Acessibilidade 2027 | AT27      | 1                |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | AT26   | editingteacher |
      | teacher1 | AT27   | editingteacher |
    And the following "activities" exist:
      | activity | name        | course | attempts | intro                     | content           |
      | quiz     | Avaliação 1 | AT26   | 2        | Tentativas permitidas: 2. |                   |
      | quiz     | Avaliação 1 | AT27   | 1        | Tentativas permitidas: 2. |                   |
      | page     | Leitura     | AT26   |          |                           | Texto da leitura. |
      | page     | Leitura     | AT27   |          |                           | Texto da leitura. |
      | page     | Leitura     | AT27   |          |                           | Texto da leitura. |
      | page     | Glossário   | AT26   |          |                           | Termos.           |

  @javascript @accessibility
  Scenario: A manager selects the two offers, confirms the order and sees what changed
    Given I log in as "manager1"
    And I navigate to "Courses > Compare offers" in site administration
    When I set the field "Source course (previous offer)" to "Acessibilidade 2026"
    And I set the field "Target course (new offer)" to "Acessibilidade 2027"
    And I press "Compare offers"
    Then I should see "Confirm which course is the previous offer and which is the new one."
    When I set the field "I confirm that the source is the previous offer and the target is the new offer" to "1"
    And I press "Compare offers"
    Then I should see "Review the new course"
    And I should see "Acessibilidade 2026" in the ".local_mooc_audit-sides" "css_element"
    And I should see "Acessibilidade 2027" in the ".local_mooc_audit-sides" "css_element"
    And I should see "extra.attempts" in the ".local_mooc_audit-modified-item" "css_element"
    And I should see "2 → 1" in the ".local_mooc_audit-modified-item" "css_element"
    And I should see "Glossário" in the ".local_mooc_audit-missing" "css_element"
    And I should see "Leitura" in the ".local_mooc_audit-ambiguity" "css_element"
    And I should see "New in the target" in the ".local_mooc_audit-class-novo_no_destino" "css_element"
    And the page should meet accessibility standards
    When I set the field "pair[]" to "Section 0, position 2"
    And I press "Compare again with these matches"
    Then I should see "chosen manually"
    And I should see "Nothing to report." in the "#local_mooc_audit-ambiguous + p" "css_element"

  @javascript
  Scenario: The audit report links to the comparison with the new offer as target
    Given I am on the "Acessibilidade 2027" "Course" page logged in as "manager1"
    And I navigate to "Technical audit" in current page administration
    When I click on "Compare with the previous offer" "link"
    Then I should see "Compare offers"
    And I should see "Acessibilidade 2027" in the "#fitem_id_target" "css_element"

  Scenario: An editing teacher of both courses audits them but is not offered the comparison
    Given I am on the "Acessibilidade 2027" "Course" page logged in as "teacher1"
    When I navigate to "Technical audit" in current page administration
    Then I should see "Technical audit: Acessibilidade 2027"
    And I should not see "Compare with the previous offer"
