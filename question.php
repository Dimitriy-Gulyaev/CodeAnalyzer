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
 * Represents a codeanalyzer question.
 *
 * @copyright  Dmitriy Gulyaev
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_codeanalyzer_question extends question_graded_automatically
{

    public function get_expected_data()
    {
        return array('answer' => PARAM_RAW);
    }

    /**
     * Start a new attempt at this question, storing any information that will
     * be needed later in the step. It is retrieved and applied by
     * apply_attempt_state.
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

    // Retrieve the saved random number seed after start_attempt was called.
    public function apply_attempt_state(question_attempt_step $step)
    {
        parent::apply_attempt_state($step);
        $this->student = unserialize($step->get_qt_var('_STUDENT'));
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
        return $response['answer'] ?? null;
    }

    public function is_complete_response(array $response)
    {
        return $this->is_gradable_response($response);
    }

    public function is_gradable_response(array $response)
    {
        // Determine if the given response has a non-empty answer
        return $this->validate_response($response) == '';
    }

    public function validate_response(array $response)
    {
        $hasanswer = array_key_exists('answer', $response);
        if (!$hasanswer || strlen($response['answer']) == 0) {
            return get_string('answerrequired', 'qtype_codeanalyzer');
        } else if (strlen($response['answer']) < constants::FUNC_MIN_LENGTH) {
            return get_string('answertooshort', 'qtype_codeanalyzer', constants::FUNC_MIN_LENGTH);
        }
        return '';  // All good.
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
        return question_utils::arrays_same_at_key_missing_is_blank(
                $prevresponse, $newresponse, 'answer') &&
            question_utils::arrays_same_at_key_missing_is_blank(
                $prevresponse, $newresponse, 'language');
    }


    public function grade_response(array $response, bool $isprecheck = false, int $prevtries = 0)
    {
        // TODO place for interactions with server, check original
        return null;
    }


    // Return an instance of the sandbox to be used to run code for this question.
    public function get_sandbox()
    {
        // TODO place for interactions with server, check original
    }

    public function get_correct_response()
    {
        return $this->answer ?? null;
    }

    public function get_validation_error(array $response)
    {
        $error = $this->validate_response($response);
        if ($error) {
            return $error;
        } else {
            return get_string('unknownerror', 'qtype_coderunner');
        }
    }
}
