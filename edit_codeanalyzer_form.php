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
 * Defines the editing form for the codeanalyzer question type.
 *
 * @package    qtype
 * @subpackage codeanalyzer
 * @copyright  Dmitriy Gulyaev
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

use qtype_codeanalyzer\constants;

/**
 * codeanalyzer question editing form definition.
 *
 * @copyright  Dmitriy Gulyaev
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_codeanalyzer_edit_form extends question_edit_form
{
    const DEFAULT_NUM_ROWS = 25;  // Answer box rows.

    public function qtype()
    {
        return 'codeanalyzer';
    }

    // Defines the bit of the CodeAnalyzer question edit form after the "General"
    // section and before the footer stuff.
    public function definition_inner($mform)
    {
        global $CFG;
        $mform->addElement('header', 'answerhdr',
            get_string('answer', 'qtype_codeanalyzer'), '');
        $mform->setExpanded('answerhdr', 1);

        $attributes = array(
            'rows' => 9,
            'class' => 'answer edit_code',
            'data-params' => $this->get_merged_ui_params());
        $mform->addElement('textarea', 'answer',
            get_string('answer', 'qtype_codeanalyzer'),
            $attributes);
        // Add a file attachment upload panel (disabled if attachments not allowed).
        $options = $this->fileoptions;
        $options['subdirs'] = false;
        $mform->addElement('filemanager', 'sampleanswerattachments',
            get_string('sampleanswerattachments', 'qtype_codeanalyzer'), null,
            $options);
        $mform->addHelpButton('sampleanswerattachments', 'sampleanswerattachments', 'qtype_codeanalyzer');
        // Unless behat is running, hide the attachments file picker.
        // behat barfs if it's hidden.
        if ($CFG->prefix !== "b_") {
            $method = method_exists($mform, 'hideIf') ? 'hideIf' : 'disabledIf';
            $mform->$method('sampleanswerattachments', 'attachments', 'eq', 0);
        }
        $mform->addElement('advcheckbox', 'validateonsave', null,
            get_string('validateonsave', 'qtype_codeanalyzer'));
        $mform->setDefault('validateonsave', true);
        $mform->addHelpButton('answer', 'answer', 'qtype_codeanalyzer');
    }

    public function data_preprocessing($question)
    {
        // Preprocess the question data to be loaded into the form. Called by set_data after
        // standard stuff all loaded.
        // TODO - consider how much of this can be dispensed with just by
        // calling question_bank::loadquestion($question->id).
        global $COURSE;

        if (!isset($question->brokenquestionmessage)) {
            $question->brokenquestionmessage = '';
        }
        if (isset($question->options->testcases)) { // Reloading a saved question?

            // Firstly check if we're editing a question with a missing prototype.
            // Set the broken_question message if so.
            $q = $this->make_question_from_form_data($question);
            if ($q->prototype === null) {
                $question->brokenquestionmessage = get_string(
                    'missingprototype', 'qtype_codeanalyzer', array('crtype' => $question->codeanalyzertype));
            }

            // Record the prototype for subsequent use.
            $question->prototype = $q->prototype;

            // Next flatten all the question->options down into the question itself.
            $question->testcode = array();
            $question->expected = array();
            $question->useasexample = array();
            $question->display = array();
            $question->extra = array();
            $question->hiderestifail = array();

            foreach ($question->options->testcases as $tc) {
                $question->testcode[] = $this->newline_hack($tc->testcode);
                $question->testtype[] = $tc->testtype;
                $question->stdin[] = $this->newline_hack($tc->stdin);
                $question->expected[] = $this->newline_hack($tc->expected);
                $question->extra[] = $this->newline_hack($tc->extra);
                $question->useasexample[] = $tc->useasexample;
                $question->display[] = $tc->display;
                $question->hiderestiffail[] = $tc->hiderestiffail;
                $question->mark[] = sprintf("%.3f", $tc->mark);
            }

            // The customise field isn't listed as an extra-question-field so also
            // needs to be copied down from the options here.
            $question->customise = $question->options->customise;

            // Save the prototypetype so can see if it changed on post-back.
            $question->saved_prototype_type = $question->prototypetype;
            $question->courseid = $COURSE->id;

            // Load the type-name if this is a prototype, else make it blank.
            if ($question->prototypetype != 0) {
                $question->typename = $question->codeanalyzertype;
            } else {
                $question->typename = '';
            }

            // Convert raw newline chars in testsplitterre into 2-char form
            // so they can be edited in a one-line entry field.
            if (isset($question->testsplitterre)) {
                $question->testsplitterre = str_replace("\n", '\n', $question->testsplitterre);
            }

            // Legacy questions may have a question.penalty but no penalty regime.
            // Dummy up a penalty regime from the question.penalty in such cases.
            if (empty($question->penaltyregime)) {
                if (empty($question->penalty) || $question->penalty == 0) {
                    $question->penaltyregime = '0';
                } else {
                    if (intval(100 * $question->penalty) == 100 * $question->penalty) {
                        $decdigits = 0;
                    } else {
                        $decdigits = 1;  // For nasty fractions like 0.33333333.
                    }
                    $penaltypercent = number_format($question->penalty * 100, $decdigits);
                    $penaltypercent2 = number_format($question->penalty * 200, $decdigits);
                    $question->penaltyregime = $penaltypercent . ', ' . $penaltypercent2 . ', ...';
                }
            }
        } else {
            // This is a new question.
            $question->penaltyregime = get_config('qtype_codeanalyzer', 'default_penalty_regime');
        }

        foreach (array('datafiles' => 'datafile',
                     'sampleanswerattachments' => 'samplefile') as $fileset => $filearea) {
            $draftid = file_get_submitted_draft_itemid($fileset);
            $options = $this->fileoptions;
            $options['subdirs'] = false;

            file_prepare_draft_area($draftid, $this->context->id,
                'qtype_codeanalyzer', $filearea,
                empty($question->id) ? null : (int)$question->id,
                $options);
            $question->$fileset = $draftid; // File manager needs this (and we need it when saving).
        }
        return $question;
    }


    private function make_question_from_form_data($data)
    {
        // Construct a question object containing all the fields from $data.
        // Used in data pre-processing and when validating a question.
        global $DB;
        $question = new qtype_codeanalyzer_question();
        foreach ($data as $key => $value) {
            if ($key === 'questiontext' || $key === 'generalfeedback') {
                // Question text and general feedback are associative arrays.
                $question->$key = $value['text'];
            } else {
                $question->$key = $value;
            }
        }
        $question->isnew = true;
        $question->supportfilemanagerdraftid = $this->get_file_manager('datafiles');

        // Clean the question object, get inherited fields.
        $qtype = new qtype_codeanalyzer();
        $qtype->clean_question_form($question, true);
        $questiontype = $question->codeanalyzertype;
        list($category) = explode(',', $question->category);
        $contextid = $DB->get_field('question_categories', 'contextid', array('id' => $category));
        $question->contextid = $contextid;
        $context = context::instance_by_id($contextid, IGNORE_MISSING);
        $question->prototype = $qtype->get_prototype($questiontype, $context);
        $qtype->set_inherited_fields($question, $question->prototype);
        return $question;
    }


    // Returns the Json for the merged template parameters.
    // It is assumed that this function is called only when a question is
    // initially loaded from the DB or a new question is being created,
    // so that it can use the question bank's load_question method to get
    // a valid question from the DB rather than the stdClass 'question'
    // provided to the form at initialisation.
    private function get_merged_ui_params()
    {
        if (isset($this->cacheduiparamsjson)) {
            return $this->cacheduiparamsjson;
        }
        $q = $this->question;
        if (isset($q->options)) {
            // Editing an existing question.
            try {
                $qfromdb = question_bank::load_question($q->id);
                $seed = 1;
                $qfromdb->evaluate_question_for_display($seed, null);
                if ($qfromdb->mergeduiparameters) {
                    $json = json_encode($qfromdb->mergeduiparameters);
                } else {
                    $json = '{}';
                }
            } catch (Throwable $e) {
                $json = '{}';  // This shouldn't happen, but has been known to.
                $q->brokenquestionmessage = get_string('corruptuiparams', 'qtype_codeanalyzer');
            };
            $this->cacheduiparamsjson = $json;
            return $json;
        } else {
            return '{}';
        }
    }


    // A horrible hack for a horrible browser "feature".
    // Inserts a newline at the start of a text string that's going to be
    // displayed at the start of a <textarea> element, because all browsers
    // strip a leading newline. If there's one there, we need to keep it, so
    // the extra one ensures we do. If there isn't one there, this one gets
    // ignored anyway.
    private function newline_hack($s)
    {
        return "\n" . $s;
    }

    // A list of the allowed values of the DB 'display' field for each testcase.
    protected function displayoptions()
    {
        return array('SHOW', 'HIDE', 'HIDE_IF_FAIL', 'HIDE_IF_SUCCEED');
    }

    // Find the id of the filemanager element draftid with the given name.
    private function get_file_manager($filemanagername)
    {
        $mform = $this->_form;
        $draftid = null;
        foreach ($mform->_elements as $element) {
            if ($element->_type == 'filemanager'
                && $element->_attributes['name'] === $filemanagername) {
                $draftid = (int)$element->getValue();
            }
        }
        return $draftid;
    }
}
