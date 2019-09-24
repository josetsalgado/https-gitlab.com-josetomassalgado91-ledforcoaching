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

// This page displays a non-completable instance of kilman.

require_once("../../config.php");
require_once($CFG->dirroot.'/mod/kilman/kilman.class.php');

$id     = optional_param('id', 0, PARAM_INT);
$sid    = optional_param('sid', 0, PARAM_INT);
$popup  = optional_param('popup', 0, PARAM_INT);
$qid    = optional_param('qid', 0, PARAM_INT);
$currentgroupid = optional_param('group', 0, PARAM_INT); // Groupid.

if ($id) {
    if (! $cm = get_coursemodule_from_id('kilman', $id)) {
        print_error('invalidcoursemodule');
    }

    if (! $course = $DB->get_record("course", array("id" => $cm->course))) {
        print_error('coursemisconf');
    }

    if (! $kilman = $DB->get_record("kilman", array("id" => $cm->instance))) {
        print_error('invalidcoursemodule');
    }
} else {
    if (! $survey = $DB->get_record("kilman_survey", array("id" => $sid))) {
        print_error('surveynotexists', 'kilman');
    }
    if (! $course = $DB->get_record("course", ["id" => $survey->courseid])) {
        print_error('coursemisconf');
    }
    // Dummy kilman object.
    $kilman = new stdClass();
    $kilman->id = 0;
    $kilman->course = $course->id;
    $kilman->name = $survey->title;
    $kilman->sid = $sid;
    $kilman->resume = 0;
    // Dummy cm object.
    if (!empty($qid)) {
        $cm = get_coursemodule_from_instance('kilman', $qid, $course->id);
    } else {
        $cm = false;
    }
}

// Check login and get context.
// Do not require login if this kilman is viewed from the Add kilman page
// to enable teachers to view template or public kilmans located in a course where they are not enroled.
if (!$popup) {
    require_login($course->id, false, $cm);
}
$context = $cm ? context_module::instance($cm->id) : false;

$url = new moodle_url('/mod/kilman/preview.php');
if ($id !== 0) {
    $url->param('id', $id);
}
if ($sid) {
    $url->param('sid', $sid);
}
$PAGE->set_url($url);

$PAGE->set_context($context);
$PAGE->set_cm($cm);   // CONTRIB-5872 - I don't know why this is needed.

$kilman = new kilman($qid, $kilman, $course, $cm);

// Add renderer and page objects to the kilman object for display use.
$kilman->add_renderer($PAGE->get_renderer('mod_kilman'));
$kilman->add_page(new \mod_kilman\output\previewpage());

$canpreview = (!isset($kilman->capabilities) &&
               has_capability('mod/kilman:preview', context_course::instance($course->id))) ||
              (isset($kilman->capabilities) && $kilman->capabilities->preview);
if (!$canpreview && !$popup) {
    // Should never happen, unless called directly by a snoop...
    print_error('nopermissions', 'kilman', $CFG->wwwroot.'/mod/kilman/view.php?id='.$cm->id);
}

if (!isset($SESSION->kilman)) {
    $SESSION->kilman = new stdClass();
}
$SESSION->kilman->current_tab = new stdClass();
$SESSION->kilman->current_tab = 'preview';

$qp = get_string('preview_kilman', 'kilman');
$pq = get_string('previewing', 'kilman');

// Print the page header.
if ($popup) {
    $PAGE->set_pagelayout('popup');
}
$PAGE->set_title(format_string($qp));
if (!$popup) {
    $PAGE->set_heading(format_string($course->fullname));
}

// Include the needed js.


$PAGE->requires->js('/mod/kilman/module.js');
// Print the tabs.


echo $kilman->renderer->header();
if (!$popup) {
    require('tabs.php');
}
$kilman->page->add_to_page('heading', clean_text($pq));

if ($kilman->capabilities->printblank) {
    // Open print friendly as popup window.

    $linkname = '&nbsp;'.get_string('printblank', 'kilman');
    $title = get_string('printblanktooltip', 'kilman');
    $url = '/mod/kilman/print.php?qid='.$kilman->id.'&amp;rid=0&amp;'.'courseid='.
            $kilman->course->id.'&amp;sec=1';
    $options = array('menubar' => true, 'location' => false, 'scrollbars' => true, 'resizable' => true,
                    'height' => 600, 'width' => 800, 'title' => $title);
    $name = 'popup';
    $link = new moodle_url($url);
    $action = new popup_action('click', $link, $name, $options);
    $class = "floatprinticon";
    $kilman->page->add_to_page('printblank',
        $kilman->renderer->action_link($link, $linkname, $action, array('class' => $class, 'title' => $title),
            new pix_icon('t/print', $title)));
}
$kilman->survey_print_render('', 'preview', $course->id, $rid = 0, $popup);
if ($popup) {
    $kilman->page->add_to_page('closebutton', $kilman->renderer->close_window_button());
}
echo $kilman->renderer->render($kilman->page);
echo $kilman->renderer->footer($course);

// Log this kilman preview.
$context = context_module::instance($kilman->cm->id);
$anonymous = $kilman->respondenttype == 'anonymous';

$event = \mod_kilman\event\kilman_previewed::create(array(
                'objectid' => $kilman->id,
                'anonymous' => $anonymous,
                'context' => $context
));
$event->trigger();
