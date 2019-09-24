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
 * This script lists all the instances of kilman in a particular course
 *
 * @package    mod
 * @subpackage kilman
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once("../../config.php");
require_once($CFG->dirroot.'/mod/kilman/locallib.php');

$id = required_param('id', PARAM_INT);
$PAGE->set_url('/mod/kilman/index.php', array('id' => $id));
if (! $course = $DB->get_record('course', array('id' => $id))) {
    print_error('incorrectcourseid', 'kilman');
}
$coursecontext = context_course::instance($id);
require_login($course->id);
$PAGE->set_pagelayout('incourse');

$event = \mod_kilman\event\course_module_instance_list_viewed::create(array(
                'context' => context_course::instance($course->id)));
$event->trigger();

// Print the header.
$strkilmans = get_string("modulenameplural", "kilman");
$PAGE->navbar->add($strkilmans);
$PAGE->set_title("$course->shortname: $strkilmans");
$PAGE->set_heading(format_string($course->fullname));
echo $OUTPUT->header();

// Get all the appropriate data.
if (!$kilmans = get_all_instances_in_course("kilman", $course)) {
    notice(get_string('thereareno', 'moodle', $strkilmans), "../../course/view.php?id=$course->id");
    die;
}

// Check if we need the closing date header.
$showclosingheader = false;
foreach ($kilmans as $kilman) {
    if ($kilman->closedate != 0) {
        $showclosingheader = true;
    }
    if ($showclosingheader) {
        break;
    }
}

// Configure table for displaying the list of instances.
$headings = array(get_string('name'));
$align = array('left');

if ($showclosingheader) {
    array_push($headings, get_string('kilmancloses', 'kilman'));
    array_push($align, 'left');
}

array_unshift($headings, get_string('sectionname', 'format_'.$course->format));
array_unshift($align, 'left');

$showing = '';

// Current user role == admin or teacher.
if (has_capability('mod/kilman:viewsingleresponse', $coursecontext)) {
    array_push($headings, get_string('responses', 'kilman'));
    array_push($align, 'center');
    $showing = 'stats';
    array_push($headings, get_string('realm', 'kilman'));
    array_push($align, 'left');
    // Current user role == student.
} else if (has_capability('mod/kilman:submit', $coursecontext)) {
    array_push($headings, get_string('status'));
    array_push($align, 'left');
    $showing = 'responses';
}

$table = new html_table();
$table->head = $headings;
$table->align = $align;

// Populate the table with the list of instances.
$currentsection = '';
foreach ($kilmans as $kilman) {
    $cmid = $kilman->coursemodule;
    $data = array();
    $realm = $DB->get_field('kilman_survey', 'realm', array('id' => $kilman->sid));
    // Template surveys should NOT be displayed as an activity to students.
    if (!($realm == 'template' && !has_capability('mod/kilman:manage', context_module::instance($cmid)))) {
        // Section number if necessary.
        $strsection = '';
        if ($kilman->section != $currentsection) {
            $strsection = get_section_name($course, $kilman->section);
            $currentsection = $kilman->section;
        }
        $data[] = $strsection;
        // Show normal if the mod is visible.
        $class = '';
        if (!$kilman->visible) {
            $class = ' class="dimmed"';
        }
        $data[] = "<a$class href=\"view.php?id=$cmid\">$kilman->name</a>";

        // Close date.
        if ($kilman->closedate) {
            $data[] = userdate($kilman->closedate);
        } else if ($showclosingheader) {
            $data[] = '';
        }

        if ($showing == 'responses') {
            $status = '';
            if ($responses = kilman_get_user_responses($kilman->id, $USER->id, $complete = false)) {
                foreach ($responses as $response) {
                    if ($response->complete == 'y') {
                        $status .= get_string('submitted', 'kilman').' '.userdate($response->submitted).'<br />';
                    } else {
                        $status .= get_string('attemptstillinprogress', 'kilman').' '.
                            userdate($response->submitted).'<br />';
                    }
                }
            }
            $data[] = $status;
        } else if ($showing == 'stats') {
            $data[] = $DB->count_records('kilman_response', ['kilmanid' => $kilman->id, 'complete' => 'y']);
            if ($survey = $DB->get_record('kilman_survey', ['id' => $kilman->sid])) {
                // For a public kilman, look for the original public kilman that it is based on.
                if ($survey->realm == 'public') {
                    $strpreview = get_string('preview_kilman', 'kilman');
                    if ($survey->courseid != $course->id) {
                        $publicoriginal = '';
                        $originalcourse = $DB->get_record('course', ['id' => $survey->courseid]);
                        $originalcoursecontext = context_course::instance($survey->courseid);
                        $originalkilman = $DB->get_record('kilman',
                            ['sid' => $survey->id, 'course' => $survey->courseid]);
                        $cm = get_coursemodule_from_instance("kilman", $originalkilman->id, $survey->courseid);
                        $context = context_course::instance($survey->courseid, MUST_EXIST);
                        $canvieworiginal = has_capability('mod/kilman:preview', $context, $USER->id, true);
                        // If current user can view kilmans in original course,
                        // provide a link to the original public kilman.
                        if ($canvieworiginal) {
                            $publicoriginal = '<br />'.get_string('publicoriginal', 'kilman').'&nbsp;'.
                                '<a href="'.$CFG->wwwroot.'/mod/kilman/preview.php?id='.
                                $cm->id.'" title="'.$strpreview.']">'.$originalkilman->name.' ['.
                                $originalcourse->fullname.']</a>';
                        } else {
                            // If current user is not enrolled as teacher in original course,
                            // only display the original public kilman's name and course name.
                            $publicoriginal = '<br />'.get_string('publicoriginal', 'kilman').'&nbsp;'.
                                $originalkilman->name.' ['.$originalcourse->fullname.']';
                        }
                        $data[] = get_string($realm, 'kilman').' '.$publicoriginal;
                    } else {
                        // Original public kilman was created in current course.
                        // Find which courses it is used in.
                        $publiccopy = '';
                        $select = 'course != '.$course->id.' AND sid = '.$kilman->sid;
                        if ($copies = $DB->get_records_select('kilman', $select, null,
                                $sort = 'course ASC', $fields = 'id, course, name')) {
                            foreach ($copies as $copy) {
                                $copycourse = $DB->get_record('course', array('id' => $copy->course));
                                $select = 'course = '.$copycourse->id.' AND sid = '.$kilman->sid;
                                $copykilman = $DB->get_record('kilman',
                                    array('id' => $copy->id, 'sid' => $survey->id, 'course' => $copycourse->id));
                                $cm = get_coursemodule_from_instance("kilman", $copykilman->id, $copycourse->id);
                                $context = context_course::instance($copycourse->id, MUST_EXIST);
                                $canviewcopy = has_capability('mod/kilman:view', $context, $USER->id, true);
                                if ($canviewcopy) {
                                    $publiccopy .= '<br />'.get_string('publiccopy', 'kilman').'&nbsp;:&nbsp;'.
                                        '<a href = "'.$CFG->wwwroot.'/mod/kilman/preview.php?id='.
                                        $cm->id.'" title = "'.$strpreview.'">'.
                                        $copykilman->name.' ['.$copycourse->fullname.']</a>';
                                } else {
                                    // If current user does not have "view" capability in copy course,
                                    // only display the copied public kilman's name and course name.
                                    $publiccopy .= '<br />'.get_string('publiccopy', 'kilman').'&nbsp;:&nbsp;'.
                                        $copykilman->name.' ['.$copycourse->fullname.']';
                                }
                            }
                        }
                        $data[] = get_string($realm, 'kilman').' '.$publiccopy;
                    }
                } else {
                    $data[] = get_string($realm, 'kilman');
                }
            } else {
                // If a kilman is a copy of a public kilman which has been deleted.
                $data[] = get_string('removenotinuse', 'kilman');
            }
        }
    }
    $table->data[] = $data;
} // End of loop over kilman instances.

echo html_writer::table($table);

// Finish the page.
echo $OUTPUT->footer();