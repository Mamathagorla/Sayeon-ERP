<?php

namespace App\Modules\Expense\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Expense claims: an employee submits an out-of-pocket expense, an approver accepts or rejects it, and Finance
 * reimburses it - which books a cash-out payment in Accounting (payments ledger) linked back to the claim.
 *
 * Also adds the expense_claim.* permissions and grants them to the standard roles (Super Admin bypasses permission
 * checks by role slug). Idempotent: existing tables/permissions/grants are left untouched.
 */
class CreateExpenseClaimsTable extends Migration
{
    private const GRANTS = [
        'employee'           => ['view', 'create'],
        'compliance_officer' => ['view', 'create'],
        'manager'            => ['view', 'create', 'approve'],
        'hr'                 => ['view', 'create', 'approve'],
        'accountant'         => ['view', 'create', 'approve', 'reimburse'],
        'admin'              => ['view', 'create', 'approve', 'reimburse'],
    ];

    private const DESCRIPTIONS = [
        'view'      => 'View expense claims',
        'create'    => 'Submit expense claims',
        'approve'   => 'Approve or reject expense claims',
        'reimburse' => 'Reimburse approved expense claims',
    ];

    public function up()
    {
        if (! $this->db->tableExists('expense_claims')) {
            $this->forge->addField([
                'id'              => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
                'company_id'      => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
                'user_id'         => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
                'title'           => ['type' => 'VARCHAR', 'constraint' => 150],
                'category'        => ['type' => 'VARCHAR', 'constraint' => 100],
                'amount'          => ['type' => 'DECIMAL', 'constraint' => '12,2'],
                'expense_date'    => ['type' => 'DATE'],
                'description'     => ['type' => 'TEXT', 'null' => true],
                'status'          => ['type' => 'ENUM', 'constraint' => ['submitted', 'approved', 'rejected', 'reimbursed', 'cancelled'], 'default' => 'submitted'],
                'decided_by'      => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
                'decided_at'      => ['type' => 'DATETIME', 'null' => true],
                'decision_note'   => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'reimbursed_by'   => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
                'reimbursed_at'   => ['type' => 'DATETIME', 'null' => true],
                'payment_method'  => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
                'payment_id'      => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
                'created_at'      => ['type' => 'DATETIME', 'null' => true],
                'updated_at'      => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['company_id', 'status']);
            $this->forge->addKey('user_id');
            $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('decided_by', 'users', 'id', 'SET NULL', 'CASCADE');
            $this->forge->addForeignKey('reimbursed_by', 'users', 'id', 'SET NULL', 'CASCADE');
            $this->forge->addForeignKey('payment_id', 'payments', 'id', 'SET NULL', 'CASCADE');
            $this->forge->createTable('expense_claims');
        }

        foreach (self::DESCRIPTIONS as $action => $description) {
            $slug = "expense_claim.{$action}";

            if ($this->db->table('permissions')->where('slug', $slug)->countAllResults() === 0) {
                $this->db->table('permissions')->insert(['slug' => $slug, 'module' => 'expense_claim', 'description' => $description]);
            }
        }

        foreach (self::GRANTS as $roleSlug => $actions) {
            $role = $this->db->table('roles')->where('slug', $roleSlug)->get()->getRowArray();
            if ($role === null) {
                continue;
            }

            foreach ($actions as $action) {
                $perm = $this->db->table('permissions')->where('slug', "expense_claim.{$action}")->get()->getRowArray();
                if ($perm === null) {
                    continue;
                }

                $has = $this->db->table('role_permissions')->where(['role_id' => $role['id'], 'permission_id' => $perm['id']])->countAllResults() > 0;
                if (! $has) {
                    $this->db->table('role_permissions')->insert(['role_id' => $role['id'], 'permission_id' => $perm['id']]);
                }
            }
        }
    }

    public function down()
    {
        $this->forge->dropTable('expense_claims', true);
        $this->db->table('permissions')->where('module', 'expense_claim')->delete();
    }
}
