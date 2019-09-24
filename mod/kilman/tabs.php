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
 * prints the tabbed bar
 *
 * @package mod_kilman
 * @copyright  2016 Mike Churchward (mike.churchward@poetgroup.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $DB, $SESSION;
$tabs = array();
$row  = array();
$inactive = array();
$activated = array();
if (!isset($SESSION->kilman)) {
    $SESSION->kilman = new stdClass();
}
$currenttab = $SESSION->kilman->current_tab;

// In a kilman instance created "using" a PUBLIC kilman, prevent anyone from editing settings, editing questions,
// viewing all responses...except in the course where that PUBLIC kilman was originally created.

$owner = $kilman->is_survey_owner();
if ($kilman->capabilities->manage  && $owner) {
    $row[] = new tabobject('settings', $CFG->wwwroot.htmlspecialchars('/mod/kilman/qsettings.php?'.
            'id='.$kilman->cm->id), get_string('advancedsettings'));
}

if ($kilman->capabilities->editquestions && $owner) {
    $row[] = new tabobject('questions', $CFG->wwwroot.htmlspecialchars('/mod/kilman/questions.php?'.
            'id='.$kilman->cm->id), get_string('questions', 'kilman'));
}

if ($kilman->capabilities->editquestions && $owner) {
    $row[] = new tabobject('feedback', $CFG->wwwroot.htmlspecialchars('/mod/kilman/feedback.php?'.
            'id='.$kilman->cm->id), get_string('feedback'));
}

if ($kilman->capabilities->preview && $owner) {
    if (!empty($kilman->questions)) {
        $row[] = new tabobject('preview', $CFG->wwwroot.htmlspecialchars('/mod/kilman/preview.php?'.
                        'id='.$kilman->cm->id), get_string('preview_label', 'kilman'));
    }
}

$usernumresp = $kilman->count_submissions($USER->id);

if ($kilman->capabilities->readownresponses && ($usernumresp > 0)) {
    $argstr = 'instance='.$kilman->id.'&user='.$USER->id.'&group='.$currentgroupid;
    if ($usernumresp == 1) {
        $argstr .= '&byresponse=1&action=vresp';
        $yourrespstring = get_string('yourresponse', 'kilman');
    } else {
        $yourrespstring = get_string('yourresponses', 'kilman');
    }
    $row[] = new tabobject('myreport', $CFG->wwwroot.htmlspecialchars('/mod/kilman/myreport.php?'.
                           $argstr), $yourrespstring);

    if ($usernumresp > 1 && in_array($currenttab, array('mysummary', 'mybyresponse', 'myvall', 'mydownloadcsv'))) {
        $inactive[] = 'myreport';
        $activated[] = 'myreport';
        $row2 = array();
        $argstr2 = $argstr.'&action=summary';
        $row2[] = new tabobject('mysummary', $CFG->wwwroot.htmlspecialchars('/mod/kilman/myreport.php?'.$argstr2),
                                get_string('summary', 'kilman'));
        $argstr2 = $argstr.'&byresponse=1&action=vresp';
        $row2[] = new tabobject('mybyresponse', $CFG->wwwroot.htmlspecialchars('/mod/kilman/myreport.php?'.$argstr2),
                                get_string('viewindividualresponse', 'kilman'));
        $argstr2 = $argstr.'&byresponse=0&action=vall&group='.$currentgroupid;
        $row2[] = new tabobject('myvall', $CFG->wwwroot.htmlspecialchars('/mod/kilman/myreport.php?'.$argstr2),
                                get_string('myresponses', 'kilman'));
        if ($kilman->capabilities->downloadresponses) {
            $argstr2 = $argstr.'&action=dwnpg';
            $link  = $CFG->wwwroot.htmlspecialchars('/mod/kilman/report.php?'.$argstr2);
            $row2[] = new tabobject('mydownloadcsv', $link, get_string('downloadtextformat', 'kilman'));
        }
    } else if (in_array($currenttab, array('mybyresponse', 'mysummary'))) {
        $inactive[] = 'myreport';
        $activated[] = 'myreport';
    }
}

$numresp = $kilman->count_submissions();
// Number of responses in currently selected group (or all participants etc.).
if (isset($SESSION->kilman->numselectedresps)) {
    $numselectedresps = $SESSION->kilman->numselectedresps;
} else {
    $numselectedresps = $numresp;
}

// If kilman is set to separate groups, prevent user who is not member of any group
// to view All responses.
$canviewgroups = true;
$groupmode = groups_get_activity_groupmode($cm, $course);
if ($groupmode == 1) {
    $canviewgroups = groups_has_membership($cm, $USER->id);
}
$canviewallgroups = has_capability('moodle/site:accessallgroups', $context);
$grouplogic = $canviewallgroups || $canviewgroups;
$resplogic = ($numresp > 0) && ($numselectedresps > 0);

if ($kilman->can_view_all_responses_anytime($grouplogic, $resplogic)) {
    $argstr = 'instance='.$kilman->id;
    $row[] = new tabobject('allreport', $CFG->wwwroot.htmlspecialchars('/mod/kilman/report.php?'.
                           $argstr.'&action=vall'), get_string('viewallresponses', 'kilman'));
    if (in_array($currenttab, array('vall', 'vresp', 'valldefault', 'vallasort', 'vallarsort', 'deleteall', 'downloadcsv',
                                     'vrespsummary', 'individualresp', 'printresp', 'deleteresp'))) {
        $inactive[] = 'allreport';
        $activated[] = 'allreport';
        if ($currenttab == 'vrespsummary' || $currenttab == 'valldefault') {
            $inactive[] = 'vresp';
        }
        $row2 = array();
        $argstr2 = $argstr.'&action=vall&group='.$currentgroupid;
        $row2[] = new tabobject('vall', $CFG->wwwroot.htmlspecialchars('/mod/kilman/report.php?'.$argstr2),
                                get_string('summary', 'kilman'));
        if ($kilman->capabilities->viewsingleresponse) {
            $argstr2 = $argstr.'&byresponse=1&action=vresp&group='.$currentgroupid;
            $row2[] = new tabobject('vrespsummary', $CFG->wwwroot.htmlspecialchars('/mod/kilman/report.php?'.$argstr2),
                                get_string('viewbyresponse', 'kilman'));
            if ($currenttab == 'individualresp' || $currenttab == 'deleteresp') {
                $argstr2 = $argstr.'&byresponse=1&action=vresp';
                $row2[] = new tabobject('vresp', $CFG->wwwroot.htmlspecialchars('/mod/kilman/report.php?'.$argstr2),
                        get_string('viewindividualresponse', 'kilman'));
            }
        }
    }
    if (in_array($currenttab, array('valldefault',  'vallasort', 'vallarsort', 'deleteall', 'downloadcsv'))) {
        $activated[] = 'vall';
        $row3 = array();

        $argstr2 = $argstr.'&action=vall&group='.$currentgroupid;
        $row3[] = new tabobject('valldefault', $CFG->wwwroot.htmlspecialchars('/mod/kilman/report.php?'.$argstr2),
                                get_string('order_default', 'kilman'));
        if ($currenttab != 'downloadcsv' && $currenttab != 'deleteall') {
            $argstr2 = $argstr.'&action=vallasort&group='.$currentgroupid;
            $row3[] = new tabobject('vallasort', $CFG->wwwroot.htmlspecialchars('/mod/kilman/report.php?'.$argstr2),
                                    get_string('order_ascending', 'kilman'));
            $argstr2 = $argstr.'&action=vallarsort&group='.$currentgroupid;
            $row3[] = new tabobject('vallarsort', $CFG->wwwroot.htmlspecialchars('/mod/kilman/report.php?'.$argstr2),
                                    get_string('order_descending', 'kilman'));
        }
        if ($kilman->capabilities->deleteresponses) {
            $argstr2 = $argstr.'&action=delallresp&group='.$currentgroupid;
            $row3[] = new tabobject('deleteall', $CFG->wwwroot.htmlspecialchars('/mod/kilman/report.php?'.$argstr2),
                                    get_string('deleteallresponses', 'kilman'));
        }

        if ($kilman->capabilities->downloadresponses) {
            $argstr2 = $argstr.'&action=dwnpg&group='.$currentgroupid;
            $link  = $CFG->wwwroot.htmlspecialchars('/mod/kilman/report.php?'.$argstr2);
            $row3[] = new tabobject('downloadcsv', $link, get_string('downloadtextformat', 'kilman'));
        }
    }

    if (in_array($currenttab, array('individualresp', 'deleteresp'))) {
        $inactive[] = 'vresp';
        if ($currenttab != 'deleteresp') {
            $activated[] = 'vresp';
        }
        if ($kilman->capabilities->deleteresponses) {
            $argstr2 = $argstr.'&action=dresp&rid='.$rid.'&individualresponse=1';
            $row2[] = new tabobject('deleteresp', $CFG->wwwroot.htmlspecialchars('/mod/kilman/report.php?'.$argstr2),
                            get_string('deleteresp', 'kilman'));
        }

    }
} else if ($kilman->can_view_all_responses_with_restrictions($usernumresp, $grouplogic, $resplogic)) {
    $argstr = 'instance='.$kilman->id.'&sid='.$kilman->sid;
    $row[] = new tabobject('allreport', $CFG->wwwroot.htmlspecialchars('/mod/kilman/report.php?'.
                           $argstr.'&action=vall&group='.$currentgroupid), get_string('viewallresponses', 'kilman'));
    if (in_array($currenttab, array('valldefault',  'vallasort', 'vallarsort', 'deleteall', 'downloadcsv'))) {
        $inactive[] = 'vall';
        $activated[] = 'vall';
        $row2 = array();
        $argstr2 = $argstr.'&action=vall&group='.$currentgroupid;
        $row2[] = new tabobject('valldefault', $CFG->wwwroot.htmlspecialchars('/mod/kilman/report.php?'.$argstr2),
                                get_string('summary', 'kilman'));
        $inactive[] = $currenttab;
        $activated[] = $currenttab;
        $row3 = array();
        $argstr2 = $argstr.'&action=vall&group='.$currentgroupid;
        $row3[] = new tabobject('valldefault', $CFG->wwwroot.htmlspecialchars('/mod/kilman/report.php?'.$argstr2),
                                get_string('order_default', 'kilman'));
        $argstr2 = $argstr.'&action=vallasort&group='.$currentgroupid;
        $row3[] = new tabobject('vallasort', $CFG->wwwroot.htmlspecialchars('/mod/kilman/report.php?'.$argstr2),
                                get_string('order_ascending', 'kilman'));
        $argstr2 = $argstr.'&action=vallarsort&group='.$currentgroupid;
        $row3[] = new tabobject('vallarsort', $CFG->wwwroot.htmlspecialchars('/mod/kilman/report.php?'.$argstr2),
                                get_string('order_descending', 'kilman'));
        if ($kilman->capabilities->deleteresponses) {
            $argstr2 = $argstr.'&action=delallresp';
            $row2[] = new tabobject('deleteall', $CFG->wwwroot.htmlspecialchars('/mod/kilman/report.php?'.$argstr2),
                                    get_string('deleteallresponses', 'kilman'));
        }

        if ($kilman->capabilities->downloadresponses) {
            $argstr2 = $argstr.'&action=dwnpg';
            $link  = htmlspecialchars('/mod/kilman/report.php?'.$argstr2);
            $row2[] = new tabobject('downloadcsv', $link, get_string('downloadtextformat', 'kilman'));
        }
        if (count($row2) <= 1) {
            $currenttab = 'allreport';
        }
    }
}

if ($kilman->capabilities->viewsingleresponse && ($canviewallgroups || $canviewgroups)) {
    $nonrespondenturl = new moodle_url('/mod/kilman/show_nonrespondents.php', array('id' => $kilman->cm->id));
    $row[] = new tabobject('nonrespondents',
                    $nonrespondenturl->out(),
                    get_string('show_nonrespondents', 'kilman'));
}

if ((count($row) > 1) || (!empty($row2) && (count($row2) > 1))) {
    $tabs[] = $row;

    if (!empty($row2) && (count($row2) > 1)) {
        $tabs[] = $row2;
    }

    if (!empty($row3) && (count($row3) > 1)) {
        $tabs[] = $row3;
    }

    $kilman->page->add_to_page('tabsarea', print_tabs($tabs, $currenttab, $inactive, $activated, true));
}