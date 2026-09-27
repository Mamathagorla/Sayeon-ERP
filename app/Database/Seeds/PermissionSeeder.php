<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * One view/create/edit/delete permission per module. As later phases
 * add modules (expense, bank_account, website,
 * hr, accounting, marketing, document, personal_task), extend
 * $modules below and re-run: php spark db:seed PermissionSeeder
 */
class PermissionSeeder extends Seeder
{
    public function run()
    {
        $modules = [
            'user', 'role', 'company', 'department', 'project', 'task', 'meeting', 'compliance', 'report',
            'employee', 'attendance', 'leave', 'payroll', 'performance', 'onboarding', 'offboarding',
            'expense', 'bank_account', 'invoice', 'bill',
            'website', 'document', 'campaign', 'policy', 'purchase_order', 'support_ticket',
        ];
        $actions = ['view', 'create', 'edit', 'delete'];

        // Reports are read-only — there's nothing to create/edit/delete,
        // just a view permission gating access to the whole module.
        $viewOnlyModules = ['report'];

        foreach ($modules as $module) {
            $moduleActions = in_array($module, $viewOnlyModules, true) ? ['view'] : $actions;

            foreach ($moduleActions as $action) {
                $slug   = "{$module}.{$action}";
                $exists = $this->db->table('permissions')->where('slug', $slug)->countAllResults() > 0;

                if (! $exists) {
                    $this->db->table('permissions')->insert([
                        'slug'        => $slug,
                        'module'      => $module,
                        'description' => ucfirst($action) . ' ' . str_replace('_', ' ', $module),
                    ]);
                }
            }
        }

        // Leave has a workflow action beyond the standard CRUD set —
        // approving/rejecting someone else's request is distinct from
        // editing your own.
        $approveSlug = 'leave.approve';
        if ($this->db->table('permissions')->where('slug', $approveSlug)->countAllResults() === 0) {
            $this->db->table('permissions')->insert([
                'slug'        => $approveSlug,
                'module'      => 'leave',
                'description' => 'Approve or reject leave requests',
            ]);
        }

        // Same workflow-action pattern as leave.approve — deciding a
        // purchase order is distinct from editing one.
        $poApproveSlug = 'purchase_order.approve';
        if ($this->db->table('permissions')->where('slug', $poApproveSlug)->countAllResults() === 0) {
            $this->db->table('permissions')->insert([
                'slug'        => $poApproveSlug,
                'module'      => 'purchase_order',
                'description' => 'Approve or reject purchase order requests',
            ]);
        }

        // Superseded by the invoice/bill split above — Invoice/BillController
        // were always separate, just shared one flat permission. Deleting
        // cascades to role_permissions, so no dangling references linger.
        $this->db->table('permissions')->where('module', 'accounting')->delete();
    }
}
