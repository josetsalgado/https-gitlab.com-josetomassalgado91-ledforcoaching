<?php
require_once($CFG->libdir . '/pdflib.php');
require_once($CFG->libdir . '/tcpdf/tcpdf.php');
class AnswerPdf extends TCPDF {
    private $nameQuiz;
    private $tema;

    function __construct($namequiz, $coursename, $tema) {
       $this->nameQuiz = $namequiz;
       $this->coursename = $coursename;
       $this->tema = $tema;
       parent::__construct();
    }

    public function Header() {
        global $OUTPUT;
        $image_file = '../pix/2019-09-13.png';
        $this->Image($image_file,  15, 5, 28, '', 'PNG', true, 'C', true, 300, '',false,false);

        // Set font
        $this->SetFont('helvetica', '', 10);
        
        $data = [
                'coursename' => $this->coursename,
                'nameQuiz' => $this->nameQuiz,
                'tema' => $this->tema
            ];
        $head = $OUTPUT->render_from_template('mod_kilman/headpdf', $data);
        $this->writeHTMLCell( $w = 0, $h = 0, $x = '', $y = '',
            $head, $border = 10, $ln = 1, $fill = 0,
            $reseth = false, $align = 'top', $autopadding = true);
    }

    public function Footer() {
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, $this->getAliasNumPage(), 0, false, 'R', 0, '', 0, false, 'T', 'M');
    }
}
