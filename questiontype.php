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
 * Question type class for the codeanalyzer question type.
 *
 * @package    qtype
 * @subpackage codeanalyzer
 * @copyright  Dmitriy Gulyaev
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/engine/lib.php');
require_once($CFG->dirroot . '/question/type/codeanalyzer/question.php');


/**
 * The codeanalyzer question type.
 *
 * @copyright  Dmitriy Gulyaev
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_codeanalyzer extends question_type
{

    /**
     * Move all the files belonging to this question from one context to another.
     * Override superclass implementation to handle the extra data files and
     * sample answer files we have in CodeRunner questions.
     * @param int $questionid the question being moved.
     * @param int $oldcontextid the context it is moving from.
     * @param int $newcontextid the context it is moving to.
     */
    public function move_files($questionid, $oldcontextid, $newcontextid)
    {
        parent::move_files($questionid, $oldcontextid, $newcontextid);
        $fs = get_file_storage();
        foreach (array('datafile', 'samplefile') as $filetype) {
            $fs->move_area_files_to_new_context($oldcontextid,
                $newcontextid, 'qtype_codeanalyzer', $filetype, $questionid);
        }
    }


    // Override save_question to record in $form if this is a new question or
    // not. Needed by save_question_options when saving prototypes.
    // Note that the $form parameter to save_question is passed through
    // to save_question_options as the $question parameter.
    public function save_question($question, $form)
    {
        $form->isnew = empty($question->id);
        return parent::save_question($question, $form);
    }

    // This override saves all the extra question data, including
    // the set of testcases and any datafiles to the database.
    public function save_question_options($question)
    {
        global $DB, $USER;

        // Tidy the form, handle inheritance from prototype.
        $this->clean_question_form($question);

        parent::save_question_options($question);

        // Write test cases to DB, reusing old ones where possible.
        $testcasetable = "question_coderunner_tests";
        if (!$oldtestcases = $DB->get_records($testcasetable,
            array('questionid' => $question->id), 'id ASC')) {
            $oldtestcases = array();
        }

        foreach ($question->testcases as $tc) {
            if (($oldtestcase = array_shift($oldtestcases))) { // Existing testcase, so reuse it.
                $tc->id = $oldtestcase->id;
                $DB->update_record($testcasetable, $tc);
            } else {
                // A new testcase.
                $tc->questionid = $question->id;
                $id = $DB->insert_record($testcasetable, $tc);
            }
        }

        // Delete old testcase records.
        foreach ($oldtestcases as $otc) {
            $DB->delete_records($testcasetable, array('id' => $otc->id));
        }

        // Lastly, save any datafiles (support files + sample answer files).
        if ($USER->id) {
            // The id check is a hack to deal with phpunit initialisation, when no user exists.
            foreach (array('datafiles' => 'datafile',
                         'sampleanswerattachments' => 'samplefile') as $fileset => $filearea) {
                if (isset($question->$fileset)) {
                    file_save_draft_area_files($question->$fileset, $question->context->id,
                        'qtype_codeanalyzer', $filearea, (int)$question->id, $this->fileoptions);
                }
            }
        }

        return true;
    }

    /**
     * Clean up the "question" (which is actually the question editing form)
     * ready for saving or for testing before saving ($isvalidation == true).
     * @param $question the question editing form
     * @param $isvalidation true if we're cleaning for validation rather than saving.
     */
    public function clean_question_form($question, $isvalidation = false)
    {
        $fields = $this->extra_question_fields();
        array_shift($fields); // Discard table name.

        if (trim($question->sandbox) === 'DEFAULT') {
            $question->sandbox = null;
        }
    }
    // Function to copy testcases from form fields into question->testcases.
    // If $validation true, we're just validating and need to add an extra
    // rownum attribute to the testcase to allow failed test case results
    // to be copied back to the form with a mouse click.
    private function copy_testcases_from_form(&$question, $validation)
    {
        $testcases = array();
        if (empty($question->testcode)) {
            $numtests = 0;  // Must be a combinator template grader with no tests.
        } else {
            $numtests = count($question->testcode);
            assert(count($question->expected) == $numtests);
        }
        for ($i = 0; $i < $numtests; $i++) {
            $testcode = $this->filter_crs($question->testcode[$i]);
            $stdin = $this->filter_crs($question->stdin[$i]);
            $expected = $this->filter_crs($question->expected[$i]);
            $extra = $this->filter_crs($question->extra[$i]);
            if (trim($testcode) === '' && trim($stdin) === '' &&
                trim($expected) === '' && trim($extra) === '') {
                continue; // Ignore testcases with only whitespace in them.
            }
            $testcase = new stdClass;
            if ($validation) {
                $testcase->rownum = $i;  // The row number in the edit form - relevant only when validating.
            }
            $testcase->questionid = isset($question->id) ? $question->id : 0;
            $testcase->testtype = isset($question->testtype[$i]) ? $question->testtype[$i] : 0;
            $testcase->testcode = $testcode;
            $testcase->stdin = $stdin;
            $testcase->expected = $expected;
            $testcase->extra = $extra;
            $testcase->useasexample = isset($question->useasexample[$i]);
            $testcase->display = $question->display[$i];
            $testcase->hiderestiffail = isset($question->hiderestiffail[$i]);
            $testcase->mark = trim($question->mark[$i]) == '' ? 1.0 : floatval($question->mark[$i]);
            $testcase->ordering = intval($question->ordering[$i]);
            $testcases[] = $testcase;
        }

        usort($testcases, function ($tc1, $tc2) {
            if ($tc1->ordering === $tc2->ordering) {
                return 0;
            } else {
                return $tc1->ordering < $tc2->ordering ? -1 : 1;
            }
        });  // Sort by ordering field.

        $question->testcases = $testcases;
    }

    // Initialise the question_definition object from the questiondata
    // read from the database (probably a cached version of the question
    // object from the database enhanced by a call to get_question_options).
    // Only fields not explicitly listed in extra_question_fields (i.e. those
    // fields not from the question_coderunner_options table) need handling here.
    // All we do is flatten the question->options fields down into the
    // question itself, which will be all those fields of question->options
    // not already flattened down by the parent implementation.
    protected function initialise_question_instance(question_definition $question, $questiondata)
    {
        parent::initialise_question_instance($question, $questiondata);
        foreach ($questiondata->options as $field => $value) {
            if (!isset($question->$field)) {
                $question->$field = $value;
            }
        }
    }


    /**
     * Get the context for a question.
     *
     * @param stdClass $question a row from either the question or question_coderunner_options tables.
     * @return context the corresponding context id.
     */
    public static function question_context($question)
    {
        return context::instance_by_id(self::question_contextid($question));
    }

    /**
     * Get the context id for a question.
     *
     * @param stdClass $question a row from either the question or question_coderunner_options tables.
     * @return int the corresponding context id.
     */
    public static function question_contextid($question)
    {
        global $DB;

        if (isset($question->contextid)) {
            return $question->contextid;
        } else {
            $questionid = isset($question->questionid) ? $question->questionid : $question->id;
            $sql = "SELECT contextid FROM {question_categories}, {question}
                     WHERE {question}.id = ?
                       AND {question}.category = {question_categories}.id";
            return $DB->get_field_sql($sql, array($questionid), MUST_EXIST);
        }
    }


    /** Utility func: remove all '\r' chars from $s and also trim trailing newlines */
    private function filter_crs($s)
    {
        $s = str_replace("\r", "", $s);
        while (substr($s, strlen($s) - 1, 1) == '\n') {
            $s = substr($s, 0, strlen($s) - 1);
        }
        return $s;
    }

    /**
     * @return array the choices that should be offered for the number of attachments.
     */
    public function attachment_options()
    {
        return array(
            0 => get_string('no'),
            1 => '1',
            2 => '2',
            3 => '3',
            -1 => get_string('unlimited'),
        );
    }
}
