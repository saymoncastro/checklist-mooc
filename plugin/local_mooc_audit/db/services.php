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
 * External functions and service of local_mooc_audit (specs/004 contracts/web-services.md).
 *
 * The service is created disabled and restricted to authorised users: the administrator enables it and authorises the
 * integration account, which also needs local/mooc_audit:api (no archetype).
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_mooc_audit_request_audit' => [
        'classname' => \local_mooc_audit\external\request_audit::class,
        'methodname' => 'execute',
        'description' => 'Request the audit of a course; the audit runs in the background and is followed with get_run.',
        'type' => 'write',
        'capabilities' => 'local/mooc_audit:api, local/mooc_audit:audit',
        'ajax' => false,
        'loginrequired' => true,
    ],
    'local_mooc_audit_get_run' => [
        'classname' => \local_mooc_audit\external\get_run::class,
        'methodname' => 'execute',
        'description' => 'One recorded audit run by id, with its state, summary, evolution and stored report.',
        'type' => 'read',
        'capabilities' => 'local/mooc_audit:api, local/mooc_audit:audit',
        'ajax' => false,
        'loginrequired' => true,
    ],
    'local_mooc_audit_get_latest_audit' => [
        'classname' => \local_mooc_audit\external\get_latest_audit::class,
        'methodname' => 'execute',
        'description' => 'Result of the last recorded audit of a course, without running a new one.',
        'type' => 'read',
        'capabilities' => 'local/mooc_audit:api, local/mooc_audit:audit',
        'ajax' => false,
        'loginrequired' => true,
    ],
];

$services = [
    'Auditor MOOC' => [
        'shortname' => 'local_mooc_audit',
        'functions' => ['local_mooc_audit_request_audit', 'local_mooc_audit_get_run', 'local_mooc_audit_get_latest_audit'],
        'requiredcapability' => 'local/mooc_audit:api',
        'restrictedusers' => 1,
        'enabled' => 0,
        'downloadfiles' => 0,
        'uploadfiles' => 0,
    ],
];
