<?php
/*
 * @package    qtype
 * @subpackage codeanalyzer
 * @copyright  Dmitriy Gulyaev
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_codeanalyzer;
defined('MOODLE_INTERNAL') || die();


class constants
{
    const DEFAULT_GRADER = 'EqualityGrader';  // External name of default grader.
    const FUNC_MIN_LENGTH = 1;  // Minimum no. of bytes for a valid bit of code.

    const FEEDBACK_USE_QUIZ = 0;
    const FEEDBACK_SHOW = 1;
    const FEEDBACK_HIDE = 2;

    const MAX_STRING_LENGTH = 8000;  // Maximum length of a string for display in the result table.
    const MAX_LINE_LENGTH = 100;     // Maximum length of a string for display in the result table.
    const MAX_NUM_LINES = 200;       // Maximum number of lines of text to be displayed a result table cell.

    const ANALYZER_HOST_DEFAULT = '';
    const ANALYZER_HOST_DEFAULT_API_KEY = '';

    const DEFAULT_NUM_ROWS = 18;     // Default answerbox size.
    const DEFAULT_NUM_COLUMNS = 100;     // Default answerbox size.
}
