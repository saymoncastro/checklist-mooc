<?php
// Laboratório s4: habilita Web Services/REST, cria a conta de integração com o papel do API.md e um token.
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->libdir . '/externallib.php');
global $DB;
set_config('enablewebservices', 1);
set_config('webserviceprotocols', 'rest');
$sys = context_system::instance();
$user = $DB->get_record('user', ['username' => 'integracao']);
if (!$user) {
    require_once($CFG->dirroot . '/user/lib.php');
    $id = user_create_user(['username' => 'integracao', 'password' => random_string(20) . 'aA1!', 'firstname' => 'Integração',
        'lastname' => 'Auditor', 'email' => 'integracao@example.com', 'auth' => 'manual', 'confirmed' => 1,
        'mnethostid' => $CFG->mnet_localhost_id]);
    $user = $DB->get_record('user', ['id' => $id]);
}
$roleid = $DB->get_field('role', 'id', ['shortname' => 'integracaoauditor']) ?: create_role('Integração Auditor MOOC',
    'integracaoauditor', '');
set_role_contextlevels($roleid, [CONTEXT_SYSTEM]);
foreach (['local/mooc_audit:api', 'local/mooc_audit:audit', 'moodle/course:view', 'moodle/course:viewhiddencourses',
    'webservice/rest:use'] as $cap) {
    assign_capability($cap, CAP_ALLOW, $roleid, $sys->id, true);
}
role_assign($roleid, $user->id, $sys->id);
$service = $DB->get_record('external_services', ['shortname' => 'local_mooc_audit'], '*', MUST_EXIST);
echo "service enabled={$service->enabled} restricted={$service->restrictedusers} cap={$service->requiredcapability}\n";
$DB->set_field('external_services', 'enabled', 1, ['id' => $service->id]);
if (!$DB->record_exists('external_services_users', ['externalserviceid' => $service->id, 'userid' => $user->id])) {
    $DB->insert_record('external_services_users', (object) ['externalserviceid' => $service->id, 'userid' => $user->id,
        'timecreated' => time()]);
}
$token = \core_external\util::generate_token(EXTERNAL_TOKEN_PERMANENT, $service, $user->id, $sys, 0, '', 'spec 004');
file_put_contents('/var/www/moodledata/s4token', $token);
echo "ok\n";
