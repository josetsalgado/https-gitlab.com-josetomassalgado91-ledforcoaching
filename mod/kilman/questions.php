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
require_once($CFG->dirroot.'/mod/kilman/classes/question/base.php'); // Needed for question type constants.

$id     = required_param('id', PARAM_INT);                 // Course module ID
$action = optional_param('action', 'main', PARAM_ALPHA);   // Screen.
$qid    = optional_param('qid', 0, PARAM_INT);             // Question id.
$moveq  = optional_param('moveq', 0, PARAM_INT);           // Question id to move.
$delq   = optional_param('delq', 0, PARAM_INT);             // Question id to delete
$qtype  = optional_param('type_id', 0, PARAM_INT);         // Question type.
$currentgroupid = optional_param('group', 0, PARAM_INT); // Group id.

if (! $cm = get_coursemodule_from_id('kilman', $id)) {
    print_error('invalidcoursemodule');
}

if (! $course = $DB->get_record("course", array("id" => $cm->course))) {
    print_error('coursemisconf');
}

if (! $kilman = $DB->get_record("kilman", array("id" => $cm->instance))) {
    print_error('invalidcoursemodule');
}

require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);

$url = new moodle_url($CFG->wwwroot.'/mod/kilman/questions.php');
$url->param('id', $id);
if ($qid) {
    $url->param('qid', $qid);
}

$PAGE->set_url($url);
$PAGE->set_context($context);

$kilman = new kilman(0, $kilman, $course, $cm);

// Add renderer and page objects to the kilman object for display use.
$kilman->add_renderer($PAGE->get_renderer('mod_kilman'));
$kilman->add_page(new \mod_kilman\output\questionspage());

if (!$kilman->capabilities->editquestions) {
    print_error('nopermissions', 'error', '', 'mod:kilman:edit');
}

$kilmanhasdependencies = $kilman->has_dependencies();
$haschildren = [];
if (!isset($SESSION->kilman)) {
    $SESSION->kilman = new stdClass();
}
$SESSION->kilman->current_tab = 'questions';
$reload = false;
$sid = $kilman->survey->id;
// Process form data.

// Delete question button has been pressed in questions_form AND deletion has been confirmed on the confirmation page.
if ($delq) {
    $qid = $delq;
    $sid = $kilman->survey->id;
    $kilmanid = $kilman->id;

    // Need to reload questions before setting deleted question to 'y'.
    $questions = $DB->get_records('kilman_question', ['surveyid' => $sid, 'deleted' => 'n'], 'id');
    $DB->set_field('kilman_question', 'deleted', 'y', ['id' => $qid, 'surveyid' => $sid]);

    // Delete all dependency records for this question.
    kilman_delete_dependencies($qid);

    // Just in case the page is refreshed (F5) after a question has been deleted.
    if (isset($questions[$qid])) {
        $select = 'surveyid = '.$sid.' AND deleted = \'n\' AND position > '.
                        $questions[$qid]->position;
    } else {
        redirect($CFG->wwwroot.'/mod/kilman/questions.php?id='.$kilman->cm->id);
    }

    if ($records = $DB->get_records_select('kilman_question', $select, null, 'position ASC')) {
        foreach ($records as $record) {
            $DB->set_field('kilman_question', 'position', $record->position - 1, array('id' => $record->id));
        }
    }
    // Delete section breaks without asking for confirmation.
    // No need to delete responses to those "question types" which are not real questions.
    if (!$kilman->questions[$qid]->supports_responses()) {
        $reload = true;
    } else {
        // Delete responses to that deleted question.
        kilman_delete_responses($qid);

        // If no questions left in this kilman, remove all responses.
        if ($DB->count_records('kilman_question', ['surveyid' => $sid, 'deleted' => 'n']) == 0) {
            $DB->delete_records('kilman_response', ['kilmanid' => $qid]);
        }
    }

    // Log question deleted event.
    $context = context_module::instance($kilman->cm->id);
    $questiontype = \mod_kilman\question\base::qtypename($kilman->questions[$qid]->type_id);
    $params = array(
                    'context' => $context,
                    'courseid' => $kilman->course->id,
                    'other' => array('questiontype' => $questiontype)
    );
    $event = \mod_kilman\event\question_deleted::create($params);
    $event->trigger();

    if ($kilmanhasdependencies) {
        $SESSION->kilman->validateresults = kilman_check_page_breaks($kilman);
    }
    $reload = true;
}

if ($action == 'main') {
    $questionsform = new \mod_kilman\questions_form('questions.php', $moveq);
    $sdata = clone($kilman->survey);
    $sdata->sid = $kilman->survey->id;
    $sdata->id = $cm->id;
    if (!empty($kilman->questions)) {
        $pos = 1;
        foreach ($kilman->questions as $qidx => $question) {
            $sdata->{'pos_'.$qidx} = $pos;
            $pos++;
        }
    }
    $questionsform->set_data($sdata);
    if ($questionsform->is_cancelled()) {
        // Switch to main screen.
        $action = 'main';
        redirect($CFG->wwwroot.'/mod/kilman/questions.php?id='.$kilman->cm->id);
        $reload = true;
    }
    if ($qformdata = $questionsform->get_data()) {
        // Quickforms doesn't return values for 'image' input types using 'exportValue', so we need to grab
        // it from the raw submitted data.
        $exformdata = data_submitted();

        if (isset($exformdata->movebutton)) {
            $qformdata->movebutton = $exformdata->movebutton;
        } else if (isset($exformdata->moveherebutton)) {
            $qformdata->moveherebutton = $exformdata->moveherebutton;
        } else if (isset($exformdata->editbutton)) {
            $qformdata->editbutton = $exformdata->editbutton;
        } else if (isset($exformdata->removebutton)) {
            $qformdata->removebutton = $exformdata->removebutton;
        } else if (isset($exformdata->requiredbutton)) {
            $qformdata->requiredbutton = $exformdata->requiredbutton;
        }

        // Insert a section break.
        if (isset($qformdata->removebutton)) {
            // Need to use the key, since IE returns the image position as the value rather than the specified
            // value in the <input> tag.
            $qid = key($qformdata->removebutton);
            $qtype = $kilman->questions[$qid]->type_id;

            // Delete section breaks without asking for confirmation.
            if ($qtype == QUESPAGEBREAK) {
                redirect($CFG->wwwroot.'/mod/kilman/questions.php?id='.$kilman->cm->id.'&amp;delq='.$qid);
            }
            if ($kilmanhasdependencies) {
                // Important: due to possibly multiple parents per question
                // just remove the dependency and inform the user about it.
                $haschildren = $kilman->get_all_dependants($qid);
            }
            if (count($haschildren) != 0) {
                $action = "confirmdelquestionparent";
            } else {
                $action = "confirmdelquestion";
            }

        } else if (isset($qformdata->editbutton)) {
            // Switch to edit question screen.
            $action = 'question';
            // Need to use the key, since IE returns the image position as the value rather than the specified
            // value in the <input> tag.
            $qid = key($qformdata->editbutton);
            $reload = true;

        } else if (isset($qformdata->requiredbutton)) {
            // Need to use the key, since IE returns the image position as the value rather than the specified
            // value in the <input> tag.

            $qid = key($qformdata->requiredbutton);
            if ($kilman->questions[$qid]->required()) {
                $kilman->questions[$qid]->set_required(false);

            } else {
                $kilman->questions[$qid]->set_required(true);
            }

            $reload = true;

        } else if (isset($qformdata->addqbutton)) {
            if ($qformdata->type_id == QUESPAGEBREAK) { // Adding section break is handled right away....
                $questionrec = new stdClass();
                $questionrec->surveyid = $qformdata->sid;
                $questionrec->type_id = QUESPAGEBREAK;
                $questionrec->content = 'break';
                $question = \mod_kilman\question\base::question_builder(QUESPAGEBREAK);
                $question->add($questionrec);
                $reload = true;
            } else {
                // Switch to edit question screen.
                $action = 'question';
                $qtype = $qformdata->type_id;
                $qid = 0;
                $reload = true;
            }

        } else if (isset($qformdata->movebutton)) {
            // Nothing I do will seem to reload the form with new data, except for moving away from the page, so...
            redirect($CFG->wwwroot.'/mod/kilman/questions.php?id='.$kilman->cm->id.
                     '&moveq='.key($qformdata->movebutton));
            $reload = true;



        } else if (isset($qformdata->moveherebutton)) {
            // Need to use the key, since IE returns the image position as the value rather than the specified
            // value in the <input> tag.

            // No need to move question if new position = old position!
            $qpos = key($qformdata->moveherebutton);
            if ($qformdata->moveq != $qpos) {
                $kilman->move_question($qformdata->moveq, $qpos);
            }
            if ($kilmanhasdependencies) {
                $SESSION->kilman->validateresults = kilman_check_page_breaks($kilman);
            }
            // Nothing I do will seem to reload the form with new data, except for moving away from the page, so...
            redirect($CFG->wwwroot.'/mod/kilman/questions.php?id='.$kilman->cm->id);
            $reload = true;

        } else if (isset($qformdata->validate)) {
            // Validates page breaks for depend questions.
            $SESSION->kilman->validateresults = kilman_check_page_breaks($kilman);
            $reload = true;
        }
    }


} else if ($action == 'question') {
    $question = kilman_prep_for_questionform($kilman, $qid, $qtype);
    $questionsform = new \mod_kilman\edit_question_form('questions.php');
    $questionsform->set_data($question);
    if ($questionsform->is_cancelled()) {
        // Switch to main screen.
        $action = 'main';
        $reload = true;

    } else if ($qformdata = $questionsform->get_data()) {
        // Saving question data.
        if (isset($qformdata->makecopy)) {
            $qformdata->qid = 0;
        }

        $question->form_update($qformdata, $kilman);

        // Make these field values 'sticky' for further new questions.
        if (!isset($qformdata->required)) {
            $qformdata->required = 'n';
        }

        kilman_check_page_breaks($kilman);
        $SESSION->kilman->required = $qformdata->required;
        $SESSION->kilman->type_id = $qformdata->type_id;
        // Switch to main screen.
        $action = 'main';
        $reload = true;
    }

    // Log question created event.
    if (isset($qformdata)) {
        $context = context_module::instance($kilman->cm->id);
        $questiontype = \mod_kilman\question\base::qtypename($qformdata->type_id);
        $params = array(
                        'context' => $context,
                        'courseid' => $kilman->course->id,
                        'other' => array('questiontype' => $questiontype)
        );
        $event = \mod_kilman\event\question_created::create($params);
        $event->trigger();
    }

    $questionsform->set_data($question);
}

// Reload the form data if called for...
if ($reload) {
    unset($questionsform);
    $kilman = new kilman($kilman->id, null, $course, $cm);
    // Add renderer and page objects to the kilman object for display use.
    $kilman->add_renderer($PAGE->get_renderer('mod_kilman'));
    $kilman->add_page(new \mod_kilman\output\questionspage());
    if ($action == 'main') {
        $questionsform = new \mod_kilman\questions_form('questions.php', $moveq);
        $sdata = clone($kilman->survey);
        $sdata->sid = $kilman->survey->id;
        $sdata->id = $cm->id;
        if (!empty($kilman->questions)) {
            $pos = 1;
            foreach ($kilman->questions as $qidx => $question) {
                $sdata->{'pos_'.$qidx} = $pos;
                $pos++;
            }
        }
        $questionsform->set_data($sdata);
    } else if ($action == 'question') {
        $question = kilman_prep_for_questionform($kilman, $qid, $qtype);
        $questionsform = new \mod_kilman\edit_question_form('questions.php');
        $questionsform->set_data($question);
    }
}

// Print the page header.
if ($action == 'question') {
    if (isset($question->qid)) {
        $streditquestion = get_string('editquestion', 'kilman', kilman_get_type($question->type_id));
    } else {
        $streditquestion = get_string('addnewquestion', 'kilman', kilman_get_type($question->type_id));
    }
} else {
    $streditquestion = get_string('managequestions', 'kilman');
}

$PAGE->set_title($streditquestion);
$PAGE->set_heading(format_string($course->fullname));
$PAGE->navbar->add($streditquestion);
echo $kilman->renderer->header();
require('tabs.php');

if ($action == "confirmdelquestion" || $action == "confirmdelquestionparent") {

    $qid = key($qformdata->removebutton);
    $question = $kilman->questions[$qid];
    $qtype = $question->type_id;

    // Count responses already saved for that question.
    $countresps = 0;
    if ($qtype != QUESSECTIONTEXT) {
        $responsetable = $DB->get_field('kilman_question_type', 'response_table', array('typeid' => $qtype));
        if (!empty($responsetable)) {
            $countresps = $DB->count_records('kilman_'.$responsetable, array('question_id' => $qid));
        }
    }

    // Needed to print potential media in question text.

    // If question text is "empty", i.e. 2 non-breaking spaces were inserted, do not display any question text.

    if ($question->content == '<p>  </p>') {
        $question->content = '';
    }

    $qname = '';
    if ($question->name) {
        $qname = ' ('.$question->name.')';
    }

    $num = get_string('position', 'kilman');
    $pos = $question->position.$qname;

    $msg = '<div class="warning centerpara"><p>'.get_string('confirmdelquestion', 'kilman', $pos).'</p>';
    if ($countresps !== 0) {
        $msg .= '<p>'.get_string('confirmdelquestionresps', 'kilman', $countresps).'</p>';
    }
    $msg .= '</div>';
    $msg .= '<div class = "qn-container">'.$num.' '.$pos.'<div class="qn-question">'.$question->content.'</div></div>';
    $args = "id={$kilman->cm->id}";
    $urlno = new moodle_url("/mod/kilman/questions.php?{$args}");
    $args .= "&delq={$qid}";
    $urlyes = new moodle_url("/mod/kilman/questions.php?{$args}");
    $buttonyes = new single_button($urlyes, get_string('yes'));
    $buttonno = new single_button($urlno, get_string('no'));
    if ($action == "confirmdelquestionparent") {
        $strnum = get_string('position', 'kilman');
        $qid = key($qformdata->removebutton);
        // Show the dependencies and inform about the dependencies to be removed.
        // Split dependencies in direct and indirect ones to separate for the confirm-dialogue. Only direct ones will be deleted.
        // List direct dependencies.
        $msg .= $kilman->renderer->dependency_warnings($haschildren->directs, 'directwarnings', $strnum);
        // List indirect dependencies.
        $msg .= $kilman->renderer->dependency_warnings($haschildren->indirects, 'indirectwarnings', $strnum);
    }
    $kilman->page->add_to_page('formarea', $kilman->renderer->confirm($msg, $buttonyes, $buttonno));

} else {
    $kilman->page->add_to_page('formarea', $questionsform->render());
}
echo $kilman->renderer->render($kilman->page);
echo $kilman->renderer->footer();