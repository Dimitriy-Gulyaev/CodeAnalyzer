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
 * YOURQTYPENAME question renderer class.
 *
 * @package    qtype
 * @subpackage codeanalyzer
 * @copyright  Dmitriy Gulyaev
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

use qtype_codeanalyzer\constants;

/**
 * Generates the output for codeanalyzer questions.
 *
 * @copyright  Dmitriy Gulyaev
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_codeanalyzer_renderer extends qtype_renderer
{
    /**
     * Generate the display of the formulation part of the question. This is the
     * area that contains the question text, and the controls for students to
     * input their answers. Some question types also embed bits of feedback, for
     * example ticks and crosses, in this area.
     *
     * @param question_attempt $qa the question attempt to display.
     * @param question_display_options $options controls what should and should not be displayed.
     * @return string HTML fragment.
     */
    public function formulation_and_controls(question_attempt $qa, question_display_options $options)
    {
        global $CFG;
        global $USER;

        $question = $qa->get_question();
        $qid = $question->id;
        if (empty($USER->codeanalyzerquestionids)) {
            $USER->codeanalyzerquestionids = array($qid);  // Record in case of AJAX request.
        } else {
            array_push($USER->codeanalyzerquestionids, $qid); // Array of active qids.
        }
        $divid = "qtype_codeanalyzer_problemspec$qid";
        $qtext = '';
        if (isset($question->initialisationerrormessage) && $question->initialisationerrormessage) {
            $qtext .= "<div class='initialisationerror'>{$question->initialisationerrormessage}</div>";
        }
        $qtext .= $question->format_questiontext($qa);
        if (isset($params->programming_contest_problem) && $params->programming_contest_problem) {
            // Special case hack for programming contest problems.
            $qtext .= "<div id='$divid'></div>";
            $probspecfilename = isset($params->problem_spec_filename) ? $params->problem_spec_filename : '';
            $this->page->requires->js_call_amd('qtype_codeanalyzer/ajaxquestionloader',
                'loadQuestionText', array($qid, $divid, $probspecfilename));
        }

        $qtext .= html_writer::start_tag('div', array('class' => 'prompt'));

        $responsefieldname = $qa->get_qt_field_name('answer');
        $responsefieldid = 'id_' . $responsefieldname;
        $answerprompt = html_writer::tag('label',
            get_string('answerprompt', 'qtype_codeanalyzer'),
            array('class' => 'answerprompt', 'for' => $responsefieldid));
        $qtext .= $answerprompt;
        $behaviour = $qa->get_behaviour(true);

        $qtext .= html_writer::end_tag('div');

        $preload = isset($question->answerpreload) ? $question->answerpreload : '';
        if ($preload) {  // Add a reset button if preloaded text is non-empty.
            $qtext .= $this->reset_button($qa, $responsefieldid, $preload);
        }

        $currentanswer = $qa->get_last_qt_var('answer');
        if ($currentanswer === null || $currentanswer === '') {
            $currentanswer = $preload;
        } else {
            // Horrible horrible hack for horrible horrible browser feature
            // of ignoring a leading newline in a textarea. So we inject an
            // extra one to ensure that if the answer begins with a newline it
            // is preserved.
            $currentanswer = "\n" . $currentanswer;
        }

        $rows = constants::DEFAULT_NUM_ROWS;
        $cols = constants::DEFAULT_NUM_COLUMNS;
        $taattributes = $this->answerbox_attributes($responsefieldname, $rows, $cols, $options->readonly);
        $qtext .= html_writer::tag('textarea', s($currentanswer), $taattributes);

        if ($qa->get_state() == question_state::$invalid) {
            $qtext .= html_writer::nonempty_tag('div',
                $question->get_validation_error($qa->get_last_qt_data()),
                array('class' => 'validationerror'));
        }

        // Add file upload controls if attachments are allowed.
//        $files = '';
//        if ($question->attachments) {
//            if (empty($options->readonly)) {
//                $files = $this->files_input($qa, $question->attachments, $options);
//
//            } else {
//                $files = $this->files_read_only($qa, $options);
//            }
//            $qtext .= html_writer::tag('div', $files,
//                array('class' => 'form-filemanager', 'data-fieldtype' => 'filemanager'));
//            // Class and data-fieldtype are so behat can find the filemanager in both boost and clean themes.
//        }

        // Initialise any JavaScript UI
        $this->page->requires->js_call_amd('qtype_codeanalyzer/textareas', 'initQuestionTA',
            array($responsefieldid));

        return $qtext;
    }

    /**
     * Generate the specific feedback. This is feedback that varies according to
     * the response the student gave.
     * @param question_attempt $qa the question attempt to display.
     * @return string HTML fragment.
     */
    protected function specific_feedback(question_attempt $qa)
    {
        $toserialised = $qa->get_last_qt_var('_testoutcome');
        if (!$toserialised) { // Something broke?
            return '';
        }

        $q = $qa->get_question();
        $outcome = unserialize($toserialised);
        if ($outcome === false) {
            $outcome = new qtype_coderunner_testing_outcome(0, 0, false);
            $outcome->set_status(qtype_coderunner_testing_outcome::STATUS_UNSERIALIZE_FAILED);
        }
        $resultsclass = $this->results_class($outcome, $q->allornothing);

        $isoutputonly = $outcome->is_output_only();
        if ($isoutputonly) {
            $resultsclass .= ' outputonly';
        }
        $isprecheck = $outcome->is_precheck($qa);
        if ($isprecheck) {
            $resultsclass .= ' precheck';
        }

        $fb = '';

        if ($q->showsource) {
            $fb .= $this->make_source_code_div($outcome);
        }

        $fb .= html_writer::start_tag('div', array('class' => $resultsclass));
        if ($outcome->invalid()) {
            $fb .= html_writer::tag('h5', get_string('unserializefailed', 'qtype_codeanalyzer'),
                array('class' => 'run_failed_error'));
        } else if ($outcome->run_failed()) {
            $fb .= html_writer::tag('h5', get_string('run_failed', 'qtype_codeanalyzer'));;
            $fb .= html_writer::tag('p', s($outcome->errormessage),
                array('class' => 'run_failed_error'));
        } else if ($outcome->has_syntax_error()) {
            $fb .= html_writer::tag('h5', get_string('syntax_errors', 'qtype_codeanalyzer'));
            $fb .= html_writer::tag('pre', s($outcome->errormessage),
                array('class' => 'pre_syntax_error'));
        } else if ($outcome->combinator_error()) {
            $fb .= html_writer::tag('h5', get_string('badquestion', 'qtype_codeanalyzer'));
            $fb .= html_writer::tag('pre', s($outcome->errormessage),
                array('class' => 'pre_question_error'));

        } else {

            // The run was successful (i.e didn't crash, but may be wrong answer). Display results.
            if ($isprecheck) {
                $fb .= html_writer::tag('h3', get_string('precheck_only', 'qtype_codeanalyzer'));
            }

            if ($isprecheck && $q->precheck == constants::PRECHECK_EMPTY && !$outcome->iscombinatorgrader()) {
                $fb .= $this->empty_precheck_status($outcome);
            } else {
                $fb .= $this->build_results_table($outcome, $q);
            }
        }

        // Summarise the status of the response in a paragraph at the end.
        // Suppress when previous errors have already said enough or it's
        // an output only question.
        if (!$outcome->has_syntax_error() &&
            !$isprecheck &&
            !$isoutputonly &&
            !$outcome->is_ungradable() &&
            !$outcome->run_failed()) {

            $fb .= $this->build_feedback_summary($qa, $outcome);
        }
        $fb .= html_writer::end_tag('div');

        return $fb;
    }

    /**
     * Return the HTML to display the sample answer, if given.
     * @param question_attempt $qa
     * @return string The html for displaying the sample answer.
     */
    public function correct_response(question_attempt $qa)
    {
        $question = $qa->get_question();
        $answer = $question->answer;
        if (!$answer) {
            return '';
        } else {
            $answer = "\n" . $answer; // Hack to ensure leading new line not lost.
        }
        $fieldname = $qa->get_qt_field_name('sampleanswer');

        $heading = get_string('asolutionis', 'qtype_codeanalyzer');
        $html = html_writer::start_tag('div', array('class' => 'sample code'));
        $html .= html_writer::tag('h4', $heading);

        $html .= html_writer::end_tag('div');
        $fieldid = 'id_' . $fieldname;
        $this->page->requires->js_call_amd('qtype_codeanalyzer/textareas', 'initQuestionTA', array($fieldid));
        return $html;
    }

    // Return the text area attributes for an answer box.
    private function answerbox_attributes($fieldname, $rows, $cols, $readonly = false)
    {
        $attributes = array(
            'class' => 'codeanalyzer-answer edit_code',
            'name' => $fieldname,
            'id' => 'id_' . $fieldname,
            'spellcheck' => 'false',
            'rows' => $rows,
            'cols' => $cols
        );

        if ($readonly) {
            $attributes['readonly'] = '';
        }
        return $attributes;
    }
}
