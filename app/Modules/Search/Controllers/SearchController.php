<?php

namespace App\Modules\Search\Controllers;

use App\Controllers\BaseController;

/**
 * Global topbar search. Each section only runs if the viewer holds that
 * module's own *.view permission and only within their company scope —
 * same rules the module's own list page already enforces, just fanned
 * out across modules instead of picking one.
 */
class SearchController extends BaseController
{
    public function live()
    {
        $term = trim((string) $this->request->getGet('q'));

        if (mb_strlen($term) < 2) {
            return $this->response->setJSON(['results' => []]);
        }

        $like         = '%' . $term . '%';
        $companyScope = $this->companyScopeFor(self::COMPANY_SCOPED_ROLES);
        $db           = db_connect();
        $results      = [];

        if (can('company.view')) {
            $builder = $db->table('companies')->select('id, name, slug')
                ->groupStart()->like('name', $term)->orLike('slug', $term)->groupEnd();
            if ($companyScope !== null) {
                $builder->where('id', $companyScope);
            }
            foreach ($builder->limit(5)->get()->getResultArray() as $row) {
                $results[] = [
                    'category' => 'Companies',
                    'icon'     => 'fa-building',
                    'label'    => $row['name'],
                    'sublabel' => 'Company',
                    'link'     => site_url('companies/' . $row['id']),
                ];
            }
        }

        if (can('employee.view')) {
            $builder = $db->table('employee_profiles')
                ->select('employee_profiles.id, users.name as user_name, employee_profiles.employee_code, companies.name as company_name')
                ->join('users', 'users.id = employee_profiles.user_id')
                ->join('companies', 'companies.id = employee_profiles.company_id')
                ->groupStart()->like('users.name', $term)->orLike('employee_profiles.employee_code', $term)->groupEnd();
            if ($companyScope !== null) {
                $builder->where('employee_profiles.company_id', $companyScope);
            }
            foreach ($builder->limit(5)->get()->getResultArray() as $row) {
                $results[] = [
                    'category' => 'Employees',
                    'icon'     => 'fa-users',
                    'label'    => $row['user_name'],
                    'sublabel' => $row['employee_code'] . ' · ' . $row['company_name'],
                    'link'     => site_url('hr/employees/' . $row['id']),
                ];
            }
        }

        if (can('task.view')) {
            $builder = $db->table('tasks')->select('id, title')
                ->like('title', $term)->where('deleted_at', null);
            if ($companyScope !== null) {
                $builder->where('company_id', $companyScope);
            }
            foreach ($builder->limit(5)->get()->getResultArray() as $row) {
                $results[] = [
                    'category' => 'Tasks',
                    'icon'     => 'fa-list-check',
                    'label'    => $row['title'],
                    'sublabel' => 'Task',
                    'link'     => site_url('tasks/' . $row['id']),
                ];
            }
        }

        if (can('meeting.view')) {
            $builder = $db->table('meetings')->select('id, title, meeting_date')->like('title', $term);
            if ($companyScope !== null) {
                $builder->where('company_id', $companyScope);
            }
            foreach ($builder->limit(5)->get()->getResultArray() as $row) {
                $results[] = [
                    'category' => 'Meetings',
                    'icon'     => 'fa-handshake',
                    'label'    => $row['title'],
                    'sublabel' => date('d/m/Y', strtotime($row['meeting_date'])),
                    'link'     => site_url('meetings/' . $row['id']),
                ];
            }
        }

        if (can('compliance.view')) {
            $builder = $db->table('compliance_items')
                ->select('compliance_items.id, compliance_items.title, compliance_types.name as type_name')
                ->join('compliance_types', 'compliance_types.id = compliance_items.compliance_type_id')
                ->groupStart()->like('compliance_items.title', $term)->orLike('compliance_types.name', $term)->groupEnd();
            if ($companyScope !== null) {
                $builder->where('compliance_items.company_id', $companyScope);
            }
            foreach ($builder->limit(5)->get()->getResultArray() as $row) {
                $results[] = [
                    'category' => 'Compliance',
                    'icon'     => 'fa-clipboard-check',
                    'label'    => $row['title'] ?: $row['type_name'],
                    'sublabel' => 'Compliance item',
                    'link'     => site_url('compliance/' . $row['id']),
                ];
            }
        }

        if (can('invoice.view')) {
            $builder = $db->table('invoices')->select('id, invoice_number')->like('invoice_number', $term);
            if ($companyScope !== null) {
                $builder->where('company_id', $companyScope);
            }
            foreach ($builder->limit(5)->get()->getResultArray() as $row) {
                $results[] = [
                    'category' => 'Invoices',
                    'icon'     => 'fa-file-invoice-dollar',
                    'label'    => $row['invoice_number'],
                    'sublabel' => 'Invoice',
                    'link'     => site_url('accounting/invoices/' . $row['id']),
                ];
            }
        }

        if (can('website.view')) {
            $builder = $db->table('websites')->select('id, domain')->like('domain', $term);
            if ($companyScope !== null) {
                $builder->where('company_id', $companyScope);
            }
            foreach ($builder->limit(5)->get()->getResultArray() as $row) {
                $results[] = [
                    'category' => 'Websites',
                    'icon'     => 'fa-server',
                    'label'    => $row['domain'],
                    'sublabel' => 'Website',
                    'link'     => site_url('websites/' . $row['id']),
                ];
            }
        }

        return $this->response->setJSON(['results' => $results]);
    }
}
