# Changes

All releases so far are ALPHA and were tested in laboratory sites only. Detailed technical notes (in Portuguese) are in
`docs/desenvolvimento/arquitetura-plugin.md` of the project repository.

## 0.7.0 (2026103102) — 2026-10-30

- Report rows explain each problem in plain Moodle language, in the viewer's language: the problem with activity
  names (no internal ids), why it matters, how to fix it (with the alternatives when the choice is a teaching
  decision), who can do it and how to confirm it. The stored evidence stays under "Technical details".
- Each row links to the exact section of the Moodle form (`showonly`: restrict access, completion conditions, grade,
  timing), to both activities of a dependency and to every activity of a cycle; links appear only when the activity
  still exists and the viewer may use the screen. Course settings and self enrolment come back to the report.
- The report says when a fix must come first (an invalid access condition blocks saving the activity form; course
  dates before enrolment and closing dates; the grade total before the certificate minimum) and warns when a plugin
  the policy depends on (certificate, game, completion progress block) is missing from the site.
- Texts are built when the page is shown, from the facts of the stored run and its policy. No change in rules,
  states, severities, occurrence keys or the JSON contract (1.0).

## 0.6.1 (2026103101) — 2026-10-30

- First release distributed as an installable ZIP, built with `tools/package-plugin.sh`; the package no longer contains
  the laboratory scripts that create test courses (`cli/make_*.php`).
- The engine version shown in reports, the history and the API follows the release (0.5.1–0.6.0 still showed 0.5.0).
- README for administrators and this change log. No change in rules or data.

## 0.6.0 (2026103100) — 2026-10-30

- Web service API (disabled at install): `local_mooc_audit_request_audit`, `local_mooc_audit_get_run`,
  `local_mooc_audit_get_latest_audit`; new capability `local/mooc_audit:api` with no default role; setting
  `api_max_pending`. Guide in `API.md`.

## 0.5.3 (2026103004) — 2026-10-30

- Tested on Moodle 5.3 LTS as well as 4.5; `$plugin->supported = [405, 503]`. Status badges use classes available in
  both Bootstrap versions. No change in rules or data.

## 0.5.2 (2026103003) — 2026-10-30

- Privacy API also exports and anonymises corrections of deleted courses. Test-data scripts refuse to run outside a
  local development site. Text extraction keeps paragraph breaks. Report tables scroll on narrow screens.

## 0.5.1 (2026103002) — 2026-10-30

- The re-audit after an assisted correction is recorded in the history. A plugin upgrade marks results as outdated
  only when the code of a rule changed. Wording and contrast fixes.

## 0.5.0 (2026103000) — 2026-10-29

- Audit history with evolution between audits, background batch audits through the task queue, follow-up dashboard,
  retention task. New tables `local_mooc_audit_run`, `_policy`, `_batch`; capabilities `viewdashboard`, `batchaudit`.
  The audit page shows the last recorded result and audits only on request.

## 0.4.0 (2026102906) — 2026-10-29

- Comparison between two offers of a course (page, command line, JSON contract `comparison` 1.0); capability
  `compare`.

## 0.3.0 (2026102905) — 2026-10-29

- Assisted correction of quiz attempts (CL-13) and quiz close date (CL-14), with preview, simulation, confirmation,
  re-audit and audit trail (table `local_mooc_audit_fix`, event `correction_applied`). Editing teachers may audit and
  correct (upgrade grants the audit capability on existing sites).

## 0.2.0 — 2026-10-29

- 29 rules in their own classes, each with tests; JSON report contract 1.0; report page in the course menu; policy
  editable in the administration.
