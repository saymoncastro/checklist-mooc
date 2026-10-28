#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
moodle_api_client.py — cliente de integração ISOLADO e SOMENTE LEITURA para a prova comparativa
"auditoria via Web Services do Moodle 4.5" (Cefor/Ifes).

Este cliente NÃO foi executado contra nenhum Moodle nesta investigação (não havia instalação de
homologação disponível). Ele existe para:
  1. fixar o contrato de chamadas (funções, parâmetros, campos) levantado no código-fonte do Moodle 4.5;
  2. permitir a prova comparativa assim que houver um ambiente de homologação e um token de serviço;
  3. produzir um "retrato" JSON da sala no MESMO formato de inventário do protótipo MBZ, para
     comparar regra a regra.

Segurança / limites (ver relatório, seção 7):
  - Somente funções da ALLOWLIST abaixo são chamadas; todas são de leitura (nenhuma *_view_* que
    dispara eventos/conclusão, nenhuma enrol/submit/process/update).
  - Token lido de variável de ambiente (MOODLE_WS_TOKEN), nunca de argumento de linha de comando.
  - HTTPS obrigatório (recusa http://), timeout curto, sem redirecionamentos.
  - Nenhum dado pessoal é persistido além de id/nome de papel dos professores (item 3 do checklist).

Uso:
  export MOODLE_WS_TOKEN=...
  python3 -I moodle_api_client.py --site https://homolog.example/moodle --course 123 --out retrato.json
  python3 -I moodle_api_client.py --site ... --course 123 --test-user 456   # visão de um usuário de teste
"""
import argparse
import json
import os
import sys
import urllib.parse
import urllib.request
import datetime as dt

# Funções de LEITURA levantadas em lib/db/services.php e mod/*/db/services.php do MOODLE_405_STABLE.
ALLOWLIST = {
    # sítio / ambiente
    "core_webservice_get_site_info",            # versão, release, funções disponíveis ao serviço, advancedfeatures (enablecompletion, enablebadges...)
    # curso
    "core_course_get_courses_by_field",         # datas, categoria, visible, summary, courseformatoptions (hiddensections), enablecompletion
    "core_course_get_contents",                 # seções + módulos: visible, uservisible, availability (JSON, exige viewhiddenactivities), completion, dates, contents (arquivos)
    "core_course_get_course_module",            # registro completo do cm (completion*, availability, grade, gradepass, gradecat) com course:manageactivities
    "core_course_get_course_content_items",     # tipos de atividade disponíveis no curso (= plugins mod instalados/habilitados)
    "core_block_get_course_blocks",             # blocos visíveis da página do curso + configs (block_html: title/text)
    "core_enrol_get_course_enrolment_methods",  # métodos de inscrição HABILITADOS (id, type, name, status) — sem datas
    "enrol_self_get_instance_info",             # instância self: id, courseid, type, name, status, enrolpassword? — sem datas
    "core_enrol_get_enrolled_users",            # participantes com papéis (item 3: professores)
    # avaliação
    "gradereport_user_get_grade_items",         # itens de nota (grademax, categoryid, weightraw, hidden) — por usuário; userid=0 exige grade:viewall
    "core_grades_get_grade_tree",               # árvore categorias/itens (sem agregação)
    "core_grades_get_gradeitems",               # id/nome/categoria dos itens
    "mod_quiz_get_quizzes_by_courses",          # attempts, timeopen/close, grade, sumgrades, grademethod, hasquestions, completionattemptsexhausted
    "mod_quiz_get_quiz_access_information",     # motivos de bloqueio de acesso para o usuário do token
    "mod_feedback_get_feedbacks_by_courses",    # configurações da pesquisa (via exporter; campos opcionais só com edititems)
    "mod_feedback_get_items",
    "mod_assign_get_assignments",
    # recursos
    "mod_forum_get_forums_by_courses",          # type, completion*, duedate/cutoffdate
    "mod_page_get_pages_by_courses",            # content HTML
    "mod_url_get_urls_by_courses",              # externalurl
    "mod_label_get_labels_by_courses",          # intro
    "mod_book_get_books_by_courses",
    "mod_resource_get_resources_by_courses",    # contentfiles (fileurl, filesize, mimetype)
    "mod_folder_get_folders_by_courses",
    "mod_glossary_get_glossaries_by_courses",
    "mod_glossary_get_entries_by_letter",       # count de entradas (glossário vazio?)
    "mod_data_get_databases_by_courses",
    # conclusão (por usuário)
    "core_completion_get_activities_completion_status",  # exige report/progress:view para outro usuário
    "core_completion_get_course_completion_status",
}

# Funções deliberadamente FORA da allowlist (escrevem ou disparam eventos/conclusão):
# mod_*_view_*, core_completion_update_activity_completion_status_manually, enrol_self_enrol_user,
# mod_quiz_start_attempt/process_attempt, mod_feedback_launch_feedback/process_page, core_course_*_course(s), ...


class MoodleReadOnlyClient:
    def __init__(self, site, token, timeout=20):
        if not site.lower().startswith('https://'):
            raise SystemExit('Recusado: o endereço do Moodle deve usar HTTPS.')
        self.endpoint = site.rstrip('/') + '/webservice/rest/server.php'
        self.token = token
        self.timeout = timeout
        self.calls = []   # auditoria das chamadas (função, parâmetros sem token, duração, status)

    def call(self, wsfunction, **params):
        if wsfunction not in ALLOWLIST:
            raise PermissionError(f'função fora da allowlist de leitura: {wsfunction}')
        data = {'wstoken': self.token, 'wsfunction': wsfunction, 'moodlewsrestformat': 'json'}
        data.update(_flatten(params))
        body = urllib.parse.urlencode(data).encode()
        req = urllib.request.Request(self.endpoint, data=body, method='POST',
                                     headers={'User-Agent': 'mooc-audit-poc/0.1'})
        t0 = dt.datetime.now()
        opener = urllib.request.build_opener(_NoRedirect())
        try:
            with opener.open(req, timeout=self.timeout) as resp:
                payload = json.loads(resp.read().decode('utf-8'))
        finally:
            self.calls.append({'function': wsfunction, 'params': params,
                               'ms': int((dt.datetime.now() - t0).total_seconds() * 1000)})
        if isinstance(payload, dict) and payload.get('exception'):
            # ex.: {"exception":"webservice_access_exception","errorcode":"accessexception",...}
            raise RuntimeError(f'{wsfunction}: {payload.get("errorcode")} — {payload.get("message")}')
        return payload


class _NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        raise RuntimeError(f'redirecionamento recusado para {newurl}')


def _flatten(params, prefix=''):
    """Converte dicts/listas aninhados no formato de parâmetros do REST do Moodle (a[0][b]=...)."""
    out = {}
    for k, v in params.items():
        key = f'{prefix}[{k}]' if prefix else k
        if isinstance(v, dict):
            out.update(_flatten(v, key))
        elif isinstance(v, (list, tuple)):
            for i, item in enumerate(v):
                if isinstance(item, (dict, list, tuple)):
                    out.update(_flatten({i: item}, key) if isinstance(item, dict) else {f'{key}[{i}]': item})
                else:
                    out[f'{key}[{i}]'] = item
        else:
            out[key] = v
    return out


def snapshot(client, courseid, test_user=None):
    """Retrato da sala no formato do inventário do protótipo MBZ (chaves equivalentes)."""
    S = {"source": "moodle-ws", "courseid": courseid, "collected_at": dt.datetime.now().isoformat(timespec='seconds'),
         "site": None, "course": None, "sections": [], "modules": {}, "blocks": None, "enrol_methods": None,
         "grade_items": None, "quizzes": None, "feedbacks": None, "forums": None, "urls": None, "resources": None,
         "glossaries": None, "teachers": None, "completion_test_user": None, "errors": []}

    def safe(key, fn, **kw):
        try:
            S[key] = client.call(fn, **kw)
        except Exception as e:  # cada chamada isolada: falta de permissão vira "não verificável", não aborta
            S["errors"].append({"key": key, "function": fn, "error": str(e)})

    safe("site", "core_webservice_get_site_info")
    safe("course", "core_course_get_courses_by_field", field='id', value=courseid)
    safe("contents", "core_course_get_contents", courseid=courseid, options=[{'name': 'includestealthmodules', 'value': 1}])
    safe("blocks", "core_block_get_course_blocks", courseid=courseid, returncontents=1)
    safe("enrol_methods", "core_enrol_get_course_enrolment_methods", courseid=courseid)
    safe("grade_items", "gradereport_user_get_grade_items", courseid=courseid, userid=0)
    safe("quizzes", "mod_quiz_get_quizzes_by_courses", courseids=[courseid])
    safe("feedbacks", "mod_feedback_get_feedbacks_by_courses", courseids=[courseid])
    safe("forums", "mod_forum_get_forums_by_courses", courseids=[courseid])
    safe("urls", "mod_url_get_urls_by_courses", courseids=[courseid])
    safe("resources", "mod_resource_get_resources_by_courses", courseids=[courseid])
    safe("glossaries", "mod_glossary_get_glossaries_by_courses", courseids=[courseid])
    safe("content_items", "core_course_get_course_content_items", courseid=courseid)
    # professores (item 3) — só id, nome e papéis
    try:
        users = client.call("core_enrol_get_enrolled_users", courseid=courseid,
                            options=[{'name': 'userfields', 'value': 'id,fullname'}])
        S["teachers"] = [{"id": u["id"], "fullname": u.get("fullname"), "roles": [r["shortname"] for r in u.get("roles", [])]}
                         for u in users if any(r["shortname"] in ('editingteacher', 'teacher') for r in u.get("roles", []))]
    except Exception as e:
        S["errors"].append({"key": "teachers", "function": "core_enrol_get_enrolled_users", "error": str(e)})
    # métodos self: detalhes (sem datas — lacuna conhecida)
    if isinstance(S.get("enrol_methods"), list):
        S["self_instances"] = []
        for m in S["enrol_methods"]:
            if m.get("type") == 'self':
                try:
                    S["self_instances"].append(client.call("enrol_self_get_instance_info", instanceid=m["id"]))
                except Exception as e:
                    S["errors"].append({"key": "self_instances", "function": "enrol_self_get_instance_info", "error": str(e)})
    # glossários: contagem de entradas (glossário vazio usado por jogos)
    if isinstance(S.get("glossaries"), dict):
        for g in S["glossaries"].get("glossaries", []):
            try:
                r = client.call("mod_glossary_get_entries_by_letter", id=g["id"], letter='ALL', **{'from': 0, 'limit': 1})
                g["entries_count"] = r.get("count")
            except Exception as e:
                S["errors"].append({"key": "glossary_entries", "function": "mod_glossary_get_entries_by_letter", "error": str(e)})
    # conclusão do usuário de teste (fase 4)
    if test_user:
        safe("completion_test_user", "core_completion_get_activities_completion_status", courseid=courseid, userid=test_user)
    # módulos indexados por cmid a partir de get_contents
    for sec in (S.get("contents") or []):
        S["sections"].append({"id": sec.get("id"), "section": sec.get("section"), "name": sec.get("name"), "visible": sec.get("visible"),
                              "uservisible": sec.get("uservisible"), "availabilityinfo": sec.get("availabilityinfo"),
                              "modules": [m.get("id") for m in sec.get("modules", [])]})
        for m in sec.get("modules", []):
            S["modules"][m["id"]] = {k: m.get(k) for k in ("id", "name", "modname", "instance", "contextid", "visible", "visibleoncoursepage",
                                                           "uservisible", "availability", "availabilityinfo", "completion", "completiondata",
                                                           "dates", "contentsinfo")}
            S["modules"][m["id"]]["files"] = [{"filename": c.get("filename"), "filesize": c.get("filesize"), "mimetype": c.get("mimetype"),
                                               "fileurl": c.get("fileurl")} for c in m.get("contents", []) if c.get("type") == 'file']
    S["calls"] = client.calls
    return S


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument('--site', required=True, help='URL do Moodle (https://...)')
    ap.add_argument('--course', type=int, required=True)
    ap.add_argument('--test-user', type=int, help='userid de um usuário de TESTE para consultar conclusão (fase 4)')
    ap.add_argument('--out', help='arquivo JSON de saída')
    a = ap.parse_args()
    token = os.environ.get('MOODLE_WS_TOKEN')
    if not token:
        raise SystemExit('Defina MOODLE_WS_TOKEN no ambiente (token de uma conta de serviço somente leitura).')
    client = MoodleReadOnlyClient(a.site, token)
    S = snapshot(client, a.course, a.test_user)
    out = json.dumps(S, ensure_ascii=False, indent=1, default=str)
    if a.out:
        with open(a.out, 'w', encoding='utf-8') as f:
            f.write(out)
    print(f'chamadas: {len(S["calls"])}; erros/permissões negadas: {len(S["errors"])}')
    for e in S["errors"]:
        print('  -', e["function"], '->', e["error"])


if __name__ == '__main__':
    main()
