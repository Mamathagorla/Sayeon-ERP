<?php

namespace App\Modules\HR\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Payroll becomes per company.
 *
 * Until now a payroll run was global: payroll_runs had UNIQUE(month, year) and no company, so once any company had
 * processed a month every other company was told "A payroll run for this period already exists". This adds
 * payroll_runs.company_id (+ paid_at) and makes the unique key (company_id, month, year).
 *
 * Existing runs are back-filled with their company when every payslip in the run belongs to one company; a legacy run
 * that mixed several companies keeps company_id NULL and is treated as an org-wide run (visible to Super Admin and to
 * any company that has a payslip in it).
 *
 * It also adds payments.payroll_run_id so that marking a run paid can book ONE cash-out payment per company in the
 * payments ledger (unique per run + company, so a repeated click cannot book it twice).
 *
 * Idempotent: every step checks what already exists.
 */
class AddCompanyToPayrollRuns extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('company_id', 'payroll_runs')) {
            $this->db->query('ALTER TABLE `payroll_runs` ADD COLUMN `company_id` INT UNSIGNED NULL AFTER `id`');
        }
        if (! $this->db->fieldExists('paid_at', 'payroll_runs')) {
            $this->db->query('ALTER TABLE `payroll_runs` ADD COLUMN `paid_at` DATETIME NULL AFTER `processed_at`');
        }

        $this->db->query(
            'UPDATE `payroll_runs` pr
             JOIN (SELECT ps.payroll_run_id AS run_id, MIN(ep.company_id) AS company_id, COUNT(DISTINCT ep.company_id) AS n
                   FROM `payslips` ps JOIN `employee_profiles` ep ON ep.user_id = ps.user_id
                   GROUP BY ps.payroll_run_id) x ON x.run_id = pr.id
             SET pr.company_id = x.company_id
             WHERE x.n = 1 AND pr.company_id IS NULL'
        );
        $this->db->query("UPDATE `payroll_runs` SET paid_at = processed_at WHERE status = 'paid' AND paid_at IS NULL");

        $indexes = array_column($this->db->query('SHOW INDEX FROM `payroll_runs`')->getResultArray(), 'Key_name');

        if (in_array('month_year', $indexes, true)) {
            $this->db->query('ALTER TABLE `payroll_runs` DROP INDEX `month_year`');
        }
        if (! in_array('company_month_year', $indexes, true)) {
            $this->db->query('ALTER TABLE `payroll_runs` ADD UNIQUE KEY `company_month_year` (`company_id`, `month`, `year`)');
        }
        if (! $this->foreignKeyExists('payroll_runs', 'payroll_runs_company_id_foreign')) {
            $this->db->query('ALTER TABLE `payroll_runs` ADD CONSTRAINT `payroll_runs_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE');
        }

        if (! $this->db->fieldExists('payroll_run_id', 'payments')) {
            $this->db->query('ALTER TABLE `payments` ADD COLUMN `payroll_run_id` INT UNSIGNED NULL AFTER `bill_id`');
            $this->db->query('ALTER TABLE `payments` ADD UNIQUE KEY `payroll_run_company` (`payroll_run_id`, `company_id`)');
            $this->db->query('ALTER TABLE `payments` ADD CONSTRAINT `payments_payroll_run_id_foreign` FOREIGN KEY (`payroll_run_id`) REFERENCES `payroll_runs` (`id`) ON DELETE SET NULL');
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('payroll_run_id', 'payments')) {
            $this->db->query('ALTER TABLE `payments` DROP FOREIGN KEY `payments_payroll_run_id_foreign`');
            $this->db->query('ALTER TABLE `payments` DROP INDEX `payroll_run_company`');
            $this->db->query('ALTER TABLE `payments` DROP COLUMN `payroll_run_id`');
        }

        if ($this->db->fieldExists('company_id', 'payroll_runs')) {
            $this->db->query('ALTER TABLE `payroll_runs` DROP FOREIGN KEY `payroll_runs_company_id_foreign`');
            $this->db->query('ALTER TABLE `payroll_runs` DROP INDEX `company_month_year`');
            // Only restorable while no two companies share a month.
            $this->db->query('ALTER TABLE `payroll_runs` ADD UNIQUE KEY `month_year` (`month`, `year`)');
            $this->db->query('ALTER TABLE `payroll_runs` DROP COLUMN `paid_at`');
            $this->db->query('ALTER TABLE `payroll_runs` DROP COLUMN `company_id`');
        }
    }

    private function foreignKeyExists(string $table, string $name): bool
    {
        return $this->db->query(
            'SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = \'FOREIGN KEY\'',
            [$table, $name]
        )->getNumRows() > 0;
    }
}
