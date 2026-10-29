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
 * English strings for local_mooc_audit.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['auditmenu'] = 'Technical audit';
$string['category_assessment_grades'] = 'Assessment and grades';
$string['category_certification'] = 'Certification';
$string['category_completion_restrictions'] = 'Completion and restrictions';
$string['category_content_files'] = 'Content and files';
$string['category_institutional_resources'] = 'Institutional resources';
$string['category_offer_enrol'] = 'Offer and enrolment';
$string['category_plugins_environment'] = 'Plugins and environment';
$string['category_structure_blocks'] = 'Structure, sections and blocks';
$string['courseid'] = 'Course id';
$string['jsonlink'] = 'JSON';
$string['label_blocks'] = 'Blocks';
$string['label_bysev'] = 'Non-conformities by severity';
$string['label_bystatus'] = 'Results by state';
$string['label_category'] = 'Category';
$string['label_collection'] = 'Data collection';
$string['label_conformant_omitted'] = 'conformant rules omitted';
$string['label_contract'] = 'contract';
$string['label_engine'] = 'Auditor';
$string['label_enrols'] = 'Enrolment methods';
$string['label_evidence'] = 'Evidence';
$string['label_executed_at'] = 'Executed at';
$string['label_expected'] = 'Expected';
$string['label_files'] = 'Files';
$string['label_files_missing'] = 'without content in storage';
$string['label_found'] = 'Found';
$string['label_grade_items'] = 'Grade items';
$string['label_guidance'] = 'Guidance';
$string['label_inventory'] = 'Inventory';
$string['label_local'] = 'Location';
$string['label_message'] = 'Message';
$string['label_modules'] = 'Modules';
$string['label_overall'] = 'Overall status';
$string['label_period'] = 'Period';
$string['label_policy'] = 'Policy';
$string['label_reason'] = 'Reason';
$string['label_reoffer_year'] = 'Reoffer year';
$string['label_rule'] = 'Rule';
$string['label_rules'] = 'Rules';
$string['label_sections'] = 'Sections';
$string['label_status'] = 'State';
$string['label_time'] = 'time';
$string['label_visible'] = 'Visible';
$string['link_title'] = 'Open: {$a}';
$string['locus_blocks'] = 'Course page (blocks)';
$string['locus_course_settings'] = 'Course settings';
$string['locus_enrol'] = 'Enrolment methods';
$string['locus_gradebook'] = 'Gradebook setup';
$string['locus_import'] = 'Import from another course';
$string['locus_module_edit'] = 'Module settings';
$string['locus_module_view'] = 'Module page';
$string['locus_none'] = 'No navigable destination';
$string['locus_section'] = 'Section page';
$string['mooc_audit:audit'] = 'Run the technical audit of a course and view its report (read-only)';
$string['mooc_audit:compare'] = 'Compare two courses, source and imported target (read-only)';
$string['none_in_section'] = 'None.';
$string['overall_bloqueada'] = 'Blocked';
$string['overall_requer_correcoes'] = 'Needs fixes';
$string['overall_sem_inconformidades'] = 'No non-conformities';
$string['pagetitle'] = 'Technical audit — {$a}';
$string['pluginname'] = 'MOOC audit';
$string['policy_badtype'] = 'Policy: key "{$a}" has an unexpected type; the default was kept.';
$string['policy_invalid'] = 'Invalid institutional policy ({$a}); the plugin defaults were used.';
$string['policyjson'] = 'Institutional policy (JSON)';
$string['policyjson_defaults'] = 'Default policy of this version (read-only reference)';
$string['policyjson_desc'] = 'JSON object overriding the default policy parameters (reoffer year, end date, minimum attempts, canonical names, required blocks...). Leave empty to use the defaults shipped with the plugin.';
$string['policyjson_invalid'] = 'Invalid policy: {$a}. Enter a JSON object (for example {"min_quiz_attempts": 3}) or leave empty for the defaults.';
$string['policyjson_keys'] = 'Accepted keys, with type and default value. Only the keys present in the JSON override the defaults; unknown keys are kept and ignored; a key with the wrong type is reported in the audit and the default is used.';
$string['policyjson_notobject'] = 'the value is valid JSON but not an object';
$string['policyjson_type_auto'] = 'integer, or empty for automatic';
$string['privacy:metadata'] = 'The MOOC audit plugin only reads course configuration and does not store personal data.';
$string['rerun'] = 'Run again';
$string['rule_cl04_guidance'] = 'Add the button at the end of the summary (Edit course settings > Description).';
$string['rule_cl04_title'] = 'Course summary contains the institutional buttons';
$string['rule_cl05_guidance'] = 'Move the course to the offer category.';
$string['rule_cl05_title'] = 'Course category = open enrolment category';
$string['rule_cl06_guidance'] = 'Adjust the start date of the offer.';
$string['rule_cl06_title'] = 'Correct start date (01/01 of the reoffer year)';
$string['rule_cl07_guidance'] = 'Adjust the end date according to the Cefor calendar.';
$string['rule_cl07_title'] = 'Correct end date (31/12 of the reoffer year)';
$string['rule_cl08_g_add'] = 'Add the Self enrolment method.';
$string['rule_cl08_g_guest'] = 'Confirm whether guest access is intended.';
$string['rule_cl08_guidance'] = 'Edit Self enrolment: enable it, allow new enrolments and align the dates with the course dates.';
$string['rule_cl08_title'] = 'Self enrolment enabled with the correct period';
$string['rule_cl09_g_import'] = 'Import the forum from the Standard Room. Checked by canonical name.';
$string['rule_cl09_guidance'] = 'Disable activity completion on the institutional forum.';
$string['rule_cl09_title'] = 'Institutional forum "Espaço de interação" present without completion';
$string['rule_cl11_g_completion'] = 'Without automatic completion the certificate restriction is never satisfied.';
$string['rule_cl11_g_import'] = 'Import the Profile survey from the Official Standard Room.';
$string['rule_cl11_guidance'] = 'Add to the certificate the completion restriction of the Profile survey.';
$string['rule_cl11_title'] = 'Profile survey present and required by the certificate';
$string['rule_cl12_g_aggregation'] = 'Change the aggregation of the course category to Natural.';
$string['rule_cl12_g_onlygraded'] = 'Untick "Exclude empty grades": with it on, the 60-point certificate condition is relative only to activities already graded.';
$string['rule_cl12_g_subcats'] = 'Check the aggregation of the subcategories.';
$string['rule_cl12_guidance'] = 'Adjust the maximum grades of the activities.';
$string['rule_cl12_title'] = 'Grade book: sum 100, Natural aggregation, empty grades not excluded';
$string['rule_cl13_g_intro'] = 'Fix the statement or the configuration.';
$string['rule_cl13_guidance'] = 'Edit quiz settings > Grade > Attempts allowed.';
$string['rule_cl13_title'] = 'Quizzes with at least two attempts (and consistent statement)';
$string['rule_cl14_guidance'] = 'Set the closing date.';
$string['rule_cl14_title'] = 'Graded activities have a closing date';
$string['rule_cl16_guidance'] = 'Add the missing completion restrictions.';
$string['rule_cl16_title'] = 'Course evaluation restricted to completion of every graded activity';
$string['rule_cl17_g_criteria'] = 'Review the completion criterion against the policy table.';
$string['rule_cl17_guidance'] = 'Switch to automatic completion with the criterion suited to the type.';
$string['rule_cl17_title'] = 'Automatic completion with a criterion suited to the type';
$string['rule_cl18_g_repopulate'] = 'Entries are not carried by backup/import without users: repopulate (import the glossary XML) or switch the games source to the question bank.';
$string['rule_cl18_guidance'] = 'Remove the glossary (collaborative glossaries are forbidden by the policy).';
$string['rule_cl18_title'] = 'Glossary: forbidden if collaborative; empty if used as game source';
$string['rule_cl19_guidance'] = 'Disable completion on the label.';
$string['rule_cl19_title'] = 'Labels without activity completion';
$string['rule_cl20_g_emptysection'] = 'Delete or hide the section.';
$string['rule_cl20_g_genericname'] = 'Name the section after its content.';
$string['rule_cl20_guidance'] = 'Tick "Hidden sections are shown as not available" = completely invisible in the course settings.';
$string['rule_cl20_title'] = 'Sections: hidden ones invisible, no empty or generically named sections';
$string['rule_cl21_guidance'] = 'Add the standard HTML block (or check the corresponding site block).';
$string['rule_cl21_title'] = 'Standard blocks present on the course page';
$string['rule_cl22_guidance'] = 'Import the Training licence components (label, survey and declaration) from the Standard Room.';
$string['rule_cl22_title'] = 'Training licence configured';
$string['rule_cl23_g_courseitem'] = 'The grade condition of the certificate must reference the Course total.';
$string['rule_cl23_g_import'] = 'Import the certificate from the Standard Room.';
$string['rule_cl23_guidance'] = 'Add to the certificate the restrictions: completion of the course evaluation and minimum grade on the Course total, with the "all" operator.';
$string['rule_cl23_title'] = 'Certificate conditioned on the course evaluation and the minimum total grade';
$string['rule_error'] = 'Internal failure in the rule: {$a}';
$string['rule_xav01_guidance'] = 'Align the statement and the maximum grade.';
$string['rule_xav01_title'] = 'Statement × configuration (points)';
$string['rule_xcert01_guidance'] = 'Static check; actual issuing requires a test user.';
$string['rule_xcert01_title'] = 'Static reachability of the certificate';
$string['rule_xcont01_g_reupload'] = 'Upload the file again. In the restore of course 670 this happened with aliases to "Server files" not included in the backup.';
$string['rule_xcont01_guidance'] = 'Fill in or remove the resource.';
$string['rule_xcont01_title'] = 'Empty resources (page/label without content, quiz without questions, file without file)';
$string['rule_xcont04_guidance'] = 'Broken image/file: upload it again in the editor. Typical of aliases not restored ("unable to restore alias").';
$string['rule_xcont04_title'] = '@@PLUGINFILE@@ references without a matching file';
$string['rule_xdep01_guidance'] = 'Remove or recreate the orphan restriction.';
$string['rule_xdep01_title'] = 'Access conditions pointing to non-existent modules';
$string['rule_xdep02_guidance'] = 'Enable completion on the target module or remove the condition; otherwise it will never be satisfied.';
$string['rule_xdep02_title'] = 'Condition requires completion of a module without completion or hidden';
$string['rule_xdep03_guidance'] = 'Recreate the grade condition pointing to an existing grade item.';
$string['rule_xdep03_title'] = 'Grade conditions pointing to non-existent items';
$string['rule_xdep04_guidance'] = 'Remove one of the conditions to break the cycle.';
$string['rule_xdep04_title'] = 'Cycles in the completion dependency graph';
$string['rule_xint02_guidance'] = 'Upload the file again. If it is an alias (Server files), the original was removed or not copied.';
$string['rule_xint02_title'] = 'File records without content in the Moodle file store';
$string['rule_xint03_guidance'] = 'Install the plugin on the site or remove its use in the course; access conditions of a missing plugin are silently ignored.';
$string['rule_xint03_title'] = 'Plugins used by the course installed on the site';
$string['rule_xstr01_g_name'] = 'Give the resource a name.';
$string['rule_xstr01_guidance'] = 'Confirm whether it should stay hidden.';
$string['rule_xstr01_title'] = 'Hidden modules, invisible to the user or with an empty name';
$string['runaudit'] = 'Run audit';
$string['section_conformant'] = 'Conformant rules';
$string['section_errors'] = 'Verification error';
$string['section_nonconformities'] = 'Non-conformities';
$string['section_notices'] = 'Notices';
$string['section_unverifiable'] = 'Not verified or not applicable';
$string['section_unverifiable_na'] = 'Does not apply to this course';
$string['section_unverifiable_nv'] = 'Could not be verified';
$string['section_warnings'] = 'Warnings';
$string['severity_blocking'] = 'Blocking';
$string['severity_high'] = 'High';
$string['severity_info'] = 'Informative';
$string['severity_low'] = 'Low';
$string['severity_medium'] = 'Medium';
$string['site_completion_disabled'] = 'Completion tracking or access restrictions are disabled on the site; completion, restriction and certification rules could not be verified.';
$string['status_atencao'] = 'Attention';
$string['status_conforme'] = 'Conformant';
$string['status_erro'] = 'Verification error';
$string['status_nao_aplicavel'] = 'Not applicable';
$string['status_nao_conforme'] = 'Non conformant';
$string['status_nao_verificavel'] = 'Not verifiable';
