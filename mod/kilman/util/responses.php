<?php

require_once('../../../config.php');
require_once($CFG->libdir . '/pdflib.php');
require_once($CFG->libdir . '/tcpdf/tcpdf.php');
global $COURSE, $DB, $OUTPUT;
require_login();
$PAGE->set_context(context_system::instance());
require_once('AnswerPdf.php');

$data = [
        "tableresponses" => array(
                "user" => "Jose Salgado",
                "acomodativo" => "3",
                "evasivo" => "4",
                "transador" => "7",
                "colaborativo" => "9",
                "competitivo" => "7",
            )
            ];
$htmlbody = $OUTPUT->render_from_template('mod_kilman/bodypdf', $data);
$pdf = new AnswerPdf("nombre del quiz","titulo 2", "tema");

// set document information
$pdf->SetCreator(PDF_CREATOR);

$tagvs = array(
    'div' => array(
        0 => array('h' => 0, 'n' => 0), 1 => array('h' => 0, 'n' => 0)
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
