<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * PHPUnit kilman generator tests
 *
 * @package    mod_kilman
 * @copyright  2015 Mike Churchward (mike@churchward.ca)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use mod_kilman\question\base;

global $CFG;
require_once($CFG->dirroot.'/mod/kilman/lib.php');
require_once($CFG->dirroot.'/mod/kilman/classes/question/base.php');

/**
 * Unit tests for {@link kilman_lib_testcase}.
 * @group mod_kilman
 */
class mod_kilman_lib_testcase extends advanced_testcase {
    public function test_kilman_supports() {
        $this->assertTrue(kilman_supports(FEATURE_BACKUP_MOODLE2));
        $this->assertFalse(kilman_supports(FEATURE_COMPLETION_TRACKS_VIEWS));
        $this->assertTrue(kilman_supports(FEATURE_COMPLETION_HAS_RULES));
        $this->assertFalse(kilman_supports(FEATURE_GRADE_HAS_GRADE));
        $this->assertFalse(kilman_supports(FEATURE_GRADE_OUTCOMES));
        $this->assertTrue(kilman_supports(FEATURE_GROUPINGS));
        $this->assertTrue(kilman_supports(FEATURE_GROUPS));
        $this->assertTrue(kilman_supports(FEATURE_MOD_INTRO));
        $this->assertTrue(kilman_supports(FEATURE_SHOW_DESCRIPTION));
        $this->assertNull(kilman_supports('unknown option'));
    }

    public function test_kilman_get_extra_capabilities() {
        $caps = kilman_get_extra_capabilities();
        $this->assertInternalType('array', $caps);
        $this->assertEquals(1, count($caps));
        $this->assertEquals('moodle/site:accessallgroups', reset($caps));
    }

    public function test_add_instance() {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();

        // Create test data as a record.
        $questdata = new stdClass();
        $questdata->course = $course->id;
        $questdata->coursemodule = '';
        $questdata->name = 'Test kilman';
        $questdata->intro = 'Intro to test kilman.';
        $questdata->introformat = FORMAT_HTML;
        $questdata->qtype = 1;
        $questdata->respondenttype = 'anonymous';
        $questdata->resp_eligible = 'none';
        $questdata->resp_view = 2;
        $questdata->opendate = 99;
        $questdata->closedate = 50;
        $questdata->resume = 1;
        $questdata->navigate = 1;
        $questdata->grade = 100;
        $questdata->sid = 1;
        $questdata->timemodified = 3;
        $questdata->completionsubmit = 1;
        $questdata->autonum = 1;

        // Call add_instance with the data.
        $this->assertTrue(kilman_add_instance($questdata) > 0);
    }

    public function test_update_instance() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        /** @var mod_kilman_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kilman');
        /** @var kilman $kilman */
        $kilman = $generator->create_instance(array('course' => $course->id, 'sid' => 1));

        $qid = $kilman->id;
        $this->assertTrue($qid > 0);

        // Change all the default values.
        // Note, we need to get the actual db row to do an update to it.
        $qrow = $DB->get_record('kilman', ['id' => $qid]);
        $qrow->qtype = 1;
        $qrow->respondenttype = 'anonymous';
        $qrow->resp_eligible = 'none';
        $qrow->resp_view = 2;
        $qrow->useopendate = true;
        $qrow->opendate = 99;
        $qrow->useclosedate = true;
        $qrow->closedate = 50;
        $qrow->resume = 1;
        $qrow->navigate = 1;
        $qrow->grade = 100;
        $qrow->timemodified = 3;
        $qrow->completionsubmit = 1;
        $qrow->autonum = 1;
        $qrow->coursemodule = $kilman->cm->id;

        // Moodle update form passes "instance" instead of "id" to [mod]_update_instance.
        $qrow->instance = $qid;
        // Grade function needs the "cm" "idnumber" field.
        $qrow->cmidnumber = '';

        $this->assertTrue(kilman_update_instance($qrow));

        $questrecord = $DB->get_record('kilman', array('id' => $qid));
        $this->assertNotEmpty($questrecord);
        $this->assertEquals($qrow->qtype, $questrecord->qtype);
        $this->assertEquals($qrow->respondenttype, $questrecord->respondenttype);
        $this->assertEquals($qrow->resp_eligible, $questrecord->resp_eligible);
        $this->assertEquals($qrow->resp_view, $questrecord->resp_view);
        $this->assertEquals($qrow->opendate, $questrecord->opendate);
        $this->assertEquals($qrow->closedate, $questrecord->closedate);
        $this->assertEquals($qrow->resume, $questrecord->resume);
        $this->assertEquals($qrow->navigate, $questrecord->navigate);
        $this->assertEquals($qrow->grade, $questrecord->grade);
        $this->assertEquals($qrow->sid, $questrecord->sid);
        $this->assertEquals($qrow->timemodified, $questrecord->timemodified);
        $this->assertEquals($qrow->completionsubmit, $questrecord->completionsubmit);
        $this->assertEquals($qrow->autonum, $questrecord->autonum);
    }

    /*
     * Need to verify that delete_instance deletes all data associated with a kilman.
     *
     */
    public function test_delete_instance() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Set up a new kilman.
        $questiondata = array();
        $questiondata['content'] = 'Enter yes or no';
        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kilman');
        $kilman = $generator->create_test_kilman($course, QUESYESNO, $questiondata);

        $question = reset($kilman->questions);

        // Add a response for the question.
        $response = $generator->create_question_response($kilman, $question, 'y');

        // Get records for database deletion confirmation.
        $survey = $DB->get_record('kilman_survey', array('id' => $kilman->sid));

        // Now delete it all.
        $this->assertTrue(kilman_delete_instance($kilman->id));
        $this->assertEmpty($DB->get_record('kilman', array('id' => $kilman->id)));
        $this->assertEmpty($DB->get_record('kilman_survey', array('id' => $kilman->sid)));
        $this->assertEmpty($DB->get_records('kilman_question', array('surveyid' => $survey->id)));
        $this->assertEmpty($DB->get_records('kilman_response', array('kilmanid' => $kilman->id)));
        $this->assertEmpty($DB->get_records('kilman_response_bool', array('response_id' => $response->id)));
        $this->assertEmpty($DB->get_records('event', array("modulename" => 'kilman', "instance" => $kilman->id)));
    }

    public function test_kilman_user_outline() {
        $this->resetAfterTest();
        $this->setAdminUser();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kilman');
        $questiondata = array();
        $questiondata['content'] = 'Enter yes or no';
        $kilman = $generator->create_test_kilman($course, QUESYESNO, $questiondata);

        // Test for correct "no response" values.
        $outline = kilman_user_outline($course, $user, null, $kilman);
        $this->assertEquals(get_string("noresponses", "kilman"), $outline->info);

        // Test for a user with one response.
        $generator->create_question_response($kilman, reset($kilman->questions), 'y', $user->id);
        $outline = kilman_user_outline($course, $user, null, $kilman);
        $this->assertEquals('1 '.get_string("response", "kilman"), $outline->info);
    }

    public function test_kilman_user_complete() {
        $this->resetAfterTest();
        $this->setAdminUser();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kilman');
        $kilman = $generator->create_test_kilman($course, QUESYESNO);

        $this->assertTrue(kilman_user_complete($course, $user, null, $kilman));
        $this->expectOutputString(get_string('noresponses', 'kilman'));
    }

    public function test_kilman_print_recent_activity() {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->assertFalse(kilman_print_recent_activity(null, null, null));
    }

    public function test_kilman_grades() {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->assertNull(kilman_grades(null));
    }

    public function test_kilman_get_user_grades() {
        $this->resetAfterTest();
        $this->setAdminUser();

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kilman');
        $kilman = $generator->create_test_kilman($course);

        // Test for an array when user specified.
        $grades = kilman_get_user_grades($kilman, $user->id);
        $this->assertInternalType('array', $grades);

        // Test for an array when no user specified.
        $grades = kilman_get_user_grades($kilman);
        $this->assertInternalType('array', $grades);
    }

    public function test_kilman_update_grades() {
        // Don't know how to test this yet! It doesn't return anything.
        $this->assertNull(kilman_update_grades());
    }

    public function test_kilman_grade_item_update() {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_kilman');
        $kilman = $generator->create_test_kilman($course);
        $kilman->cmidnumber = $kilman->cm->idnumber;
        $kilman->courseid = $kilman->course->id;
        $this->assertEquals(GRADE_UPDATE_OK, kilman_grade_item_update($kilman));
    }
}
