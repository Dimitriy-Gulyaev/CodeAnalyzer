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
 * Plugin administration pages are defined here.
 *
 * @package     qtype
 * @category    codeanalyzer
 * @copyright   Dmitriy Gulyaev
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use qtype_codeanalyzer\constants;

$links = array(
    get_string('bulkquestiontester', 'qtype_codeanalyzer',
        array('link' => (string)new moodle_url('/question/type/codeanalyzer/bulktestindex.php')))
);

$settings->add(new admin_setting_heading('supportscripts',
    get_string('supportscripts', 'qtype_codeanalyzer'), '* ' . implode("\n* ", $links)));

$settings->add(new admin_setting_heading('codeanalyzersettings',
    get_string('codeanalyzersettings', 'qtype_codeanalyzer'), ''));

$settings->add(new admin_setting_configtext(
    "qtype_codeanalyzer/default_penalty_regime",
    get_string('default_penalty_regime', 'qtype_codeanalyzer'),
    get_string('default_penalty_regime_desc', 'qtype_codeanalyzer'),
    '10, 20, ...'
));

$sandboxes = qtype_coderunner_sandbox::available_sandboxes();
foreach ($sandboxes as $sandbox => $classname) {
    $settings->add(new admin_setting_configcheckbox(
        "qtype_codeanalyzer/{$sandbox}_enabled",
        get_string('enable', 'qtype_codeanalyzer') . ' ' . $sandbox,
        get_string('enable_sandbox_desc', 'qtype_codeanalyzer'),
        $sandbox === 'jobesandbox')  // Only jobesandbox is enabled by default.
    );
}

$settings->add(new admin_setting_configtext(
    "qtype_codeanalyzer/jobe_host",
    get_string('jobe_host', 'qtype_codeanalyzer'),
    get_string('jobe_host_desc', 'qtype_codeanalyzer'),
    constants::ANALYZER_HOST_DEFAULT));

$settings->add(new admin_setting_configtext(
    "qtype_codeanalyzer/jobe_apikey",
    get_string('jobe_apikey', 'qtype_codeanalyzer'),
    get_string('jobe_apikey_desc', 'qtype_codeanalyzer'),
    constants::ANALYZER_HOST_DEFAULT_API_KEY));

$settings->add(new admin_setting_configtext(
    "qtype_codeanalyzer/ideone_user",
    get_string('ideone_user', 'qtype_codeanalyzer'),
    get_string('ideone_user_desc', 'qtype_codeanalyzer'),
    ''));

$settings->add(new admin_setting_configtext(
    "qtype_codeanalyzer/ideone_password",
    get_string('ideone_pass', 'qtype_codeanalyzer'),
    get_string('ideone_pass_desc', 'qtype_codeanalyzer'),
    ''));

$settings->add(new admin_setting_heading('codeanalyzerwssettings',
    get_string('codeanalyzerwssettings', 'qtype_codeanalyzer'), ''));

$settings->add(new admin_setting_configcheckbox(
        "qtype_codeanalyzer/wsenabled",
        get_string('enable_sandbox_ws', 'qtype_codeanalyzer'),
        get_string('enable_sandbox_ws_desc', 'qtype_codeanalyzer'),
        false)
);

$settings->add(new admin_setting_configtext(
        "qtype_codeanalyzer/wsjobeserver",
        get_string('jobe_host_ws', 'qtype_codeanalyzer'),
        get_string('jobe_host_ws_desc', 'qtype_codeanalyzer'),
        '')
);

$settings->add(new admin_setting_configcheckbox(
        "qtype_codeanalyzer/wsloggingenabled",
        get_string('wsloggingenable', 'qtype_codeanalyzer'),
        get_string('wsloggingenable_desc', 'qtype_codeanalyzer'),
        true)
);

$settings->add(new admin_setting_configtext(
        "qtype_codeanalyzer/wsmaxhourlyrate",
        get_string('wsmaxhourlyrate', 'qtype_codeanalyzer'),
        get_string('wsmaxhourlyrate_desc', 'qtype_codeanalyzer'),
        '200')
);

$settings->add(new admin_setting_configtext(
        "qtype_codeanalyzer/wsmaxcputime",
        get_string('wsmaxcputime', 'qtype_codeanalyzer'),
        get_string('wsmaxcputime_desc', 'qtype_codeanalyzer'),
        '5')
);

