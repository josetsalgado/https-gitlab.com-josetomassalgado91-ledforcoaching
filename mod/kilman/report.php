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

$instance = optional_param('instance', false, PARAM_INT);   // kilman ID.
$action = optional_param('action', 'vall', PARAM_ALPHA);
$sid = optional_param('sid', null, PARAM_INT);              // Survey id.
$rid = optional_param('rid', false, PARAM_INT);
$type = optional_param('type', '', PARAM_ALPHA);
$byresponse = optional_param('byresponse', false, PARAM_INT);
$individualresponse = optional_param('individualresponse', false, PARAM_INT);
$currentgroupid = optional_param('group', 0, PARAM_INT); // Groupid.
$user = optional_param('user', '', PARAM_INT);
$userid = $USER->id;
switch ($action) {
    case 'vallasort':
        $sort = 'ascending';
        break;
    case 'vallarsort':
        $sort = 'descending';
        break;
    default:
        $sort = 'default';
}

if ($instance === false) {
    if (!empty($SESSION->instance)) {
        $instance = $SESSION->instance;
    } else {
        print_error('requiredparameter', 'kilman');
    }
}
$SESSION->instance = $instance;
$usergraph = get_config('kilman', 'usergraph');

if (! $kilman = $DB->get_record("kilman", array("id" => $instance))) {
    print_error('incorrectkilman', 'kilman');
}
if (! $course = $DB->get_record("course", array("id" => $kilman->course))) {
    print_error('coursemisconf');
}
if (! $cm = get_coursemodule_from_instance("kilman", $kilman->id, $course->id)) {
    print_error('invalidcoursemodule');
}

require_course_login($course, true, $cm);

$kilman = new kilman(0, $kilman, $course, $cm);

// Add renderer and page objects to the kilman object for display use.
$kilman->add_renderer($PAGE->get_renderer('mod_kilman'));
$kilman->add_page(new \mod_kilman\output\reportpage());

// If you can't view the kilman, or can't view a specified response, error out.
$context = context_module::instance($cm->id);
if (!has_capability('mod/kilman:readallresponseanytime', $context) &&
    !($kilman->capabilities->view && $kilman->can_view_response($rid))) {
    // Should never happen, unless called directly by a snoop...
    print_error('nopermissions', 'moodle', $CFG->wwwroot.'/mod/kilman/view.php?id='.$cm->id);
}

$kilman->canviewallgroups = has_capability('moodle/site:accessallgroups', $context);
$sid = $kilman->survey->id;

$url = new moodle_url($CFG->wwwroot.'/mod/kilman/report.php');
if ($instance) {
    $url->param('instance', $instance);
}

$url->param('action', $action);

if ($type) {
    $url->param('type', $type);
}
if ($byresponse || $individualresponse) {
    $url->param('byresponse', 1);
}
if ($user) {
    $url->param('user', $user);
}
if ($action == 'dresp') {
    $url->param('action', 'dresp');
    $url->param('byresponse', 1);
    $url->param('rid', $rid);
    $url->param('individualresponse', 1);
}
if ($currentgroupid !== null) {
    $url->param('group', $currentgroupid);
}

$PAGE->set_url($url);
$PAGE->set_context($context);

// Tab setup.
if (!isset($SESSION->kilman)) {
    $SESSION->kilman = new stdClass();
}
$SESSION->kilman->current_tab = 'allreport';

// Get all responses for further use in viewbyresp and deleteall etc.
// All participants.
$respsallparticipants = $kilman->get_responses();
$SESSION->kilman->numrespsallparticipants = count ($respsallparticipants);
$SESSION->kilman->numselectedresps = $SESSION->kilman->numrespsallparticipants;

// Available group modes (0 = no groups; 1 = separate groups; 2 = visible groups).
$groupmode = groups_get_activity_groupmode($cm, $course);
$kilmangroups = '';
$groupscount = 0;
$SESSION->kilman->respscount = 0;
$SESSION->kilman_surveyid = $sid;

if ($groupmode > 0) {
    if ($groupmode == 1) {
        $kilmangroups = groups_get_all_groups($course->id, $userid);
    }
    if ($groupmode == 2 || $kilman->canviewallgroups) {
        $kilmangroups = groups_get_all_groups($course->id);
    }

    if (!empty($kilmangroups)) {
        $groupscount = count($kilmangroups);
        foreach ($kilmangroups as $key) {
            $firstgroupid = $key->id;
            break;
        }
        if ($groupscount === 0 && $groupmode == 1) {
            $currentgroupid = 0;
        }
        if ($groupmode == 1 && !$kilman->canviewallgroups && $currentgroupid == 0) {
            $currentgroupid = $firstgroupid;
        }
    } else {
        // Groupmode = separate groups but user is not member of any group
        // and does not have moodle/site:accessallgroups capability -> refuse view responses.
        if (!$kilman->canviewallgroups) {
            $currentgroupid = 0;
        }
    }

    if ($currentgroupid > 0) {
        $groupname = get_string('group').' <strong>'.groups_get_group_name($currentgroupid).'</strong>';
    } else {
        $groupname = '<strong>'.get_string('allparticipants').'</strong>';
    }
}
if ($usergraph) {
    $charttype = $kilman->survey->chart_type;
    if ($charttype) {
        $PAGE->requires->js('/mod/kilman/javascript/RGraph/RGraph.common.core.js');

        switch ($charttype) {
            case 'bipolar':
                $PAGE->requires->js('/mod/kilman/javascript/RGraph/RGraph.bipolar.js');
                break;
            case 'hbar':
                $PAGE->requires->js('/mod/kilman/javascript/RGraph/RGraph.hbar.js');
                break;
            case 'radar':
                $PAGE->requires->js('/mod/kilman/javascript/RGraph/RGraph.radar.js');
                break;
            case 'rose':
                $PAGE->requires->js('/mod/kilman/javascript/RGraph/RGraph.rose.js');
                break;
            case 'vprogress':
                $PAGE->requires->js('/mod/kilman/javascript/RGraph/RGraph.vprogress.js');
                break;
        }
    }
}

switch ($action) {

    case 'dresp':  // Delete individual response? Ask for confirmation.

        require_capability('mod/kilman:deleteresponses', $context);

        if (empty($kilman->survey)) {
            $id = $kilman->survey;
            notify ("kilman->survey = /$id/");
            print_error('surveynotexists', 'kilman');
        } else if ($kilman->survey->courseid != $course->id) {
            print_error('surveyowner', 'kilman');
        } else if (!$rid || !is_numeric($rid)) {
            print_error('invalidresponse', 'kilman');
        } else if (!($resp = $DB->get_record('kilman_response', array('id' => $rid)))) {
            print_error('invalidresponserecord', 'kilman');
        }

        $ruser = false;
        if (!empty($resp->userid)) {
            if ($user = $DB->get_record('user', ['id' => $resp->userid])) {
                $ruser = fullname($user);
            } else {
                $ruser = '- '.get_string('unknown', 'kilman').' -';
            }
        } else {
            $ruser = $resp->userid;
        }

        // Print the page header.
        $PAGE->set_title(get_string('deletingresp', 'kilman'));
        $PAGE->set_heading(format_string($course->fullname));
        echo $kilman->renderer->header();

        // Print the tabs.
        $SESSION->kilman->current_tab = 'deleteresp';
        include('tabs.php');

        $timesubmitted = '<br />'.get_string('submitted', 'kilman').'&nbsp;'.userdate($resp->submitted);
        if ($kilman->respondenttype == 'anonymous') {
            $ruser = '- '.get_string('anonymous', 'kilman').' -';
            $timesubmitted = '';
        }

        // Print the confirmation.
        $msg = '<div class="warning centerpara">';
        $msg .= get_string('confirmdelresp', 'kilman', $ruser.$timesubmitted);
        $msg .= '</div>';
        $urlyes = new moodle_url('report.php', array('action' => 'dvresp',
            'rid' => $rid, 'individualresponse' => 1, 'instance' => $instance, 'group' => $currentgroupid));
        $urlno = new moodle_url('report.php', array('action' => 'vresp', 'instance' => $instance,
            'rid' => $rid, 'individualresponse' => 1, 'group' => $currentgroupid));
        $buttonyes = new single_button($urlyes, get_string('delete'), 'post');
        $buttonno = new single_button($urlno, get_string('cancel'), 'get');
        $kilman->page->add_to_page('notifications', $kilman->renderer->confirm($msg, $buttonyes, $buttonno));
        echo $kilman->renderer->render($kilman->page);
        // Finish the page.
        echo $kilman->renderer->footer($course);
        break;

    case 'delallresp': // Delete all responses? Ask for confirmation.
        require_capability('mod/kilman:deleteresponses', $context);

        if (!empty($respsallparticipants)) {

            // Print the page header.
            $PAGE->set_title(get_string('deletingresp', 'kilman'));
            $PAGE->set_heading(format_string($course->fullname));
            echo $kilman->renderer->header();

            // Print the tabs.
            $SESSION->kilman->current_tab = 'deleteall';
            include('tabs.php');

            // Print the confirmation.
            $msg = '<div class="warning centerpara">';
            if ($groupmode == 0) {   // No groups or visible groups.
                $msg .= get_string('confirmdelallresp', 'kilman');
            } else {                 // Separate groups.
                $msg .= get_string('confirmdelgroupresp', 'kilman', $groupname);
            }
            $msg .= '</div>';

            $urlyes = new moodle_url('report.php', array('action' => 'dvallresp', 'sid' => $sid,
                'instance' => $instance, 'group' => $currentgroupid));
            $urlno = new moodle_url('report.php', array('instance' => $instance, 'group' => $currentgroupid));
            $buttonyes = new single_button($urlyes, get_string('delete'), 'post');
            $buttonno = new single_button($urlno, get_string('cancel'), 'get');

            $kilman->page->add_to_page('notifications', $kilman->renderer->confirm($msg, $buttonyes, $buttonno));
            echo $kilman->renderer->render($kilman->page);
            // Finish the page.
            echo $kilman->renderer->footer($course);
        }
        break;

    case 'dvresp': // Delete single response. Do it!

        require_capability('mod/kilman:deleteresponses', $context);

        if (empty($kilman->survey)) {
            print_error('surveynotexists', 'kilman');
        } else if ($kilman->survey->courseid != $course->id) {
            print_error('surveyowner', 'kilman');
        } else if (!$rid || !is_numeric($rid)) {
            print_error('invalidresponse', 'kilman');
        } else if (!($response = $DB->get_record('kilman_response', array('id' => $rid)))) {
            print_error('invalidresponserecord', 'kilman');
        }

        if (kilman_delete_response($response, $kilman)) {
            if (!$DB->count_records('kilman_response', array('kilmanid' => $kilman->id, 'complete' => 'y'))) {
                $redirection = $CFG->wwwroot.'/mod/kilman/view.php?id='.$cm->id;
            } else {
                $redirection = $CFG->wwwroot.'/mod/kilman/report.php?action=vresp&amp;instance='.
                    $instance.'&amp;byresponse=1';
            }

            // Log this kilman delete single response action.
            $params = array('objectid' => $kilman->survey->id,
                'context' => $kilman->context,
                'courseid' => $kilman->course->id,
                'relateduserid' => $response->userid);
            $event = \mod_kilman\event\response_deleted::create($params);
            $event->trigger();

            redirect($redirection);
        } else {
            if ($kilman->respondenttype == 'anonymous') {
                $ruser = '- '.get_string('anonymous', 'kilman').' -';
            } else if (!empty($response->userid)) {
                if ($user = $DB->get_record('user', ['id' => $response->userid])) {
                    $ruser = fullname($user);
                } else {
                    $ruser = '- '.get_string('unknown', 'kilman').' -';
                }
            } else {
                $ruser = $response->userid;
            }
            error (get_string('couldnotdelresp', 'kilman').$rid.get_string('by', 'kilman').$ruser.'?',
                $CFG->wwwroot.'/mod/kilman/report.php?action=vresp&amp;sid='.$sid.'&amp;&amp;instance='.
                $instance.'byresponse=1');
        }
        break;

    case 'dvallresp': // Delete all responses in kilman (or group). Do it!

        require_capability('mod/kilman:deleteresponses', $context);

        if (empty($kilman->survey)) {
            print_error('surveynotexists', 'kilman');
        } else if ($kilman->survey->courseid != $course->id) {
            print_error('surveyowner', 'kilman');
        }

        // Available group modes (0 = no groups; 1 = separate groups; 2 = visible groups).
        if ($groupmode > 0) {
            switch ($currentgroupid) {
                case 0:     // All participants.
                    $resps = $respsallparticipants;
                    break;
                default:     // Members of a specific group.
                    if (!($resps = $kilman->get_responses(false, $currentgroupid))) {
                        $resps = [];
                    }
            }
            if (empty($resps)) {
                $noresponses = true;
            } else {
                if ($rid === false) {
                    $resp = current($resps);
                    $rid = $resp->id;
                } else {
                    $resp = $DB->get_record('kilman_response', array('id' => $rid));
                }
                if (!empty($resp->userid)) {
                    if ($user = $DB->get_record('user', ['id' => $resp->userid])) {
                        $ruser = fullname($user);
                    } else {
                        $ruser = '- '.get_string('unknown', 'kilman').' -';
                    }
                } else {
                    $ruser = $resp->userid;
                }
            }
        } else {
            $resps = $respsallparticipants;
        }

        if (!empty($resps)) {
            foreach ($resps as $response) {
                kilman_delete_response($response, $kilman);
            }
            if (!$kilman->count_submissions()) {
                $redirection = $CFG->wwwroot.'/mod/kilman/view.php?id='.$cm->id;
            } else {
                $redirection = $CFG->wwwroot.'/mod/kilman/report.php?action=vall&amp;sid='.$sid.'&amp;instance='.$instance;
            }

            // Log this kilman delete all responses action.
            $context = context_module::instance($kilman->cm->id);
            $anonymous = $kilman->respondenttype == 'anonymous';

            $event = \mod_kilman\event\all_responses_deleted::create(array(
                'objectid' => $kilman->id,
                'anonymous' => $anonymous,
                'context' => $context
            ));
            $event->trigger();

            redirect($redirection);
        } else {
            error (get_string('couldnotdelresp', 'kilman'),
                $CFG->wwwroot.'/mod/kilman/report.php?action=vall&amp;sid='.$sid.'&amp;instance='.$instance);
        }
        break;

    case 'dwnpg': // Download page options.

        require_capability('mod/kilman:downloadresponses', $context);

        $PAGE->set_title(get_string('kilmanreport', 'kilman'));
        $PAGE->set_heading(format_string($course->fullname));
        echo $kilman->renderer->header();

        // Print the tabs.
        // Tab setup.
        if (empty($user)) {
            $SESSION->kilman->current_tab = 'downloadcsv';
        } else {
            $SESSION->kilman->current_tab = 'mydownloadcsv';
        }

        include('tabs.php');

        $groupname = '';
        if ($groupmode > 0) {
            switch ($currentgroupid) {
                case 0:     // All participants.
                    $groupname = get_string('allparticipants');
                    break;
                default:     // Members of a specific group.
                    $groupname = get_string('membersofselectedgroup', 'group').' '.get_string('group').' '.
                        $kilmangroups[$currentgroupid]->name;
            }
        }
        $output = '';
        $output .= "<br /><br />\n";
        $output .= $kilman->renderer->help_icon('downloadtextformat', 'kilman');
        $output .= '&nbsp;' . (get_string('downloadtextformat', 'kilman')) . ':&nbsp;' .
            get_string('responses', 'kilman').'&nbsp;'.$groupname;
        $output .= $kilman->renderer->heading(get_string('textdownloadoptions', 'kilman'));
        $output .= $kilman->renderer->box_start();
        $output .= "<form action=\"{$CFG->wwwroot}/mod/kilman/report.php\" method=\"GET\">\n";
        $output .= "<input type=\"hidden\" name=\"instance\" value=\"$instance\" />\n";
        $output .= "<input type=\"hidden\" name=\"user\" value=\"$user\" />\n";
        $output .= "<input type=\"hidden\" name=\"sid\" value=\"$sid\" />\n";
        $output .= "<input type=\"hidden\" name=\"action\" value=\"dcsv\" />\n";
        $output .= "<input type=\"hidden\" name=\"group\" value=\"$currentgroupid\" />\n";
        $output .= html_writer::checkbox('choicecodes', 1, true, get_string('includechoicecodes', 'kilman'));
        $output .= "<br />\n";
        $output .= html_writer::checkbox('choicetext', 1, true, get_string('includechoicetext', 'kilman'));
        $output .= "<br />\n";
        $output .= html_writer::checkbox('complete', 1, false, get_string('includeincomplete', 'kilman'));
        $output .= "<br />\n";
        $output .= "<br />\n";
        $output .= "<input type=\"submit\" name=\"submit\" value=\"".get_string('download', 'kilman')."\" />\n";
        $output .= "</form>\n";
        $output .= $kilman->renderer->box_end();

        $kilman->page->add_to_page('respondentinfo', $output);
        echo $kilman->renderer->render($kilman->page);

        echo $kilman->renderer->footer('none');

        // Log saved as text action.
        $params = array('objectid' => $kilman->id,
            'context' => $kilman->context,
            'courseid' => $course->id,
            'other' => array('action' => $action, 'instance' => $instance, 'currentgroupid' => $currentgroupid)
        );
        $event = \mod_kilman\event\all_responses_saved_as_text::create($params);
        $event->trigger();

        exit();
        break;

    case 'dcsv': // Download responses data as text (cvs) format.
        require_capability('mod/kilman:downloadresponses', $context);
        require_once($CFG->libdir.'/dataformatlib.php');

        // Use the kilman name as the file name. Clean it and change any non-filename characters to '_'.
        $name = clean_param($kilman->name, PARAM_FILE);
        $name = preg_replace("/[^A-Z0-9]+/i", "_", trim($name));

        $choicecodes = optional_param('choicecodes', '0', PARAM_INT);
        $choicetext  = optional_param('choicetext', '0', PARAM_INT);
        $showincompletes  = optional_param('complete', '0', PARAM_INT);
        $output = $kilman->generate_csv('', $user, $choicecodes, $choicetext, $currentgroupid, $showincompletes);

        // Use Moodle's core download function for outputting csv.
        $rowheaders = array_shift($output);
        download_as_dataformat($name, 'csv', $rowheaders, $output);
        exit();
        break;

    case 'vall':         // View all responses.
    case 'vallasort':    // View all responses sorted in ascending order.
    case 'vallarsort':   // View all responses sorted in descending order.

        $PAGE->set_title(get_string('kilmanreport', 'kilman'));
        $PAGE->set_heading(format_string($course->fullname));
        echo $kilman->renderer->header();
        if (!$kilman->capabilities->readallresponses && !$kilman->capabilities->readallresponseanytime) {
            // Should never happen, unless called directly by a snoop.
            print_error('nopermissions', '', '', get_string('viewallresponses', 'kilman'));
            // Finish the page.
            echo $kilman->renderer->footer($course);
            break;
        }

        // Print the tabs.
        switch ($action) {
            case 'vallasort':
                $SESSION->kilman->current_tab = 'vallasort';
                break;
            case 'vallarsort':
                $SESSION->kilman->current_tab = 'vallarsort';
                break;
            default:
                $SESSION->kilman->current_tab = 'valldefault';
        }
        include('tabs.php');

        $respinfo = '';
        $resps = array();
        // Enable choose_group if there are kilman groups and groupmode is not set to "no groups"
        // and if there are more goups than 1 (or if user can view all groups).
        if (is_array($kilmangroups) && $groupmode > 0) {
            $groupselect = groups_print_activity_menu($cm, $url->out(), true);
            // Count number of responses in each group.
            foreach ($kilmangroups as $group) {
                $respscount = $kilman->count_submissions(false, $group->id);
                $thisgroupname = groups_get_group_name($group->id);
                $escapedgroupname = preg_quote($thisgroupname, '/');
                if (!empty ($respscount)) {
                    // Add number of responses to name of group in the groups select list.
                    $groupselect = preg_replace('/\<option value="'.$group->id.'">'.$escapedgroupname.'<\/option>/',
                        '<option value="'.$group->id.'">'.$thisgroupname.' ('.$respscount.')</option>', $groupselect);
                } else {
                    // Remove groups with no responses from the groups select list.
                    $groupselect = preg_replace('/\<option value="'.$group->id.'">'.$escapedgroupname.
                        '<\/option>/', '', $groupselect);
                }
            }
            $respinfo .= isset($groupselect) ? ($groupselect . ' ') : '';
            $currentgroupid = groups_get_activity_group($cm);
        }
        if ($currentgroupid > 0) {
            $groupname = get_string('group').': <strong>'.groups_get_group_name($currentgroupid).'</strong>';
        } else {
            $groupname = '<strong>'.get_string('allparticipants').'</strong>';
        }

        // Available group modes (0 = no groups; 1 = separate groups; 2 = visible groups).
        if ($groupmode > 0) {
            switch ($currentgroupid) {
                case 0:     // All participants.
                    $resps = $respsallparticipants;
                    break;
                default:     // Members of a specific group.
                    if (!($resps = $kilman->get_responses(false, $currentgroupid))) {
                        $resps = '';
                    }
            }
            if (empty($resps)) {
                $noresponses = true;
            }
        } else {
            $resps = $respsallparticipants;
        }
        if (!empty($resps)) {
            // NOTE: response_analysis uses $resps to get the id's of the responses only.
            // Need to figure out what this function does.
            $feedbackmessages = $kilman->response_analysis(0, $resps, false, false, true, $currentgroupid);

            if ($feedbackmessages) {
                $msgout = '';
                foreach ($feedbackmessages as $msg) {
                    $msgout .= $msg;
                }
                $kilman->page->add_to_page('feedbackmessages', $msgout);
            }
        }

        $params = array('objectid' => $kilman->id,
            'context' => $context,
            'courseid' => $course->id,
            'other' => array('action' => $action, 'instance' => $instance, 'groupid' => $currentgroupid)
        );
        $event = \mod_kilman\event\all_responses_viewed::create($params);
        $event->trigger();

        $respinfo .= get_string('viewallresponses', 'kilman').'. '.$groupname.'. ';
        $strsort = get_string('order_'.$sort, 'kilman');
        $respinfo .= $strsort;
        $respinfo .= $kilman->renderer->help_icon('orderresponses', 'kilman');
        $kilman->page->add_to_page('respondentinfo', $respinfo);

        $ret = $kilman->survey_results(1, 1, '', '', '', false, $currentgroupid, $sort);

        echo $kilman->renderer->render($kilman->page);

        // Finish the page.
        echo $kilman->renderer->footer($course);
        break;

    case 'vresp': // View by response.

    default:
        if (empty($kilman->survey)) {
            print_error('surveynotexists', 'kilman');
        } else if ($kilman->survey->courseid != $course->id) {
            print_error('surveyowner', 'kilman');
        }
        $ruser = false;
        $noresponses = false;
        if ($usergraph) {
            $charttype = $kilman->survey->chart_type;
            if ($charttype) {
                $PAGE->requires->js('/mod/kilman/javascript/RGraph/RGraph.common.core.js');

                switch ($charttype) {
                    case 'bipolar':
                        $PAGE->requires->js('/mod/kilman/javascript/RGraph/RGraph.bipolar.js');
                        break;
                    case 'hbar':
                        $PAGE->requires->js('/mod/kilman/javascript/RGraph/RGraph.hbar.js');
                        break;
                    case 'radar':
                        $PAGE->requires->js('/mod/kilman/javascript/RGraph/RGraph.radar.js');
                        break;
                    case 'rose':
                        $PAGE->requires->js('/mod/kilman/javascript/RGraph/RGraph.rose.js');
                        break;
                    case 'vprogress':
                        $PAGE->requires->js('/mod/kilman/javascript/RGraph/RGraph.vprogress.js');
                        break;
                }
            }
        }

        if ($byresponse || $rid) {
            // Available group modes (0 = no groups; 1 = separate groups; 2 = visible groups).
            if ($groupmode > 0) {
                switch ($currentgroupid) {
                    case 0:     // All participants.
                        $resps = $respsallparticipants;
                        break;
                    default:     // Members of a specific group.
                        $resps = $kilman->get_responses(false, $currentgroupid);
                }
                if (empty($resps)) {
                    $noresponses = true;
                } else {
                    if ($rid === false) {
                        $resp = current($resps);
                        $rid = $resp->id;
                    } else {
                        $resp = $DB->get_record('kilman_response', ['id' => $rid]);
                    }
                    if (!empty($resp->userid)) {
                        if ($user = $DB->get_record('user', ['id' => $resp->userid])) {
                            $ruser = fullname($user);
                        } else {
                            $ruser = '- '.get_string('unknown', 'kilman').' -';
                        }
                    } else {
                        $ruser = $resp->userid;
                    }
                }
            } else {
                $resps = $respsallparticipants;
            }
        }
        $rids = array_keys($resps);
        if (!$rid && !$noresponses) {
            $rid = $rids[0];
        }

        // Print the page header.
        $PAGE->set_title(get_string('kilmanreport', 'kilman'));
        $PAGE->set_heading(format_string($course->fullname));
        echo $kilman->renderer->header();

        // Print the tabs.
        if ($byresponse) {
            $SESSION->kilman->current_tab = 'vrespsummary';
        }
        if ($individualresponse) {
            $SESSION->kilman->current_tab = 'individualresp';
        }
        include('tabs.php');

        // Print the main part of the page.
        // TODO provide option to select how many columns and/or responses per page.

        if ($noresponses) {
            $kilman->page->add_to_page('respondentinfo',
                get_string('group').' <strong>'.groups_get_group_name($currentgroupid).'</strong>: '.
                get_string('noresponses', 'kilman'));
        } else {
            $groupname = get_string('group').': <strong>'.groups_get_group_name($currentgroupid).'</strong>';
            if ($currentgroupid == 0 ) {
                $groupname = get_string('allparticipants');
            }
            if ($byresponse) {
                $respinfo = '';
                $respinfo .= $kilman->renderer->box_start();
                $respinfo .= $kilman->renderer->help_icon('viewindividualresponse', 'kilman').'&nbsp;';
                $respinfo .= get_string('viewindividualresponse', 'kilman').' <strong> : '.$groupname.'</strong>';
                $respinfo .= $kilman->renderer->box_end();
                $kilman->page->add_to_page('respondentinfo', $respinfo);
            }
            $kilman->survey_results_navbar_alpha($rid, $currentgroupid, $cm, $byresponse);
            if (!$byresponse) { // Show respondents individual responses.
                $kilman->view_response($rid, '', false, $resps, true, true, false, $currentgroupid);
            }
        }

        echo $kilman->renderer->render($kilman->page);

        // Finish the page.
        echo $kilman->renderer->footer($course);
        break;
}
