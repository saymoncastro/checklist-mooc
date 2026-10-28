#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
mbz_inspect.py — PROTÓTIPO de inspeção somente-leitura de backups Moodle (.mbz)

Prova de conceito para o projeto "Auditoria automatizada de cursos MOOC" (Cefor/Ifes).
NÃO é a implementação definitiva. Objetivos:
  1. Demonstrar leitura segura do .mbz (gzip+tar ou zip) com limites e checagens.
  2. Normalizar os XML do formato "moodle2" para um modelo interno simples.
  3. Executar regras exploratórias (checklist institucional + descobertas adicionais)
     e produzir evidências reproduzíveis (arquivo, campo, valor).

Uso:
  python3 -I mbz_inspect.py <arquivo.mbz | diretório_extraído> [--out saida.json] [--policy politica.json]

Somente leitura: o .mbz original nunca é modificado; a extração ocorre em diretório temporário
(ou em --workdir). Nenhum conteúdo do backup é executado. Nenhum acesso à rede.
"""
import argparse
import base64
import datetime as dt
import difflib
import html
import json
import os
import re
import shutil
import sys
import tarfile
import tempfile
import zipfile
import unicodedata
import xml.etree.ElementTree as ET
from collections import Counter, defaultdict

NULL = '$@NULL@$'

# ----------------------------------------------------------------------------------
# POLÍTICA INSTITUCIONAL (parametrizável; valores abaixo são EXEMPLOS derivados do checklist)
# ----------------------------------------------------------------------------------
DEFAULT_POLICY = {
    "policy_version": "2027-decisoes-2026-10-28",
    "reoffer_year": None,                 # None = ano seguinte ao da auditoria (o processo de reoferta começa em out/nov do ano anterior)
    "reference_date": None,               # ISO; None = hoje
    "evaluation_moment": "backup",        # "backup" = auditoria do .mbz (regras de oferta viram ATENÇÃO); "restored" = sala nova (viram NÃO CONFORME)
    "required_start_date": "01/01",       # decisão institucional: início em 01/01 do ano da reoferta
    "allowed_end_dates": ["31/12"],       # decisão institucional: término sempre em 31/12
    "min_days_between_start_and_end": 30,
    "target_category_name": "Inscrições Abertas",
    "min_quiz_attempts": 2,
    "attempts_rule": "min",               # "min" (>= 2) ou "exact" (== 2) — AMBIGUIDADE do checklist
    "course_total_max": 100.0,
    "certificate_min_grade": 60,
    "forbidden_modules": ["glossary"],
    "required_forum_name": "Espaço de interação",
    "required_feedback_profile": "Pesquisa de Perfil",
    "required_feedback_evaluation": "Avaliação do curso",
    "required_certificate_module": "simplecertificate",
    "required_blocks": {
        "completion_progress": None,
        "html": ["Atenção ao prazo", "Dúvidas técnicas", "Acessibilidade"]
    },
    "summary_required_links": [
        "https://mooc.cefor.ifes.edu.br/moodle/login/signup.php",
        "https://mooc.cefor.ifes.edu.br/moodle/course/index.php?categoryid=7"
    ],
    "licence_label_text": "Licença para capacitação",
    "licence_feedback_name": "Licença para capacitação",
    "licence_certificate_name": "Declaração licença para capacitação",
    "certificate_expected_text": "O Instituto Federal do Espírito Santo, por meio do {CAMPUS}, certifica que {USERNAME} participou do curso {COURSENAME}, no período de {TIMESTART} a {TIMEEND}, com carga horária de {CARGA_HORARIA} horas.",
    "certificate_reference_image_hashes": [],   # sha1 (contenthash) das imagens oficiais frente/verso
    "origin_wwwroot": "https://mooc.cefor.ifes.edu.br/moodle",
    "core_modules": ["assign", "assignment", "book", "chat", "choice", "data", "feedback", "folder", "forum",
                     "glossary", "h5pactivity", "imscp", "label", "lesson", "lti", "page", "quiz", "resource",
                     "scorm", "survey", "url", "wiki", "workshop", "bigbluebuttonbn", "subsection"],
    "core_availability": ["completion", "date", "grade", "group", "grouping", "profile"],
    "core_blocks": ["html", "activity_modules", "calendar_month", "calendar_upcoming", "comments", "news_items",
                    "online_users", "recent_activity", "search_forums", "section_links", "activity_results",
                    "badges", "blog_menu", "blog_recent", "blog_tags", "completionstatus", "course_list",
                    "course_summary", "feedback", "glossary_random", "globalsearch", "login", "mentees",
                    "mnet_hosts", "myoverview", "myprofile", "navigation", "private_files", "recentlyaccesseditems",
                    "rss_client", "selfcompletion", "settings", "site_main_menu", "social_activities",
                    "starredcourses", "tag_flickr", "tag_youtube", "tags", "timeline", "lp", "admin_bookmarks",
                    "accessreview"],
    "core_qtypes": ["calculated", "calculatedmulti", "calculatedsimple", "ddimageortext", "ddmarker", "ddwtos",
                    "description", "essay", "gapselect", "match", "missingtype", "multianswer", "multichoice",
                    "numerical", "ordering", "random", "randomsamatch", "shortanswer", "truefalse"],
    "generic_section_names": r"^(topic|tópico|topico|seção|secao|section)\s*\d+$",
    "generic_link_texts": ["clique aqui", "aqui", "link", "saiba mais", "acesse", "clique", "veja", "leia mais"],
}

LIMITS = {"max_entries": 100000, "max_total_bytes": 6 * 1024 ** 3, "max_member_bytes": 2 * 1024 ** 3}

# ----------------------------------------------------------------------------------
# UTILIDADES
# ----------------------------------------------------------------------------------

def norm(s):
    """Normaliza texto para comparação: minúsculas, sem acentos, espaços colapsados."""
    if s is None:
        return ''
    s = unicodedata.normalize('NFKD', str(s))
    s = ''.join(c for c in s if not unicodedata.combining(c))
    return re.sub(r'\s+', ' ', s).strip().lower()


def strip_html(s):
    if not s:
        return ''
    s = re.sub(r'<(script|style)[^>]*>.*?</\1>', ' ', s, flags=re.S | re.I)
    s = re.sub(r'<[^>]+>', ' ', s)
    s = html.unescape(s)
    return re.sub(r'\s+', ' ', s).strip()


def ts(v):
    try:
        v = int(v)
    except (TypeError, ValueError):
        return None
    if v <= 0:
        return None
    return dt.datetime.fromtimestamp(v, dt.timezone(dt.timedelta(hours=-3))).strftime('%Y-%m-%d %H:%M')


def val(el, tag, default=None):
    if el is None:
        return default
    t = el.findtext(tag)
    if t is None or t == NULL:
        return default
    return t


def ival(el, tag, default=None):
    v = val(el, tag)
    try:
        return int(float(v))
    except (TypeError, ValueError):
        return default


def fval(el, tag, default=None):
    v = val(el, tag)
    try:
        return float(v)
    except (TypeError, ValueError):
        return default


def parse_xml(path):
    """Parsing defensivo: rejeita DOCTYPE/ENTITY (XXE) antes de entregar ao parser."""
    with open(path, 'rb') as f:
        head = f.read(8192)
    if b'<!DOCTYPE' in head or b'<!ENTITY' in head:
        raise ValueError(f'XML com DOCTYPE/ENTITY rejeitado: {path}')
    return ET.parse(path).getroot()


def php_unserialize(s):
    """Desserializador PHP mínimo (s,i,d,b,N,a,O) para configdata de blocos. Sem execução de código."""
    pos = 0

    def read(n):
        nonlocal pos
        r = s[pos:pos + n]
        pos += n
        return r

    def parse():
        nonlocal pos
        t = s[pos]
        if t == 'N':
            pos += 2
            return None
        if t in 'ib':
            end = s.index(';', pos)
            v = int(s[pos + 2:end]); pos = end + 1
            return bool(v) if t == 'b' else v
        if t == 'd':
            end = s.index(';', pos)
            v = float(s[pos + 2:end]); pos = end + 1
            return v
        if t == 's':
            colon = s.index(':', pos + 2)
            ln = int(s[pos + 2:colon])
            pos = colon + 2
            v = s[pos:pos + ln]
            pos += ln + 2
            return v
        if t in 'aO':
            if t == 'O':
                colon = s.index(':', pos + 2)
                ln = int(s[pos + 2:colon]); pos = colon + ln + 2   # pos+2 passa a apontar para o contador de propriedades
            colon = s.index(':', pos + 2)
            n = int(s[pos + 2:colon]); pos = colon + 2
            d = {}
            for _ in range(n):
                k = parse(); v = parse(); d[k] = v
            pos += 1
            return d
        raise ValueError('token desconhecido em serialização PHP: ' + t)

    # PHP serializa comprimentos de string em BYTES: o chamador passa a string decodificada em latin-1
    # (1 byte = 1 char) e aqui reconvertemos cada string para UTF-8.
    def fix(v):
        if isinstance(v, str):
            return v.encode('latin-1', errors='replace').decode('utf-8', errors='replace')
        if isinstance(v, dict):
            return {fix(k): fix(x) for k, x in v.items()}
        return v
    return fix(parse())


# ----------------------------------------------------------------------------------
# 1. LEITOR MBZ (seguro, somente leitura)
# ----------------------------------------------------------------------------------

def detect_format(path):
    with open(path, 'rb') as f:
        magic = f.read(4)
    if magic[:2] == b'\x1f\x8b':
        return 'tgz'
    if magic[:2] == b'PK':
        return 'zip'
    return 'unknown'


def _safe_name(name):
    if name.startswith('/') or name.startswith('\\'):
        return False
    parts = name.replace('\\', '/').split('/')
    if any(p == '..' for p in parts):
        return False
    if re.match(r'^[A-Za-z]:', name):
        return False
    return True


def safe_extract(mbz, dest):
    """Extrai o .mbz com checagens: sem caminhos absolutos/'..', sem links, limites de tamanho.
    Retorna relatório de integridade. Nunca executa nada do conteúdo."""
    rep = {"format": detect_format(mbz), "entries": 0, "bytes": 0, "rejected": [], "stream_error": None,
           "last_entry": None, "size_bytes": os.path.getsize(mbz)}
    os.makedirs(dest, exist_ok=True)
    if rep["format"] == 'tgz':
        try:
            with tarfile.open(mbz, mode='r|gz') as tf:   # streaming: não carrega índice completo
                for m in tf:
                    rep["last_entry"] = m.name
                    if rep["entries"] >= LIMITS["max_entries"]:
                        raise RuntimeError('limite de entradas excedido')
                    if not _safe_name(m.name) or m.issym() or m.islnk() or m.isdev():
                        rep["rejected"].append(m.name); continue
                    if m.isdir():
                        os.makedirs(os.path.join(dest, m.name), exist_ok=True); rep["entries"] += 1; continue
                    if not m.isfile():
                        rep["rejected"].append(m.name); continue
                    if m.size > LIMITS["max_member_bytes"] or rep["bytes"] + m.size > LIMITS["max_total_bytes"]:
                        raise RuntimeError('limite de bytes excedido')
                    target = os.path.join(dest, m.name)
                    os.makedirs(os.path.dirname(target), exist_ok=True)
                    src = tf.extractfile(m)
                    with open(target, 'wb') as out:
                        shutil.copyfileobj(src, out, 1024 * 1024)
                    rep["entries"] += 1; rep["bytes"] += m.size
        except Exception as e:  # tarfile.ReadError, EOFError, zlib.error, RuntimeError
            rep["stream_error"] = f'{type(e).__name__}: {e}'
    elif rep["format"] == 'zip':
        try:
            with zipfile.ZipFile(mbz) as zf:
                for m in zf.infolist():
                    rep["last_entry"] = m.filename
                    if not _safe_name(m.filename):
                        rep["rejected"].append(m.filename); continue
                    if m.is_dir():
                        os.makedirs(os.path.join(dest, m.filename), exist_ok=True); continue
                    if m.file_size > LIMITS["max_member_bytes"] or rep["bytes"] + m.file_size > LIMITS["max_total_bytes"]:
                        raise RuntimeError('limite de bytes excedido')
                    target = os.path.join(dest, m.filename)
                    os.makedirs(os.path.dirname(target), exist_ok=True)
                    with zf.open(m) as src, open(target, 'wb') as out:
                        shutil.copyfileobj(src, out, 1024 * 1024)
                    rep["entries"] += 1; rep["bytes"] += m.file_size
                bad = zf.testzip()
                if bad:
                    rep["stream_error"] = f'CRC inválido em {bad}'
        except Exception as e:
            rep["stream_error"] = f'{type(e).__name__}: {e}'
    else:
        rep["stream_error"] = 'formato desconhecido (nem gzip nem zip)'
    return rep


def check_archive_index(root):
    """Compara .ARCHIVE_INDEX (manifesto do tgz_packer) com o que foi efetivamente extraído."""
    idx = os.path.join(root, '.ARCHIVE_INDEX')
    out = {"present": os.path.exists(idx), "declared": None, "missing": [], "size_mismatch": [], "checked": 0}
    if not out["present"]:
        return out
    with open(idx, encoding='utf-8', errors='replace') as f:
        lines = f.read().splitlines()
    m = re.match(r'Moodle archive file index\. Count: (\d+)', lines[0] if lines else '')
    out["declared"] = int(m.group(1)) if m else None
    for ln in lines[1:]:
        parts = ln.split('\t')
        if len(parts) < 3:
            continue
        p, typ, size = parts[0], parts[1], parts[2]
        if typ != 'f':
            continue
        out["checked"] += 1
        fp = os.path.join(root, p)
        if not os.path.exists(fp):
            out["missing"].append(p)
        elif os.path.getsize(fp) != int(size):
            out["size_mismatch"].append((p, int(size), os.path.getsize(fp)))
    return out


# ----------------------------------------------------------------------------------
# 2. NORMALIZADOR (XML moodle2 -> modelo interno)
# ----------------------------------------------------------------------------------

MOD_TEXT_FIELDS = {
    'page': ['intro', 'content'], 'label': ['intro'], 'url': ['intro'], 'book': ['intro'], 'forum': ['intro'],
    'quiz': ['intro'], 'feedback': ['intro', 'page_after_submit'], 'resource': ['intro'], 'folder': ['intro'],
    'choice': ['intro'], 'glossary': ['intro'], 'data': ['intro'], 'game': ['intro'], 'hvp': ['intro'],
    'assign': ['intro'], 'simplecertificate': ['intro', 'certificatetext', 'secondpagetext'],
}


def load_model(root):
    M = {"root": root, "missing_files": [], "info": {}, "settings": {}, "course": {}, "sections": {},
         "modules": {}, "grade_items": {}, "gradebook": {}, "files": {}, "blocks": [], "enrols": [],
         "badges": [], "questions": {"categories": 0, "questions": 0, "qtypes": Counter()},
         "completion_defaults": {}, "parse_errors": []}

    def need(rel):
        p = os.path.join(root, rel)
        if not os.path.exists(p):
            M["missing_files"].append(rel)
            return None
        return p

    # --- moodle_backup.xml
    p = need('moodle_backup.xml')
    if p:
        r = parse_xml(p)
        inf = r.find('information')
        for k in ['name', 'moodle_version', 'moodle_release', 'backup_version', 'backup_release', 'backup_date',
                  'include_files', 'include_file_references_to_external_content', 'original_wwwroot',
                  'original_course_id', 'original_course_format', 'original_course_fullname',
                  'original_course_shortname', 'original_course_startdate', 'original_course_enddate']:
            M["info"][k] = val(inf, k)
        for s in r.iter('setting'):
            if val(s, 'level') == 'root':
                M["settings"][val(s, 'name')] = val(s, 'value')
        for s in inf.findall('contents/sections/section'):
            sid = ival(s, 'sectionid')
            M["sections"].setdefault(sid, {})["title_in_manifest"] = val(s, 'title')
            M["sections"][sid]["directory"] = val(s, 'directory')
        M["info"]["activities_in_manifest"] = len(inf.findall('contents/activities/activity'))

    # --- course/course.xml
    p = need('course/course.xml')
    if p:
        r = parse_xml(p)
        c = M["course"]
        c["id"] = r.get('id'); c["contextid"] = r.get('contextid')
        for k in ['shortname', 'fullname', 'summary', 'format', 'visible', 'lang', 'enablecompletion',
                  'showcompletionconditions', 'timecreated', 'timemodified', 'idnumber']:
            c[k] = val(r, k)
        c["startdate"] = ival(r, 'startdate'); c["enddate"] = ival(r, 'enddate')
        cat = r.find('category')
        c["category"] = {"id": cat.get('id'), "name": val(cat, 'name')} if cat is not None else None
        c["tags"] = [val(t, 'rawname') for t in r.findall('tags/tag')]
        c["formatoptions"] = {val(o, 'name'): val(o, 'value') for o in r.findall('courseformatoptions/courseformatoption')}

    p = need('course/enrolments.xml')
    if p:
        r = parse_xml(p)
        for e in r.findall('enrols/enrol'):
            M["enrols"].append({"id": e.get('id'), "enrol": val(e, 'enrol'), "status": ival(e, 'status'),
                                "enrolstartdate": ival(e, 'enrolstartdate'), "enrolenddate": ival(e, 'enrolenddate'),
                                "customint6": ival(e, 'customint6'), "roleid": ival(e, 'roleid'),
                                "has_password": bool(val(e, 'password'))})

    p = need('course/completiondefaults.xml')
    if p:
        r = parse_xml(p)
        for d in r.findall('course_completion_default'):
            M["completion_defaults"][val(d, 'modulename')] = {"completion": ival(d, 'completion'),
                                                             "completionview": ival(d, 'completionview')}

    # --- blocos
    bdir = os.path.join(root, 'course', 'blocks')
    if os.path.isdir(bdir):
        for d in sorted(os.listdir(bdir)):
            bx = os.path.join(bdir, d, 'block.xml')
            if not os.path.exists(bx):
                continue
            r = parse_xml(bx)
            b = {"dir": d, "name": val(r, 'blockname'), "region": val(r, 'defaultregion'), "config": None,
                 "version": r.get('version'), "title": None}
            cd = val(r, 'configdata')
            if cd:
                try:
                    raw = base64.b64decode(cd).decode('latin-1')
                    b["config"] = php_unserialize(raw)
                    if isinstance(b["config"], dict):
                        b["title"] = b["config"].get('title')
                except Exception as e:  # noqa
                    b["config_error"] = str(e)
            M["blocks"].append(b)

    # --- seções
    sdir = os.path.join(root, 'sections')
    if os.path.isdir(sdir):
        for d in sorted(os.listdir(sdir)):
            sx = os.path.join(sdir, d, 'section.xml')
            if not os.path.exists(sx):
                continue
            r = parse_xml(sx)
            sid = int(r.get('id'))
            s = M["sections"].setdefault(sid, {})
            s.update({"id": sid, "number": ival(r, 'number'), "name": val(r, 'name'), "summary": val(r, 'summary', ''),
                      "sequence": [int(x) for x in (val(r, 'sequence', '') or '').split(',') if x],
                      "visible": ival(r, 'visible'), "availability": parse_availability(val(r, 'availabilityjson')),
                      "file": f'sections/{d}/section.xml'})
    else:
        M["missing_files"].append('sections/')

    # --- gradebook raiz
    p = need('gradebook.xml')
    if p:
        r = parse_xml(p)
        for c in r.findall('grade_categories/grade_category'):
            M["gradebook"].setdefault("categories", {})[int(c.get('id'))] = {
                "fullname": val(c, 'fullname'), "aggregation": ival(c, 'aggregation'), "parent": ival(c, 'parent'),
                "aggregateonlygraded": ival(c, 'aggregateonlygraded'), "hidden": ival(c, 'hidden')}
        for gi in r.findall('grade_items/grade_item'):
            M["grade_items"][int(gi.get('id'))] = grade_item_dict(gi, 'gradebook.xml')

    # --- files.xml
    p = need('files.xml')
    if p:
        r = parse_xml(p)
        for f in r.findall('file'):
            fid = int(f.get('id'))
            M["files"][fid] = {"id": fid, "contenthash": val(f, 'contenthash'), "contextid": ival(f, 'contextid'),
                               "component": val(f, 'component'), "filearea": val(f, 'filearea'),
                               "itemid": ival(f, 'itemid'), "filepath": val(f, 'filepath'), "filename": val(f, 'filename'),
                               "filesize": ival(f, 'filesize'), "mimetype": val(f, 'mimetype'),
                               "reference": val(f, 'reference'), "repositorytype": val(f, 'repositorytype')}
            h = M["files"][fid]["contenthash"]
            M["files"][fid]["on_disk"] = (M["files"][fid]["filename"] == '.') or os.path.exists(os.path.join(root, 'files', h[:2], h))

    # --- badges
    p = need('badges.xml')
    if p:
        r = parse_xml(p)
        for b in r.findall('badge'):
            M["badges"].append({"name": val(b, 'name'), "status": ival(b, 'status'),
                                "module_criteria": [val(pp, 'value') for c in b.findall('criteria/criterion')
                                                    for pp in c.findall('parameters/parameter')
                                                    if (val(pp, 'name') or '').startswith('module_')]})

    # --- questions
    p = need('questions.xml')
    if p:
        r = parse_xml(p)
        M["questions"]["categories"] = len(r.findall('question_category'))
        for q in r.iter('question'):
            M["questions"]["questions"] += 1
            M["questions"]["qtypes"][val(q, 'qtype')] += 1

    # --- atividades
    adir = os.path.join(root, 'activities')
    if os.path.isdir(adir):
        for d in sorted(os.listdir(adir)):
            mx = os.path.join(adir, d, 'module.xml')
            if not os.path.exists(mx):
                continue
            try:
                M["modules"][int(d.rsplit('_', 1)[1])] = load_module(root, d)
            except Exception as e:
                M["parse_errors"].append(f'{d}: {type(e).__name__}: {e}')
    else:
        M["missing_files"].append('activities/')

    # seções reconstruídas a partir dos módulos quando sections/ está ausente
    for cmid, m in M["modules"].items():
        s = M["sections"].setdefault(m["sectionid"], {"id": m["sectionid"], "name": None, "sequence": [], "visible": None,
                                                     "availability": None, "summary": '', "file": None, "reconstructed": True})
        s.setdefault("number", m["sectionnumber"])
        if m["sectionid"] not in [x for x in s.get("sequence", [])]:
            pass
        if s.get("reconstructed") and cmid not in s["sequence"]:
            s["sequence"].append(cmid)
    return M


def grade_item_dict(gi, src):
    return {"id": int(gi.get('id')), "categoryid": ival(gi, 'categoryid'), "itemname": val(gi, 'itemname'),
            "itemtype": val(gi, 'itemtype'), "itemmodule": val(gi, 'itemmodule'), "iteminstance": ival(gi, 'iteminstance'),
            "itemnumber": ival(gi, 'itemnumber'), "gradetype": ival(gi, 'gradetype'), "grademax": fval(gi, 'grademax'),
            "grademin": fval(gi, 'grademin'), "gradepass": fval(gi, 'gradepass'), "aggregationcoef": fval(gi, 'aggregationcoef'),
            "aggregationcoef2": fval(gi, 'aggregationcoef2'), "weightoverride": ival(gi, 'weightoverride'),
            "hidden": ival(gi, 'hidden'), "calculation": val(gi, 'calculation'), "src": src}


def parse_availability(s):
    if not s or s == NULL:
        return None
    try:
        return json.loads(html.unescape(s))
    except Exception:
        return {"_error": "JSON inválido", "_raw": s[:200]}


def load_module(root, d):
    base = os.path.join(root, 'activities', d)
    r = parse_xml(os.path.join(base, 'module.xml'))
    modname = val(r, 'modulename')
    m = {"dir": d, "file": f'activities/{d}/module.xml', "cmid": int(r.get('id')), "modname": modname,
         "plugin_version": r.get('version'), "sectionid": ival(r, 'sectionid'), "sectionnumber": ival(r, 'sectionnumber'),
         "visible": ival(r, 'visible'), "visibleoncoursepage": ival(r, 'visibleoncoursepage'),
         "completion": ival(r, 'completion'), "completionview": ival(r, 'completionview'),
         "completiongradeitemnumber": ival(r, 'completiongradeitemnumber'), "completionpassgrade": ival(r, 'completionpassgrade'),
         "completionexpected": ival(r, 'completionexpected'), "availability": parse_availability(val(r, 'availability')),
         "availability_raw": val(r, 'availability'), "name": None, "instanceid": None, "contextid": None, "texts": {},
         "grade_items": [], "fileref": [], "extra": {}}
    # inforef
    ix = os.path.join(base, 'inforef.xml')
    if os.path.exists(ix):
        ir = parse_xml(ix)
        m["fileref"] = [int(val(f, 'id')) for f in ir.findall('fileref/file')]
    # grades.xml
    gx = os.path.join(base, 'grades.xml')
    if os.path.exists(gx):
        gr = parse_xml(gx)
        for gi in gr.findall('grade_items/grade_item'):
            m["grade_items"].append(grade_item_dict(gi, f'activities/{d}/grades.xml'))
    # <mod>.xml
    ax = os.path.join(base, f'{modname}.xml')
    if os.path.exists(ax):
        ar = parse_xml(ax)
        m["contextid"] = ar.get('contextid')
        inst = ar.find(modname)
        if inst is not None:
            m["instanceid"] = int(inst.get('id'))
            m["name"] = val(inst, 'name', '')
            for fld in MOD_TEXT_FIELDS.get(modname, ['intro']):
                t = val(inst, fld)
                if t is not None:
                    m["texts"][fld] = t
            x = m["extra"]
            # critérios de conclusão específicos do módulo (completionsubmit, completionentries, completionposts...)
            x["completion_fields"] = {e.tag: e.text for e in inst if e.tag.startswith('completion') and e.text not in (None, '0', NULL, '')}
            if modname == 'quiz':
                x.update({"attempts": ival(inst, 'attempts_number'), "timeopen": ival(inst, 'timeopen'),
                          "timeclose": ival(inst, 'timeclose'), "grade": fval(inst, 'grade'), "sumgrades": fval(inst, 'sumgrades'),
                          "grademethod": ival(inst, 'grademethod'), "slots": len(inst.findall('question_instances/question_instance')),
                          "completionminattempts": ival(inst, 'completionminattempts'),
                          "completionattemptsexhausted": ival(inst, 'completionattemptsexhausted'),
                          "timelimit": ival(inst, 'timelimit'), "has_password": bool(val(inst, 'password'))})
                x["slot_qtypes"] = Counter()
                x["random_slots"] = 0
                for qi in inst.findall('question_instances/question_instance'):
                    if qi.find('question_set_reference') is not None:
                        x["random_slots"] += 1
            elif modname == 'feedback':
                x.update({"completionsubmit": ival(inst, 'completionsubmit'), "items": len(inst.findall('items/item')),
                          "anonymous": ival(inst, 'anonymous'), "timeopen": ival(inst, 'timeopen'), "timeclose": ival(inst, 'timeclose')})
            elif modname == 'forum':
                x.update({"type": val(inst, 'type'), "completiondiscussions": ival(inst, 'completiondiscussions'),
                          "completionreplies": ival(inst, 'completionreplies'), "completionposts": ival(inst, 'completionposts'),
                          "duedate": ival(inst, 'duedate'), "cutoffdate": ival(inst, 'cutoffdate'), "grade_forum": ival(inst, 'grade_forum')})
            elif modname == 'url':
                x.update({"externalurl": val(inst, 'externalurl'), "display": ival(inst, 'display')})
            elif modname == 'choice':
                x.update({"completionsubmit": ival(inst, 'completionsubmit'), "options": [val(o, 'text') for o in inst.findall('options/option')],
                          "timeclose": ival(inst, 'timeclose')})
            elif modname == 'book':
                x["chapters"] = [{"title": val(c, 'title'), "hidden": ival(c, 'hidden'), "content": val(c, 'content', '')}
                                 for c in inst.findall('chapters/chapter')]
                for i, c in enumerate(x["chapters"]):
                    m["texts"][f'chapter[{i}]'] = c["content"]
            elif modname == 'game':
                x.update({"sourcemodule": val(inst, 'sourcemodule'), "glossaryid": ival(inst, 'glossaryid'), "quizid": ival(inst, 'quizid'),
                          "glossarycategoryid": ival(inst, 'glossarycategoryid'),
                          "gamekind": val(inst, 'gamekind'), "grade": fval(inst, 'grade'), "timeclose": ival(inst, 'timeclose'),
                          "attempts": ival(inst, 'param2')})
            elif modname == 'data':
                x.update({"completionentries": ival(inst, 'completionentries'), "timeavailableto": ival(inst, 'timeavailableto')})
            elif modname == 'glossary':
                x.update({"mainglossary": ival(inst, 'mainglossary'), "entries": len(inst.findall('entries/entry'))})
            elif modname == 'hvp':
                x.update({"machine_name": val(inst, 'machine_name'), "completionpass": ival(inst, 'completionpass')})
            elif modname == 'assign':
                x.update({"duedate": ival(inst, 'duedate'), "cutoffdate": ival(inst, 'cutoffdate'), "grade": fval(inst, 'grade'),
                          "allowsubmissionsfromdate": ival(inst, 'allowsubmissionsfromdate')})
            elif modname == 'simplecertificate':
                x.update({"certificateimage": val(inst, 'certificateimage'), "secondimage": val(inst, 'secondimage'),
                          "requiredtime": ival(inst, 'requiredtime'), "coursename": val(inst, 'coursename'),
                          "certdate": ival(inst, 'certdate'), "certgrade": ival(inst, 'certgrade'), "enablesecondpage": ival(inst, 'enablesecondpage')})
    return m


# ----------------------------------------------------------------------------------
# 3. MOTOR DE REGRAS
# ----------------------------------------------------------------------------------
CONFORME, NAO_CONFORME, ATENCAO, NA, NV, ERRO = 'CONFORME', 'NAO_CONFORME', 'ATENCAO', 'NAO_APLICAVEL', 'NAO_VERIFICAVEL', 'ERRO'
RULES = []


def rule(rid, titulo, origem, classe, gravidade):
    def deco(fn):
        RULES.append({"id": rid, "titulo": titulo, "origem": origem, "classe": classe, "gravidade": gravidade, "fn": fn})
        return fn
    return deco


def R(status, local=None, evidencia=None, encontrado=None, esperado=None, orientacao=None, gravidade=None):
    return {"status": status, "local": local, "evidencia": evidencia, "encontrado": encontrado, "esperado": esperado,
            "orientacao": orientacao, "gravidade": gravidade}


def mod_label(m):
    return f'{m["modname"]} "{m.get("name") or "(sem nome)"}" (cmid={m["cmid"]}, {m["file"]})'


def find_mods(M, modname=None, name_like=None):
    out = []
    for m in M["modules"].values():
        if modname and m["modname"] != modname:
            continue
        if name_like and norm(name_like) not in norm(m.get("name")):
            continue
        out.append(m)
    return out


def graded_modules(M):
    """Módulos com item de nota de valor (gradetype=1) e grademax>0 — 'atividades que valem nota'."""
    out = []
    for m in M["modules"].values():
        for gi in m["grade_items"]:
            if gi["gradetype"] == 1 and (gi["grademax"] or 0) > 0 and gi["itemtype"] == 'mod':
                out.append((m, gi))
    return out


def course_grade_item(M):
    for gi in M["grade_items"].values():
        if gi["itemtype"] == 'course':
            return gi
    return None


def walk_conditions(av):
    """Itera condições folha de uma árvore de disponibilidade (ignora operadores)."""
    if not av or not isinstance(av, dict):
        return
    for c in av.get('c', []) or []:
        if isinstance(c, dict) and 'type' in c:
            yield c
        elif isinstance(c, dict) and 'c' in c:
            yield from walk_conditions(c)


def offer_fail(P):
    """Decisão institucional (3): regras de datas/categoria/inscrição só são falhas na sala restaurada;
    no backup produzem ATENÇÃO com a orientação de reavaliar após a restauração."""
    return NAO_CONFORME if P.get("evaluation_moment") == 'restored' else ATENCAO


OFFER_NOTE = ' [estado do backup: reavaliar após a restauração/criação da sala nova]'


def cert_module(M, P):
    cs = [m for m in find_mods(M, P["required_certificate_module"]) if norm(P["licence_certificate_name"]) not in norm(m["name"])]
    return cs


# ---- Checklist institucional -------------------------------------------------------

@rule('CL-01', 'Informações sobre o curso atualizadas conforme documento padrão', 'checklist:1', 'D', 'Média')
def cl01(M, P):
    pages = find_mods(M, 'page', 'Informações sobre o curso')
    if not pages:
        return [R(NAO_CONFORME, 'Área do curso', 'nenhum módulo page com nome "Informações sobre o curso"', orientacao='Criar a página padrão.')]
    out = []
    for p in pages:
        txt = strip_html(p["texts"].get('content', ''))
        if 'libras' in norm(txt) and len(txt) < 400:
            out.append(R(NA, mod_label(p), 'versão alternativa (Libras) — avaliada a versão textual', gravidade='Informativa'))
            continue
        heads = ['nome do curso', 'descricao do curso', 'carga horaria', 'idioma', 'nivel de dificuldade', 'publico-alvo',
                 'requisitos tecnicos', 'pre-requisitos', 'conteudos', 'metodologia', 'processo de avaliacao', 'professor']
        found = [h for h in heads if h in norm(txt)]
        missing = [h for h in heads if h not in norm(txt)]
        out.append(R(ATENCAO if missing else CONFORME, mod_label(p), f'cabeçalhos encontrados={found}', encontrado=f'ausentes={missing}',
                     esperado='estrutura do documento padrão (verificação humana do conteúdo)',
                     orientacao='Conferir o texto com o documento-padrão; a estrutura é verificável, o conteúdo não.'))
    return out


@rule('CL-02', 'Informações iguais ao Projeto do Curso (SIPAC)', 'checklist:2', 'B', 'Média')
def cl02(M, P):
    return [R(NV, 'course/course.xml + page "Informações sobre o curso"', 'o projeto do curso não está no backup',
              orientacao='Exige fonte externa (SIPAC/PDF do projeto). Automatizável apenas se o projeto for fornecido em formato estruturado.')]


@rule('CL-03', 'Professores cadastrados iguais ao projeto', 'checklist:3', 'C', 'Média')
def cl03(M, P):
    users = M["settings"].get('users')
    ra = M["settings"].get('role_assignments')
    return [R(NV, 'moodle_backup.xml <settings>', f'users={users} role_assignments={ra}: backup sem usuários/papéis',
              orientacao='Verificar via API/DB do Moodle após restauração (ou no curso de origem).')]


@rule('CL-04', 'Sumário replica Informações e contém botões Criar Conta / Turmas anteriores', 'checklist:4', 'A', 'Média')
def cl04(M, P):
    s = M["course"].get('summary') or ''
    out = []
    for link in P["summary_required_links"]:
        ok = link in html.unescape(s)
        out.append(R(CONFORME if ok else NAO_CONFORME, 'course/course.xml <summary>', f'link {link} {"presente" if ok else "ausente"}',
                     orientacao='Acrescentar o código do botão ao final do sumário.'))
    pages = find_mods(M, 'page', 'Informações sobre o curso')
    if pages:
        a = norm(strip_html(s)); b = norm(strip_html(pages[0]["texts"].get('content', '')))
        ratio = difflib.SequenceMatcher(None, a, b).ratio()
        out.append(R(CONFORME if ratio >= 0.6 else ATENCAO, 'course.xml <summary> vs ' + mod_label(pages[0]),
                     f'similaridade textual={ratio:.2f}', esperado='>= 0.60 (heurística)', orientacao='Replicar o texto da página no sumário.'))
    return out


@rule('CL-05', 'Categoria do curso = categoria de inscrições abertas', 'checklist:5', 'A', 'Baixa')
def cl05(M, P):
    cat = (M["course"].get('category') or {}).get('name')
    ok = norm(cat) == norm(P["target_category_name"])
    return [R(CONFORME if ok else offer_fail(P), 'course/course.xml <category>', f'categoria="{cat}"' + ('' if ok else OFFER_NOTE), esperado=P["target_category_name"],
              orientacao='Alterar a categoria. Observação: o MOOC Admin pode definir a categoria após a restauração; neste caso a regra deve ser avaliada pós-restauração.')]


@rule('CL-06', 'Data de início correta (futura, ≥1 mês antes do fim)', 'checklist:6', 'A', 'Alta')
def cl06(M, P):
    sd, ed = M["course"].get('startdate'), M["course"].get('enddate')
    ref = dt.datetime.fromisoformat(P["reference_date"]) if P.get("reference_date") else dt.datetime.now()
    if not sd:
        return [R(NAO_CONFORME, 'course/course.xml <startdate>', 'startdate vazio/0')]
    sdt = dt.datetime.fromtimestamp(sd, dt.timezone(dt.timedelta(hours=-3)))
    probs = []
    if sdt.year != P["reoffer_year"]:
        probs.append(f'ano={sdt.year} ≠ {P["reoffer_year"]}')
    if P.get("required_start_date") and sdt.strftime('%d/%m') != P["required_start_date"]:
        probs.append(f'dia/mês={sdt.strftime("%d/%m")} ≠ {P["required_start_date"]}')
    if ed and (ed - sd) < P["min_days_between_start_and_end"] * 86400:
        probs.append('menos de 1 mês antes do fim')
    if ed and sd >= ed:
        probs.append('início após o fim')
    return [R(offer_fail(P) if probs else CONFORME, 'course/course.xml <startdate>', f'startdate={ts(sd)} enddate={ts(ed)} ref={ref.date()}' + (OFFER_NOTE if probs else ''),
              encontrado='; '.join(probs) or 'ok', esperado=f'{P.get("required_start_date") or "início"}/{P["reoffer_year"]}, ≥{P["min_days_between_start_and_end"]} dias antes do fim',
              orientacao='Ajustar a data de início. O MOOC Admin pode redefinir datas na reoferta: diferenciar auditoria do backup da auditoria pós-restauração.')]


@rule('CL-07', 'Data de término no último dia do ciclo (31/12 do ano da reoferta)', 'checklist:7', 'A', 'Alta')
def cl07(M, P):
    ed = M["course"].get('enddate')
    if not ed:
        return [R(NAO_CONFORME, 'course/course.xml <enddate>', 'enddate=0 (sem término)')]
    edt = dt.datetime.fromtimestamp(ed, dt.timezone(dt.timedelta(hours=-3)))
    dm = edt.strftime('%d/%m')
    ok = dm in P["allowed_end_dates"] and edt.year == P["reoffer_year"]
    return [R(CONFORME if ok else offer_fail(P), 'course/course.xml <enddate>', f'enddate={ts(ed)} ({dm}/{edt.year})' + ('' if ok else OFFER_NOTE),
              esperado=f'{P["allowed_end_dates"]} de {P["reoffer_year"]}', orientacao='Ajustar a data de término conforme calendário do Cefor.')]


@rule('CL-08', 'Autoinscrição habilitada com período correto', 'checklist:8', 'A', 'Alta')
def cl08(M, P):
    selfs = [e for e in M["enrols"] if e["enrol"] == 'self']
    if not selfs:
        return [R(NAO_CONFORME, 'course/enrolments.xml', 'nenhum método "self"', orientacao='Adicionar/ativar autoinscrição.')]
    out = []
    sd, ed = M["course"].get('startdate'), M["course"].get('enddate')
    for e in selfs:
        probs = []
        if e["status"] != 0:
            probs.append('método desabilitado (status≠0)')
        if e["customint6"] != 1:
            probs.append('"Permitir novas inscrições" ≠ Sim (customint6)')
        if not e["enrolstartdate"] or not e["enrolenddate"]:
            probs.append('datas de inscrição não definidas')
        else:
            if sd and e["enrolstartdate"] != sd:
                probs.append(f'início inscrição {ts(e["enrolstartdate"])} ≠ início curso {ts(sd)}')
            if ed and e["enrolenddate"] != ed:
                probs.append(f'fim inscrição {ts(e["enrolenddate"])} ≠ fim curso {ts(ed)}')
        out.append(R(offer_fail(P) if probs else CONFORME, f'course/enrolments.xml enrol id={e["id"]} (self)',
                     f'status={e["status"]} customint6={e["customint6"]} start={ts(e["enrolstartdate"])} end={ts(e["enrolenddate"])}' + (OFFER_NOTE if probs else ''),
                     encontrado='; '.join(probs) or 'ok', orientacao='Editar o método Autoinscrição: visível, datas e "permitir novas inscrições". '
                     'Nota: a igualdade estrita com as datas do curso é interpretação; o checklist só pede datas "corretas".'))
    guest = [e for e in M["enrols"] if e["enrol"] == 'guest' and e["status"] == 0]
    if guest:
        out.append(R(ATENCAO, 'course/enrolments.xml (guest)', f'acesso de visitante habilitado{" com senha" if guest[0]["has_password"] else ""}',
                     orientacao='Confirmar se o acesso de visitante é desejado na reoferta.', gravidade='Informativa'))
    return out


@rule('CL-09', 'Fórum "Espaço de Interação" presente', 'checklist:9', 'A', 'Média')
def cl09(M, P):
    f = find_mods(M, 'forum', P["required_forum_name"])
    if f:
        m = f[0]
        res = [R(CONFORME, mod_label(m), f'visible={m["visible"]} completion={m["completion"]}')]
        if m["completion"] != 0:
            res.append(R(NAO_CONFORME, mod_label(m), f'completion={m["completion"]}', esperado='conclusão desabilitada (checklist item 17)',
                         orientacao='Desabilitar a conclusão de atividade do Espaço de interação.'))
        return res
    others = [m["name"] for m in find_mods(M, 'forum')]
    return [R(NAO_CONFORME, 'activities/forum_*', f'nenhum fórum com nome ~ "{P["required_forum_name"]}"; fóruns existentes={others}',
              orientacao='Importar o fórum da Sala Padrão. Verificação por NOME: fórum equivalente com outro nome gera falso positivo.')]


@rule('CL-10', 'Vídeos funcionam e estão incorporados', 'checklist:10', 'B', 'Média')
def cl10(M, P):
    urls = [(m, m["extra"].get('externalurl')) for m in find_mods(M, 'url')]
    video_hosts = ('youtube.com', 'youtu.be', 'vimeo.com')
    links = [(m, u) for m, u in urls if u and any(h in u for h in video_hosts)]
    iframes = []
    for m in M["modules"].values():
        for fld, t in m["texts"].items():
            for src in re.findall(r'<iframe\b[^>]*\bsrc="([^"]+)"', html.unescape(t)):
                iframes.append((m, fld, src))
    out = [R(NV, 'activities/*', f'{len(iframes)} iframes incorporados; {len(links)} módulos URL apontando para vídeo externo',
             orientacao='Disponibilidade só verificável com acesso à rede (fase 2, com proteção SSRF e lista de domínios permitidos).', gravidade='Informativa')]
    for m, u in links:
        out.append(R(ATENCAO, mod_label(m), f'externalurl={u}', esperado='vídeo incorporado (embed) na própria sala',
                     orientacao='Checklist pede vídeos "embedados"; URL externa de vídeo é um sinal, não prova de problema.', gravidade='Baixa'))
    return out


@rule('CL-11', 'Pesquisa de Perfil presente e exigida pelo Certificado', 'checklist:11', 'A', 'Alta')
def cl11(M, P):
    prof = find_mods(M, 'feedback', P["required_feedback_profile"])
    out = []
    if not prof:
        out.append(R(NAO_CONFORME, 'activities/feedback_*', f'nenhum feedback com nome ~ "{P["required_feedback_profile"]}"',
                     orientacao='Importar a Pesquisa de Perfil da Sala Padrão Oficial.'))
        return out
    pm = prof[0]
    out.append(R(CONFORME, mod_label(pm), f'completion={pm["completion"]} completionsubmit={pm["extra"].get("completionsubmit")}'))
    if pm["completion"] != 2 or pm["extra"].get("completionsubmit") != 1:
        out.append(R(NAO_CONFORME, mod_label(pm), f'completion={pm["completion"]} completionsubmit={pm["extra"].get("completionsubmit")}',
                     esperado='conclusão automática ao enviar (completion=2, completionsubmit=1)',
                     orientacao='Sem conclusão automática, a restrição do certificado nunca é satisfeita.'))
    for c in cert_module(M, P):
        cms = [x.get('cm') for x in walk_conditions(c["availability"]) if x.get('type') == 'completion']
        ok = pm["cmid"] in cms
        out.append(R(CONFORME if ok else NAO_CONFORME, mod_label(c), f'availability={c["availability_raw"]}',
                     encontrado=f'cmids exigidos={cms}', esperado=f'conter completion cm={pm["cmid"]} ({pm["name"]})',
                     orientacao='Adicionar ao certificado a restrição de conclusão da Pesquisa de Perfil.'))
    if not cert_module(M, P):
        out.append(R(NAO_CONFORME, 'activities/simplecertificate_*', 'nenhum certificado encontrado'))
    return out


@rule('CL-12', 'Livro de notas soma 100 pontos', 'checklist:12', 'A', 'Alta')
def cl12(M, P):
    gm = graded_modules(M)
    total = sum(gi["grademax"] for _, gi in gm)
    out = []
    det = [f'{m["modname"]}:{m["cmid"]}={gi["grademax"]:g}' for m, gi in gm]
    ok = abs(total - P["course_total_max"]) < 0.001
    out.append(R(CONFORME if ok else NAO_CONFORME, 'activities/*/grades.xml (itens itemtype=mod, gradetype=1)',
                 f'soma grademax={total:g}; itens={det}', esperado=f'{P["course_total_max"]:g}',
                 orientacao='Ajustar notas máximas das atividades. Interpretação adotada: soma dos grademax dos itens de valor (agregação Natural). '
                 'Se houver categorias com peso/agregação diferente, a soma bruta não representa a nota final.'))
    ci = course_grade_item(M)
    if ci:
        cats = M["gradebook"].get("categories", {})
        agg = cats.get(ci["iteminstance"], {}).get("aggregation")
        if agg != 13:
            out.append(R(ATENCAO, 'gradebook.xml', f'aggregation da categoria curso={agg}', esperado='13 (Natural/soma)',
                         orientacao='Com outra agregação, a soma de 100 não é o critério correto.'))
        if ci["grademax"] != P["course_total_max"]:
            out.append(R(ATENCAO, 'gradebook.xml item do curso', f'grademax do item curso={ci["grademax"]}', esperado=f'{P["course_total_max"]:g}'))
        nonnat = [gi for _, gi in gm if gi["categoryid"] != ci["iteminstance"]]
        if nonnat:
            out.append(R(ATENCAO, 'activities/*/grades.xml', f'{len(nonnat)} itens em subcategorias de nota', orientacao='Verificar agregação das subcategorias.'))
    else:
        out.append(R(NV, 'gradebook.xml', 'arquivo ausente (backup incompleto): agregação do curso não verificável'))
    hidden = [gi for _, gi in gm if gi["hidden"]]
    if hidden:
        out.append(R(ATENCAO, 'grades.xml', f'{len(hidden)} itens de nota ocultos', gravidade='Baixa'))
    return out


@rule('CL-13', 'Questionários com ao menos duas tentativas', 'checklist:13', 'A', 'Alta')
def cl13(M, P):
    out = []
    for m in find_mods(M, 'quiz'):
        a = m["extra"].get('attempts')
        if P["attempts_rule"] == 'exact':
            ok = a == P["min_quiz_attempts"]
        else:
            ok = (a == 0) or (a is not None and a >= P["min_quiz_attempts"])   # 0 = ilimitadas
        out.append(R(CONFORME if ok else NAO_CONFORME, mod_label(m), f'attempts_number={a} ({m["dir"]}/quiz.xml)',
                     esperado=f'{"=" if P["attempts_rule"]=="exact" else ">="} {P["min_quiz_attempts"]} (0=ilimitadas)',
                     orientacao='Editar configurações do questionário > Nota > Tentativas permitidas.'))
        intro = strip_html(m["texts"].get('intro', ''))
        mm = re.search(r'tentativas?\s*(?:permitidas?)?\s*[:\-]?\s*(\d+)', norm(intro))
        if mm:
            declared = int(mm.group(1))
            if a is not None and declared != a:
                out.append(R(NAO_CONFORME, mod_label(m), f'enunciado declara {declared} tentativas; configurado={a}',
                             esperado='enunciado coerente com a configuração', orientacao='Corrigir o enunciado ou a configuração.'))
        elif intro:
            out.append(R(ATENCAO, mod_label(m), 'enunciado não menciona número de tentativas', gravidade='Baixa',
                         orientacao='Checklist pede que o enunciado informe as tentativas.'))
    if not out:
        out.append(R(NA, 'activities/quiz_*', 'curso sem questionários'))
    return out


@rule('CL-14', 'Atividades avaliativas com data de encerramento', 'checklist:14', 'A', 'Média')
def cl14(M, P):
    out = []
    ed = M["course"].get('enddate')
    for m, gi in graded_modules(M):
        x = m["extra"]
        close = {'quiz': x.get('timeclose'), 'assign': x.get('cutoffdate') or x.get('duedate'), 'game': x.get('timeclose'),
                 'forum': x.get('cutoffdate') or x.get('duedate'), 'data': x.get('timeavailableto'), 'feedback': x.get('timeclose'),
                 'choice': x.get('timeclose')}.get(m["modname"], 'n/a')
        if close == 'n/a':
            out.append(R(NV, mod_label(m), 'tipo de módulo sem campo de encerramento conhecido pelo protótipo', gravidade='Informativa'))
        elif not close:
            out.append(R(NAO_CONFORME, mod_label(m), 'data de encerramento = 0 (não definida)', orientacao='Definir a data de encerramento.'))
        else:
            note = ''
            if ed and close != ed:
                note = f' (≠ fim do curso {ts(ed)})'
            out.append(R(ATENCAO if note else CONFORME, mod_label(m), f'encerramento={ts(close)}{note}', gravidade='Baixa' if note else None))
    return out or [R(NA, 'activities', 'sem atividades avaliativas')]


@rule('CL-15', 'Restrições das avaliações corretas; 1º questionário condicionado à "Escolha a sua situação"', 'checklist:15', 'D', 'Média')
def cl15(M, P):
    out = []
    choices = find_mods(M, 'choice', 'situação')
    quizzes = sorted(find_mods(M, 'quiz'), key=lambda m: (m["sectionnumber"] or 0, M["sections"].get(m["sectionid"], {}).get("sequence", []).index(m["cmid"]) if m["cmid"] in M["sections"].get(m["sectionid"], {}).get("sequence", []) else 0))
    if not quizzes:
        return [R(NA, 'activities/quiz_*', 'sem questionários')]
    first = quizzes[0]
    if not choices:
        out.append(R(ATENCAO, 'activities/choice_*', 'nenhuma atividade Escolha ~ "situação junto ao Ifes" no curso',
                     orientacao='Checklist pressupõe essa escolha; cursos recentes podem não usá-la — confirmar se a regra ainda vale.'))
    else:
        cms = [c.get('cm') for c in walk_conditions(first["availability"]) if c.get('type') == 'completion']
        ok = choices[0]["cmid"] in cms
        out.append(R(CONFORME if ok else NAO_CONFORME, mod_label(first), f'availability={first["availability_raw"]}',
                     esperado=f'completion cm={choices[0]["cmid"]}', orientacao='Adicionar restrição de conclusão da Escolha ao primeiro questionário.'))
    for q in quizzes:
        conds = list(walk_conditions(q["availability"]))
        out.append(R(ATENCAO if not conds else CONFORME, mod_label(q), f'{len(conds)} condições de acesso: {q["availability_raw"]}',
                     gravidade='Informativa', orientacao='Restrições são opcionais pelo checklist; revisar manualmente se fazem sentido.'))
    return out


@rule('CL-16', 'Avaliação do Curso restrita à conclusão de todas as atividades que valem nota', 'checklist:16', 'A', 'Alta')
def cl16(M, P):
    ev = find_mods(M, 'feedback', P["required_feedback_evaluation"])
    if not ev:
        return [R(NAO_CONFORME, 'activities/feedback_*', f'nenhum feedback ~ "{P["required_feedback_evaluation"]}"')]
    e = ev[0]
    req = {c.get('cm') for c in walk_conditions(e["availability"]) if c.get('type') == 'completion'}
    graded = {m["cmid"]: m for m, _ in graded_modules(M)}
    missing = [mod_label(graded[c]) for c in graded if c not in req]
    extra = [c for c in req if c not in graded]
    out = [R(NAO_CONFORME if missing else CONFORME, mod_label(e), f'availability={e["availability_raw"]}',
             encontrado=f'faltam {len(missing)}: {missing}' if missing else f'todas as {len(graded)} atividades com nota exigidas',
             esperado='conclusão de todas as atividades com nota', orientacao='Adicionar restrições de conclusão faltantes.')]
    if extra:
        out.append(R(ATENCAO, mod_label(e), f'exige também cmids sem nota: {extra}', gravidade='Informativa'))
    op = (e["availability"] or {}).get('op')
    if op and op != '&':
        out.append(R(ATENCAO, mod_label(e), f'operador raiz={op}', esperado='& (todas)', orientacao='Com OR/negação a exigência "todas" não se sustenta.'))
    return out


@rule('CL-17', 'Conclusão automática em atividades e recursos', 'checklist:17', 'A', 'Média')
def cl17(M, P):
    out = []
    counts = Counter()
    for m in M["modules"].values():
        counts[m["completion"]] += 1
        if m["completion"] == 1:
            out.append(R(NAO_CONFORME, mod_label(m), 'completion=1 (manual)', esperado='2 (automática) ou 0 (sem acompanhamento)',
                         orientacao='Trocar para conclusão automática com critério adequado ao tipo.'))
        if m["completion"] == 2 and m["completiongradeitemnumber"] is None and not m["completionview"] \
                and not m["extra"].get('completion_fields') and m["modname"] != 'label':
            out.append(R(ATENCAO, mod_label(m), 'conclusão automática sem nenhum critério configurado (nem visualização, nem nota, nem critério do módulo)', gravidade='Média'))
    out.insert(0, R(CONFORME if counts[1] == 0 else ATENCAO, 'activities/*/module.xml <completion>',
                    f'distribuição: nenhuma={counts[0]} manual={counts[1]} automática={counts[2]}', gravidade='Informativa'))
    return out


@rule('CL-18', 'Curso sem Glossário', 'checklist:18', 'A', 'Média')
def cl18(M, P):
    out = []
    for mod in P["forbidden_modules"]:
        gs = find_mods(M, mod)
        if not gs:
            out.append(R(CONFORME, 'activities/', f'nenhum módulo {mod}'))
        for g in gs:
            deps = [m for m in find_mods(M, 'game') if m["extra"].get('sourcemodule') == 'glossary' and m["extra"].get('glossaryid') == g["instanceid"]]
            entries = g["extra"].get('entries', 0)
            userinfo = M["settings"].get('users')
            if deps:
                # Decisão institucional (2026-10-28): glossário usado só como fonte de jogos é tolerado,
                # mas as ENTRADAS são dados de usuário e não vêm em backup sem usuários -> os jogos quebram.
                st = NAO_CONFORME if entries == 0 else ATENCAO
                out.append(R(st, mod_label(g), f'glossário com {entries} entradas no backup (users={userinfo}); fonte de {len(deps)} jogos: '
                             + ', '.join(f'{m["modname"]}:{m["cmid"]} "{m["name"]}" (categoria {m["extra"].get("glossarycategoryid")})' for m in deps),
                             esperado='entradas disponíveis para os jogos na sala restaurada',
                             orientacao='As entradas do glossário são dados de usuário (mod/glossary backup_glossary_stepslib: tabela glossary_entries só é exportada com userinfo). '
                             'Em backup sem usuários o glossário é restaurado vazio e os jogos que dependem dele ficam sem palavras. '
                             'Repovoar as entradas após a restauração (importação XML do glossário) ou trocar a fonte dos jogos para o banco de questões.',
                             gravidade='Bloqueante' if entries == 0 else 'Média'))
            else:
                out.append(R(NAO_CONFORME, mod_label(g), f'módulo {mod} presente (instance={g["instanceid"]}, {entries} entradas) sem uso por jogos',
                             orientacao='Remover o glossário (glossário colaborativo é proibido pela política).'))
    return out


@rule('CL-19', 'Rótulos e materiais complementares sem conclusão de atividade', 'checklist:19', 'A', 'Baixa')
def cl19(M, P):
    out = []
    for m in find_mods(M, 'label'):
        if m["completion"] != 0:
            out.append(R(NAO_CONFORME, mod_label(m), f'completion={m["completion"]}', esperado='0', orientacao='Desabilitar conclusão no rótulo.'))
    for m in M["modules"].values():
        sec = M["sections"].get(m["sectionid"], {})
        if 'complementar' in norm(sec.get("name")) and m["completion"] != 0:
            out.append(R(NAO_CONFORME, mod_label(m), f'completion={m["completion"]} em seção "{sec.get("name")}"', esperado='0'))
    return out or [R(CONFORME, 'activities/label_*', 'nenhum rótulo com conclusão habilitada')]


@rule('CL-20', 'Seções excedentes suprimidas / ocultas invisíveis', 'checklist:20', 'A', 'Baixa')
def cl20(M, P):
    out = []
    hs = M["course"].get('formatoptions', {}).get('hiddensections')
    out.append(R(CONFORME if hs == '1' else NAO_CONFORME, 'course/course.xml courseformatoption hiddensections', f'hiddensections={hs}',
                 esperado='1 (completamente invisíveis)', orientacao='Editar configurações do curso > Formato > Seções escondidas.'))
    for s in M["sections"].values():
        if s.get("number") is None:
            continue
        mods = [M["modules"][c] for c in s.get("sequence", []) if c in M["modules"]]
        if not mods and s.get("number", 0) > 0:
            out.append(R(ATENCAO, f'seção {s["number"]} "{s.get("name")}" ({s.get("file")})', 'seção sem módulos', orientacao='Excluir ou ocultar a seção.'))
        elif mods and all(m["modname"] == 'label' for m in mods) and s.get("number", 0) > 0:
            out.append(R(ATENCAO, f'seção {s["number"]} "{s.get("name")}"', f'seção só com rótulos ({len(mods)})', gravidade='Baixa'))
        if s.get("visible") == 0 and any(m["completion"] for m in mods):
            out.append(R(ATENCAO, f'seção {s["number"]} "{s.get("name")}"', 'seção oculta contendo atividades com conclusão', gravidade='Média'))
    return out


@rule('CL-21', 'Blocos padrão presentes', 'checklist:21', 'A', 'Média')
def cl21(M, P):
    out = []
    names = [b["name"] for b in M["blocks"]]
    titles = [norm(b.get("title")) for b in M["blocks"] if b.get("title")]
    out.append(R(CONFORME if 'completion_progress' in names else NAO_CONFORME, 'course/blocks/', f'blocos no backup={names}; títulos html={titles}'))
    for t in P["required_blocks"]["html"]:
        ok = any(norm(t) in x for x in titles)
        out.append(R(CONFORME if ok else ATENCAO, 'course/blocks/html_*/block.xml configdata', f'bloco HTML "{t}": {"presente" if ok else "ausente no backup"}',
                     orientacao='Decisão institucional: o bloco deve estar na sala. Como não aparece em nenhum backup examinado, a verificação definitiva é pós-restauração (classe C); '
                                'se for bloco de nível de site, ele não entra no backup do curso.' if not ok else None))
    return out


@rule('X-BLK-01', 'Bloco de progresso referencia atividades existentes', 'extra:6.7', 'A', 'Média')
def xblk01(M, P):
    out = []
    inst = {(m["modname"], m["instanceid"]): m for m in M["modules"].values()}
    for b in M["blocks"]:
        if b["name"] != 'completion_progress' or not isinstance(b.get("config"), dict):
            continue
        sel = b["config"].get('selectactivities') or {}
        sel = list(sel.values()) if isinstance(sel, dict) else []
        mode = b["config"].get('activitiesincluded')
        if mode != 'selectedactivities':
            out.append(R(CONFORME, f'course/blocks/{b["dir"]}', f'activitiesincluded={mode} (todas as atividades com conclusão)'))
            continue
        missing, nocompl = [], []
        for s in sel:
            try:
                mn, iid = s.rsplit('-', 1); iid = int(iid)
            except ValueError:
                continue
            m = inst.get((mn, iid))
            if not m:
                missing.append(s)
            elif m["completion"] == 0:
                nocompl.append(f'{s} ({m["name"]})')
        st = NAO_CONFORME if missing or nocompl else CONFORME
        out.append(R(st, f'course/blocks/{b["dir"]}/block.xml configdata', f'{len(sel)} atividades selecionadas; inexistentes={missing}; sem conclusão={nocompl}',
                     orientacao='Reconfigurar o bloco Progresso de Conclusão após alterações nas atividades. Observação: os ids de instância são remapeados na restauração; a lista é verificável apenas contra o próprio backup.'))
    return out or [R(NA, 'course/blocks', 'sem bloco completion_progress')]


@rule('CL-22', 'Licença para Capacitação configurada', 'checklist:22', 'A', 'Média')
def cl22(M, P):
    out = []
    sec0 = [m for m in find_mods(M, 'label') if m["sectionnumber"] == 0 and norm(P["licence_label_text"]) in norm(strip_html(m["texts"].get('intro', '')))]
    out.append(R(CONFORME if sec0 else NAO_CONFORME, 'activities/label_* (seção 0)', f'rótulo com "{P["licence_label_text"]}": {[m["cmid"] for m in sec0]}'))
    fb = find_mods(M, 'feedback', P["licence_feedback_name"])
    out.append(R(CONFORME if fb else NAO_CONFORME, 'activities/feedback_*', f'feedback "{P["licence_feedback_name"]}": {[m["cmid"] for m in fb]}'))
    dc = find_mods(M, 'simplecertificate', P["licence_certificate_name"])
    out.append(R(CONFORME if dc else NAO_CONFORME, 'activities/simplecertificate_*', f'declaração "{P["licence_certificate_name"]}": {[m["cmid"] for m in dc]}'))
    return out


@rule('CL-23', 'Certificado condicionado à Avaliação do Curso e a 60 pontos', 'checklist:23', 'A', 'Bloqueante')
def cl23(M, P):
    out = []
    certs = cert_module(M, P)
    if not certs:
        return [R(NAO_CONFORME, 'activities/simplecertificate_*', 'nenhum certificado de conclusão', orientacao='Importar o certificado da Sala Padrão.')]
    ev = find_mods(M, 'feedback', P["required_feedback_evaluation"])
    ci = course_grade_item(M)
    for c in certs:
        conds = list(walk_conditions(c["availability"]))
        comp = [x.get('cm') for x in conds if x.get('type') == 'completion']
        grades = [x for x in conds if x.get('type') == 'grade']
        ok_ev = bool(ev) and ev[0]["cmid"] in comp
        out.append(R(CONFORME if ok_ev else NAO_CONFORME, mod_label(c), f'availability={c["availability_raw"]}',
                     encontrado=f'completion cms={comp}', esperado=f'conter cm da Avaliação do curso ({ev[0]["cmid"] if ev else "inexistente"})'))
        g60 = [g for g in grades if (g.get('min') is not None and float(g['min']) >= P["certificate_min_grade"])]
        if not g60:
            out.append(R(NAO_CONFORME, mod_label(c), f'condições de nota={grades}', esperado=f'grade min ≥ {P["certificate_min_grade"]} no total do curso'))
        else:
            g = g60[0]
            if ci and g.get('id') != ci["id"]:
                out.append(R(NAO_CONFORME, mod_label(c), f'condição de nota aponta grade item id={g.get("id")}', esperado=f'item total do curso id={ci["id"]}',
                             orientacao='A condição de 60 pontos deve referenciar o "Total do curso".'))
            elif not ci:
                out.append(R(NV, mod_label(c), f'grade item {g.get("id")} min={g.get("min")}: gradebook.xml ausente, não é possível confirmar que é o total do curso'))
            else:
                out.append(R(CONFORME, mod_label(c), f'grade id={g["id"]} (total do curso) min={g.get("min")}'))
        op = (c["availability"] or {}).get('op')
        if op and op != '&':
            out.append(R(NAO_CONFORME, mod_label(c), f'operador raiz={op}', esperado='&'))
    return out


@rule('CL-24', 'Assinatura do certificado atualizada (diretor-geral)', 'checklist:24', 'B', 'Alta')
def cl24(M, P):
    out = []
    for c in find_mods(M, 'simplecertificate'):
        imgs = [f for f in M["files"].values() if f["component"] == 'mod_simplecertificate' and f["filename"] != '.'
                and str(f["contextid"]) == str(c["contextid"])]
        for f in imgs:
            ref = P["certificate_reference_image_hashes"]
            st = NV if not ref else (CONFORME if f["contenthash"] in ref else NAO_CONFORME)
            out.append(R(st, mod_label(c), f'imagem {f["filearea"]}/{f["filename"]} sha1={f["contenthash"]} ({f["filesize"]} bytes)',
                         orientacao='A assinatura está dentro da imagem: comparar o hash com o da arte oficial vigente (parâmetro de política).'))
        if not imgs and not M["files"]:
            out.append(R(NV, mod_label(c), 'files.xml ausente'))
    return out or [R(NA, 'simplecertificate', 'sem certificado')]


@rule('CL-25', 'Texto do certificado conforme padrão / emissão de teste', 'checklist:25', 'D', 'Alta')
def cl25(M, P):
    out = []
    for c in cert_module(M, P):
        t = norm(strip_html(c["texts"].get('certificatetext', '')))
        exp = norm(P["certificate_expected_text"])
        ratio = difflib.SequenceMatcher(None, t, exp).ratio()
        ph = re.findall(r'\{[A-Z_]+\}', c["texts"].get('certificatetext', ''))
        out.append(R(CONFORME if ratio > 0.95 else ATENCAO, mod_label(c), f'texto="{t[:200]}"; similaridade com padrão={ratio:.2f}; placeholders={ph}',
                     esperado=P["certificate_expected_text"], orientacao='Conferir o texto-padrão. A emissão de teste (layout, campus, assinatura) exige ambiente Moodle.'))
        out.append(R(NV, mod_label(c), 'emissão de certificado de teste requer Moodle em execução', gravidade='Informativa'))
    return out or [R(NA, 'simplecertificate', 'sem certificado')]


@rule('CL-26', 'Padrão visual (imagem de topo, divisores, imagens de seção)', 'checklist:26', 'D', 'Baixa')
def cl26(M, P):
    out = []
    if not M["files"]:
        return [R(NV, 'files.xml', 'ausente: imagens não verificáveis')]
    ov = [f for f in M["files"].values() if f["component"] == 'course' and f["filearea"] == 'overviewfiles' and f["filename"] != '.']
    out.append(R(CONFORME if ov else NAO_CONFORME, 'files.xml course/overviewfiles', f'imagem de curso: {[f["filename"] for f in ov]}'))
    secs = [s for s in M["sections"].values() if s.get("number") and s.get("number") > 0]
    noimg = [s for s in secs if '<img' not in html.unescape(s.get("summary") or '')]
    out.append(R(CONFORME if not noimg else ATENCAO, 'sections/*/section.xml <summary>',
                 f'{len(secs) - len(noimg)}/{len(secs)} seções com imagem no sumário; sem imagem: {[s.get("number") for s in noimg]}',
                 orientacao='Conformidade com o padrão visual exige avaliação humana; aqui só se detecta presença/ausência.'))
    return out


@rule('CL-27', 'Capa da sala igual ao thumbnail da vitrine (sem texto)', 'checklist:27', 'B', 'Baixa')
def cl27(M, P):
    if not M["files"]:
        return [R(NV, 'files.xml', 'ausente')]
    ov = [f for f in M["files"].values() if f["component"] == 'course' and f["filearea"] == 'overviewfiles' and f["filename"] != '.']
    return [R(NV if ov else NAO_CONFORME, 'files.xml course/overviewfiles', f'capa={[(f["filename"], f["contenthash"]) for f in ov]}',
              orientacao='Comparação com a vitrine (WordPress) e ausência de texto na imagem exigem fonte externa/OCR.')]


@rule('CL-28', 'Curso possui URL única', 'checklist:28', 'E', 'Baixa')
def cl28(M, P):
    return [R(NV, 'fora do backup', 'URL amigável é configuração do servidor/CGTI; não consta no MBZ')]


@rule('CL-29', 'Cadastro na vitrine', 'checklist:29', 'E', 'Baixa')
def cl29(M, P):
    return [R(NV, 'fora do backup', 'vitrine WordPress é sistema externo')]


# ---- Descobertas adicionais ----------------------------------------------------------

@rule('X-INT-01', 'Integridade do pacote (stream, manifesto, arquivos essenciais)', 'extra:6.1', 'A', 'Bloqueante')
def xint01(M, P):
    out = []
    ar = M.get("archive", {})
    if ar.get("stream_error"):
        out.append(R(NAO_CONFORME, 'arquivo .mbz', f'erro de descompressão: {ar["stream_error"]}; última entrada lida={ar.get("last_entry")}',
                     orientacao='Backup corrompido/truncado: gerar novamente o backup no Moodle de origem e transferir com verificação de hash.'))
    if ar.get("rejected"):
        out.append(R(NAO_CONFORME, 'arquivo .mbz', f'entradas rejeitadas por segurança: {ar["rejected"][:10]}'))
    ix = M.get("index", {})
    if ix.get("present"):
        st = NAO_CONFORME if (ix["missing"] or ix["size_mismatch"]) else CONFORME
        out.append(R(st, '.ARCHIVE_INDEX', f'declarados={ix["declared"]} arquivos checados={ix["checked"]} ausentes={len(ix["missing"])} tamanho divergente={len(ix["size_mismatch"])}',
                     encontrado=(ix["missing"][:5] + [f'...+{len(ix["missing"])-5}'] if len(ix["missing"]) > 5 else ix["missing"]) or None))
    for f in ['moodle_backup.xml', 'course/course.xml', 'files.xml', 'gradebook.xml', 'sections/', 'activities/']:
        if f in M["missing_files"]:
            out.append(R(NAO_CONFORME, f, 'arquivo/diretório essencial ausente', orientacao='Sem este componente a restauração falha ou fica incompleta.'))
    if M["parse_errors"]:
        out.append(R(ERRO, 'activities/', f'erros de parsing: {M["parse_errors"][:5]}'))
    return out or [R(CONFORME, '.mbz', 'pacote íntegro')]


@rule('X-INT-02', 'Arquivos referenciados em files.xml sem conteúdo no backup', 'extra:6.1/6.2', 'A', 'Alta')
def xint02(M, P):
    if not M["files"]:
        return [R(NV, 'files.xml', 'ausente')]
    miss = [f for f in M["files"].values() if not f["on_disk"]]
    if not miss:
        return [R(CONFORME, 'files.xml vs files/', f'{sum(1 for f in M["files"].values() if f["filename"] != ".")} arquivos presentes')]
    out = []
    ctx2mod = {str(m["contextid"]): m for m in M["modules"].values()}
    for f in miss:
        m = ctx2mod.get(str(f["contextid"]))
        out.append(R(NAO_CONFORME, mod_label(m) if m else f'contexto {f["contextid"]} ({f["component"]})',
                     f'files.xml file id={f["id"]} {f["component"]}/{f["filearea"]} "{f["filename"]}" {f["filesize"]} bytes contenthash={f["contenthash"]} — ausente em files/{f["contenthash"][:2]}/',
                     orientacao='O Moodle omite silenciosamente arquivos cujo conteúdo não existe no filedir de origem; após a restauração o link ficará quebrado. Reenviar o arquivo no curso de origem.'))
    return out


@rule('X-INT-03', 'Plugins não nativos necessários no destino', 'extra:6.1', 'A', 'Alta')
def xint03(M, P):
    out = []
    mods = Counter(m["modname"] for m in M["modules"].values())
    for mn, n in sorted(mods.items()):
        if mn not in P["core_modules"]:
            ver = next(m["plugin_version"] for m in M["modules"].values() if m["modname"] == mn)
            out.append(R(ATENCAO, f'activities/{mn}_* ({n} instâncias)', f'módulo não nativo mod_{mn} versão {ver}',
                         orientacao='Garantir que o plugin exista no Moodle de destino na mesma versão ou superior; caso contrário, a restauração omite as atividades.'))
    avtypes = Counter()
    for m in list(M["modules"].values()) + list(M["sections"].values()):
        for c in walk_conditions(m.get("availability")):
            avtypes[c.get('type')] += 1
    for t, n in avtypes.items():
        if t not in P["core_availability"]:
            out.append(R(ATENCAO, f'availability type="{t}" ({n} usos)', f'plugin availability_{t} não nativo',
                         orientacao='Se ausente no destino, o Moodle ignora a condição ao carregar (modo "lax"), alterando as regras de acesso silenciosamente.'))
    for b in M["blocks"]:
        if b["name"] not in P["core_blocks"]:
            out.append(R(ATENCAO, f'course/blocks/{b["dir"]}', f'bloco não nativo block_{b["name"]} versão {b["version"]}'))
    for q, n in M["questions"]["qtypes"].items():
        if q not in P["core_qtypes"]:
            out.append(R(ATENCAO, 'questions.xml', f'tipo de questão não nativo qtype_{q} ({n})'))
    return out or [R(CONFORME, 'backup', 'apenas componentes nativos')]


@rule('X-INT-04', 'Referências inforef a arquivos inexistentes em files.xml', 'extra:6.1', 'A', 'Média')
def xint04(M, P):
    if not M["files"]:
        return [R(NV, 'files.xml', 'ausente')]
    out = []
    for m in M["modules"].values():
        bad = [i for i in m["fileref"] if i not in M["files"]]
        if bad:
            out.append(R(NAO_CONFORME, mod_label(m), f'inforef.xml referencia file ids inexistentes: {bad}'))
    return out or [R(CONFORME, 'activities/*/inforef.xml', 'todas as referências resolvidas')]


@rule('X-DEP-01', 'Condições de acesso apontando para módulos inexistentes', 'extra:6.4', 'A', 'Alta')
def xdep01(M, P):
    out = []
    for m in list(M["modules"].values()) + [s for s in M["sections"].values() if s.get("file")]:
        lbl = mod_label(m) if 'cmid' in m else f'seção {m.get("number")} "{m.get("name")}"'
        for c in walk_conditions(m.get("availability")):
            if c.get('type') == 'completion' and c.get('cm') not in M["modules"]:
                out.append(R(NAO_CONFORME, lbl, f'condição completion cm={c.get("cm")} não existe no backup',
                             orientacao='Condição órfã: o Moodle a ignora ou bloqueia; remover/recriar a restrição.'))
    return out or [R(CONFORME, 'availability', 'todas as referências a cm existem')]


@rule('X-DEP-02', 'Condição exige conclusão de módulo sem acompanhamento de conclusão (impossível)', 'extra:6.4', 'A', 'Bloqueante')
def xdep02(M, P):
    out = []
    for m in list(M["modules"].values()) + [s for s in M["sections"].values() if s.get("file")]:
        lbl = mod_label(m) if 'cmid' in m else f'seção {m.get("number")} "{m.get("name")}"'
        for c in walk_conditions(m.get("availability")):
            if c.get('type') == 'completion' and c.get('cm') in M["modules"]:
                t = M["modules"][c['cm']]
                if t["completion"] == 0:
                    out.append(R(NAO_CONFORME, lbl, f'exige conclusão (e={c.get("e")}) de {mod_label(t)} que tem completion=0',
                                 orientacao='Habilitar conclusão no módulo-alvo ou remover a condição; do contrário a condição nunca será satisfeita.'))
                elif t["visible"] == 0:
                    out.append(R(ATENCAO, lbl, f'exige conclusão de módulo OCULTO {mod_label(t)}', gravidade='Alta'))
                if c.get('e') in (2, 3) and not t["grade_items"]:
                    out.append(R(NAO_CONFORME, lbl, f'exige conclusão com aprovação/reprovação (e={c["e"]}) de módulo sem nota {mod_label(t)}'))
    return out or [R(CONFORME, 'availability', 'todas as condições de conclusão apontam para módulos com conclusão habilitada')]


@rule('X-DEP-03', 'Condições de nota apontando para itens inexistentes', 'extra:6.4', 'A', 'Alta')
def xdep03(M, P):
    allgi = set(M["grade_items"]) | {gi["id"] for m in M["modules"].values() for gi in m["grade_items"]}
    out = []
    for m in M["modules"].values():
        for c in walk_conditions(m.get("availability")):
            if c.get('type') == 'grade':
                if c.get('id') not in allgi:
                    st = NV if 'gradebook.xml' in M["missing_files"] else NAO_CONFORME
                    out.append(R(st, mod_label(m), f'condição grade id={c.get("id")} min={c.get("min")} max={c.get("max")} não encontrada nos itens de nota do backup'))
    return out or [R(CONFORME, 'availability', 'todas as condições de nota resolvidas')]


@rule('X-DEP-04', 'Ciclos no grafo de dependências de conclusão', 'extra:6.4', 'A', 'Bloqueante')
def xdep04(M, P):
    g = defaultdict(set)
    for m in M["modules"].values():
        for c in walk_conditions(m.get("availability")):
            if c.get('type') == 'completion':
                g[m["cmid"]].add(c.get('cm'))
    # DFS
    WHITE, GRAY, BLACK = 0, 1, 2
    color = defaultdict(int); cycles = []
    def dfs(u, path):
        color[u] = GRAY; path.append(u)
        for v in g.get(u, ()):
            if color[v] == GRAY:
                cycles.append(path[path.index(v):] + [v])
            elif color[v] == WHITE:
                dfs(v, path)
        path.pop(); color[u] = BLACK
    for u in list(g):
        if color[u] == WHITE:
            dfs(u, [])
    edges = sum(len(v) for v in g.values())
    if cycles:
        return [R(NAO_CONFORME, 'grafo de disponibilidade', f'ciclo: {c}', orientacao='Remover uma das condições para quebrar o ciclo.') for c in cycles]
    return [R(CONFORME, 'grafo de disponibilidade', f'{len(g)} nós com condições, {edges} arestas, sem ciclos')]


@rule('X-DEP-05', 'Condições redundantes/duplicadas', 'extra:6.4', 'A', 'Baixa')
def xdep05(M, P):
    out = []
    for m in M["modules"].values():
        seen = Counter(json.dumps({k: (int(v) if isinstance(v, str) and v.isdigit() else v) for k, v in c.items()}, sort_keys=True)
                       for c in walk_conditions(m.get("availability")))
        for k, n in seen.items():
            if n > 1:
                out.append(R(ATENCAO, mod_label(m), f'condição repetida {n}x: {k}', orientacao='Remover a duplicata (inofensiva, mas indica edição descuidada).'))
    return out or [R(CONFORME, 'availability', 'sem condições duplicadas')]


@rule('X-CERT-01', 'Alcançabilidade estática do certificado', 'extra:6.5', 'A', 'Bloqueante')
def xcert01(M, P):
    out = []
    for c in cert_module(M, P):
        need, stack, seen = [], [c["cmid"]], set()
        while stack:
            u = stack.pop()
            if u in seen:
                continue
            seen.add(u)
            m = M["modules"].get(u)
            if not m:
                continue
            for cond in walk_conditions(m.get("availability")):
                if cond.get('type') == 'completion':
                    need.append((u, cond.get('cm'))); stack.append(cond.get('cm'))
        chain = sorted({v for _, v in need})
        probs = []
        for v in chain:
            t = M["modules"].get(v)
            if not t:
                probs.append(f'cm {v} inexistente')
            elif t["completion"] == 0:
                probs.append(f'{t["modname"]}:{v} sem conclusão')
            elif t["visible"] == 0:
                probs.append(f'{t["modname"]}:{v} oculto')
        types = Counter(cond.get('type') for cond in walk_conditions(c["availability"]))
        out.append(R(NAO_CONFORME if probs else CONFORME, mod_label(c),
                     f'cadeia transitiva de conclusão exige {len(chain)} módulos: {chain}; tipos de condição no certificado={dict(types)}',
                     encontrado='; '.join(probs) or 'cadeia estaticamente satisfazível',
                     orientacao='Verificação estática: não prova emissão. Condições "grade", "days" e critérios de conclusão por nota dependem de execução real (usuário de teste).'))
        if c["visible"] == 0:
            out.append(R(NAO_CONFORME, mod_label(c), 'certificado oculto'))
    return out or [R(NAO_CONFORME, 'simplecertificate', 'sem certificado de conclusão')]


@rule('X-CONT-01', 'Recursos vazios (página/rótulo/livro sem texto nem mídia)', 'extra:6.2', 'A', 'Média')
def xcont01(M, P):
    out = []
    for m in M["modules"].values():
        if m["modname"] in ('page', 'label', 'book'):
            tx = ' '.join(html.unescape(t) for t in m["texts"].values())
            has_media = bool(re.search(r'<(img|iframe|video|audio|a)\b', tx))
            if not strip_html(tx) and not has_media:
                out.append(R(NAO_CONFORME, mod_label(m), 'sem texto e sem mídia/links', orientacao='Preencher ou remover o recurso.'))
            if m["modname"] == 'book' and not m["extra"].get("chapters"):
                out.append(R(NAO_CONFORME, mod_label(m), 'livro sem capítulos'))
        if m["modname"] == 'quiz' and m["extra"].get('slots', 0) == 0:
            out.append(R(NAO_CONFORME, mod_label(m), 'questionário sem questões', gravidade='Alta'))
        if m["modname"] == 'feedback' and m["extra"].get('items', 0) == 0:
            out.append(R(NAO_CONFORME, mod_label(m), 'feedback sem itens'))
        if m["modname"] in ('resource', 'folder') and M["files"]:
            fs = [f for f in M["files"].values() if str(f["contextid"]) == str(m["contextid"]) and f["filename"] != '.']
            if not fs:
                out.append(R(NAO_CONFORME, mod_label(m), 'recurso de arquivo sem nenhum arquivo associado', gravidade='Alta'))
    return out or [R(CONFORME, 'activities', 'nenhum recurso vazio detectado')]


@rule('X-CONT-02', 'Módulos com nomes duplicados', 'extra:6.2', 'D', 'Baixa')
def xcont02(M, P):
    by = defaultdict(list)
    for m in M["modules"].values():
        by[(m["modname"], norm(m.get("name")))].append(m)
    out = []
    for (mn, nm), ms in by.items():
        if len(ms) > 1 and nm:
            same = len({json.dumps(m["texts"], sort_keys=True) for m in ms}) == 1
            out.append(R(ATENCAO, f'{mn} "{ms[0]["name"]}" cmids={[m["cmid"] for m in ms]}',
                         'conteúdo idêntico' if same else 'conteúdo DIFERENTE (p.ex. versão em Libras) — não é duplicata',
                         orientacao='Se idêntico: remover duplicata. Se versões alternativas: diferenciar o nome (ex.: sufixo "(Libras)") para navegação/acessibilidade.',
                         gravidade='Baixa' if not same else 'Média'))
    return out or [R(CONFORME, 'activities', 'sem nomes duplicados')]


@rule('X-CONT-03', 'Links absolutos para o Moodle de origem com identificadores do curso antigo', 'extra:6.2/6.6', 'A', 'Média')
def xcont03(M, P):
    out = []
    www = re.escape(P["origin_wwwroot"])
    pat = re.compile(www + r'/(?:course/view\.php\?id=\d+|mod/\w+/view\.php\?id=\d+|question/edit\.php\?courseid=\d+|course/modedit\.php[^"\s<]*|grade/[^"\s<]*)')
    for m in M["modules"].values():
        for fld, t in m["texts"].items():
            for u in set(pat.findall(html.unescape(t))):
                out.append(R(NAO_CONFORME, mod_label(m) + f' campo {fld}', f'link absoluto {u}',
                             orientacao='Links com id numérico não são remapeados na restauração (só links codificados $@...VIEWBYID@$ são). Substituir por link relativo/atualizado.'))
    s = html.unescape(M["course"].get('summary') or '')
    for u in set(pat.findall(s)):
        out.append(R(NAO_CONFORME, 'course.xml <summary>', f'link absoluto {u}'))
    return out or [R(CONFORME, 'conteúdos', 'nenhum link absoluto com id de curso/módulo antigo')]


@rule('X-CONT-04', 'Referências @@PLUGINFILE@@ sem arquivo correspondente', 'extra:6.2', 'A', 'Média')
def xcont04(M, P):
    if not M["files"]:
        return [R(NV, 'files.xml', 'ausente')]
    out = []
    from urllib.parse import unquote
    byctx = defaultdict(set)
    for f in M["files"].values():
        if f["filename"] != '.':
            byctx[str(f["contextid"])].add((f["filepath"] or '/') + f["filename"])
    for m in M["modules"].values():
        for fld, t in m["texts"].items():
            for ref in set(re.findall(r'@@PLUGINFILE@@(/[^"\'\s)<>]+)', html.unescape(t))):
                path = unquote(ref.split('?')[0].split('#')[0])
                if path not in byctx.get(str(m["contextid"]), set()):
                    out.append(R(NAO_CONFORME, mod_label(m) + f' campo {fld}', f'@@PLUGINFILE@@{ref} não encontrado entre os arquivos do contexto {m["contextid"]}',
                                 orientacao='Imagem/arquivo quebrado após restauração. Reenviar o arquivo no editor.'))
    for s in M["sections"].values():
        for ref in set(re.findall(r'@@PLUGINFILE@@(/[^"\'\s)<>]+)', html.unescape(s.get("summary") or ''))):
            path = unquote(ref.split('?')[0].split('#')[0])
            secfiles = {(f["filepath"] or '/') + f["filename"] for f in M["files"].values() if f["component"] == 'course' and f["filearea"] == 'section' and f["itemid"] == s["id"]}
            if path not in secfiles:
                out.append(R(NAO_CONFORME, f'seção {s.get("number")} "{s.get("name")}"', f'@@PLUGINFILE@@{ref} não encontrado (course/section itemid={s["id"]})'))
    return out or [R(CONFORME, 'conteúdos', 'todas as referências @@PLUGINFILE@@ resolvidas')]


@rule('X-CONT-05', 'Menções a anos anteriores em campos institucionais', 'extra:6.2/6.6', 'D', 'Baixa')
def xcont05(M, P):
    out = []
    fields = [('course.xml <summary>', M["course"].get('summary') or '')]
    for m in M["modules"].values():
        if m["modname"] == 'page' and 'informa' in norm(m["name"]) and 'curso' in norm(m["name"]):
            fields.append((mod_label(m), m["texts"].get('content', '')))
        if m["modname"] == 'simplecertificate':
            fields.append((mod_label(m), ' '.join(m["texts"].values())))
    for lbl, t in fields:
        yrs = sorted({int(y) for y in re.findall(r'\b(20[12]\d)\b', strip_html(t)) if int(y) < P["reoffer_year"]})
        if yrs:
            out.append(R(ATENCAO, lbl, f'anos anteriores citados: {yrs}', orientacao='Conferir se a menção é referência bibliográfica (ok) ou dado da oferta anterior (atualizar).'))
    return out or [R(CONFORME, 'campos institucionais', 'sem menções a anos anteriores')]


@rule('X-CONT-06', 'Inventário de dependências externas (hosts)', 'extra:6.2', 'B', 'Informativa')
def xcont06(M, P):
    hosts = Counter()
    for m in M["modules"].values():
        for t in m["texts"].values():
            for u in re.findall(r'(?:href|src)="(https?://[^"/]+)', html.unescape(t)):
                hosts[u] += 1
        if m["modname"] == 'url' and m["extra"].get('externalurl'):
            mm = re.match(r'https?://[^/]+', m["extra"]["externalurl"])
            if mm:
                hosts[mm.group(0)] += 1
    http = [h for h in hosts if h.startswith('http://')]
    out = [R(CONFORME, 'conteúdos', f'{len(hosts)} hosts externos; top={hosts.most_common(8)}', gravidade='Informativa')]
    if http:
        out.append(R(ATENCAO, 'conteúdos', f'{len(http)} hosts via http:// (conteúdo misto pode ser bloqueado pelo navegador): {http[:8]}', gravidade='Baixa'))
    return out


@rule('X-ACC-01', 'Imagens sem texto alternativo', 'extra:6.8', 'A', 'Média')
def xacc01(M, P):
    out = []
    tot = 0
    for m in M["modules"].values():
        nimg = nbad = nempty = 0
        flds = []
        for fld, t in m["texts"].items():
            imgs = re.findall(r'<img\b[^>]*>', html.unescape(t))
            bad = [i for i in imgs if not re.search(r'\balt=', i)]
            nimg += len(imgs); nbad += len(bad); nempty += sum(1 for i in imgs if re.search(r'\balt=""', i))
            if bad:
                flds.append(fld)
        tot += nimg
        if nbad:
            out.append(R(NAO_CONFORME, mod_label(m), f'{nbad}/{nimg} <img> sem atributo alt (alt vazio={nempty}) nos campos {flds}',
                         orientacao='Adicionar texto alternativo (alt="" apenas para imagens decorativas). Sinal estático; não substitui auditoria WCAG/eMAG.'))
    for s in M["sections"].values():
        imgs = re.findall(r'<img\b[^>]*>', html.unescape(s.get("summary") or ''))
        bad = [i for i in imgs if not re.search(r'\balt=', i)]
        if bad:
            out.append(R(NAO_CONFORME, f'seção {s.get("number")} "{s.get("name")}"', f'{len(bad)}/{len(imgs)} <img> sem alt'))
    return out or [R(CONFORME, 'conteúdos', f'{tot} imagens, todas com alt')]


@rule('X-ACC-02', 'Links com texto genérico', 'extra:6.8', 'A', 'Baixa')
def xacc02(M, P):
    out = []
    for m in M["modules"].values():
        for fld, t in m["texts"].items():
            for href, txt in re.findall(r'<a\b[^>]*href="([^"]+)"[^>]*>(.*?)</a>', html.unescape(t), re.S):
                if norm(strip_html(txt)) in P["generic_link_texts"]:
                    out.append(R(ATENCAO, mod_label(m) + f' campo {fld}', f'texto "{strip_html(txt)}" -> {href[:80]}', orientacao='Usar texto de link descritivo.'))
    return out or [R(CONFORME, 'conteúdos', 'sem links com texto genérico')]


@rule('X-AV-01', 'Coerência entre enunciado e configuração (pontos)', 'extra:6.3', 'A', 'Média')
def xav01(M, P):
    out = []
    for m, gi in graded_modules(M):
        intro = norm(strip_html(m["texts"].get('intro', '')))
        mm = re.search(r'valor\s*[:\-]?\s*(\d+(?:[.,]\d+)?)\s*pontos?', intro)
        if mm:
            declared = float(mm.group(1).replace(',', '.'))
            if abs(declared - gi["grademax"]) > 0.001:
                out.append(R(NAO_CONFORME, mod_label(m), f'enunciado declara "Valor: {declared:g} pontos"; grademax={gi["grademax"]:g}',
                             orientacao='Alinhar enunciado e nota máxima.'))
    s = norm(strip_html(M["course"].get('summary') or ''))
    for mm in re.finditer(r'(\w+)\s+atividades avaliativas no formato de question[aá]rio', s):
        words = {'uma': 1, 'duas': 2, 'tres': 3, 'quatro': 4, 'cinco': 5, 'seis': 6, 'sete': 7, 'oito': 8, 'nove': 9, 'dez': 10}
        n = words.get(mm.group(1)) or (int(mm.group(1)) if mm.group(1).isdigit() else None)
        q = len(find_mods(M, 'quiz'))
        if n is not None and n != q:
            out.append(R(ATENCAO, 'course.xml <summary> "Processo de avaliação"', f'texto declara {n} questionários; curso tem {q}', orientacao='Atualizar o texto institucional.'))
    return out or [R(CONFORME, 'enunciados', 'sem divergências detectadas entre enunciado e pontuação')]


@rule('X-STR-01', 'Módulos ocultos, sem nome ou seções com nome genérico', 'extra:6.7', 'A', 'Baixa')
def xstr01(M, P):
    out = []
    for m in M["modules"].values():
        if m["visible"] == 0:
            out.append(R(ATENCAO, mod_label(m), 'módulo oculto', orientacao='Confirmar se deve permanecer oculto na reoferta.'))
        if m["modname"] != 'label' and not strip_html(m.get("name") or ''):
            out.append(R(NAO_CONFORME, mod_label(m), 'nome vazio'))
        if m["modname"] == 'label' and (m.get("name") or '').strip() == 'Rótulo' and not re.search(r'<img', html.unescape(m["texts"].get('intro', ''))):
            pass
    for s in M["sections"].values():
        if s.get("name") and re.match(P["generic_section_names"], norm(s["name"])):
            out.append(R(ATENCAO, f'seção {s.get("number")} ({s.get("file")})', f'nome genérico "{s["name"]}"', orientacao='Nomear a seção conforme o conteúdo (o banner da seção já traz o título).'))
    return out or [R(CONFORME, 'estrutura', 'sem módulos ocultos/sem nome e sem seções com nome genérico')]


@rule('X-SEC-01', 'Dados sensíveis no backup (senhas de inscrição)', 'extra:12', 'A', 'Informativa')
def xsec01(M, P):
    pw = [e for e in M["enrols"] if e["has_password"]]
    return [R(ATENCAO if pw else CONFORME, 'course/enrolments.xml', f'{len(pw)} métodos de inscrição com senha em texto claro no backup' if pw else 'sem senhas de inscrição',
              orientacao='O relatório nunca deve exibir o valor; o arquivo deve ser tratado como confidencial.', gravidade='Informativa')]


# ----------------------------------------------------------------------------------
# 4. AGREGAÇÃO E RELATÓRIO
# ----------------------------------------------------------------------------------

def run_rules(M, P):
    results = []
    for r in RULES:
        try:
            for res in r["fn"](M, P):
                res.update({"rule": r["id"], "titulo": r["titulo"], "origem": r["origem"], "classe": r["classe"]})
                res["gravidade"] = res.get("gravidade") or r["gravidade"]
                results.append(res)
        except Exception as e:
            results.append({"rule": r["id"], "titulo": r["titulo"], "origem": r["origem"], "classe": r["classe"], "status": ERRO,
                            "gravidade": r["gravidade"], "local": None, "evidencia": f'{type(e).__name__}: {e}'})
    return results


def inventory(M):
    inv = {"curso": {k: M["course"].get(k) for k in ('id', 'shortname', 'fullname', 'format', 'visible', 'lang', 'enablecompletion')},
           "categoria": M["course"].get('category'), "startdate": ts(M["course"].get('startdate')), "enddate": ts(M["course"].get('enddate')),
           "origem": {k: M["info"].get(k) for k in ('moodle_release', 'backup_version', 'original_wwwroot', 'original_course_id', 'backup_date')},
           "backup_date": ts(M["info"].get('backup_date')), "settings": M["settings"], "arquivos_ausentes_no_pacote": M["missing_files"],
           "secoes": [{"n": s.get("number"), "nome": s.get("name"), "modulos": len(s.get("sequence", [])), "visible": s.get("visible"),
                       "reconstruida": s.get("reconstructed", False)} for s in sorted(M["sections"].values(), key=lambda s: (s.get("number") is None, s.get("number") or 0))],
           "modulos_por_tipo": dict(Counter(m["modname"] for m in M["modules"].values())),
           "total_modulos": len(M["modules"]),
           "questionarios": [{"cmid": m["cmid"], "nome": m["name"], **{k: m["extra"].get(k) for k in ('attempts', 'timeclose', 'grade', 'slots', 'grademethod')}} for m in find_mods(M, 'quiz')],
           "itens_de_nota": [{"cmid": m["cmid"], "mod": m["modname"], "nome": gi["itemname"], "grademax": gi["grademax"], "gradetype": gi["gradetype"], "coef2": gi["aggregationcoef2"]}
                             for m in M["modules"].values() for gi in m["grade_items"]],
           "categorias_de_nota": M["gradebook"].get("categories"),
           "restricoes": [{"cmid": m["cmid"], "mod": m["modname"], "nome": m["name"], "availability": m["availability"]} for m in M["modules"].values() if m["availability"]],
           "conclusao": dict(Counter(f'{m["modname"]}:{m["completion"]}' for m in M["modules"].values())),
           "blocos": [{"name": b["name"], "title": b.get("title"), "version": b["version"]} for b in M["blocks"]],
           "inscricao": M["enrols"], "badges": len(M["badges"]), "questoes": {"categorias": M["questions"]["categories"], "questoes": M["questions"]["questions"], "tipos": dict(M["questions"]["qtypes"])},
           "arquivos": {"registros": len(M["files"]), "nao_diretorio": sum(1 for f in M["files"].values() if f["filename"] != '.'),
                        "bytes_declarados": sum((f["filesize"] or 0) for f in M["files"].values()), "ausentes_no_backup": sum(1 for f in M["files"].values() if not f["on_disk"]),
                        "por_mimetype": dict(Counter(f["mimetype"] for f in M["files"].values() if f["filename"] != '.'))},
           "plugins": {"modulos": {m["modname"]: m["plugin_version"] for m in M["modules"].values()}, "blocos": {b["name"]: b["version"] for b in M["blocks"]}}}
    return inv


def summarize(results):
    c = Counter(r["status"] for r in results)
    sev = Counter(r["gravidade"] for r in results if r["status"] == NAO_CONFORME)
    return {"por_status": dict(c), "nao_conformes_por_gravidade": dict(sev), "regras_executadas": len(RULES)}


def print_report(name, inv, results):
    print(f'\n=================== {name} ===================')
    print(f'Curso: {inv["curso"]["fullname"]} [{inv["curso"]["shortname"]}] id={inv["curso"]["id"]} | Moodle {inv["origem"].get("moodle_release")} | backup {inv["backup_date"]}')
    print(f'Período: {inv["startdate"]} → {inv["enddate"]} | categoria: {(inv["categoria"] or {}).get("name")}')
    print(f'Seções: {len(inv["secoes"])} | módulos: {inv["total_modulos"]} {inv["modulos_por_tipo"]}')
    if inv["arquivos_ausentes_no_pacote"]:
        print(f'!! Componentes ausentes no pacote: {inv["arquivos_ausentes_no_pacote"]}')
    s = summarize(results)
    print('Resumo:', s["por_status"], '| não conformes por gravidade:', s["nao_conformes_por_gravidade"])
    order = {NAO_CONFORME: 0, ATENCAO: 1, ERRO: 2, NV: 3, NA: 4, CONFORME: 5}
    sevorder = {'Bloqueante': 0, 'Alta': 1, 'Média': 2, 'Baixa': 3, 'Informativa': 4}
    for r in sorted(results, key=lambda r: (order.get(r["status"], 9), sevorder.get(r.get("gravidade"), 9), r["rule"])):
        if r["status"] == CONFORME:
            continue
        print(f'  [{r["status"]:15}] {r["rule"]:9} {r.get("gravidade", ""):11} {r["titulo"]}')
        print(f'      local: {r.get("local")}')
        print(f'      evidência: {str(r.get("evidencia"))[:400]}')
        if r.get("encontrado"):
            print(f'      encontrado: {str(r["encontrado"])[:300]}')
        if r.get("esperado"):
            print(f'      esperado: {str(r["esperado"])[:200]}')
        if r.get("orientacao"):
            print(f'      orientação: {r["orientacao"]}')
    conf = [r for r in results if r["status"] == CONFORME]
    print(f'  ({len(conf)} resultados CONFORMES omitidos: {sorted(set(r["rule"] for r in conf))})')


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument('target', help='.mbz ou diretório já extraído')
    ap.add_argument('--out', help='arquivo JSON de saída')
    ap.add_argument('--policy', help='JSON com parâmetros de política (sobrescreve os padrões)')
    ap.add_argument('--workdir', help='diretório de trabalho para extração (padrão: temporário, removido ao final)')
    a = ap.parse_args()
    P = dict(DEFAULT_POLICY)
    if a.policy:
        with open(a.policy, encoding='utf-8') as f:
            P.update(json.load(f))
    if not P.get("reoffer_year"):
        ref = dt.datetime.fromisoformat(P["reference_date"]) if P.get("reference_date") else dt.datetime.now()
        P["reoffer_year"] = ref.year + 1   # auditoria em out/nov prepara as salas que iniciam em 01/01 do ano seguinte
    tmp = None
    if os.path.isdir(a.target):
        root = a.target; archive = {"format": "dir", "note": "diretório já extraído; integridade do stream não avaliada aqui"}
    else:
        tmp = a.workdir or tempfile.mkdtemp(prefix='mbz_')
        root = os.path.join(tmp, 'x'); archive = safe_extract(a.target, root)
    M = load_model(root)
    M["archive"] = archive
    M["index"] = check_archive_index(root)
    inv = inventory(M)
    results = run_rules(M, P)
    out = {"engine": "mbz_inspect PoC 0.1", "policy_version": P["policy_version"], "executed_at": dt.datetime.now().isoformat(timespec='seconds'),
           "target": os.path.basename(a.target), "archive": archive, "archive_index": {k: v for k, v in M["index"].items() if k != 'missing'} | {"missing_count": len(M["index"].get("missing", []))},
           "inventory": inv, "summary": summarize(results), "results": results}
    if a.out:
        with open(a.out, 'w', encoding='utf-8') as f:
            json.dump(out, f, ensure_ascii=False, indent=1, default=str)
    print_report(os.path.basename(a.target), inv, results)
    if tmp and not a.workdir:
        shutil.rmtree(tmp, ignore_errors=True)


if __name__ == '__main__':
    main()
