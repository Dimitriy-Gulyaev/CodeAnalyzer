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
        $question = $qa->get_question();
        $qtext = '';
        $qtext .= $question->format_questiontext($qa);
        $qtext .= html_writer::start_tag('div', array('class' => 'prompt'));

        $responsefieldname = $qa->get_qt_field_name('answer');
        $responsefieldid = 'id_' . $responsefieldname;
        $answerprompt = html_writer::tag('label',
            get_string('answerprompt', 'qtype_codeanalyzer'),
            array('class' => 'answerprompt', 'for' => $responsefieldid));
        $qtext .= $answerprompt;
        $qtext .= html_writer::end_tag('div');

        $currentanswer = $qa->get_last_qt_var('answer');

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
        return '';
    }

    /**
     * Return the HTML to display the sample answer, if given.
     * @param question_attempt $qa
     * @return string The html for displaying the sample answer.
     */
    public function correct_response(question_attempt $qa)
    {
        $question = $qa->get_question();
        return $question->answer ?? '';
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
