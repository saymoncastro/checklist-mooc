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
 * CLI (TEST ENVIRONMENTS ONLY): creates two controlled courses for the proof of concept.
 *
 *   Course A — reference configuration according to the institutional policy.
 *   Course B — deliberately inconsistent configuration (known, documented problems).
 *
 * php local/mooc_audit/cli/make_fixtures.php --year=2027
 *
 * Refuses to run unless the site is a local/dev site ($CFG->wwwroot contains localhost or debugdeveloper is on).
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->libdir . '/blocklib.php');
require_once($CFG->libdir . '/pagelib.php');
require_once($CFG->dirroot . '/question/format.php');
require_once($CFG->dirroot . '/question/format/gift/format.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->libdir . '/resourcelib.php');
require_once($CFG->libdir . '/enrollib.php');

list($options) = cli_get_params(['year' => 0, 'help' => false], ['h' => 'help']);
if ($options['help']) {
    cli_writeln('Cria os cursos de teste A (referência) e B (inconsistente). Opção --year=AAAA (padrão: ano seguinte).');
    exit(0);
}
if (strpos($CFG->wwwroot, 'localhost') === false && empty($CFG->debugdeveloper)) {
    cli_error('Recusado: este script só roda em ambiente local de desenvolvimento.');
}
\core\session\manager::set_user(get_admin());
$year = (int) ($options['year'] ?: (date('Y') + 1));
$tz = new DateTimeZone(core_date::get_server_timezone());
$start = (new DateTime("$year-01-01 00:00", $tz))->getTimestamp();
$end = (new DateTime("$year-12-31 23:59", $tz))->getTimestamp();

// Category "Inscrições Abertas".
$cat = $DB->get_record('course_categories', ['name' => 'Inscrições Abertas']);
if (!$cat) {
    $cat = core_course_category::create(['name' => 'Inscrições Abertas']);
}

/**
 * Create a module through the official modlib API.
 *
 * @param stdClass $course
 * @param string $modname
 * @param int $section
 * @param array $data
 * @return stdClass moduleinfo (with coursemodule, instance)
 */
function fx_add_module(stdClass $course, string $modname, int $section, array $data): stdClass {
    global $DB;
    $module = $DB->get_record('modules', ['name' => $modname], '*', MUST_EXIST);
    $mi = (object) array_merge([
        'modulename' => $modname, 'module' => $module->id, 'course' => $course->id, 'section' => $section, 'visible' => 1,
        'visibleoncoursepage' => 1, 'introeditor' => ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0], 'cmidnumber' => '',
        'completion' => COMPLETION_TRACKING_NONE, 'completionview' => 0, 'completiongradeitemnumber' => null, 'availability' => null,
        'groupmode' => 0, 'groupingid' => 0, 'showdescription' => 0,
    ], $data);
    return create_module($mi);
}

/**
 * Set the availability JSON of a course module directly (same as the editing form does) and rebuild the cache.
 *
 * @param int $cmid
 * @param array|null $tree
 */
function fx_set_availability(int $cmid, ?array $tree): void {
    global $DB;
    $DB->set_field('course_modules', 'availability', $tree === null ? null : json_encode($tree), ['id' => $cmid]);
}

/**
 * Import GIFT questions into the course question bank and add them to a quiz.
 *
 * @param stdClass $course
 * @param stdClass $quizinfo moduleinfo returned by create_module
 * @param int $n number of true/false questions
 */
function fx_add_questions(stdClass $course, stdClass $quizinfo, int $n): void {
    global $DB, $CFG;
    $context = context_module::instance($quizinfo->coursemodule);
    $qcat = question_get_default_category($context->id) ?: question_make_default_categories([$context]);
    $gift = '';
    for ($i = 1; $i <= $n; $i++) {
        $gift .= "::Q$i::Afirmação de teste $i é verdadeira.{TRUE}\n\n";
    }
    $tmp = make_request_directory() . '/q.gift';
    file_put_contents($tmp, $gift);
    $format = new qformat_gift();
    $format->setCategory($qcat);
    $format->setContexts([$context]);
    $format->setCourse($course);
    $format->setFilename($tmp);
    $format->setRealfilename('q.gift');
    $format->setMatchgrades('error');
    $format->setCatfromfile(false);
    $format->setStoponerror(true);
    if (!$format->importpreprocess() || !$format->importprocess() || !$format->importpostprocess()) {
        cli_error('Falha ao importar questões GIFT');
    }
    $quiz = $DB->get_record('quiz', ['id' => $quizinfo->instance], '*', MUST_EXIST);
    $sql = "SELECT q.id FROM {question} q JOIN {question_versions} qv ON qv.questionid = q.id
              JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid WHERE qbe.questioncategoryid = :cat ORDER BY q.id";
    foreach ($DB->get_records_sql($sql, ['cat' => $qcat->id]) as $q) {
        quiz_add_quiz_question($q->id, $quiz);
    }
    quiz_update_sumgrades($quiz);
}

/**
 * Insert a simple item into a feedback (fixtures only; there is no public API for this).
 *
 * @param int $feedbackid
 */
function fx_add_feedback_item(int $feedbackid): void {
    global $DB;
    $DB->insert_record('feedback_item', (object) ['feedback' => $feedbackid, 'template' => 0, 'name' => 'Comentários', 'label' => '',
        'presentation' => '', 'typ' => 'textarea', 'hasvalue' => 1, 'position' => 1, 'required' => 0, 'dependitem' => 0, 'dependvalue' => '', 'options' => '']);
}

/**
 * Add a block instance to the course page.
 *
 * @param stdClass $course
 * @param string $blockname
 * @param array|null $config
 */
function fx_add_block(stdClass $course, string $blockname, ?array $config = null): void {
    global $DB;
    $context = context_course::instance($course->id);
    $page = new moodle_page();
    $page->set_course($course);
    $page->set_context($context);
    $page->set_pagelayout('course');
    $page->set_pagetype('course-view-' . $course->format);
    $page->set_url(new moodle_url('/course/view.php', ['id' => $course->id]));
    $page->blocks->add_regions(['side-pre'], false);
    $page->blocks->set_default_region('side-pre');
    $page->blocks->add_block($blockname, 'side-pre', 0, false, 'course-view-*');
    if ($config) {
        $inst = $DB->get_records('block_instances', ['blockname' => $blockname, 'parentcontextid' => $context->id], 'id DESC', '*', 0, 1);
        $inst = reset($inst);
        $inst->configdata = base64_encode(serialize((object) $config));
        $DB->update_record('block_instances', $inst);
    }
}

/**
 * Build one course.
 *
 * @param string $shortname
 * @param string $fullname
 * @param bool $broken whether to introduce the known problems
 * @param int $catid
 * @param int $start
 * @param int $end
 * @return array [course, map of logical name => cmid]
 */
function fx_build_course(string $shortname, string $fullname, bool $broken, int $catid, int $start, int $end): array {
    global $DB;
    if ($existing = $DB->get_record('course', ['shortname' => $shortname])) {
        delete_course($existing, false);
    }
    $summary = '<p><strong>Nome do curso:</strong> ' . $fullname . '</p><p><strong>Carga horária:</strong> 30 horas</p>'
        . '<p><a href="https://mooc.cefor.ifes.edu.br/moodle/login/signup.php"><button>Criar Conta</button></a></p>'
        . '<p><a href="https://mooc.cefor.ifes.edu.br/moodle/course/index.php?categoryid=7"><button>Turmas anteriores</button></a></p>';
    $course = create_course((object) [
        'fullname' => $fullname, 'shortname' => $shortname, 'category' => $catid, 'format' => 'topics', 'numsections' => 4,
        'startdate' => $broken ? $start - 365 * DAYSECS : $start, 'enddate' => $broken ? $start + 180 * DAYSECS : $end,
        'visible' => 1, 'enablecompletion' => 1, 'showcompletionconditions' => 1, 'summary' => $summary, 'summaryformat' => FORMAT_HTML,
        'hiddensections' => $broken ? 0 : 1, 'lang' => 'pt_br',
    ]);
    $ids = [];

    // Section names.
    $names = [1 => 'Boas-vindas', 2 => 'Conteúdos', 3 => $broken ? 'Topic 3' : 'Avaliação', 4 => 'Certificado e conclusão'];
    foreach ($names as $num => $name) {
        $sec = $DB->get_record('course_sections', ['course' => $course->id, 'section' => $num]);
        course_update_section($course, $sec, ['name' => $name]);
    }

    // Área do curso (seção 0).
    $ids['info'] = fx_add_module($course, 'page', 0, ['name' => 'Informações sobre o curso',
        'page' => ['text' => '<p><strong>Nome do curso:</strong> ' . $fullname . '</p><p><strong>Carga horária:</strong> 30 horas</p>', 'format' => FORMAT_HTML, 'itemid' => 0], 'content' => '<p><strong>Nome do curso:</strong> ' . $fullname . '</p><p><strong>Carga horária:</strong> 30 horas</p>', 'contentformat' => FORMAT_HTML,
        'display' => RESOURCELIB_DISPLAY_OPEN, 'printintro' => 0, 'printlastmodified' => 0])->coursemodule;
    $ids['forum'] = fx_add_module($course, 'forum', 0, ['name' => 'Espaço de interação', 'type' => 'general', 'forcesubscribe' => 0, 'grade_forum' => 0, 'scale' => 0, 'assessed' => 0,
        'completion' => $broken ? COMPLETION_TRACKING_MANUAL : COMPLETION_TRACKING_NONE, 'maxbytes' => 0, 'maxattachments' => 1, 'completionposts' => 0, 'completionreplies' => 0, 'completiondiscussions' => 0])->coursemodule;
    $perfil = fx_add_module($course, 'feedback', 0, ['name' => 'Pesquisa de Perfil', 'anonymous' => 1, 'completionsubmit' => 1,
        'completion' => COMPLETION_TRACKING_AUTOMATIC, 'page_after_submit_editor' => ['text' => 'Obrigado', 'format' => FORMAT_HTML, 'itemid' => 0], 'page_after_submit' => 'Obrigado', 'page_after_submitformat' => FORMAT_HTML, 'site_after_submit' => '', 'multiple_submit' => 0, 'publish_stats' => 0, 'email_notification' => 0]);
    $ids['perfil'] = $perfil->coursemodule;
    fx_add_feedback_item($perfil->instance);
    $ids['licenca_label'] = fx_add_module($course, 'label', 0, ['introeditor' => ['text' => '<p>Licença para capacitação: se você é servidor público...</p>', 'format' => FORMAT_HTML, 'itemid' => 0],
        'completion' => $broken ? COMPLETION_TRACKING_MANUAL : COMPLETION_TRACKING_NONE])->coursemodule;

    // Conteúdos.
    $ids['pagina1'] = fx_add_module($course, 'page', 1, ['name' => 'Conceitos iniciais', 'page' => ['text' => '<p>Texto do conteúdo 1.</p>', 'format' => FORMAT_HTML, 'itemid' => 0], 'content' => '<p>Texto do conteúdo 1.</p>', 'contentformat' => FORMAT_HTML,
        'display' => RESOURCELIB_DISPLAY_OPEN, 'printintro' => 0, 'printlastmodified' => 0, 'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionview' => 1])->coursemodule;
    $ids['pagina2'] = fx_add_module($course, 'page', 2, ['name' => 'Aprofundamento', 'page' => ['text' => '<p>Texto do conteúdo 2.</p>', 'format' => FORMAT_HTML, 'itemid' => 0], 'content' => '<p>Texto do conteúdo 2.</p>', 'contentformat' => FORMAT_HTML,
        'display' => RESOURCELIB_DISPLAY_OPEN, 'printintro' => 0, 'printlastmodified' => 0, 'completion' => $broken ? COMPLETION_TRACKING_NONE : COMPLETION_TRACKING_AUTOMATIC, 'completionview' => $broken ? 0 : 1])->coursemodule;
    if ($broken) {
        $ids['pagina_vazia'] = fx_add_module($course, 'page', 2, ['name' => 'Página sem conteúdo', 'page' => ['text' => '<p></p>', 'format' => FORMAT_HTML, 'itemid' => 0], 'content' => '<p></p>', 'contentformat' => FORMAT_HTML,
            'display' => RESOURCELIB_DISPLAY_OPEN, 'printintro' => 0, 'printlastmodified' => 0])->coursemodule;
        $ids['pagina_img'] = fx_add_module($course, 'page', 2, ['name' => 'Página com imagem perdida', 'page' => ['text' => '<p><img src="@@PLUGINFILE@@/banner.png" alt="banner"></p>', 'format' => FORMAT_HTML, 'itemid' => 0], 'content' => '<p><img src="@@PLUGINFILE@@/banner.png" alt="banner"></p>', 'contentformat' => FORMAT_HTML,
            'display' => RESOURCELIB_DISPLAY_OPEN, 'printintro' => 0, 'printlastmodified' => 0])->coursemodule;
        $ids['pagina_oculta'] = fx_add_module($course, 'page', 2, ['name' => 'Leitura obrigatória (oculta)', 'visible' => 0, 'page' => ['text' => '<p>Obrigatória.</p>', 'format' => FORMAT_HTML, 'itemid' => 0], 'content' => '<p>Obrigatória.</p>', 'contentformat' => FORMAT_HTML,
            'display' => RESOURCELIB_DISPLAY_OPEN, 'printintro' => 0, 'printlastmodified' => 0, 'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionview' => 1])->coursemodule;
    }

    // Avaliação: dois questionários (50 + 50 = 100; no curso B 50 + 40 = 90 e 1 tentativa no primeiro).
    $q1 = fx_add_module($course, 'quiz', 3, ['name' => 'Questionário 1', 'introeditor' => ['text' => '<p>Tentativas permitidas: 2. Valor: 50 pontos.</p>', 'format' => FORMAT_HTML, 'itemid' => 0],
        'attempts' => $broken ? 1 : 2, 'grade' => 50, 'grademethod' => QUIZ_GRADEHIGHEST, 'timeclose' => $broken ? 0 : $end, 'timeopen' => 0, 'timelimit' => 0,
        'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completiongradeitemnumber' => 0, 'completionusegrade' => 1, 'quizpassword' => '', 'preferredbehaviour' => 'deferredfeedback',
        'questionsperpage' => 1, 'shuffleanswers' => 1, 'decimalpoints' => 2, 'questiondecimalpoints' => -1, 'overduehandling' => 'autosubmit', 'graceperiod' => 0,
        'reviewattempt' => 69904, 'reviewcorrectness' => 4352, 'reviewmarks' => 4352, 'reviewspecificfeedback' => 4352, 'reviewgeneralfeedback' => 4352, 'reviewrightanswer' => 4352, 'reviewoverallfeedback' => 4352, 'reviewmaxmarks' => 69904]);
    $ids['quiz1'] = $q1->coursemodule;
    fx_add_questions($course, $q1, 5);
    $q2 = fx_add_module($course, 'quiz', 3, ['name' => 'Questionário 2', 'introeditor' => ['text' => '<p>Tentativas permitidas: 2. Valor: 50 pontos.</p>', 'format' => FORMAT_HTML, 'itemid' => 0],
        'attempts' => 2, 'grade' => $broken ? 40 : 50, 'grademethod' => QUIZ_GRADEHIGHEST, 'timeclose' => $end, 'timeopen' => 0, 'timelimit' => 0,
        'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completiongradeitemnumber' => 0, 'completionusegrade' => 1, 'quizpassword' => '', 'preferredbehaviour' => 'deferredfeedback',
        'questionsperpage' => 1, 'shuffleanswers' => 1, 'decimalpoints' => 2, 'questiondecimalpoints' => -1, 'overduehandling' => 'autosubmit', 'graceperiod' => 0,
        'reviewattempt' => 69904, 'reviewcorrectness' => 4352, 'reviewmarks' => 4352, 'reviewspecificfeedback' => 4352, 'reviewgeneralfeedback' => 4352, 'reviewrightanswer' => 4352, 'reviewoverallfeedback' => 4352, 'reviewmaxmarks' => 69904]);
    $ids['quiz2'] = $q2->coursemodule;
    if (!$broken) {
        fx_add_questions($course, $q2, 5);
    } // Curso B: questionário 2 sem questões.
    // Quiz 1 restricted to reading page 1 (valid), in B additionally to page 2 which has NO completion (impossible).
    $c = [['type' => 'completion', 'cm' => $ids['pagina1'], 'e' => 1]];
    if ($broken) {
        $c[] = ['type' => 'completion', 'cm' => $ids['pagina2'], 'e' => 1];
        $c[] = ['type' => 'completion', 'cm' => $ids['pagina_oculta'], 'e' => 1];
    }
    fx_set_availability($ids['quiz1'], ['op' => '&', 'c' => $c, 'showc' => array_fill(0, count($c), true)]);

    // Glossário + jogo (curso B): glossário vazio como fonte de palavras cruzadas.
    if ($broken && $DB->record_exists('modules', ['name' => 'game'])) {
        $gl = fx_add_module($course, 'glossary', 3, ['name' => 'Glossário de termos', 'displayformat' => 'dictionary', 'mainglossary' => 0, 'globalglossary' => 0,
            'allowduplicatedentries' => 0, 'showspecial' => 1, 'showalphabet' => 1, 'showall' => 1, 'allowcomments' => 0, 'allowprintview' => 1, 'usedynalink' => 0,
            'defaultapproval' => 1, 'entbypage' => 10, 'editalways' => 0, 'rsstype' => 0, 'rssarticles' => 0, 'assessed' => 0, 'scale' => 0]);
        $ids['glossario'] = $gl->coursemodule;
        $game = fx_add_module($course, 'game', 3, ['name' => 'Palavras cruzadas', 'gamekind' => 'cross', 'sourcemodule' => 'glossary', 'glossaryid' => $gl->instance,
            'glossarycategoryid' => 0, 'grade' => 10, 'grademethod' => 1, 'param1' => 0, 'param2' => 0, 'shuffle' => 1, 'timeopen' => 0, 'timeclose' => 0,
            'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completiongradeitemnumber' => 0, 'completionusegrade' => 1]);
        $ids['jogo'] = $game->coursemodule;
    }

    // Certificado e conclusão.
    $avaliacao = fx_add_module($course, 'feedback', 4, ['name' => 'Avaliação do curso', 'anonymous' => 1, 'completionsubmit' => 1,
        'completion' => COMPLETION_TRACKING_AUTOMATIC, 'page_after_submit_editor' => ['text' => 'Obrigado', 'format' => FORMAT_HTML, 'itemid' => 0], 'page_after_submit' => 'Obrigado', 'page_after_submitformat' => FORMAT_HTML, 'site_after_submit' => '', 'multiple_submit' => 0, 'publish_stats' => 0, 'email_notification' => 0]);
    $ids['avaliacao'] = $avaliacao->coursemodule;
    fx_add_feedback_item($avaliacao->instance);
    $req = $broken ? [$ids['quiz1']] : [$ids['quiz1'], $ids['quiz2']];
    fx_set_availability($ids['avaliacao'], ['op' => '&', 'c' => array_map(fn($cm) => ['type' => 'completion', 'cm' => $cm, 'e' => 1], $req), 'showc' => array_fill(0, count($req), true)]);
    $licfb = fx_add_module($course, 'feedback', 4, ['name' => 'Licença para capacitação', 'anonymous' => 2, 'completionsubmit' => 0,
        'completion' => COMPLETION_TRACKING_NONE, 'page_after_submit_editor' => ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0], 'page_after_submit' => '', 'page_after_submitformat' => FORMAT_HTML, 'site_after_submit' => '', 'multiple_submit' => 0, 'publish_stats' => 0, 'email_notification' => 0]);
    $ids['licenca_fb'] = $licfb->coursemodule;
    fx_add_feedback_item($licfb->instance);
    if ($DB->record_exists('modules', ['name' => 'simplecertificate'])) {
        $courseitem = grade_item::fetch_course_item($course->id);
        $cert = fx_add_module($course, 'simplecertificate', 4, ['name' => 'Certificado digital de conclusão', 'certificatetext' => ['text' => 'Certificamos que {USERNAME} concluiu {COURSENAME}.', 'format' => FORMAT_HTML],
            'secondpagetext' => ['text' => '', 'format' => FORMAT_HTML], 'width' => 297, 'height' => 210, 'certificatetextx' => 40, 'certificatetexty' => 80, 'coursename' => '',
            'certdate' => -1, 'certgrade' => 0, 'gradefmt' => 1, 'emailothers' => '', 'emailteachers' => 0, 'reportcert' => 0, 'delivery' => 0, 'requiredtime' => 0,
            'printqrcode' => 1, 'qrcodefirstpage' => 0, 'codex' => 240, 'codey' => 150, 'enablesecondpage' => 0, 'secondpagex' => 40, 'secondpagey' => 3, 'certdatefmt' => '', 'timestartdatefmt' => '',
            'outcome' => 0, 'certificateimage' => '', 'secondimage' => '']);
        $ids['certificado'] = $cert->coursemodule;
        $c = [['type' => 'completion', 'cm' => $ids['avaliacao'], 'e' => 1], ['type' => 'completion', 'cm' => $ids['perfil'], 'e' => 1]];
        if (!$broken) {
            $c[] = ['type' => 'grade', 'id' => (int) $courseitem->id, 'min' => 60];
        } // Curso B: certificado sem a condição de nota.
        fx_set_availability($ids['certificado'], ['op' => '&', 'c' => $c, 'showc' => array_fill(0, count($c), true)]);
        $decl = fx_add_module($course, 'simplecertificate', 4, ['name' => 'Declaração licença para capacitação', 'certificatetext' => ['text' => 'Declaração para {USERNAME}.', 'format' => FORMAT_HTML],
            'secondpagetext' => ['text' => '', 'format' => FORMAT_HTML], 'width' => 297, 'height' => 210, 'certificatetextx' => 40, 'certificatetexty' => 80, 'coursename' => '',
            'certdate' => -1, 'certgrade' => 0, 'gradefmt' => 1, 'emailothers' => '', 'emailteachers' => 0, 'reportcert' => 0, 'delivery' => 0, 'requiredtime' => 0,
            'printqrcode' => 0, 'qrcodefirstpage' => 0, 'codex' => 240, 'codey' => 150, 'enablesecondpage' => 0, 'secondpagex' => 40, 'secondpagey' => 3, 'certdatefmt' => '', 'timestartdatefmt' => '',
            'outcome' => 0, 'certificateimage' => '', 'secondimage' => '']);
        $ids['declaracao'] = $decl->coursemodule;
    }

    // Curso B: dependência circular entre duas páginas e seção 4 oculta.
    if ($broken) {
        $pa = fx_add_module($course, 'page', 1, ['name' => 'Ciclo A', 'page' => ['text' => '<p>A</p>', 'format' => FORMAT_HTML, 'itemid' => 0], 'content' => '<p>A</p>', 'contentformat' => FORMAT_HTML, 'display' => RESOURCELIB_DISPLAY_OPEN, 'printintro' => 0, 'printlastmodified' => 0,
            'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionview' => 1])->coursemodule;
        $pb = fx_add_module($course, 'page', 1, ['name' => 'Ciclo B', 'page' => ['text' => '<p>B</p>', 'format' => FORMAT_HTML, 'itemid' => 0], 'content' => '<p>B</p>', 'contentformat' => FORMAT_HTML, 'display' => RESOURCELIB_DISPLAY_OPEN, 'printintro' => 0, 'printlastmodified' => 0,
            'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionview' => 1])->coursemodule;
        fx_set_availability($pa, ['op' => '&', 'c' => [['type' => 'completion', 'cm' => $pb, 'e' => 1]], 'showc' => [true]]);
        fx_set_availability($pb, ['op' => '&', 'c' => [['type' => 'completion', 'cm' => $pa, 'e' => 1]], 'showc' => [true]]);
        $ids['cicloA'] = $pa;
        $ids['cicloB'] = $pb;
        // Arquivo cujo conteúdo será removido do armazenamento (simula perda no filedir).
        $res = fx_add_module($course, 'resource', 2, ['name' => 'Apostila (arquivo perdido)', 'display' => RESOURCELIB_DISPLAY_AUTO, 'printintro' => 0, 'files' => 0]);
        $ids['apostila'] = $res->coursemodule;
        $fs = get_file_storage();
        $file = $fs->create_file_from_string(['contextid' => context_module::instance($res->coursemodule)->id, 'component' => 'mod_resource', 'filearea' => 'content',
            'itemid' => 0, 'filepath' => '/', 'filename' => 'apostila.txt'], 'conteudo de teste ' . $course->id);
        $ids['apostila_hash'] = $file->get_contenthash();
    }

    // Blocos: progresso + HTML institucionais (curso B sem "Acessibilidade").
    fx_add_block($course, 'completion_progress');
    fx_add_block($course, 'html', ['title' => 'Atenção ao prazo!!!', 'text' => '<p>Em {QTD_DIAS_FIM} dias este curso se encerrará.</p>', 'format' => FORMAT_HTML]);
    fx_add_block($course, 'html', ['title' => 'Dúvidas técnicas? Podemos ajudar!', 'text' => '<p>Suporte.</p>', 'format' => FORMAT_HTML]);
    if (!$broken) {
        fx_add_block($course, 'html', ['title' => 'Acessibilidade', 'text' => '<p>Recursos de acessibilidade.</p>', 'format' => FORMAT_HTML]);
    }

    // Autoinscrição.
    $plugin = enrol_get_plugin('self');
    $instances = enrol_get_instances($course->id, false);
    $self = null;
    foreach ($instances as $inst) {
        if ($inst->enrol === 'self') {
            $self = $inst;
        }
    }
    if (!$self) {
        $id = $plugin->add_instance($course, ['status' => ENROL_INSTANCE_ENABLED, 'roleid' => 5]);
        $self = $DB->get_record('enrol', ['id' => $id]);
    }
    $plugin->update_instance($self, (object) ['status' => $broken ? ENROL_INSTANCE_DISABLED : ENROL_INSTANCE_ENABLED, 'enrolstartdate' => $broken ? 0 : $course->startdate,
        'enrolenddate' => $broken ? 0 : $course->enddate, 'customint6' => 1, 'roleid' => 5, 'name' => '']);

    // Livro de notas: curso A sem "excluir notas vazias" (o padrão do Moodle é 1!); curso B com.
    $root = grade_category::fetch_course_category($course->id);
    $root->aggregateonlygraded = $broken ? 1 : 0;
    $root->aggregation = 13;
    $root->update();
    rebuild_course_cache($course->id, true);
    return [$course, $ids];
}

[$a, $idsa] = fx_build_course('MOOC_TESTE_A', 'Curso A — referência', false, $cat->id, $start, $end);
[$b, $idsb] = fx_build_course('MOOC_TESTE_B', 'Curso B — inconsistente', true, $cat->id, $start, $end);

cli_writeln(json_encode(['A' => ['courseid' => $a->id, 'cmids' => $idsa], 'B' => ['courseid' => $b->id, 'cmids' => $idsb]], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
