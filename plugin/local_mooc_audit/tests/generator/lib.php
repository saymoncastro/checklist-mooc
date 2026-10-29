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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->libdir . '/blocklib.php');
require_once($CFG->libdir . '/pagelib.php');
require_once($CFG->libdir . '/resourcelib.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

/**
 * Data generator for local_mooc_audit: the two reference courses of the proof of concept.
 *
 *   Course A — configured according to the institutional policy (every rule CONFORME).
 *   Course B — deliberately inconsistent (known, documented problems).
 *
 * Used by PHPUnit (characterisation and rule tests) and by cli/make_fixtures.php (lab only), so there is a single
 * definition of the fixtures. Everything is created through Moodle APIs (create_course, create_module, block_manager,
 * enrol plugins, grade API); the only direct inserts are feedback items (no public API) and the deliberate removal
 * of a file's content from the file store (to simulate a lost file).
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_mooc_audit_generator extends component_generator_base {
    /** @var string Name of the target category required by the default policy. */
    const CATEGORY_NAME = 'Inscrições Abertas';

    /**
     * Course A: reference course.
     *
     * @param int $year reoffer year (dates 01/01 → 31/12 of that year)
     * @return array [stdClass course, array logical name => cmid]
     */
    public function create_course_a(int $year): array {
        return $this->build('MOOC_TESTE_A', 'Curso A — referência', false, $year);
    }

    /**
     * Course B: inconsistent course.
     *
     * @param int $year reoffer year
     * @return array [stdClass course, array logical name => cmid]
     */
    public function create_course_b(int $year): array {
        return $this->build('MOOC_TESTE_B', 'Curso B — inconsistente', true, $year);
    }

    /**
     * Whether a module plugin is installed (third-party dependencies are optional).
     *
     * @param string $modname
     * @return bool
     */
    public static function has_module(string $modname): bool {
        global $DB;
        return $DB->record_exists('modules', ['name' => $modname]);
    }

    /**
     * The category required by the default policy (created on demand).
     *
     * @return core_course_category
     */
    public function target_category(): core_course_category {
        global $DB;
        $cat = $DB->get_record('course_categories', ['name' => self::CATEGORY_NAME]);
        if ($cat) {
            return core_course_category::get($cat->id);
        }
        return core_course_category::create(['name' => self::CATEGORY_NAME]);
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
    public function add_module(stdClass $course, string $modname, int $section, array $data): stdClass {
        global $DB;
        $module = $DB->get_record('modules', ['name' => $modname], '*', MUST_EXIST);
        $mi = (object) array_merge([
            'modulename' => $modname, 'module' => $module->id, 'course' => $course->id, 'section' => $section, 'visible' => 1,
            'visibleoncoursepage' => 1, 'introeditor' => ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0], 'cmidnumber' => '',
            'completion' => COMPLETION_TRACKING_NONE, 'completionview' => 0, 'completiongradeitemnumber' => null,
                'availability' => null,
            'groupmode' => 0, 'groupingid' => 0, 'showdescription' => 0,
        ], $data);
        return create_module($mi);
    }

    /**
     * Set the availability JSON of a course module (same effect as the editing form).
     *
     * @param int $cmid
     * @param array|null $tree
     */
    public function set_availability(int $cmid, ?array $tree): void {
        global $DB;
        $DB->set_field('course_modules', 'availability', $tree === null ? null : json_encode($tree), ['id' => $cmid]);
    }

    /**
     * Add n true/false questions to a quiz using the core question generator.
     *
     * @param stdClass $quizinfo moduleinfo returned by add_module
     * @param int $n
     */
    public function add_questions(stdClass $quizinfo, int $n): void {
        global $DB;
        $context = context_module::instance($quizinfo->coursemodule);
        $qgen = $this->datagenerator->get_plugin_generator('core_question');
        $cat = $qgen->create_question_category(['contextid' => $context->id]);
        $quiz = $DB->get_record('quiz', ['id' => $quizinfo->instance], '*', MUST_EXIST);
        for ($i = 1; $i <= $n; $i++) {
            $q = $qgen->create_question('truefalse', null, ['category' => $cat->id, 'name' => "Q$i"]);
            quiz_add_quiz_question($q->id, $quiz);
        }
        \mod_quiz\quiz_settings::create($quiz->id)->get_grade_calculator()->recompute_quiz_sumgrades();
    }

    /**
     * Insert a simple item into a feedback (there is no public API for this).
     *
     * @param int $feedbackid
     */
    public function add_feedback_item(int $feedbackid): void {
        global $DB;
        $DB->insert_record('feedback_item', (object) ['feedback' => $feedbackid, 'template' => 0, 'name' => 'Comentários',
            'label' => '',
            'presentation' => '', 'typ' => 'textarea', 'hasvalue' => 1, 'position' => 1, 'required' => 0, 'dependitem' => 0,
            'dependvalue' => '', 'options' => '']);
    }

    /**
     * Add a block instance to the course page.
     *
     * @param stdClass $course
     * @param string $blockname
     * @param array|null $config
     */
    public function add_block(stdClass $course, string $blockname, ?array $config = null): void {
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
            $inst = $DB->get_records(
                'block_instances',
                ['blockname' => $blockname, 'parentcontextid' => $context->id],
                'id DESC',
                '*',
                0,
                1
            );
            $inst = reset($inst);
            $inst->configdata = base64_encode(serialize((object) $config));
            $DB->update_record('block_instances', $inst);
        }
    }

    /**
     * Remove the content of a stored file from the file store, keeping its record (simulates a lost file).
     *
     * @param stored_file $file
     */
    public function remove_file_content(stored_file $file): void {
        global $CFG;
        $hash = $file->get_contenthash();
        $path = $CFG->dataroot . '/filedir/' . substr($hash, 0, 2) . '/' . substr($hash, 2, 2) . '/' . $hash;
        if (file_exists($path)) {
            unlink($path);
        }
    }

    /**
     * Build one course.
     *
     * @param string $shortname
     * @param string $fullname
     * @param bool $broken whether to introduce the known problems
     * @param int $year
     * @return array [course, map of logical name => cmid]
     */
    protected function build(string $shortname, string $fullname, bool $broken, int $year): array {
        global $DB;
        $tz = new DateTimeZone(core_date::get_server_timezone());
        $start = (new DateTime("$year-01-01 00:00", $tz))->getTimestamp();
        $end = (new DateTime("$year-12-31 23:59", $tz))->getTimestamp();
        $catid = $this->target_category()->id;

        $summary = '<p><strong>Nome do curso:</strong> ' . $fullname . '</p><p><strong>Carga horária:</strong> 30 horas</p>'
            . '<p><a href="https://mooc.cefor.ifes.edu.br/moodle/login/signup.php"><button>Criar Conta</button></a></p>'
            . '<p><a href="https://mooc.cefor.ifes.edu.br/moodle/course/index.php?categoryid=7">'
            . '<button>Turmas anteriores</button></a></p>';
        $course = create_course((object) [
            'fullname' => $fullname, 'shortname' => $shortname, 'category' => $catid, 'format' => 'topics', 'numsections' => 4,
            'startdate' => $broken ? $start - 365 * DAYSECS : $start, 'enddate' => $broken ? $start + 180 * DAYSECS : $end,
            'visible' => 1, 'enablecompletion' => 1, 'showcompletionconditions' => 1, 'summary' => $summary,
                'summaryformat' => FORMAT_HTML,
            'hiddensections' => $broken ? 0 : 1, 'lang' => 'pt_br',
        ]);
        $ids = [];

        // Section names.
        $names = [1 => 'Boas-vindas', 2 => 'Conteúdos', 3 => $broken ? 'Topic 3' : 'Avaliação', 4 => 'Certificado e conclusão'];
        foreach ($names as $num => $name) {
            $sec = $DB->get_record('course_sections', ['course' => $course->id, 'section' => $num]);
            course_update_section($course, $sec, ['name' => $name]);
        }

        $pagedefaults = ['display' => RESOURCELIB_DISPLAY_OPEN, 'printintro' => 0, 'printlastmodified' => 0];
        $page = function (string $name, string $html, array $extra = []) use ($pagedefaults) {
            return array_merge(['name' => $name, 'page' => ['text' => $html, 'format' => FORMAT_HTML, 'itemid' => 0],
                'content' => $html, 'contentformat' => FORMAT_HTML], $pagedefaults, $extra);
        };

        // Seção 0: área do curso.
        $infohtml = '<p><strong>Nome do curso:</strong> ' . $fullname . '</p><p><strong>Carga horária:</strong> 30 horas</p>';
        $ids['info'] = $this->add_module($course, 'page', 0, $page('Informações sobre o curso', $infohtml))->coursemodule;
        $ids['forum'] = $this->add_module($course, 'forum', 0, ['name' => 'Espaço de interação', 'type' => 'general',
            'forcesubscribe' => 0,
            'grade_forum' => 0, 'scale' => 0, 'assessed' => 0, 'completion' => $broken ? COMPLETION_TRACKING_MANUAL
                : COMPLETION_TRACKING_NONE,
            'maxbytes' => 0, 'maxattachments' => 1, 'completionposts' => 0, 'completionreplies' => 0,
                'completiondiscussions' => 0])->coursemodule;
        $perfil = $this->add_module($course, 'feedback', 0, $this->feedback_data(
            'Pesquisa de Perfil',
            1,
            1,
            COMPLETION_TRACKING_AUTOMATIC
        ));
        $ids['perfil'] = $perfil->coursemodule;
        $this->add_feedback_item($perfil->instance);
        $ids['licenca_label'] = $this->add_module(
            $course,
            'label',
            0,
            ['introeditor' => ['text' => '<p>Licença para capacitação: se você é servidor público...</p>',
            'format' => FORMAT_HTML, 'itemid' => 0], 'completion' => $broken ? COMPLETION_TRACKING_MANUAL
            : COMPLETION_TRACKING_NONE]
        )->coursemodule;

        // Conteúdos.
        $ids['pagina1'] = $this->add_module($course, 'page', 1, $page(
            'Conceitos iniciais',
            '<p>Texto do conteúdo 1.</p>',
            ['completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionview' => 1]
        ))->coursemodule;
        $ids['pagina2'] = $this->add_module($course, 'page', 2, $page(
            'Aprofundamento',
            '<p>Texto do conteúdo 2.</p>',
            ['completion' => $broken ? COMPLETION_TRACKING_NONE : COMPLETION_TRACKING_AUTOMATIC, 'completionview' => $broken ? 0
                : 1]
        ))->coursemodule;
        if ($broken) {
            $ids['pagina_vazia'] = $this->add_module($course, 'page', 2, $page('Página sem conteúdo', '<p></p>'))->coursemodule;
            $ids['pagina_img'] = $this->add_module($course, 'page', 2, $page(
                'Página com imagem perdida',
                '<p><img src="@@PLUGINFILE@@/banner.png" alt="banner"></p>'
            ))->coursemodule;
            $ids['pagina_oculta'] = $this->add_module($course, 'page', 2, $page(
                'Leitura obrigatória (oculta)',
                '<p>Obrigatória.</p>',
                ['visible' => 0, 'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionview' => 1]
            ))->coursemodule;
        }

        // Avaliação: dois questionários (50 + 50 = 100; no curso B 50 + 40 = 90 e 1 tentativa no primeiro).
        $q1 = $this->add_module($course, 'quiz', 3, $this->quiz_data('Questionário 1', $broken ? 1 : 2, 50, $broken ? 0 : $end));
        $ids['quiz1'] = $q1->coursemodule;
        $this->add_questions($q1, 5);
        $q2 = $this->add_module($course, 'quiz', 3, $this->quiz_data('Questionário 2', 2, $broken ? 40 : 50, $end));
        $ids['quiz2'] = $q2->coursemodule;
        if (!$broken) {
            $this->add_questions($q2, 5);
        } // Curso B: questionário 2 sem questões.
        // Quiz 1 restricted to reading page 1 (valid); in B additionally to page 2 (no completion) and to a hidden page.
        $c = [['type' => 'completion', 'cm' => $ids['pagina1'], 'e' => 1]];
        if ($broken) {
            $c[] = ['type' => 'completion', 'cm' => $ids['pagina2'], 'e' => 1];
            $c[] = ['type' => 'completion', 'cm' => $ids['pagina_oculta'], 'e' => 1];
        }
        $this->set_availability($ids['quiz1'], ['op' => '&', 'c' => $c, 'showc' => array_fill(0, count($c), true)]);

        // Glossário + jogo (curso B): glossário vazio como fonte de palavras cruzadas.
        if ($broken && self::has_module('game')) {
            $gl = $this->add_module($course, 'glossary', 3, ['name' => 'Glossário de termos', 'displayformat' => 'dictionary',
                'mainglossary' => 0,
                'globalglossary' => 0, 'allowduplicatedentries' => 0, 'showspecial' => 1, 'showalphabet' => 1, 'showall' => 1,
                    'allowcomments' => 0,
                'allowprintview' => 1, 'usedynalink' => 0, 'defaultapproval' => 1, 'entbypage' => 10, 'editalways' => 0,
                    'rsstype' => 0,
                'rssarticles' => 0, 'assessed' => 0, 'scale' => 0]);
            $ids['glossario'] = $gl->coursemodule;
            $game = $this->add_module($course, 'game', 3, ['name' => 'Palavras cruzadas', 'gamekind' => 'cross',
                'sourcemodule' => 'glossary',
                'glossaryid' => $gl->instance, 'glossarycategoryid' => 0, 'grade' => 10, 'grademethod' => 1, 'param1' => 0,
                    'param2' => 0, 'shuffle' => 1,
                'timeopen' => 0, 'timeclose' => 0, 'completion' => COMPLETION_TRACKING_AUTOMATIC,
                    'completiongradeitemnumber' => 0, 'completionusegrade' => 1]);
            $ids['jogo'] = $game->coursemodule;
        }

        // Certificado e conclusão.
        $avaliacao = $this->add_module($course, 'feedback', 4, $this->feedback_data(
            'Avaliação do curso',
            1,
            1,
            COMPLETION_TRACKING_AUTOMATIC
        ));
        $ids['avaliacao'] = $avaliacao->coursemodule;
        $this->add_feedback_item($avaliacao->instance);
        $req = $broken ? [$ids['quiz1']] : [$ids['quiz1'], $ids['quiz2']];
        $this->set_availability($ids['avaliacao'], ['op' => '&', 'c' => array_map(fn($cm) => ['type' => 'completion',
            'cm' => $cm, 'e' => 1], $req),
            'showc' => array_fill(0, count($req), true)]);
        $licfb = $this->add_module($course, 'feedback', 4, $this->feedback_data(
            'Licença para capacitação',
            2,
            0,
            COMPLETION_TRACKING_NONE,
            ''
        ));
        $ids['licenca_fb'] = $licfb->coursemodule;
        $this->add_feedback_item($licfb->instance);
        if (self::has_module('simplecertificate')) {
            $courseitem = grade_item::fetch_course_item($course->id);
            $cert = $this->add_module($course, 'simplecertificate', 4, $this->certificate_data(
                'Certificado digital de conclusão',
                'Certificamos que {USERNAME} concluiu {COURSENAME}.',
                1
            ));
            $ids['certificado'] = $cert->coursemodule;
            $c = [['type' => 'completion', 'cm' => $ids['avaliacao'], 'e' => 1], ['type' => 'completion',
                'cm' => $ids['perfil'], 'e' => 1]];
            if (!$broken) {
                $c[] = ['type' => 'grade', 'id' => (int) $courseitem->id, 'min' => 60];
            } // Curso B: certificado sem a condição de nota.
            $this->set_availability($ids['certificado'], ['op' => '&', 'c' => $c, 'showc' => array_fill(0, count($c), true)]);
            $decl = $this->add_module($course, 'simplecertificate', 4, $this->certificate_data(
                'Declaração licença para capacitação',
                'Declaração para {USERNAME}.',
                0
            ));
            $ids['declaracao'] = $decl->coursemodule;
        }

        // Curso B: dependência circular entre duas páginas e arquivo perdido.
        if ($broken) {
            $pa = $this->add_module($course, 'page', 1, $page(
                'Ciclo A',
                '<p>A</p>',
                ['completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionview' => 1]
            ))->coursemodule;
            $pb = $this->add_module($course, 'page', 1, $page(
                'Ciclo B',
                '<p>B</p>',
                ['completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionview' => 1]
            ))->coursemodule;
            $this->set_availability($pa, ['op' => '&', 'c' => [['type' => 'completion', 'cm' => $pb, 'e' => 1]],
                'showc' => [true]]);
            $this->set_availability($pb, ['op' => '&', 'c' => [['type' => 'completion', 'cm' => $pa, 'e' => 1]],
                'showc' => [true]]);
            $ids['cicloA'] = $pa;
            $ids['cicloB'] = $pb;
            $res = $this->add_module($course, 'resource', 2, ['name' => 'Apostila (arquivo perdido)',
                'display' => RESOURCELIB_DISPLAY_AUTO, 'printintro' => 0, 'files' => 0]);
            $ids['apostila'] = $res->coursemodule;
            $fs = get_file_storage();
            $file = $fs->create_file_from_string(['contextid' => context_module::instance($res->coursemodule)->id,
                'component' => 'mod_resource',
                'filearea' => 'content', 'itemid' => 0, 'filepath' => '/', 'filename' => 'apostila.txt'], 'conteudo de teste '
                    . $course->id);
            $ids['apostila_hash'] = $file->get_contenthash();
            $this->remove_file_content($file);
        }

        // Blocos: progresso + HTML institucionais (curso B sem "Acessibilidade").
        if ($DB->record_exists('block', ['name' => 'completion_progress'])) {
            $this->add_block($course, 'completion_progress');
        }
        $this->add_block($course, 'html', ['title' => 'Atenção ao prazo!!!',
            'text' => '<p>Em {QTD_DIAS_FIM} dias este curso se encerrará.</p>', 'format' => FORMAT_HTML]);
        $this->add_block($course, 'html', ['title' => 'Dúvidas técnicas? Podemos ajudar!', 'text' => '<p>Suporte.</p>',
            'format' => FORMAT_HTML]);
        if (!$broken) {
            $this->add_block($course, 'html', ['title' => 'Acessibilidade', 'text' => '<p>Recursos de acessibilidade.</p>',
                'format' => FORMAT_HTML]);
        }

        // Autoinscrição.
        $plugin = enrol_get_plugin('self');
        $self = null;
        foreach (enrol_get_instances($course->id, false) as $inst) {
            if ($inst->enrol === 'self') {
                $self = $inst;
            }
        }
        if (!$self) {
            $id = $plugin->add_instance($course, ['status' => ENROL_INSTANCE_ENABLED, 'roleid' => 5]);
            $self = $DB->get_record('enrol', ['id' => $id]);
        }
        $plugin->update_instance($self, (object) ['status' => $broken ? ENROL_INSTANCE_DISABLED : ENROL_INSTANCE_ENABLED,
            'enrolstartdate' => $broken ? 0 : $course->startdate, 'enrolenddate' => $broken ? 0 : $course->enddate,
                'customint6' => 1, 'roleid' => 5,
            'name' => '', 'expirynotify' => 0, 'expirythreshold' => $self->expirythreshold, 'notifyall' => 0]);

        // Livro de notas: curso A sem "excluir notas vazias" (o padrão do Moodle é 1); curso B com.
        $root = grade_category::fetch_course_category($course->id);
        $root->aggregateonlygraded = $broken ? 1 : 0;
        $root->aggregation = 13;
        $root->update();
        rebuild_course_cache($course->id, true);
        return [get_course($course->id), $ids];
    }

    /**
     * Module data for a quiz.
     *
     * @param string $name
     * @param int $attempts
     * @param float $grade
     * @param int $timeclose
     * @return array
     */
    public function quiz_data(string $name, int $attempts, float $grade, int $timeclose): array {
        return ['name' => $name, 'introeditor' => ['text' => '<p>Tentativas permitidas: 2. Valor: 50 pontos.</p>',
            'format' => FORMAT_HTML, 'itemid' => 0],
            'attempts' => $attempts, 'grade' => $grade, 'grademethod' => QUIZ_GRADEHIGHEST, 'timeclose' => $timeclose,
                'timeopen' => 0, 'timelimit' => 0,
            'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completiongradeitemnumber' => 0, 'completionusegrade' => 1,
                'quizpassword' => '',
            'preferredbehaviour' => 'deferredfeedback', 'questionsperpage' => 1, 'shuffleanswers' => 1, 'decimalpoints' => 2,
                'questiondecimalpoints' => -1,
            'overduehandling' => 'autosubmit', 'graceperiod' => 0, 'reviewattempt' => 69904, 'reviewcorrectness' => 4352,
                'reviewmarks' => 4352,
            'reviewspecificfeedback' => 4352, 'reviewgeneralfeedback' => 4352, 'reviewrightanswer' => 4352,
                'reviewoverallfeedback' => 4352,
            'reviewmaxmarks' => 69904];
    }

    /**
     * Module data for a feedback.
     *
     * @param string $name
     * @param int $anonymous
     * @param int $completionsubmit
     * @param int $completion
     * @param string $aftersubmit
     * @return array
     */
    public function feedback_data(
        string $name,
        int $anonymous,
        int $completionsubmit,
        int $completion,
        string $aftersubmit = 'Obrigado'
    ): array {
        return ['name' => $name, 'anonymous' => $anonymous, 'completionsubmit' => $completionsubmit, 'completion' => $completion,
            'page_after_submit_editor' => ['text' => $aftersubmit, 'format' => FORMAT_HTML, 'itemid' => 0],
                'page_after_submit' => $aftersubmit,
            'page_after_submitformat' => FORMAT_HTML, 'site_after_submit' => '', 'multiple_submit' => 0, 'publish_stats' => 0,
                'email_notification' => 0];
    }

    /**
     * Module data for a simplecertificate.
     *
     * @param string $name
     * @param string $text
     * @param int $qrcode
     * @return array
     */
    public function certificate_data(string $name, string $text, int $qrcode): array {
        return ['name' => $name, 'certificatetext' => ['text' => $text, 'format' => FORMAT_HTML],
            'secondpagetext' => ['text' => '', 'format' => FORMAT_HTML],
            'width' => 297, 'height' => 210, 'certificatetextx' => 40, 'certificatetexty' => 80, 'coursename' => '',
                'certdate' => -1, 'certgrade' => 0,
            'gradefmt' => 1, 'emailothers' => '', 'emailteachers' => 0, 'reportcert' => 0, 'delivery' => 0, 'requiredtime' => 0,
                'printqrcode' => $qrcode,
            'qrcodefirstpage' => 0, 'codex' => 240, 'codey' => 150, 'enablesecondpage' => 0, 'secondpagex' => 40,
                'secondpagey' => 3, 'certdatefmt' => '',
            'timestartdatefmt' => '', 'outcome' => 0, 'certificateimage' => '', 'secondimage' => ''];
    }
}
