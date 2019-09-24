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
 * @package mod_kilman
 * @copyright  2016 Mike Churchward (mike.churchward@poetgroup.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Define all the restore steps that will be used by the restore_kilman_activity_task
 */

/**
 * Structure step to restore one kilman activity
 */
class restore_kilman_activity_structure_step extends restore_activity_structure_step {

    /**
     * @var array $olddependquestions Contains any question id's with dependencies.
     */
    protected $olddependquestions = [];

    /**
     * @var array $olddependchoices Contains any choice id's for questions with dependencies.
     */
    protected $olddependchoices = [];

    /**
     * @var array $olddependencies Contains the old id's from the dependencies array.
     */
    protected $olddependencies = [];

    protected function define_structure() {

        $paths = array();
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('kilman', '/activity/kilman');
        $paths[] = new restore_path_element('kilman_survey', '/activity/kilman/surveys/survey');
        $paths[] = new restore_path_element('kilman_fb_sections',
                        '/activity/kilman/surveys/survey/fb_sections/fb_section');
        $paths[] = new restore_path_element('kilman_feedback',
                        '/activity/kilman/surveys/survey/fb_sections/fb_section/feedbacks/feedback');
        $paths[] = new restore_path_element('kilman_question',
                        '/activity/kilman/surveys/survey/questions/question');
        $paths[] = new restore_path_element('kilman_quest_choice',
                        '/activity/kilman/surveys/survey/questions/question/quest_choices/quest_choice');
        $paths[] = new restore_path_element('kilman_dependency',
                '/activity/kilman/surveys/survey/questions/question/quest_dependencies/quest_dependency');

        if ($userinfo) {
            if ($this->task->get_old_moduleversion() < 2018050102) {
                // Old system.
                $paths[] = new restore_path_element('kilman_attempt', '/activity/kilman/attempts/attempt');
                $paths[] = new restore_path_element('kilman_response',
                    '/activity/kilman/attempts/attempt/responses/response');
                $paths[] = new restore_path_element('kilman_response_bool',
                    '/activity/kilman/attempts/attempt/responses/response/response_bools/response_bool');
                $paths[] = new restore_path_element('kilman_response_date',
                    '/activity/kilman/attempts/attempt/responses/response/response_dates/response_date');
                $paths[] = new restore_path_element('kilman_response_multiple',
                    '/activity/kilman/attempts/attempt/responses/response/response_multiples/response_multiple');
                $paths[] = new restore_path_element('kilman_response_other',
                    '/activity/kilman/attempts/attempt/responses/response/response_others/response_other');
                $paths[] = new restore_path_element('kilman_response_rank',
                    '/activity/kilman/attempts/attempt/responses/response/response_ranks/response_rank');
                $paths[] = new restore_path_element('kilman_response_single',
                    '/activity/kilman/attempts/attempt/responses/response/response_singles/response_single');
                $paths[] = new restore_path_element('kilman_response_text',
                    '/activity/kilman/attempts/attempt/responses/response/response_texts/response_text');

            } else {
                // New system.
                $paths[] = new restore_path_element('kilman_response', '/activity/kilman/responses/response');
                $paths[] = new restore_path_element('kilman_response_bool',
                    '/activity/kilman/responses/response/response_bools/response_bool');
                $paths[] = new restore_path_element('kilman_response_date',
                    '/activity/kilman/responses/response/response_dates/response_date');
                $paths[] = new restore_path_element('kilman_response_multiple',
                    '/activity/kilman/responses/response/response_multiples/response_multiple');
                $paths[] = new restore_path_element('kilman_response_other',
                    '/activity/kilman/responses/response/response_others/response_other');
                $paths[] = new restore_path_element('kilman_response_rank',
                    '/activity/kilman/responses/response/response_ranks/response_rank');
                $paths[] = new restore_path_element('kilman_response_single',
                    '/activity/kilman/responses/response/response_singles/response_single');
                $paths[] = new restore_path_element('kilman_response_text',
                    '/activity/kilman/responses/response/response_texts/response_text');
            }
        }

        // Return the paths wrapped into standard activity structure.
        return $this->prepare_activity_structure($paths);
    }

    protected function process_kilman($data) {
        global $DB;

        $data = (object)$data;
        $data->course = $this->get_courseid();

        $data->opendate = $this->apply_date_offset($data->opendate);
        $data->closedate = $this->apply_date_offset($data->closedate);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        // Insert the kilman record.
        $newitemid = $DB->insert_record('kilman', $data);
        // Immediately after inserting "activity" record, call this.
        $this->apply_activity_instance($newitemid);
    }

    protected function process_kilman_survey($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->courseid = $this->get_courseid();

        // Check for a 'feedbacksections' value larger than 2, and limit it to 2. As of 3.5.1 this has a different meaning.
        if ($data->feedbacksections > 2) {
            $data->feedbacksections = 2;
        }

        // Insert the kilman_survey record.
        $newitemid = $DB->insert_record('kilman_survey', $data);
        $this->set_mapping('kilman_survey', $oldid, $newitemid, true);

        // Update the kilman record we just created with the new survey id.
        $DB->set_field('kilman', 'sid', $newitemid, array('id' => $this->get_new_parentid('kilman')));
    }

    protected function process_kilman_question($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->surveyid = $this->get_new_parentid('kilman_survey');

        // Insert the kilman_question record.
        $newitemid = $DB->insert_record('kilman_question', $data);
        $this->set_mapping('kilman_question', $oldid, $newitemid, true);

        if (isset($data->dependquestion) && ($data->dependquestion > 0)) {
            // Questions using the old dependency system will need to be processed and restored using the new system.
            // See CONTRIB-6787.
            $this->olddependquestions[$newitemid] = $data->dependquestion;
            $this->olddependchoices[$newitemid] = $data->dependchoice;
        }
    }

    /**
     * $qid is unused, but is needed in order to get the $key elements of the array. Suppress PHPMD warning.
     *
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     */
    protected function process_kilman_fb_sections($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->surveyid = $this->get_new_parentid('kilman_survey');

        // If this kilman has separate sections feedbacks.
        if (isset($data->scorecalculation)) {
            $scorecalculation = unserialize($data->scorecalculation);
            $newscorecalculation = array();
            foreach ($scorecalculation as $qid => $val) {
                $newqid = $this->get_mappingid('kilman_question', $qid);
                $newscorecalculation[$newqid] = $val;
            }
            $data->scorecalculation = serialize($newscorecalculation);
        }

        // Insert the kilman_fb_sections record.
        $newitemid = $DB->insert_record('kilman_fb_sections', $data);
        $this->set_mapping('kilman_fb_sections', $oldid, $newitemid, true);
    }

    protected function process_kilman_feedback($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->sectionid = $this->get_new_parentid('kilman_fb_sections');

        // Insert the kilman_feedback record.
        $newitemid = $DB->insert_record('kilman_feedback', $data);
        $this->set_mapping('kilman_feedback', $oldid, $newitemid, true);
    }

    protected function process_kilman_quest_choice($data) {
        global $CFG, $DB;

        $data = (object)$data;

        // Replace the = separator with :: separator in quest_choice content.
        // This fixes radio button options using old "value"="display" formats.
        require_once($CFG->dirroot.'/mod/kilman/locallib.php');

        // Some old systems had '' instead of NULL. Change it to NULL.
        if ($data->value === '') {
            $data->value = null;
        }

        if (($data->value == null || $data->value == 'NULL') && !preg_match("/^([0-9]{1,3}=.*|!other=.*)$/", $data->content)) {
            $content = kilman_choice_values($data->content);
            if (strpos($content->text, '=')) {
                $data->content = str_replace('=', '::', $content->text);
            }
        }

        $oldid = $data->id;
        $data->question_id = $this->get_new_parentid('kilman_question');

        // Insert the kilman_quest_choice record.
        $newitemid = $DB->insert_record('kilman_quest_choice', $data);
        $this->set_mapping('kilman_quest_choice', $oldid, $newitemid);
    }

    protected function process_kilman_dependency($data) {
        $data = (object)$data;

        $data->questionid = $this->get_new_parentid('kilman_question');
        $data->surveyid = $this->get_new_parentid('kilman_survey');

        if (isset($data)) {
            $this->olddependencies[] = $data;
        }
    }

    protected function process_kilman_attempt($data) {
        // New structure will be completed in process_kilman_response. Nothing to do here any more.
        return true;
    }

    protected function process_kilman_response($data) {
        global $DB;

        $data = (object)$data;

        // Older versions of kilman used 'username' instead of 'userid'. If 'username' exists, change it to 'userid'.
        if (isset($data->username) && !isset($data->userid)) {
            $data->userid = $data->username;
        }

        $oldid = $data->id;
        $data->kilmanid = $this->get_new_parentid('kilman');
        $data->userid = $this->get_mappingid('user', $data->userid);

        // Insert the kilman_response record.
        $newitemid = $DB->insert_record('kilman_response', $data);
        $this->set_mapping('kilman_response', $oldid, $newitemid);
    }

    protected function process_kilman_response_bool($data) {
        global $DB;

        $data = (object)$data;
        $data->response_id = $this->get_new_parentid('kilman_response');
        $data->question_id = $this->get_mappingid('kilman_question', $data->question_id);

        // Insert the kilman_response_bool record.
        $DB->insert_record('kilman_response_bool', $data);
    }

    protected function process_kilman_response_date($data) {
        global $DB;

        $data = (object)$data;
        $data->response_id = $this->get_new_parentid('kilman_response');
        $data->question_id = $this->get_mappingid('kilman_question', $data->question_id);

        // Insert the kilman_response_date record.
        $DB->insert_record('kilman_response_date', $data);
    }

    protected function process_kilman_response_multiple($data) {
        global $DB;

        $data = (object)$data;
        $data->response_id = $this->get_new_parentid('kilman_response');
        $data->question_id = $this->get_mappingid('kilman_question', $data->question_id);
        $data->choice_id = $this->get_mappingid('kilman_quest_choice', $data->choice_id);

        // Insert the kilman_resp_multiple record.
        $DB->insert_record('kilman_resp_multiple', $data);
    }

    protected function process_kilman_response_other($data) {
        global $DB;

        $data = (object)$data;
        $data->response_id = $this->get_new_parentid('kilman_response');
        $data->question_id = $this->get_mappingid('kilman_question', $data->question_id);
        $data->choice_id = $this->get_mappingid('kilman_quest_choice', $data->choice_id);

        // Insert the kilman_response_other record.
        $DB->insert_record('kilman_response_other', $data);
    }

    protected function process_kilman_response_rank($data) {
        global $DB;

        $data = (object)$data;

        // Older versions of kilman used 'rank' instead of 'rankvalue'. If 'rank' exists, change it to 'rankvalue'.
        if (isset($data->rank) && !isset($data->rankvalue)) {
            $data->rankvalue = $data->rank;
        }

        $data->response_id = $this->get_new_parentid('kilman_response');
        $data->question_id = $this->get_mappingid('kilman_question', $data->question_id);
        $data->choice_id = $this->get_mappingid('kilman_quest_choice', $data->choice_id);

        // Insert the kilman_response_rank record.
        $DB->insert_record('kilman_response_rank', $data);
    }

    protected function process_kilman_response_single($data) {
        global $DB;

        $data = (object)$data;
        $data->response_id = $this->get_new_parentid('kilman_response');
        $data->question_id = $this->get_mappingid('kilman_question', $data->question_id);
        $data->choice_id = $this->get_mappingid('kilman_quest_choice', $data->choice_id);

        // Insert the kilman_resp_single record.
        $DB->insert_record('kilman_resp_single', $data);
    }

    protected function process_kilman_response_text($data) {
        global $DB;

        $data = (object)$data;
        $data->response_id = $this->get_new_parentid('kilman_response');
        $data->question_id = $this->get_mappingid('kilman_question', $data->question_id);

        // Insert the kilman_response_text record.
        $DB->insert_record('kilman_response_text', $data);
    }

    protected function after_execute() {
        global $DB;

        // Process any question dependencies after all questions and choices have already been processed to ensure we have all of
        // the new id's.

        // First, process any old system question dependencies into the new system.
        foreach ($this->olddependquestions as $newid => $olddependid) {
            $newrec = new stdClass();
            $newrec->questionid = $newid;
            $newrec->surveyid = $this->get_new_parentid('kilman_survey');
            $newrec->dependquestionid = $this->get_mappingid('kilman_question', $olddependid);
            // Only change mapping for RADIO and DROP question types, not for YESNO question.
            $dependqtype = $DB->get_field('kilman_question', 'type_id', ['id' => $newrec->dependquestionid]);
            if (($dependqtype !== false) && ($dependqtype != 1)) {
                $newrec->dependchoiceid = $this->get_mappingid('kilman_quest_choice',
                    $this->olddependchoices[$newid]);
            } else {
                $newrec->dependchoiceid = $this->olddependchoices[$newid];
            }
            $newrec->dependlogic = 1; // Set to "answer given", previously the only option.
            $newrec->dependandor = 'and'; // Not used previously.
            $DB->insert_record('kilman_dependency', $newrec);
        }

        // Next process all new system dependencies.
        foreach ($this->olddependencies as $data) {
            $data->dependquestionid = $this->get_mappingid('kilman_question', $data->dependquestionid);

            // Only change mapping for RADIO and DROP question types, not for YESNO question.
            $dependqtype = $DB->get_field('kilman_question', 'type_id', ['id' => $data->dependquestionid]);
            if (($dependqtype !== false) && ($dependqtype != 1)) {
                $data->dependchoiceid = $this->get_mappingid('kilman_quest_choice', $data->dependchoiceid);
            }
            $DB->insert_record('kilman_dependency', $data);
        }

        // Add kilman related files, no need to match by itemname (just internally handled context).
        $this->add_related_files('mod_kilman', 'intro', null);
        $this->add_related_files('mod_kilman', 'info', 'kilman_survey');
        $this->add_related_files('mod_kilman', 'thankbody', 'kilman_survey');
        $this->add_related_files('mod_kilman', 'feedbacknotes', 'kilman_survey');
        $this->add_related_files('mod_kilman', 'question', 'kilman_question');
        $this->add_related_files('mod_kilman', 'sectionheading', 'kilman_fb_sections');
        $this->add_related_files('mod_kilman', 'feedback', 'kilman_feedback');
    }
}