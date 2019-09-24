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

require_once("../../config.php");
require_once($CFG->dirroot.'/mod/kilman/kilman.class.php');

$qid = required_param('qid', PARAM_INT);
$rid = required_param('rid', PARAM_INT);
$courseid = required_param('courseid', PARAM_INT);
$sec = required_param('sec', PARAM_INT);
$null = null;
$referer = $CFG->wwwroot.'/mod/kilman/report.php';

if (! $kilman = $DB->get_record("kilman", array("id" => $qid))) {
    print_error('invalidcoursemodule');
}
if (! $course = $DB->get_record("course", array("id" => $kilman->course))) {
    print_error('coursemisconf');
}
if (! $cm = get_coursemodule_from_instance("kilman", $kilman->id, $course->id)) {
    print_error('invalidcoursemodule');
}

// Check login and get context.
require_login($courseid);

$kilman = new kilman(0, $kilman, $course, $cm);

// Add renderer and page objects to the kilman object for display use.
$kilman->add_renderer($PAGE->get_renderer('mod_kilman'));
if (!empty($rid)) {
    $kilman->add_page(new \mod_kilman\output\reportpage());
} else {
    $kilman->add_page(new \mod_kilman\output\previewpage());
}

// If you can't view the kilman, or can't view a specified response, error out.
if (!($kilman->capabilities->view && (($rid == 0) || $kilman->can_view_response($rid)))) {
    // Should never happen, unless called directly by a snoop...
    print_error('nopermissions', 'moodle', $CFG->wwwroot.'/mod/kilman/view.php?id='.$cm->id);
}
$blankkilman = true;
if ($rid != 0) {
    $blankkilman = false;
}
$url = new moodle_url($CFG->wwwroot.'/mod/kilman/print.php');
$url->param('qid', $qid);
$url->param('rid', $rid);
$url->param('courseid', $courseid);
$url->param('sec', $sec);
$PAGE->set_url($url);
$PAGE->set_title($kilman->survey->title);
$PAGE->set_pagelayout('popup');
echo $kilman->renderer->header();
$kilman->page->add_to_page('closebutton', $kilman->renderer->close_window_button());
$kilman->survey_print_render('', 'print', $courseid, $rid, $blankkilman);
echo $kilman->renderer->render($kilman->page);
echo $kilman->renderer->footer();
