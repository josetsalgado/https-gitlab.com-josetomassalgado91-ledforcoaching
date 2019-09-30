<?php

require_once('../../../config.php');
require_once($CFG->libdir . '/pdflib.php');
require_once($CFG->libdir . '/tcpdf/tcpdf.php');
require_once('AnswerPdf.php');
require_once($CFG->dirroot . '/mod/kilman/kilman.class.php');
require_once($CFG->dirroot . '/mod/kilman/locallib.php');

global $COURSE, $DB, $OUTPUT;

require_login();
$PAGE->set_context(context_system::instance());

$instance = required_param('instance', PARAM_INT);   // kilman ID.
$userid = optional_param('user', $USER->id, PARAM_INT);
$rid = optional_param('rid', null, PARAM_INT);
$byresponse = optional_param('byresponse', 0, PARAM_INT);
$action = optional_param('action', 'summary', PARAM_ALPHA);
$currentgroupid = optional_param('group', 0, PARAM_INT);
$i = 0;


if (!$kilman = $DB->get_record("kilman", array("id" => $instance))) {
    print_error('incorrectkilman', 'kilman');
}
if (!$course = $DB->get_record("course", array("id" => $kilman->course))) {
    print_error('coursemisconf');
}
if (!$cm = get_coursemodule_from_instance("kilman", $kilman->id, $course->id)) {
    print_error('invalidcoursemodule');
}

require_course_login($course, true, $cm);

$kilman = new kilman(0, $kilman, $course, $cm);

// Add renderer and page objects to the kilman object for display use.
$kilman->add_renderer($PAGE->get_renderer('mod_kilman'));
$kilman->add_page(new \mod_kilman\output\reportpage());

$sid = $kilman->survey->id;

$context = context_module::instance($cm->id);
$kilman->canviewallgroups = has_capability('moodle/site:accessallgroups', $context);
// Should never happen, unless called directly by a snoop...
if (!has_capability('mod/kilman:readownresponses', $context) || $userid != $USER->id) {
    print_error('Permission denied');
}
$iscurrentgroupmember = false;

$iscurrentgroupmember = groups_is_member($currentgroupid, $userid);

$resps = $kilman->get_responses($userid);
$respsuser = $kilman->get_responses($userid);
$rids = array_keys($resps);

if (count($resps) > 1) {
    $userresps = $resps;
    $kilman->survey_results_navbar_student($rid, $userid, $instance, $userresps);
}
$resps = array();
// Determine here which "global" responses should get displayed for comparison with current user.
// Current user is viewing his own group's results.
if (isset($currentgroupresps)) {
    $resps = $currentgroupresps;
}

// Current user is viewing another group's results so we must add their own results to that group's results.

if (!$iscurrentgroupmember) {
    $resps += $respsuser;
}
// No groups.
if ($groupmode == 0 || $currentgroupid == 0) {
    $resps = $respsallparticipants;
}
$compare = true;

$table2 = $kilman->response_analysispdf($rid, $resps, $compare, $iscurrentgroupmember, false, $currentgroupid);

$data = new stdClass();
$kilman->response_import_allpdf($rid, $data);
//$responses = $kilman->response_select($rid, 'content');
$responsesuser = array();
foreach ($kilman->questions as $question) {
            if ($question->type_id < QUESPAGEBREAK) {
                $i++;
            }
            if ($question->type_id != QUESPAGEBREAK) {
//                $responsesuser[] = array(
//                    "data" => $kilman->renderer->response_output($question, $data, $i)
//                );
                $responsesuser[] = array("data" => $kilman->renderer->response_outputpdf($question, $data, $i));
            }
//    echo "<br>----------------------------------------";
//    echo print_r($responsesuser, true);
//    echo "<br>----------------------------------------";
        }
//        
//    echo "<br>----------------------------------------";
//    echo print_r($responsesuser, true);
//    echo "<br>----------------------------------------";
////
//////
////        
//        exit;
$data = [
    "tableresponses" => array(
        "user" => $table2["data"][0],
        "acomodativo" => $table2["data"][1],
        "evasivo" => $table2["data"][2],
        "transador" => $table2["data"][3],
        "colaborativo" => $table2["data"][4],
        "competitivo" => $table2["data"][5],
    ),
    "responsesuser" => $responsesuser
];
$htmlbody = $OUTPUT->render_from_template('mod_kilman/bodypdf', $data);


//echo $htmlbody;

$pdf = new AnswerPdf("nombre del quiz", "titulo 2", "tema");

// set document information
$pdf->SetCreator(PDF_CREATOR);

$tagvs = array(
    'div' => array(
        0 => array('h' => 0, 'n' => 0), 
        1 => array('h' => 0, 'n' => 0),
        2 => array('h' => 0, 'n' => 0),
        3 => array('h' => 0, 'n' => 0),
        4 => array('h' => 0, 'n' => 0),
        5 => array('h' => 0, 'n' => 0),
        6 => array('h' => 0, 'r' => 0)
    ),
    'ul' => array(
        0 => array('h' => 0, 'n' => 0), 1 => array('h' => 0, 'n' => 0)
    ),
    'hr' => array(
        0 => array('h' => 0, 'n' => 8), 1 => array('h' => 0, 'n' => 0)
    ),
    'ol' => array(
        0 => array('h' => 0, 'n' => 0), 1 => array('h' => 0, 'n' => 0)
    )
);

$pdf->setHtmlVSpace($tagvs);

// set header and footer fonts
$pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
$pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

// set default monospaced font
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

// set margins
$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
$pdf->SetHeaderMargin(8);
$pdf->SetTopMargin(50);
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

// set auto page breaks
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

// set font
$pdf->SetFont('helvetica', '', 10);

// add a page
$pdf->AddPage();

// output the HTML content
$pdf->writeHTML($htmlbody, true, 0, true, 0);

// reset pointer to the last page
$pdf->lastPage();

$pdf->Output('Cuestinario.pdf');
