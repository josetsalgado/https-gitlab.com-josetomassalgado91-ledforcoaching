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

// This page prints a particular instance of kilman.

require_once("../../config.php");
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->dirroot.'/mod/kilman/kilman.class.php');

if (!isset($SESSION->kilman)) {
    $SESSION->kilman = new stdClass();
}
$SESSION->kilman->current_tab = 'view';

$id = optional_param('id', null, PARAM_INT);    // Course Module ID.
$a = optional_param('a', null, PARAM_INT);      // kilman ID.

$sid = optional_param('sid', null, PARAM_INT);  // Survey id.
$resume = optional_param('resume', null, PARAM_INT);    // Is this attempt a resume of a saved attempt?

list($cm, $course, $kilman) = kilman_get_standard_page_items($id, $a);

// Check login and get context.
require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/kilman:view', $context);

$url = new moodle_url($CFG->wwwroot.'/mod/kilman/complete.php');
if (isset($id)) {
    $url->param('id', $id);
} else {
    $url->param('a', $a);
}

$PAGE->set_url($url);
$PAGE->set_context($context);
$kilman = new kilman(0, $kilman, $course, $cm);
// Add renderer and page objects to the kilman object for display use.
$kilman->add_renderer($PAGE->get_renderer('mod_kilman'));
$kilman->add_page(new \mod_kilman\output\completepage());

$kilman->strkilmans = get_string("modulenameplural", "kilman");
$kilman->strkilman  = get_string("modulename", "kilman");

// Mark as viewed.
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

if ($resume) {
    $context = context_module::instance($kilman->cm->id);
    $anonymous = $kilman->respondenttype == 'anonymous';

    $event = \mod_kilman\event\attempt_resumed::create(array(
                    'objectid' => $kilman->id,
                    'anonymous' => $anonymous,
                    'context' => $context
    ));
    $event->trigger();
}

// Generate the view HTML in the page.
$kilman->view();

// Output the page.
echo $kilman->renderer->header();
echo $kilman->renderer->render($kilman->page);
echo $kilman->renderer->footer($course);