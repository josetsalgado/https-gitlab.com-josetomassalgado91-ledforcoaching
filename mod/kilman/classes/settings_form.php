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

namespace mod_kilman;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class settings_form extends \moodleform {

    public function definition() {
        global $kilman, $kilmanrealms;

        $mform    =& $this->_form;

        $mform->addElement('header', 'contenthdr', get_string('contentoptions', 'kilman'));

        $capabilities = kilman_load_capabilities($kilman->cm->id);
        if (!$capabilities->createtemplates) {
            unset($kilmanrealms['template']);
        }
        if (!$capabilities->createpublic) {
            unset($kilmanrealms['public']);
        }
        if (isset($kilmanrealms['public']) || isset($kilmanrealms['template'])) {
            $mform->addElement('select', 'realm', get_string('realm', 'kilman'), $kilmanrealms);
            $mform->setDefault('realm', $kilman->survey->realm);
            $mform->addHelpButton('realm', 'realm', 'kilman');
        } else {
            $mform->addElement('hidden', 'realm', 'private');
        }
        $mform->setType('realm', PARAM_RAW);

        $mform->addElement('text', 'title', get_string('title', 'kilman'), array('size' => '60'));
        $mform->setDefault('title', $kilman->survey->title);
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', null, 'required', null, 'client');
        $mform->addHelpButton('title', 'title', 'kilman');

        $mform->addElement('text', 'subtitle', get_string('subtitle', 'kilman'), array('size' => '60'));
        $mform->setDefault('subtitle', $kilman->survey->subtitle);
        $mform->setType('subtitle', PARAM_TEXT);
        $mform->addHelpButton('subtitle', 'subtitle', 'kilman');

        $editoroptions = array('maxfiles' => EDITOR_UNLIMITED_FILES, 'trusttext' => true);
        $mform->addElement('editor', 'info', get_string('additionalinfo', 'kilman'), null, $editoroptions);
        $mform->setDefault('info', $kilman->survey->info);
        $mform->setType('info', PARAM_RAW);
        $mform->addHelpButton('info', 'additionalinfo', 'kilman');

        $mform->addElement('header', 'submithdr', get_string('submitoptions', 'kilman'));

        $mform->addElement('text', 'thanks_page', get_string('url', 'kilman'), array('size' => '60'));
        $mform->setType('thanks_page', PARAM_TEXT);
        $mform->setDefault('thanks_page', $kilman->survey->thanks_page);
        $mform->addHelpButton('thanks_page', 'url', 'kilman');

        $mform->addElement('static', 'confmes', get_string('confalts', 'kilman'));
        $mform->addHelpButton('confmes', 'confpage', 'kilman');

        $mform->addElement('text', 'thank_head', get_string('headingtext', 'kilman'), array('size' => '30'));
        $mform->setType('thank_head', PARAM_TEXT);
        $mform->setDefault('thank_head', $kilman->survey->thank_head);

        $editoroptions = array('maxfiles' => EDITOR_UNLIMITED_FILES, 'trusttext' => true);
        $mform->addElement('editor', 'thank_body', get_string('bodytext', 'kilman'), null, $editoroptions);
        $mform->setType('thank_body', PARAM_RAW);
        $mform->setDefault('thank_body', $kilman->survey->thank_body);

        $mform->addElement('text', 'email', get_string('email', 'kilman'), array('size' => '75'));
        $mform->setType('email', PARAM_TEXT);
        $mform->setDefault('email', $kilman->survey->email);
        $mform->addHelpButton('email', 'sendemail', 'kilman');

        // Hidden fields.
        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'sid', 0);
        $mform->setType('sid', PARAM_INT);
        $mform->addElement('hidden', 'name', '');
        $mform->setType('name', PARAM_TEXT);
        $mform->addElement('hidden', 'courseid', '');
        $mform->setType('courseid', PARAM_RAW);

        // Buttons.

        $submitlabel = get_string('savechangesanddisplay');
        $submit2label = get_string('savechangesandreturntocourse');
        $mform = $this->_form;

        // Elements in a row need a group.
        $buttonarray = array();
        $buttonarray[] = &$mform->createElement('submit', 'submitbutton2', $submit2label);
        $buttonarray[] = &$mform->createElement('submit', 'submitbutton', $submitlabel);
        $buttonarray[] = &$mform->createElement('cancel');

        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
        $mform->setType('buttonar', PARAM_RAW);
        $mform->closeHeaderBefore('buttonar');

    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        return $errors;
    }
}