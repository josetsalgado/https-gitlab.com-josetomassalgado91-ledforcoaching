@mod @mod_kilman
Feature: Review responses with different capabilities
  In order to review and manage kilman responses
  As a user
  I need proper capabilities to access the view responses features

@javascript
  Scenario: A teacher with mod/kilman:readallresponseanytime can see all responses.
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
    And I log in as "admin"
    And I set the following system permissions of "Teacher" role:
      | capability           | permission |
      | mod/kilman:readallresponseanytime | Allow |
    And the following "activities" exist:
      | activity | name | description | course | idnumber |
      | kilman | Test kilman | Test kilman description | C1 | kilman0 |
    And "Test kilman" has questions and responses
    And I log out
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test kilman"
    Then I should see "View All Responses"
    And I navigate to "View All Responses" in current page administration
    Then I should see "View All Responses."
    And I should see "All participants."
    And I should see "View Default order"
    And I should see "Responses: 6"
    And I log out

  @javascript
  Scenario: A teacher denied mod/kilman:readallresponseanytime cannot see all responses.
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
    And I log in as "admin"
    And I set the following system permissions of "Teacher" role:
      | capability           | permission |
      | mod/kilman:readallresponseanytime | Prohibit |
      | mod/kilman:readallresponses | Allow |
    And the following "activities" exist:
      | activity | name | description | course | idnumber |
      | kilman | Test kilman | Test kilman description | C1 | kilman0 |
    And "Test kilman" has questions and responses
    And I log out
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test kilman"
    Then I should not see "View All Responses"
    And I log out

  @javascript
  Scenario: A teacher with mod/kilman:readallresponses can see responses after appropriate time rules.
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
    And I log in as "admin"
    And I set the following system permissions of "Teacher" role:
      | capability           | permission |
      | mod/kilman:readallresponseanytime | Prohibit |
      | mod/kilman:readallresponses | Allow |
    And the following "activities" exist:
      | activity | name | description | course | idnumber | resp_view |
      | kilman | Test kilman | Test kilman description | C1 | kilman0 | 0 |
      | kilman | Test kilman 2 | Test kilman 2 description | C1 | kilman2 | 3 |
    And "Test kilman" has questions and responses
    And "Test kilman 2" has questions and responses
    And I log out
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test kilman"
    Then I should not see "View All Responses"
    And I am on "Course 1" course homepage
    And I follow "Test kilman 2"
    Then I should see "View All Responses"
    And I log out
