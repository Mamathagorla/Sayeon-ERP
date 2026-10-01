<?php

namespace App\Modules\HR\Database\Migrations;

use CodeIgniter\Database\Migration;

class DropCompanyEmployeeSequencesTable extends Migration
{
    /**
     * Reverses 2026-09-29-090001_CreateCompanyEmployeeSequencesTable —
     * a separate consumed-counter table turned out to be the wrong
     * shape for this: EmployeeController only ever calls
     * nextEmployeeCode() to pre-fill a *suggested* value on the "Add
     * Employee" GET form (the field stays a plain editable text input,
     * and whatever is actually POSTed is what gets saved, guarded by
     * employee_code's existing table-wide UNIQUE key) — never to
     * atomically consume a slot at save time. A separate counter
     * incremented on every form *view* would burn numbers every time an
     * admin opened the form and didn't save, and would need its own
     * out-of-band correction if an admin hand-typed a different code.
     * nextEmployeeCode(int $companyId) instead just scans this
     * company's own existing employee_code rows for its highest current
     * number and previews +1 — the exact same read-only, no-side-effect
     * shape the original global version already had, merely scoped to
     * one company's own codes instead of the whole table. Kept as its
     * own migration (rather than editing 090001) per this project's own
     * convention of never rewriting an already-applied migration.
     */
    public function up()
    {
        $this->forge->dropTable('company_employee_sequences', true);
    }

    public function down()
    {
        // Intentionally not recreated — see up()'s docblock for why this
        // table was a dead end. A rollback here is a no-op.
    }
}
