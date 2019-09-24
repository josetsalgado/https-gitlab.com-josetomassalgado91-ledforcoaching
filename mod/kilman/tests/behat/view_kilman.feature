@mod @mod_kilman
Feature: kilmans can be public, private or template
  In order to view a kilman
  As a user
  The type of the kilman affects how it is displayed.

@javascript
  Scenario: Add a template kilman
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | manager1 | Manager | 1 | manager1@example.com |
      | teacher1 | Teacher | 1 | teacher1@example.com |
      | student1 | Student | 1 | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |
      | manager1 | C1 | manager |
    And the following "activities" exist:
      | activity | name | description | course | idnumber |
      | kilman | Test kilman | Test kilman description | C1 | kilman0 |
    And I log in as "manager1"
    And I am on site homepage
    And I am on "Course 1" course homepage
    And I follow "Test kilman"
    And I navigate to "Advanced settings" in current page administration
    And I should see "Content options"
    And I set the field "id_realm" to "template"
    And I press "Save and display"
    Then I should see "Template kilmans are not viewable"

@javascript
  Scenario: Add a kilman from a public kilman
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | manager1 | Manager | 1 | manager1@example.com |
      | teacher1 | Teacher | 1 | teacher1@example.com |
      | student1 | Student | 1 | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
      | Course 2 | C2 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |
      | manager1 | C1 | manager |
      | manager1 | C2 | manager |
      | student1 | C2 | student |
    And the following "activities" exist:
      | activity | name | description | course | idnumber |
      | kilman | Test kilman | Test kilman description | C1 | kilman0 |
    And the following config values are set as admin:
      | coursebinenable | 0 | tool_recyclebin |
    And I log in as "manager1"
    And I am on site homepage
    And I am on "Course 1" course homepage
    And I follow "Test kilman"
    And I follow "Test kilman"
    And I navigate to "Questions" in current page administration
    And I add a "Check Boxes" question and I fill the form with:
      | Question Name | Q1 |
      | Yes | y |
      | Min. forced responses | 1 |
      | Max. forced responses | 2 |
      | Question Text | Select one or two choices only |
      | Possible answers | One,Two,Three,Four |
# Neither of the following steps work in 3.2, since the admin options are not available on any page but "view".
    And I follow "Advanced settings"
    And I should see "Content options"
    And I set the field "id_realm" to "public"
    And I press "Save and return to course"
# Verify that a public kilman cannot be used in the same course.
    And I turn editing mode on
    And I add a "kilman" to section "1"
    And I expand all fieldsets
    Then I should see "(No public kilmans.)"
    And I press "Cancel"
# Verify that a public kilman can be used in a different course.
    And I am on site homepage
    And I am on "Course 2" course homepage
    And I add a "kilman" to section "1"
    And I expand all fieldsets
    And I set the field "name" to "kilman from public"
    And I click on "Test kilman [Course 1]" "radio"
    And I press "Save and return to course"
    And I log out
    And I log in as "student1"
    And I am on "Course 2" course homepage
    And I follow "kilman from public"
    Then I should see "Answer the questions..."
# Verify message for public kilman that has been deleted.
    And I log out
    And I log in as "manager1"
    And I am on site homepage
    And I am on "Course 1" course homepage
    And I turn editing mode on
    And I delete "Test kilman" activity
    And I am on site homepage
    And I am on "Course 2" course homepage
    And I follow "kilman from public"
    Then I should see "This kilman used to depend on a Public kilman which has been deleted."
    And I should see "It can no longer be used and should be deleted."
    And I log out
    And I log in as "student1"
    And I am on "Course 2" course homepage
    And I follow "kilman from public"
    Then I should see "This kilman is no longer available. Ask your teacher to delete it."