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

/**
 * codeanalyzer question editing form definition.
 *
 * @copyright  Dmitriy Gulyaev
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_codeanalyzer_edit_form extends question_edit_form
{
    public function qtype()
    {
        return 'codeanalyzer';
    }

    public function definition_inner($mform)
    {
        // TODO maybe save this to DB
        $attributes = array(
            'rows' => 9,
            'class' => 'answer edit_code');
        $mform->addElement('textarea', 'answer',
            get_string('answer', 'qtype_codeanalyzer'),
            $attributes);
        $mform->addHelpButton('answer', 'answer', 'qtype_codeanalyzer');
    }
}
