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
 * Manage feedback sections.
 *
 * @package mod_kilman
 * @copyright  2016 onward Mike Churchward (mike.churchward@poetgroup.org)
 * @author Joseph Rezeau
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */

require_once("../../config.php");
require_once($CFG->dirroot.'/mod/kilman/kilman.class.php');

$id = required_param('id', PARAM_INT);    // Course module ID.
$section = optional_param('section', 1, PARAM_INT);
if ($section == 0) {
    $section = 1;
}
$currentgroupid = optional_param('group', 0, PARAM_INT); // Groupid.
$action = optional_param('action', '', PARAM_ALPHA);
$sectionid = optional_param('sectionid', 0, PARAM_INT);

if (! $cm = get_coursemodule_from_id('kilman', $id)) {
    print_error('invalidcoursemodule');
}

if (! $course = $DB->get_record("course", ["id" => $cm->course])) {
    print_error('coursemisconf');
}

if (! $kilman = $DB->get_record("kilman", ["id" => $cm->instance])) {
    print_error('invalidcoursemodule');
}

// Needed here for forced language courses.
require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);

$url = new moodle_url('/mod/kilman/fbsections.php', ['id' => $id]);
$PAGE->set_url($url);
$PAGE->set_context($context);
if (!isset($SESSION->kilman)) {
    $SESSION->kilman = new stdClass();
}

$kilman = new kilman(0, $kilman, $course, $cm);

if ($sectionid) {
    // Get the specified section by its id.
    $feedbacksection = new mod_kilman\feedback\section(['id' => $sectionid], $kilman->questions);

} else if (!$DB->count_records('kilman_fb_sections', ['surveyid' => $kilman->sid])) {
    // There are no sections currently, so create one.
    if ($kilman->survey->feedbacksections == 1) {
        $sectionlabel = get_string('feedbackglobal', 'kilman');
    } else {
        $sectionlabel = get_string('feedbackdefaultlabel', 'kilman');
    }
    $feedbacksection = mod_kilman\feedback\section::new_section($kilman->sid, $sectionlabel);

} else {
    // Get the specified section by section number.
    $feedbacksection = new mod_kilman\feedback\section(['surveyid' => $kilman->survey->id, 'sectionnum' => $section],
        $kilman->questions);
}

// Get all questions that are valid feedback questions.
$validquestions = [];
foreach ($kilman->questions as $question) {
    if ($question->valid_feedback()) {
        $validquestions[$question->id] = $question->name;
    }
}

// Add renderer and page objects to the kilman object for display use.
$kilman->add_renderer($PAGE->get_renderer('mod_kilman'));
$kilman->add_page(new \mod_kilman\output\feedbackpage());

$SESSION->kilman->current_tab = 'feedback';

if (!$kilman->capabilities->editquestions) {
    print_error('nopermissions', 'error', '', 'mod:kilman:editquestions');
}

// Handle confirmed actions that impact display immediately.
if ($action == 'removequestion') {
    $sectionid = required_param('sectionid', PARAM_INT);
    $qid = required_param('qid', PARAM_INT);
    $feedbacksection->remove_question($qid);

} else if ($action == 'deletesection') {
    $sectionid = required_param('sectionid', PARAM_INT);
    if ($sectionid == $feedbacksection->id) {
        $feedbacksection->delete();
        redirect(new moodle_url('/mod/kilman/fbsections.php', ['id' => $cm->id]));
    }
}

$customdata = new stdClass();
$customdata->feedbacksection = $feedbacksection;
$customdata->validquestions = $validquestions;
$customdata->survey = $kilman->survey;
$customdata->sectionselect = $DB->get_records_menu('kilman_fb_sections', ['surveyid' => $kilman->survey->id],
    'section', 'id,sectionlabel');

$feedbackform = new \mod_kilman\feedback_section_form('fbsections.php', $customdata);
$sdata = clone($feedbacksection);
$sdata->sid = $kilman->survey->id;
$sdata->sectionid = $feedbacksection->id;
$sdata->id = $cm->id;

$draftideditor = file_get_submitted_draft_itemid('sectionheading');
$currentinfo = file_prepare_draft_area($draftideditor, $context->id, 'mod_kilman', 'sectionheading',
    $feedbacksection->id, ['subdirs' => true], $feedbacksection->sectionheading);
$sdata->sectionheading = ['text' => $currentinfo, 'format' => FORMAT_HTML, 'itemid' => $draftideditor];

$feedbackform->set_data($sdata);

if ($feedbackform->is_cancelled()) {
    redirect(new moodle_url('/mod/kilman/feedback.php', ['id' => $cm->id]));
}

if ($settings = $feedbackform->get_data()) {
    // Because formslib doesn't support 'numeric' or 'image' inputs, the results won't show up in the $feedbackform object.
    $fullform = data_submitted();

    if (isset($settings->gotosection)) {
        if ($settings->navigatesections != $feedbacksection->id) {
            redirect(new moodle_url('/mod/kilman/fbsections.php',
                ['id' => $cm->id, 'sectionid' => $settings->navigatesections]));
        }

    } else if (isset($settings->addnewsection)) {
        $newsection = mod_kilman\feedback\section::new_section($kilman->survey->id, $settings->newsectionlabel);
        redirect(new moodle_url('/mod/kilman/fbsections.php', ['id' => $cm->id, 'sectionid' => $newsection->id]));

    } else if (isset($fullform->confirmdeletesection)) {
        redirect(new moodle_url('/mod/kilman/fbsections.php',
            ['id' => $cm->id, 'sectionid' => $feedbacksection->id, 'action' => 'confirmdeletesection']));

    } else if (isset($fullform->confirmremovequestion)) {
        $qid = key($fullform->confirmremovequestion);
        redirect(new moodle_url('/mod/kilman/fbsections.php',
            ['id' => $cm->id, 'sectionid' => $settings->sectionid, 'action' => 'confirmremovequestion', 'qid' => $qid]));

    } else if (isset($settings->addquestion)) {
        $scorecalculation = [];
        // Check for added question.
        if (isset($settings->addquestionselect) && ($settings->addquestionselect != 0)) {
            if ($kilman->questions[$settings->addquestionselect]->supports_feedback_scores()) {
                $scorecalculation[$settings->addquestionselect] = 1;
            } else {
                $scorecalculation[$settings->addquestionselect] = -1;
            }
        }
        // Get all current asigned questions.
        if (isset($fullform->weight)) {
            foreach ($fullform->weight as $qid => $value) {
                $scorecalculation[$qid] = $value;
            }
        }
        // Update the section with question weights.
        $feedbacksection->set_new_scorecalculation($scorecalculation);

    } else if (isset($settings->submitbutton)) {
        if (isset($fullform->weight)) {
            $feedbacksection->scorecalculation = $fullform->weight;
        } else {
            $feedbacksection->scorecalculation = [];
        }
        $feedbacksection->sectionlabel = $settings->sectionlabel;
        $feedbacksection->sectionheading = file_save_draft_area_files((int)$settings->sectionheading['itemid'], $context->id,
            'mod_kilman', 'sectionheading', $feedbacksection->id, ['subdirs' => false, 'maxfiles' => -1, 'maxbytes' => 0],
            $settings->sectionheading['text']);
        $feedbacksection->sectionheadingformat = $settings->sectionheading['format'];

        // May have changed the section label and weights, so update the data.
        $customdata->sectionselect[$feedbacksection->id] = $settings->sectionlabel;
        if (isset($fullform->weight)) {
            $customdata->feedbacksection->scorecalculation = $fullform->weight;
        }

        // Save current section's feedbacks
        // first delete all existing feedbacks for this section - if any - because we never know whether editing feedbacks will
        // have more or less texts, so it's easiest to delete all and start afresh.
        $feedbacksection->delete_sectionfeedback();

        $i = 0;
        while (!empty($settings->feedbackboundaries[$i])) {
            $boundary = trim($settings->feedbackboundaries[$i]);
            if (strlen($boundary) > 0 && $boundary[strlen($boundary) - 1] == '%') {
                $boundary = trim(substr($boundary, 0, -1));
            }
            $settings->feedbackboundaries[$i] = $boundary;
            $i += 1;
        }
        $numboundaries = $i;
        $settings->feedbackboundaries[-1] = 101;
        $settings->feedbackboundaries[$numboundaries] = 0;
        $settings->feedbackboundarycount = $numboundaries;

        // Now set up new section feedback records for each saved boundary.
        for ($i = 0; $i <= $settings->feedbackboundarycount; $i++) {
            $feedback = new stdClass();
            $feedback->sectionid = $feedbacksection->id;
            if (isset($settings->feedbacklabel[$i])) {
                $feedback->feedbacklabel = $settings->feedbacklabel[$i];
            } else {
                $feedback->feedbacklabel = null;
            }
            $feedback->feedbacktext = '';
            $feedback->feedbacktextformat = $settings->feedbacktext[$i]['format'];
            $feedback->minscore = $settings->feedbackboundaries[$i];
            $feedback->maxscore = $settings->feedbackboundaries[$i - 1];

            $fbid = $feedbacksection->load_sectionfeedback($feedback);

            $feedbacktext = file_save_draft_area_files((int)$settings->feedbacktext[$i]['itemid'],
                $context->id, 'mod_kilman', 'feedback', $fbid, ['subdirs' => false, 'maxfiles' => -1, 'maxbytes' => 0],
                $settings->feedbacktext[$i]['text']);
            $feedbacksection->sectionfeedback[$fbid]->feedbacktext = $feedbacktext;
        }

        // Update all feedback data.
        $feedbacksection->update();
    }
    $feedbackform = new \mod_kilman\feedback_section_form('fbsections.php', $customdata);
}

// Print the page header.
$PAGE->set_title(get_string('editingfeedback', 'kilman'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->navbar->add(get_string('editingfeedback', 'kilman'));
echo $kilman->renderer->header();
require('tabs.php');

// Handle confirmations differently.
if ($action == 'confirmremovequestion') {
    $sectionid = required_param('sectionid', PARAM_INT);
    $qid = required_param('qid', PARAM_INT);
    $msgargs = new stdClass();
    $msgargs->qname = $kilman->questions[$qid]->name;
    $msgargs->sname = $feedbacksection->sectionlabel;
    $msg = '<div class="warning centerpara"><p>' . get_string('confirmremovequestion', 'kilman', $msgargs) . '</p></div>';
    $args = ['id' => $kilman->cm->id, 'sectionid' => $sectionid];
    $urlno = new moodle_url('/mod/kilman/fbsections.php', $args);
    $args['action'] = 'removequestion';
    $args['qid'] = $qid;
    $urlyes = new moodle_url('/mod/kilman/fbsections.php', $args);
    $buttonyes = new single_button($urlyes, get_string('yes'));
    $buttonno = new single_button($urlno, get_string('no'));
    $kilman->page->add_to_page('formarea', $kilman->renderer->confirm($msg, $buttonyes, $buttonno));

} else if ($action == 'confirmdeletesection') {
    $sectionid = required_param('sectionid', PARAM_INT);
    $msg = '<div class="warning centerpara"><p>' .
        get_string('confirmdeletesection', 'kilman', $feedbacksection->sectionlabel) . '</p></div>';
    $args = ['id' => $kilman->cm->id, 'sectionid' => $sectionid];
    $urlno = new moodle_url('/mod/kilman/fbsections.php', $args);
    $args['action'] = 'deletesection';
    $urlyes = new moodle_url('/mod/kilman/fbsections.php', $args);
    $buttonyes = new single_button($urlyes, get_string('yes'));
    $buttonno = new single_button($urlno, get_string('no'));
    $kilman->page->add_to_page('formarea', $kilman->renderer->confirm($msg, $buttonyes, $buttonno));

} else {
    $kilman->page->add_to_page('formarea', $feedbackform->render());
}

echo $kilman->renderer->render($kilman->page);
echo $kilman->renderer->footer($course);
