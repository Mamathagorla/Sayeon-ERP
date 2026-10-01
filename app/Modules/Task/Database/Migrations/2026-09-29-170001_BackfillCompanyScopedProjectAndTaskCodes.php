<?php

namespace App\Modules\Task\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Backfills every existing project/task with a company-prefixed code
 * (e.g. 'SAY-P001', 'SAY-T001') — these columns existed already
 * (2026-09-29-120001_AddTaskAndProjectCodes) but were left blank for
 * every pre-existing row since there was no short_code to derive a
 * prefix from at the time; now there is (see companies.
 * ReaddShortCodeToCompanies), and the new short format replaces the
 * older 'PRJ-SAY-0001'/'TSK-SAY-0001' style this app never actually
 * shipped with real data in. Ordered by id within each company for a
 * stable, reproducible sequence.
 */
class BackfillCompanyScopedProjectAndTaskCodes extends Migration
{
    public function up()
    {
        $companies = $this->db->table('companies')->select('id, short_code')->where('short_code IS NOT NULL')->get()->getResultArray();

        foreach ($companies as $company) {
            $this->backfill('projects', 'project_code', $company['id'], $company['short_code'] . '-P');
            $this->backfill('tasks', 'task_code', $company['id'], $company['short_code'] . '-T');
        }
    }

    private function backfill(string $table, string $column, int $companyId, string $prefix): void
    {
        $rows = $this->db->table($table)
            ->select('id')
            ->where('company_id', $companyId)
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        $n = 1;
        foreach ($rows as $row) {
            $code = $prefix . str_pad((string) $n, 3, '0', STR_PAD_LEFT);
            $this->db->table($table)->where('id', $row['id'])->update([$column => $code]);
            $n++;
        }
    }

    public function down()
    {
        $this->db->table('projects')->where('id >', 0)->update(['project_code' => null]);
        $this->db->table('tasks')->where('id >', 0)->update(['task_code' => null]);
    }
}
