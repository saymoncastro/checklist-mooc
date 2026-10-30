# MOOC audit (`local_mooc_audit`)

Technical audit of Moodle courses before they are offered again. The plugin reads an existing course, checks it against
29 rules (dates, enrolment, completion and access restrictions, grade book, quizzes, certificate, required resources,
broken files and links, hidden or empty content) and shows a report with the location of each problem, the evidence
and what to change. It was built for the yearly re-offer of the MOOC courses of Cefor/Ifes (Brazil), where every course
is re-imported and must be checked again.

- **Who uses it:** the people who prepare a course for a new offer (managers and editing teachers) and the team that
  follows all courses (managers).
- **Read-only by default:** auditing never changes the course. Two settings of quizzes can be fixed from the report,
  always after a preview and an explicit confirmation (see *Assisted correction*).
- **Status:** release 0.6.1, maturity **ALPHA**. Used in a laboratory and under institutional validation; not yet
  used in production.

> **Institutional policy.** The default policy is the one of Cefor/Ifes (course dates on 01/01–31/12, category
> "Inscrições Abertas", forum "Espaço de interação", certificate by `mod_simplecertificate`, Progress block, Portuguese
> activity names). On any other site, adjust the policy before relying on the results — otherwise most courses are
> reported as *Blocked*. See *Configuration*.

## Features

| Feature | Where | Who (default roles) |
|---|---|---|
| Technical audit of a course: run it, read the last result, export JSON | Course > More > **Technical audit** | Manager, editing teacher |
| Assisted correction of quiz attempts (CL-13) and quiz close date (CL-14), with preview, simulation and audit trail | *See correction proposal* link in the report | Manager, editing teacher |
| Audit history of a course and evolution between audits (new, persistent, resolved problems) | *Audit history* link on the audit page | Manager, editing teacher |
| Comparison between two offers of a course (previous offer × new offer) | Site administration > Courses > **Compare offers** | Manager |
| Follow-up dashboard of all courses and background batch audits | Site administration > Courses > **Audit follow-up** | Manager |
| Command line: audit, batch, comparison | `local/mooc_audit/cli/{audit,batch,compare}.php` | Server administrator |
| Web service API: request an audit, follow a run, read the last result (service disabled at install) | Site administration > Server > Web services | Integration account, configured by an administrator |

Every report lists all 29 rules — identifiers CL-04 … CL-23 come from the institutional checklist, X-* are additional
technical checks — either among the problems found or among the conformant rules.

## Requirements

| | Tested | Notes |
|---|---|---|
| Moodle | **4.5** (4.5.15) and **5.3** (5.3+, build 20261030) | `$plugin->supported = [405, 503]`. Other versions not tested. |
| PHP | 8.3 | |
| Database | PostgreSQL 17 | MySQL/MariaDB not tested yet. |
| Other plugins | none required | When installed, `mod_simplecertificate`, `mod_game`, `mod_hvp` and `block_completion_progress` are also read; the default policy expects the first and the last. |
| Cron | required for batch audits and API requests | Single audits from the course page run immediately. |

## Installation

**From the ZIP (recommended).** Download `local_mooc_audit-<release>-<version>.zip`, then *Site administration >
Plugins > Install plugins*, upload the ZIP, click *Install plugin from the ZIP file*, *Continue* and *Upgrade Moodle
database now*. The ZIP contains a single folder, `mooc_audit/`.

**Manually.** Unzip the package so that the plugin ends in `local/mooc_audit` (Moodle 4.5) or
`public/local/mooc_audit` (Moodle 5.1 and later), then visit *Site administration > Notifications* or run
`php admin/cli/upgrade.php`.

**Do not** use GitHub's *Download ZIP* of the project repository: it contains the whole development project and
Moodle cannot install it.

**Upgrade:** install the new ZIP the same way (Moodle replaces the folder) or replace the folder manually, then run
the upgrade. History, correction trail and settings are kept.

**Uninstall:** *Site administration > Plugins > Plugins overview > MOOC audit > Uninstall*. This deletes the audit
history, the batches and the correction trail; changes already applied to courses stay.

## Configuration

1. *Site administration > Plugins > Local plugins > MOOC audit*: **Institutional policy (JSON)**. The page lists every
   key with its type and default. Only the keys you set override the defaults, for example
   `{"target_category_name": "Open courses", "min_quiz_attempts": 3}`.
   Invalid JSON is refused when saving. The other settings are retention of the history (365 days), when a result is
   considered outdated (7 days), background concurrency, batch size and API limit.
2. Check the **site time zone**: date rules use it.
3. Capabilities (*Site administration > Users > Permissions > Define roles*):

| Capability | Context | Default |
|---|---|---|
| `local/mooc_audit:audit` — run the audit, see the report and the history | course | manager, editing teacher |
| `local/mooc_audit:preparecorrection` — see correction proposals | course | manager, editing teacher |
| `local/mooc_audit:applycorrection` — confirm a correction (also needs `moodle/course:manageactivities`; `RISK_CONFIG`) | course | manager, editing teacher |
| `local/mooc_audit:compare` — compare two offers (also needs `audit` in both courses) | system | manager |
| `local/mooc_audit:viewdashboard` — dashboard and batches (read) | system | manager |
| `local/mooc_audit:batchaudit` — request batches | system | manager |
| `local/mooc_audit:api` — use the web service API | system | nobody |

Non-editing teachers and students receive nothing. The API needs extra setup, described in [API.md](API.md)
(Portuguese).

## First audit

1. Open the course and choose **More > Technical audit**.
2. The page says the course has not been audited yet. Click **Run audit**.
3. Read the **Overall status**: *Blocked* (at least one blocking problem), *Needs fixes* or *No non-conformities*. "No
   non-conformities" means only that none of the 29 rules found a problem; it is not an approval of the course.
4. Go through **Non-conformities**, grouped by severity (blocking, high, medium, low, informative) and by category.
   Each line has the rule, the location (a link to the setting to change when there is one), the evidence and the
   guidance. *Warnings* need a human decision; *Not verified or not applicable* lists what the plugin could not check.
5. Fix the course in Moodle and click **Run again**. The new result is compared with the previous one.

The page always shows the **last recorded result** with its date; it warns when the course, the policy or the rules
changed after it.

### Result states

| State | Meaning |
|---|---|
| Non conformant | The rule found a problem to fix. |
| Attention | Possible problem; a person must decide. |
| Conformant | Checked, no problem. |
| Not applicable | The rule does not apply to this course. |
| Not verifiable | The data needed is not available (for example a missing plugin). Not a failure. |
| Verification error | The rule failed to run; the other rules are not affected. |

## Assisted correction

For CL-13 (quiz attempts) and CL-14 (quiz close date) the report offers *See correction proposal*: current and proposed
value, justification, simulated effect on the rules, impact on students' data and impediments. *Confirm correction*
writes only that setting, re-reads it, re-audits the course and records who changed what. There is no automatic undo;
the previous value is kept in the trail. Other problems link to the Moodle page where they are fixed.

## Privacy

The plugin stores, for each recorded audit, batch and confirmed correction, the id of the user who requested or applied
it. It implements the Privacy API (export and anonymisation, including rows of deleted courses). It sends no data
outside Moodle.

## Known limitations

- The default policy and the canonical activity names are specific to Cefor/Ifes (see *Configuration*).
- The evidence texts of the rules are generated in Portuguese, even with the interface in English.
- Only quizzes can be corrected from the plugin; the comparison between offers does not store manual matches.
- Tested on PostgreSQL only.

## Support and development

- Source code, issues and documentation: <https://github.com/saymoncastro/checklist-mooc> (the plugin lives in
  `plugin/local_mooc_audit/`; the package is built with `tools/package-plugin.sh`).
- Changes between releases: [CHANGES.md](CHANGES.md).

## License

Copyright 2026 Cefor/Ifes. Licensed under the GNU GPL v3 or later (<https://www.gnu.org/licenses/gpl-3.0.html>).
