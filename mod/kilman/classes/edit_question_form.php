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
 * @author Mike Churchward & Joseph Rézeau
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package kilman
 */

namespace mod_kilman;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Class edit_question_form
 * @package mod_kilman
 * @property \MoodleQuickForm _form
 * @property array _customdata
 */
class edit_question_form extends \moodleform {

    public function definition() {
        // TODO - Find a way to not use globals. Maybe the base class allows more parameters to be passed?
        global $kilman, $question, $SESSION;

        // TODO - Is there a better way to do this without session global?
        // The 'sticky' required response value for further new questions.
        if (isset($SESSION->kilman->required) && !isset($question->qid)) {
            $question->required = $SESSION->kilman->required;
        }
        if (!isset($question->type_id)) {
            print_error('undefinedquestiontype', 'kilman');
        }

        // Each question can provide its own form elements to the provided form, or use the default ones.
        if (!$question->edit_form($this, $kilman)) {
            print_error("Question type had an unknown error in the edit_form method.");
        }
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // If this is a rate question.
        if ($data['type_id'] == QUESRATE) {
            if ($data['length'] < 2) {
                $errors["length"] = get_string('notenoughscaleitems', 'kilman');
            }
            // If this is a rate question with no duplicates option.
            if ($data['precise'] == 2 ) {
                $allchoices = $data['allchoices'];
                $allchoices = explode("\n", $allchoices);
                $nbvalues = 0;
                foreach ($allchoices as $choice) {
                    if ($choice && !preg_match("/^[0-9]{1,3}=/", $choice)) {
                            $nbvalues++;
                    }
                }
                if ($nbvalues < 2) {
                    $errors["allchoices"] = get_string('noduplicateschoiceserror', 'kilman');
                }
            }
        }

        return $errors;
    }

    /**
     * Magic method for getting the protected $_form MoodleQuickForm and $_customdata array properties.
     * @param string $name
     * @return mixed
     * @throws \coding_exception
     */
    public function __get($name) {
        if ($name == '_form') {
            return $this->_form;
        } else if ($name == '_customdata') {
            return $this->_customdata;
        } else {
            throw new \coding_exception($name.' is not a publicly accessible property of '.get_class($this));
        }
    }
}