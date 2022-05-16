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
 * YOURQTYPENAME question definition class.
 *
 * @package    qtype
 * @subpackage codeanalyzer
 * @copyright  Dmitriy Gulyaev
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

use qtype_codeanalyzer\constants;

/**
 *This holds the definition of a particular question of this type.
 *If you load three questions from the question bank, then you will get three instances of
 *that class. This class is not just the question definition, it can also track the current
 *state of a question as a student attempts it through a question_attempt instance.
 */


/**
 * Represents a codeanalyzer question.
 *
 * @copyright  Dmitriy Gulyaev
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_codeanalyzer_question extends question_graded_automatically
{

    public function get_expected_data()
    {
        $expecteddata = array('answer' => PARAM_RAW,
            'language' => PARAM_NOTAGS);
        if ($this->attachments != 0) {
            $expecteddata['attachments'] = question_attempt::PARAM_FILES;
        }
        return $expecteddata;
    }

    /**
     * Start a new attempt at this question, storing any information that will
     * be needed later in the step. It is retrieved and applied by
     * apply_attempt_state.
     *
     * For CodeAnalyzer questions we pre-process the template parameters for any
     * randomisation required, storing the processed template parameters in
     * the question_attempt_step.
     *
     * @param question_attempt_step The first step of the {@link question_attempt}
     *      being started. Can be used to store state. Is set to null during
     *      question validation, and must then be ignored.
     * @param int $varant which variant of this question to start. Will be between
     *      1 and {@link get_num_variants()} inclusive.
     */
    public function start_attempt(question_attempt_step $step = null, $variant = null)
    {
        global $DB, $USER;
        if ($step !== null) {
            parent::start_attempt($step, $variant);
            $userid = $step->get_user_id();
            $this->student = $DB->get_record('user', array('id' => $userid));
            $step->set_qt_var('_STUDENT', serialize($this->student));
        } else {  // Validation, so just use the global $USER as student.
            $this->student = $USER;
        }

        $seed = mt_rand();
        if ($step !== null) {
            $step->set_qt_var('_mtrandseed', $seed);
        }
    }

    // Retrieve the saved random number seed and reconstruct the template
    // parameters to the state they were left after start_attempt was called.
    // Also twig expand the rest of the question fields if $this->twigall is true.
    public function apply_attempt_state(question_attempt_step $step)
    {
        parent::apply_attempt_state($step);
        $this->student = unserialize($step->get_qt_var('_STUDENT'));
        $seed = $step->get_qt_var('_mtrandseed');
        if ($seed === null) {
            // Rendering a question that was begun before randomisation
            // was introduced into the code.
            $seed = mt_rand();
        }
    }

    /**
     * @return summary
     * A string that summarises how the user responded.
     * It is written to responsesummary field of
     * the question_attempts table, and used in the
     * the quiz responses report
     * */
    public function summarise_response(array $response)
    {
        if (isset($response['answer'])) {
            return $response['answer'];
        } else {
            return null;
        }
    }

    public function is_complete_response(array $response)
    {
        return $this->is_gradable_response($response);
    }

    /**
     * In situations where is_gradable_response() returns false, this method
     * should generate a description of what the problem is.
     * @return string the message.
     */
    public function get_validation_error(array $response)
    {
        $error = $this->validate_response($response);
        if ($error) {
            return $error;
        } else {
            return get_string('unknownerror', 'qtype_codeanalyzer');
        }
    }

    /** This function is used by the question engine to prevent regrading of
     *  unchanged submissions.
     *
     * @param array $prevresponse
     * @param array $newresponse
     * @return boolean
     */
    public function is_same_response(array $prevresponse, array $newresponse)
    {
        $sameanswer = question_utils::arrays_same_at_key_missing_is_blank(
                $prevresponse, $newresponse, 'answer') &&
            question_utils::arrays_same_at_key_missing_is_blank(
                $prevresponse, $newresponse, 'language');
        $attachments1 = $this->get_attached_files($prevresponse);
        $attachments2 = $this->get_attached_files($newresponse);
        $sameattachments = $attachments1 === $attachments2;
        return $sameanswer && $sameattachments;
    }

    /**
     * @return question_answer an answer that
     * contains the a response that would get full marks.
     * used in preview mode. If this doesn't return a
     * correct value the button labeled "Fill in correct response"
     * in the preview form will not work. This value gets written
     * into the rightanswer field of the question_attempts table
     * when a quiz containing this question starts.
     */
    public function get_correct_response()
    {
        return $this->get_correct_answer();
    }


    public function check_file_access($qa, $options, $component, $filearea, $args, $forcedownload)
    {
        if ($component == 'question' && $filearea == 'response_attachments') {
            // Response attachments visible if the question has them.
            return $this->attachments != 0;
        } else {
            return parent::check_file_access($qa, $options, $component,
                $filearea, $args, $forcedownload);
        }
    }

    /**
     * Grade the given student's response.
     * This implementation assumes a modified behaviour that will accept a
     * third array element in its response, containing data to be cached and
     * served up again in the response on subsequent calls.
     * @param array $response the qt_data for the current pending step. The
     * main relevant keys are '_testoutcome', which is a cached copy of the
     * grading outcome if this response has already been graded and 'answer'
     * (the student's answer) otherwise. Also present are 'numchecks',
     * 'numprechecks' and 'fraction' which relate to the current (pending) step and
     * the history of prior submissions.
     * @param bool $isprecheck true iff this grading is occurring because the
     * student clicked the precheck button
     * @param int $prevtries how many previous tries have been recorded for
     * this question, not including the current one.
     * @return 3-element array of the mark (0 - 1), the question_state (
     * gradedright, gradedwrong, gradedpartial, invalid) and the full
     * qtype_codeanalyzer_testing_outcome object to be cached. The invalid
     * state is used when a sandbox error occurs.
     * @throws coding_exception
     */
    public function grade_response(array $response, bool $isprecheck = false, int $prevtries = 0)
    {
        if ($isprecheck && empty($this->precheck)) {
            throw new coding_exception("Unexpected precheck");
        }
        $language = empty($response['language']) ? '' : $response['language'];
        $gradingreqd = true;
        if (!empty($response['_testoutcome'])) {
            $testoutcomeserial = $response['_testoutcome'];
            $testoutcome = unserialize($testoutcomeserial);
            if ($testoutcome instanceof qtype_coderunner_testing_outcome  // Ignore legacy-format outcomes.
                && $testoutcome->isprecheck == $isprecheck) {
                $gradingreqd = false;  // Already graded and with same precheck state.
            }
        }
        if ($gradingreqd) {
            // We haven't already graded this submission or we graded it with
            // a different precheck setting. Get the code and the attachments
            // from the response. The attachments is an array with keys being
            // filenames and values being file contents.
            $code = $response['answer'];
            $attachments = $this->get_attached_files($response);
            $runner = new qtype_coderunner_jobrunner();
            $this->stepinfo = self::step_info($response);
            if (isset($response['graderstate'])) {
                $this->stepinfo->graderstate = $response['graderstate'];
            } else {
                $this->stepinfo->graderstate = '';
            }
//            $testoutcome = $runner->run_tests($this, $code, $attachments, $testcases, $isprecheck, $language);
            $testoutcomeserial = serialize($testoutcome);
        }

        $datatocache = array('_testoutcome' => $testoutcomeserial);
        if ($testoutcome->run_failed()) {
            return array(0, question_state::$invalid, $datatocache);
        } else if ($testoutcome->all_correct()) {
            return array(1, question_state::$gradedright, $datatocache);
        } else if ($this->allornothing &&
            !($this->grader === 'TemplateGrader' && $this->iscombinatortemplate)) {
            return array(0, question_state::$gradedwrong, $datatocache);
        } else {
            // Allow partial marks if not allornothing or if it's a combinator template grader.
            return array($testoutcome->mark_as_fraction(),
                question_state::$gradedpartial, $datatocache);
        }
    }


    public function get_correct_answer()
    {
        // Return the sample answer, if supplied.
        if (!isset($this->answer)) {
            return null;
        } else {
            $answer = array('answer' => $this->answer);
            // For multilanguage questions we also need to specify the language.
            // Use the answer_language template parameter value if given, otherwise
            // run with the default.
            $params = $this->parameters;
            if (!empty($params->answer_language)) {
                $answer['language'] = $params->answer_language;
            } else if (!empty($this->acelang) && strpos($this->acelang, ',') !== false) {
                list($langs, $defaultlang) = qtype_coderunner_util::extract_languages($this->acelang);
                $default = empty($defaultlang) ? $langs[0] : $defaultlang;
                $answer['language'] = $default;
            }
            return $answer;
        }
    }


    // Return a map from filename to file contents for all the attached files
    // in the given response.
    private function get_attached_files($response)
    {
        $attachments = array();
        if (array_key_exists('attachments', $response) && $response['attachments']) {
            $files = $response['attachments']->get_files();
            foreach ($files as $file) {
                $attachments[$file->get_filename()] = $file->get_content();
            }
        }
        return $attachments;
    }


    /** Pulls out the step information in the response, added by the CodeRunner
     * /*  custom behaviour, for use by the question author in issuing feedback.
     *
     * @param type $response The usual response array enhanced by the addition of
     * numchecks, numprechecks and fraction values relating to the current step.
     * @return stdClass object with the numchecks, numprechecks and fraction
     * attributes.
     */

    private static function step_info($response)
    {
        $stepinfo = new stdClass();
        foreach (['numchecks', 'numprechecks', 'fraction', 'preferredbehaviour'] as $key) {
            $value = isset($response[$key]) ? $response[$key] : 0;
            $stepinfo->$key = $value;
        }
        $stepinfo->codeanalyzerversion = get_config('qtype_codeanalyzer')->version;
        return $stepinfo;
    }


    // Return an empty testcase - an artifical testcase with all fields
    // empty or zero except for a mark of 1.
    private function empty_testcase()
    {
        return (object)array(
            'testtype' => 0,
            'testcode' => '',
            'stdin' => '',
            'expected' => '',
            'extra' => '',
            'display' => 'HIDE',
            'useasexample' => 0,
            'hiderestiffail' => 0,
            'mark' => 1
        );
    }

    // Return an array of all the use_as_example testcases.
    public function example_testcases()
    {
        return array_filter($this->testcases, function ($tc) {
            return $tc->useasexample;
        });
    }

    /**
     * Load the prototype for this question and store in $this->prototype
     */
    public function get_prototype()
    {
        if (isset($this->prototype)) {
            return;  // Nothing to do.
        }
        if ($this->prototypetype == 0) {
            $context = qtype_codeanalyzer::question_context($this);
            $this->prototype = qtype_codeanalyzer::get_prototype($this->codeanalyzertype, $context);
        } else {
            $this->prototype = null;
        }
    }

    // Twig expand all text fields of the question except the templateparam field
    // (which should have been expanded when the question was started) and
    // the template itself.
    // Done only if the Twig All checkbox is checked.
    private function twig_all()
    {
        // Twig expand everything in a context that includes the template
        // parameters and the STUDENT and QUESTION objects.
        $this->questiontext = $this->twig_expand($this->questiontext);
        $this->generalfeedback = $this->twig_expand($this->generalfeedback);
        $this->answer = $this->twig_expand($this->answer);
        $this->answerpreload = $this->twig_expand($this->answerpreload);
        $this->globalextra = $this->twig_expand($this->globalextra);
    }

    /**
     * Return Twig-expanded version of the given text.
     * Twig environment includes the question itself (this) and, if template
     * parameters are to be hoisted, the (key, value) pairs in $this->parameters.
     * @param string $text Text to be twig expanded.
     */
    public function twig_expand($text, $context = array())
    {
        if (empty(trim($text))) {
            return $text;
        } else {
            $context['QUESTION'] = $this->sanitised_clone_of_this();
            if ($this->hoisttemplateparams) {
                foreach ($this->parameters as $key => $value) {
                    $context[$key] = $value;
                }
            }
            return qtype_coderunner_twig::render($text, $this->student, $context);
        }
    }

    // Return a stdObject pseudo-clone of this question with only the fields
    // documented in the README.md, for use in Twig expansion.
    // HACK ALERT - the field uiparameters exported to the Twig context is
    // actually the mergeduiparameters field, just as the parameters field
    // is the merged template parameters. [Where merging refers to the combining
    // of the prototype and the question].
    protected function sanitised_clone_of_this()
    {
        $clone = new stdClass();
        $fieldsrequired = array('id', 'name', 'questiontext', 'generalfeedback',
            'generalfeedbackformat', 'testcases',
            'answer', 'answerpreload', 'language', 'globalextra', 'useace', 'sandbox',
            'grader', 'cputimelimitsecs', 'memlimitmb', 'sandboxparams',
            'parameters', 'resultcolumns', 'allornothing', 'precheck',
            'hidecheck', 'penaltyregime', 'iscombinatortemplate',
            'allowmultiplestdins', 'acelang', 'uiplugin', 'attachments',
            'attachmentsrequired', 'displayfeedback', 'stepinfo');
        foreach ($fieldsrequired as $field) {
            if (isset($this->$field)) {
                $clone->$field = $this->$field;
            } else {
                $clone->$field = null;
            }
        }
        $clone->questionid = $this->id; // Legacy support.
        return $clone;
    }

    // Return the support files for this question, namely all the files
    // uploaded with this question itself plus all the files uploaded with the
    // prototype. This does not include files attached to the answer.
    // Returns an associative array mapping filenames to filecontents.
    public function get_files()
    {
        if ($this->prototypetype != 0) { // Is this a prototype question?
            $files = array(); // Don't load the files twice.
        } else {
            // Load any files from the prototype.
            $this->get_prototype();
            $files = self::get_support_files($this->prototype);
        }
        $files = array_merge($files, self::get_support_files($this));  // Add in files for this question.
        return $files;
    }

    /**
     *  Return an associative array mapping filename to file contents
     *  for all the support files for the given question.
     *  The sample answer files are not included in the return value.
     */
    private static function get_support_files($question)
    {
        global $DB, $USER;

        // If not given in the question object get the contextid from the database.
        if (isset($question->contextid)) {
            $contextid = $question->contextid;
        } else {
            $context = qtype_codeanalyzer::question_context($question);
            $contextid = $context->id;
        }

        $fs = get_file_storage();
        $filemap = array();

        if (isset($question->supportfilemanagerdraftid)) {
            // If we're just validating a question, get files from user draft area.
            $draftid = $question->supportfilemanagerdraftid;
            $context = context_user::instance($USER->id);
            $files = $fs->get_area_files($context->id, 'user', 'draft', $draftid, '', false);
        } else {
            // Otherwise, get the stored support files for this question (not
            // the sample answer files).
            $files = $fs->get_area_files($contextid, 'qtype_codeanalyzer', 'datafile', $question->id);
        }

        foreach ($files as $f) {
            $name = $f->get_filename();
            if ($name !== '.') {
                $filemap[$f->get_filename()] = $f->get_content();
            }
        }
        return $filemap;
    }

    // Return an instance of the sandbox to be used to run code for this question.
    public function get_sandbox()
    {
        // TODO place for interactions with server, check original
    }

    public function validate_response(array $response)
    {
        // Check the response and return a validation error message if it's
        // faulty or an empty string otherwise.

        // First check the attachments.
        $hasattachments = array_key_exists('attachments', $response)
            && $response['attachments'] instanceof question_response_files;
        if ($hasattachments) {
            $attachmentfiles = $response['attachments']->get_files();
            $attachcount = count($attachmentfiles);
            // Check the filetypes.
            $invalidfiles = array();
            $regex = $this->filenamesregex;
            $supportfiles = $this->get_files();
            foreach ($attachmentfiles as $file) {
                $filename = $file->get_filename();
                if (!$this->is_valid_filename($filename, $regex, $supportfiles)) {
                    $invalidfiles[] = $filename;
                }
            }

            if (count($invalidfiles) > 0) {
                $badfilelist = implode(', ', $invalidfiles);
                return get_string('badfiles', 'qtype_codeanalyzer', $badfilelist);
            }
        } else {
            $attachcount = 0;
        }

        if ($attachcount < $this->attachmentsrequired) {
            return get_string('insufficientattachments', 'qtype_codeanalyzer', $this->attachmentsrequired);
        }

        if ($attachcount == 0) { // If no attachments, require an answer.
            $hasanswer = array_key_exists('answer', $response);
            if (!$hasanswer || strlen($response['answer']) == 0) {
                return get_string('answerrequired', 'qtype_codeanalyzer');
            } else if (strlen($response['answer']) < constants::FUNC_MIN_LENGTH) {
                return get_string('answertooshort', 'qtype_codeanalyzer', constants::FUNC_MIN_LENGTH);
            }
        }
        return '';  // All good.
    }

    // Return true iff the given filename is valid, meaning it matches the
    // regex (if given), contains only alphanumerics plus '-', '_' and '.',
    // doesn't clash with any of the support files and doesn't
    // start with double underscore..
    private function is_valid_filename($filename, $regex, $supportfiles)
    {
        if (strpos($filename, '__') === 0) {
            return false;  // Dunder names are reserved for runtime task.
        }
        if (!ctype_alnum(str_replace(array('-', '_', '.'), '', $filename))) {
            return false;  // Filenames must be alphanumeric plus '.', '-', or '_'.
        }
        if (!empty($regex) && preg_match('=^' . $this->filenamesregex . '$=', $filename) !== 1) {
            return false;  // Filename doesn't match given regex.
        }
        foreach (array_keys($supportfiles) as $supportfilename) {
            if ($supportfilename == $filename) {
                return false;  // Filename collides with a support file name.
            }
        }
        return true;
    }
}
