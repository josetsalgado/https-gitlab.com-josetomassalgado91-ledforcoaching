@mod @mod_kilman
Feature: Add a kilman activity
  In order to conduct surveys of the users in a course
  As a teacher
  I need to add a kilman activity to a moodle course

@javascript
  Scenario: Add a kilman to a course without questions
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
      | student1 | Student | 1 | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |
    And the following "activities" exist:
      | activity | name | description | course | idnumber |
      | kilman | Test kilman | Test kilman description | C1 | kilman0 |
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test kilman"
    Then I should see "This kilman does not contain any questions."