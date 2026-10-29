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

namespace local_mooc_audit\output;

use plugin_renderer_base;

/**
 * Renderer of local_mooc_audit (Mustache templates).
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends plugin_renderer_base {
    /**
     * Render the audit report page body.
     *
     * @param report $report
     * @return string HTML
     */
    public function render_report(report $report): string {
        return $this->render_from_template('local_mooc_audit/report', $report->export_for_template($this));
    }

    /**
     * Render the preview of a correction proposal.
     *
     * @param correction_proposal $page
     * @return string HTML
     */
    public function render_correction_proposal(correction_proposal $page): string {
        return $this->render_from_template('local_mooc_audit/correction', $page->export_for_template($this));
    }

    /**
     * Render the outcome of a confirmed correction.
     *
     * @param correction_result $page
     * @return string HTML
     */
    public function render_correction_result(correction_result $page): string {
        return $this->render_from_template('local_mooc_audit/correction_result', $page->export_for_template($this));
    }

    /**
     * Render a comparison between two offers (specs/003).
     *
     * @param comparison_report $page
     * @return string HTML
     */
    public function render_comparison_report(comparison_report $page): string {
        return $this->render_from_template('local_mooc_audit/comparison', $page->export_for_template($this));
    }

    /**
     * Render the "since the previous audit" box (specs/005).
     *
     * @param run_summary $box
     * @return string HTML
     */
    public function render_run_summary(run_summary $box): string {
        return $this->render_from_template('local_mooc_audit/run_summary', $box->export_for_template($this));
    }

    /**
     * Render the list of recorded audits of a course (specs/005).
     *
     * @param history_page $page
     * @return string HTML
     */
    public function render_history_page(history_page $page): string {
        return $this->render_from_template('local_mooc_audit/history', $page->export_for_template($this));
    }

    /**
     * Render one recorded audit with its evolution (specs/005).
     *
     * @param run_page $page
     * @return string HTML
     */
    public function render_run_page(run_page $page): string {
        return $this->render_from_template('local_mooc_audit/run', $page->export_for_template($this));
    }

    /**
     * Render the follow-up dashboard (specs/005).
     *
     * @param dashboard_page $page
     * @return string HTML
     */
    public function render_dashboard_page(dashboard_page $page): string {
        return $this->render_from_template('local_mooc_audit/dashboard', $page->export_for_template($this));
    }

    /**
     * Render a batch (specs/005).
     *
     * @param batch_page $page
     * @return string HTML
     */
    public function render_batch_page(batch_page $page): string {
        return $this->render_from_template('local_mooc_audit/batch', $page->export_for_template($this));
    }
}
