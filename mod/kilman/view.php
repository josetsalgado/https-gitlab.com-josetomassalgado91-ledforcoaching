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
require_once($CFG->dirroot.'/mod/kilman/locallib.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->dirroot.'/mod/kilman/kilman.class.php');

if (!isset($SESSION->kilman)) {
    $SESSION->kilman = new stdClass();
}
$SESSION->kilman->current_tab = 'view';

$id = optional_param('id', null, PARAM_INT);    // Course Module ID.
$a = optional_param('a', null, PARAM_INT);      // Or kilman ID.

$sid = optional_param('sid', null, PARAM_INT);  // Survey id.

list($cm, $course, $kilman) = kilman_get_standard_page_items($id, $a);

// Check login and get context.
require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);

$url = new moodle_url($CFG->wwwroot.'/mod/kilman/view.php');
if (isset($id)) {
    $url->param('id', $id);
} else {
    $url->param('a', $a);
}
if (isset($sid)) {
    $url->param('sid', $sid);
}

$PAGE->set_url($url);
$PAGE->set_context($context);
$kilman = new kilman(0, $kilman, $course, $cm);
// Add renderer and page objects to the kilman object for display use.
$kilman->add_renderer($PAGE->get_renderer('mod_kilman'));
$kilman->add_page(new \mod_kilman\output\viewpage());

$PAGE->set_title(format_string($kilman->name));
$PAGE->set_heading(format_string($course->fullname));

echo $kilman->renderer->header();
$kilman->page->add_to_page('kilmanname', format_string($kilman->name));

// Print the main part of the page.
if ($kilman->intro) {
    $kilman->page->add_to_page('intro', format_module_intro('kilman', $kilman, $cm->id));
}

$cm = $kilman->cm;
$currentgroupid = groups_get_activity_group($cm);
if (!groups_is_member($currentgroupid, $USER->id)) {
    $currentgroupid = 0;
}

if (!$kilman->is_active()) {
    if ($kilman->capabilities->manage) {
        $msg = 'removenotinuse';
    } else {
        $msg = 'notavail';
    }
    $kilman->page->add_to_page('message', get_string($msg, 'kilman'));

} else if ($kilman->survey->realm == 'template') {
    // If this is a template survey, notify and exit.
    $kilman->page->add_to_page('message', get_string('templatenotviewable', 'kilman'));
    echo $kilman->renderer->render($kilman->page);
    echo $kilman->renderer->footer($kilman->course);
    exit();

} else if (!$kilman->is_open()) {
    $kilman->page->add_to_page('message', get_string('notopen', 'kilman', userdate($kilman->opendate)));

} else if ($kilman->is_closed()) {
    $kilman->page->add_to_page('message', get_string('closed', 'kilman', userdate($kilman->closedate)));

} else if (!$kilman->user_is_eligible($USER->id)) {
    if ($kilman->questions) {
        $kilman->page->add_to_page('message', get_string('noteligible', 'kilman'));
    }

} else if (!$kilman->user_can_take($USER->id)) {
    switch ($kilman->qtype) {
        case kilmanDAILY:
            $msgstring = ' '.get_string('today', 'kilman');
            break;
        case kilmanWEEKLY:
            $msgstring = ' '.get_string('thisweek', 'kilman');
            break;
        case kilmanMONTHLY:
            $msgstring = ' '.get_string('thismonth', 'kilman');
            break;
        default:
            $msgstring = '';
            break;
    }
    $kilman->page->add_to_page('message', get_string("alreadyfilled", "kilman", $msgstring));

} else if ($kilman->user_can_take($USER->id)) {
    if ($kilman->questions) { // Sanity check.
        if (!$kilman->user_has_saved_response($USER->id)) {
            $kilman->page->add_to_page('complete',
                '<a href="'.$CFG->wwwroot.htmlspecialchars('/mod/kilman/complete.php?' .
                'id='.$kilman->cm->id).'">'.get_string('answerquestions', 'kilman').'</a>');
        } else {
            $resumesurvey = get_string('resumesurvey', 'kilman');
            $kilman->page->add_to_page('complete',
                '<a href="'.$CFG->wwwroot.htmlspecialchars('/mod/kilman/complete.php?' .
                'id='.$kilman->cm->id.'&resume=1').'" title="'.$resumesurvey.'">'.$resumesurvey.'</a>');
        }
    } else {
        $kilman->page->add_to_page('message', get_string('noneinuse', 'kilman'));
    }
}

if ($kilman->capabilities->editquestions && !$kilman->questions && $kilman->is_active()) {
    $kilman->page->add_to_page('complete',
        '<a href="'.$CFG->wwwroot.htmlspecialchars('/mod/kilman/questions.php?'.
        'id='.$kilman->cm->id).'">'.'<strong>'.get_string('addquestions', 'kilman').'</strong></a>');
}

if (isguestuser()) {
    $guestno = html_writer::tag('p', get_string('noteligible', 'kilman'));
    $liketologin = html_writer::tag('p', get_string('liketologin'));
    $kilman->page->add_to_page('guestuser',
        $kilman->renderer->confirm($guestno."\n\n".$liketologin."\n", get_login_url(), get_local_referer(false)));
}

// Log this course module view.
// Needed for the event logging.
$context = context_module::instance($kilman->cm->id);
$anonymous = $kilman->respondenttype == 'anonymous';

$event = \mod_kilman\event\course_module_viewed::create(array(
                'objectid' => $kilman->id,
                'anonymous' => $anonymous,
                'context' => $context
));
$event->trigger();

$usernumresp = $kilman->count_submissions($USER->id);

if ($kilman->capabilities->readownresponses && ($usernumresp > 0)) {
    $argstr = 'instance='.$kilman->id.'&user='.$USER->id;
    if ($usernumresp > 1) {
        $titletext = get_string('viewyourresponses', 'kilman', $usernumresp);
    } else {
        $titletext = get_string('yourresponse', 'kilman');
        $argstr .= '&byresponse=1&action=vresp';
    }
    $kilman->page->add_to_page('yourresponse',
        '<a href="'.$CFG->wwwroot.htmlspecialchars('/mod/kilman/myreport.php?'.$argstr).'">'.$titletext.'</a>');
}

if ($kilman->can_view_all_responses($usernumresp)) {
    $argstr = 'instance='.$kilman->id.'&group='.$currentgroupid;
    $kilman->page->add_to_page('allresponses',
        '<a href="'.$CFG->wwwroot.htmlspecialchars('/mod/kilman/report.php?'.$argstr).'">'.
        get_string('viewallresponses', 'kilman').'</a>');
}

echo $kilman->renderer->render($kilman->page);
echo $kilman->renderer->footer();
