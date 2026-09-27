<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Populates tasks/meetings/compliance items/leave requests/expenses with
 * realistic sample rows so the dashboard has real, non-zero data to show.
 * Dev/demo only — run with: php spark db:seed DemoDataSeeder
 */
class DemoDataSeeder extends Seeder
{
    private array $companies;
    private array $departments;
    private array $userIds = [2, 3, 4, 5, 6, 7]; // skip super admin (1) as an assignee

    public function run()
    {
        $this->companies   = $this->db->table('companies')->select('id, name')->get()->getResultArray();
        $this->departments = $this->db->table('departments')->select('id')->get()->getResultArray();

        if (empty($this->companies)) {
            return;
        }

        if ($this->db->table('employee_profiles')->countAllResults() === 0) {
            $this->seedEmployeeProfiles();
        }

        if ($this->db->table('websites')->countAllResults() === 0) {
            $this->seedWebsites();
        }

        if ($this->db->table('company_directors')->countAllResults() === 0) {
            $this->seedCompanyDetails();
            $this->seedDirectors();
            $this->seedBankAccounts();
            $this->seedCompanyDocuments();
        }

        if ($this->db->table('attendance')->countAllResults() === 0) {
            $this->seedAttendance();
        }

        if ($this->db->table('tasks')->countAllResults() > 0) {
            return; // rest already seeded
        }

        $projectIdsByCompany = $this->seedProjects();
        $this->seedTasks($projectIdsByCompany);
        $this->seedMeetings();
        $this->seedComplianceItems();
        $this->seedLeaveRequests();
        $this->seedExpenses();
    }

    private function seedEmployeeProfiles(): void
    {
        $users = $this->db->table('users')->select('id, name')->get()->getResultArray();
        $designations = [
            'Managing Director', 'Operations Manager', 'Project Manager', 'Senior Accountant',
            'HR Manager', 'Compliance Officer', 'Software Engineer',
        ];
        $rows = [];

        foreach ($users as $i => $user) {
            $company = $this->companies[$i % count($this->companies)];

            $rows[] = [
                'user_id'                => $user['id'],
                'company_id'             => $company['id'],
                'department_id'          => $this->departments[array_rand($this->departments)]['id'],
                'employee_code'          => sprintf('EMP-%04d', $i + 1),
                'designation'            => $designations[$i % count($designations)],
                'reporting_manager_id'   => null,
                'employment_type'        => 'full_time',
                'status'                 => 'active',
                'date_of_joining'        => date('Y-m-d', strtotime('-' . random_int(60, 900) . ' days')),
                'date_of_birth'          => null,
                'address'                => null,
                'emergency_contact_name' => null,
                'emergency_contact_phone' => null,
                'created_at'             => date('Y-m-d H:i:s'),
                'updated_at'             => date('Y-m-d H:i:s'),
            ];
        }

        $this->db->table('employee_profiles')->insertBatch($rows);
    }

    private function uuid(): string
    {
        $data    = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    private function seedAttendance(): void
    {
        $rows = [];

        foreach ($this->userIds as $userId) {
            for ($daysAgo = 20; $daysAgo >= 0; $daysAgo--) {
                $date      = date('Y-m-d', strtotime("-{$daysAgo} days"));
                $dayOfWeek = (int) date('N', strtotime($date)); // 6=Sat, 7=Sun

                if ($dayOfWeek >= 6) {
                    $rows[] = [
                        'user_id'    => $userId,
                        'date'       => $date,
                        'check_in'   => null,
                        'check_out'  => null,
                        'status'     => 'week_off',
                        'notes'      => null,
                        'created_at' => $date . ' 09:00:00',
                        'updated_at' => $date . ' 09:00:00',
                    ];
                    continue;
                }

                // Mostly present, with a little realistic variety.
                $roll = random_int(1, 100);
                if ($roll <= 4) {
                    $status = 'absent';
                } elseif ($roll <= 9) {
                    $status = 'on_leave';
                } elseif ($roll <= 15) {
                    $status = 'half_day';
                } else {
                    $status = 'present';
                }

                if ($status === 'absent' || $status === 'on_leave') {
                    $rows[] = [
                        'user_id'    => $userId,
                        'date'       => $date,
                        'check_in'   => null,
                        'check_out'  => null,
                        'status'     => $status,
                        'notes'      => null,
                        'created_at' => $date . ' 09:00:00',
                        'updated_at' => $date . ' 09:00:00',
                    ];
                    continue;
                }

                $checkInHour   = random_int(9, 9);
                $checkInMinute = random_int(0, 45);
                $checkIn       = "{$date} " . sprintf('%02d:%02d:00', $checkInHour, $checkInMinute);

                if ($status === 'half_day') {
                    $checkOut = "{$date} " . sprintf('%02d:%02d:00', random_int(13, 14), random_int(0, 45));
                } else {
                    $checkOut = "{$date} " . sprintf('%02d:%02d:00', random_int(18, 19), random_int(0, 45));
                }

                // Today: only checked in so far for about half of them, to
                // look like a normal mid-day snapshot rather than everyone
                // already having clocked out.
                if ($daysAgo === 0 && random_int(0, 1) === 0) {
                    $checkOut = null;
                }

                $rows[] = [
                    'user_id'    => $userId,
                    'date'       => $date,
                    'check_in'   => $checkIn,
                    'check_out'  => $checkOut,
                    'status'     => $status,
                    'notes'      => null,
                    'created_at' => $checkIn,
                    'updated_at' => $checkOut ?? $checkIn,
                ];
            }
        }

        $this->db->table('attendance')->insertBatch($rows);
    }

    private function seedCompanyDetails(): void
    {
        // CIN/GST/PAN are Indian regulatory identifiers — only filled in
        // for the India-registered companies, left blank for the US ones
        // rather than fabricating a local equivalent that doesn't apply.
        $details = [
            1 => ['country' => 'India', 'cin' => 'U74999MH2019PTC321001', 'gst' => '27ABCDE1234F1Z5', 'pan' => 'ABCDE1234F', 'address' => "14th Floor, Solitaire Corporate Park,\nAndheri Kurla Road, Andheri East,\nMumbai, Maharashtra 400093, India", 'inc' => '2019-04-12'],
            2 => ['country' => 'USA',   'cin' => null, 'gst' => null, 'pan' => null, 'address' => "548 Market Street, Suite 62910,\nSan Francisco, CA 94104, USA", 'inc' => '2020-08-03'],
            3 => ['country' => 'India', 'cin' => 'U52100MH2018PTC312045', 'gst' => '27FGHIJ5678K1Z2', 'pan' => 'FGHIJ5678K', 'address' => "45 Wellness Avenue, Andheri West,\nMumbai, Maharashtra 400058, India", 'inc' => '2018-11-20'],
            4 => ['country' => 'India', 'cin' => 'U40100MH2017PTC298765', 'gst' => '27KLMNO9012P1Z8', 'pan' => 'KLMNO9012P', 'address' => "Plot No. 22, MIDC Industrial Area,\nAndheri East, Mumbai, Maharashtra 400093, India", 'inc' => '2017-06-15'],
            5 => ['country' => 'USA',   'cin' => null, 'gst' => null, 'pan' => null, 'address' => "1201 Elm Street, Suite 5400,\nDallas, TX 75270, USA", 'inc' => '2021-02-28'],
        ];

        foreach ($this->companies as $company) {
            $d = $details[$company['id']] ?? null;
            if ($d === null) {
                continue;
            }

            $this->db->table('companies')->where('id', $company['id'])->update([
                'cin'                 => $d['cin'],
                'gst'                 => $d['gst'],
                'pan'                 => $d['pan'],
                'registered_address'  => $d['address'],
                'incorporation_date'  => $d['inc'],
                'updated_at'          => date('Y-m-d H:i:s'),
            ]);
        }
    }

    private function seedDirectors(): void
    {
        $namePool = [
            ['Rohan Mehta', 'rohan.mehta'], ['Ananya Iyer', 'ananya.iyer'], ['Vikram Shah', 'vikram.shah'],
            ['Priya Nair', 'priya.nair'], ['Arjun Kapoor', 'arjun.kapoor'], ['Sneha Rao', 'sneha.rao'],
            ['David Coleman', 'david.coleman'], ['Laura Bennett', 'laura.bennett'],
            ['Karan Malhotra', 'karan.malhotra'], ['Meera Pillai', 'meera.pillai'],
        ];
        $designations = ['Managing Director', 'Whole-time Director', 'Independent Director'];
        $rows = [];
        $poolIndex = 0;

        foreach ($this->companies as $company) {
            for ($i = 0; $i < 2; $i++) {
                [$name, $emailSlug] = $namePool[$poolIndex % count($namePool)];
                $poolIndex++;

                $rows[] = [
                    'company_id'     => $company['id'],
                    'name'           => $name,
                    'din'            => (string) random_int(1000000, 9999999),
                    'designation'    => $designations[$i % count($designations)],
                    'email'          => $emailSlug . '@' . strtolower(str_replace([' ', '&'], ['', 'and'], $company['name'])) . '.com',
                    'phone'          => '9' . random_int(100000000, 999999999),
                    'appointed_date' => date('Y-m-d', strtotime('-' . random_int(365, 1800) . ' days')),
                    'resigned_date'  => null,
                    'created_at'     => date('Y-m-d H:i:s'),
                    'updated_at'     => date('Y-m-d H:i:s'),
                ];
            }
        }

        $this->db->table('company_directors')->insertBatch($rows);
    }

    private function seedBankAccounts(): void
    {
        $banks = [
            ['HDFC Bank', 'Andheri East Branch'],
            ['ICICI Bank', 'Bandra Kurla Complex Branch'],
            ['Axis Bank', 'Powai Branch'],
        ];
        $encrypter = service('encrypter');
        $rows = [];

        foreach ($this->companies as $company) {
            for ($i = 0; $i < 2; $i++) {
                [$bankName, $branch] = $banks[($company['id'] + $i) % count($banks)];
                $accountNumber = (string) random_int(100000000000, 999999999999);

                $rows[] = [
                    'company_id'             => $company['id'],
                    'bank_name'              => $bankName,
                    'branch'                 => $branch,
                    'account_holder'         => $company['name'],
                    'account_number_cipher'  => base64_encode($encrypter->encrypt($accountNumber)),
                    'account_number_last4'   => substr($accountNumber, -4),
                    'ifsc'                   => strtoupper(substr($bankName, 0, 4)) . '0' . random_int(100000, 999999),
                    'authorized_signatories' => 'Phani (Director)',
                    'status'                 => $i === 0 ? 'active' : 'inactive',
                    'document_path'          => null,
                    'document_name'          => null,
                    'created_at'             => date('Y-m-d H:i:s'),
                    'updated_at'             => date('Y-m-d H:i:s'),
                ];
            }
        }

        $this->db->table('bank_accounts')->insertBatch($rows);
    }

    private function seedCompanyDocuments(): void
    {
        $docs = [
            ['legal', 'Incorporation Certificate', "Certificate of Incorporation\n\nThis is a demo placeholder document seeded for preview data.\nIt confirms the company's registration with the relevant authority."],
            ['finance', 'GST Registration Certificate', "GST Registration Certificate\n\nThis is a demo placeholder document seeded for preview data."],
            ['legal', 'Office Lease Agreement', "Office Lease Agreement\n\nThis is a demo placeholder document seeded for preview data.\nCovers the registered office premises."],
            ['hr', 'Employee Handbook', "Employee Handbook\n\nThis is a demo placeholder document seeded for preview data."],
        ];

        $storage = service('fileStorage');
        $rows    = [];

        foreach ($this->companies as $company) {
            foreach (array_slice($docs, 0, 3) as $doc) {
                [$category, $title, $body] = $doc;

                $tmpPath = sys_get_temp_dir() . '/' . uniqid('doc_', true) . '.txt';
                file_put_contents($tmpPath, $body);
                $fileSize = filesize($tmpPath);
                $file     = new \CodeIgniter\Files\File($tmpPath, true);

                $relativePath = $storage->store($file, "company_documents/{$company['id']}");

                $rows[] = [
                    'company_id'       => $company['id'],
                    'category'         => $category,
                    'document_type'    => $title,
                    'title'            => $title,
                    'employee_user_id' => null,
                    'file_path'        => $relativePath,
                    'original_name'    => strtolower(str_replace(' ', '_', $title)) . '.txt',
                    'file_size'        => $fileSize,
                    'expiry_date'      => null,
                    'uploaded_by'      => 1,
                    'created_at'       => date('Y-m-d H:i:s'),
                ];
            }
        }

        $this->db->table('documents')->insertBatch($rows);
    }

    private function seedWebsites(): void
    {
        $domains = [
            1 => ['sanvima.com', 'Hostinger'],
            2 => ['sayeon.com', 'AWS'],
            3 => ['festivehq.com', 'Shopify'],
            4 => ['nihirapower.com', 'GoDaddy'],
            5 => ['intrepidpros.com', 'DigitalOcean'],
        ];
        $rows = [];

        foreach ($this->companies as $company) {
            [$domain, $hosting] = $domains[$company['id']] ?? [strtolower($company['name']) . '.com', 'Hostinger'];

            $rows[] = [
                'company_id'                  => $company['id'],
                'domain'                      => $domain,
                'registrar'                   => 'GoDaddy',
                'hosting_provider'            => $hosting,
                'server_ip'                   => '103.21.58.' . random_int(2, 250),
                'dns_details'                 => 'A record -> hosting IP, MX -> Google Workspace',
                'ssl_expiry'                  => date('Y-m-d', strtotime('+' . random_int(30, 300) . ' days')),
                'renewal_date'                => date('Y-m-d', strtotime('+' . random_int(60, 360) . ' days')),
                'control_panel'               => 'cPanel',
                'control_panel_url'           => 'https://' . $domain . ':2083',
                'git_repository'              => 'git@github.com:sanvima/' . strtok($domain, '.') . '.git',
                'ftp_host'                    => 'ftp.' . $domain,
                'ftp_username'                => 'deploy@' . $domain,
                'ftp_password_cipher'         => base64_encode(service('encrypter')->encrypt('DemoFtpPass!23')),
                'admin_login_username'        => 'admin@' . $domain,
                'admin_login_password_cipher' => base64_encode(service('encrypter')->encrypt('DemoAdminPass!23')),
                'status'                      => 'active',
                'notes'                       => 'Demo hosting record seeded for preview data.',
                'created_by'                  => 1,
                'created_at'                  => date('Y-m-d H:i:s'),
                'updated_at'                  => date('Y-m-d H:i:s'),
            ];
        }

        $this->db->table('websites')->insertBatch($rows);
    }

    private function seedProjects(): array
    {
        $names = ['Client Onboarding Rollout', 'Internal Process Revamp', 'Annual Compliance Drive', 'Digital Presence Refresh'];
        $ids   = [];

        foreach ($this->companies as $company) {
            $rows = [];
            for ($i = 0; $i < 2; $i++) {
                $rows[] = [
                    'company_id'  => $company['id'],
                    'name'        => $names[array_rand($names)] . ' — ' . $company['name'],
                    'description' => 'Demo project seeded for dashboard preview data.',
                    'status'      => 'active',
                    'start_date'  => date('Y-m-d', strtotime('-' . random_int(10, 60) . ' days')),
                    'end_date'    => date('Y-m-d', strtotime('+' . random_int(30, 120) . ' days')),
                    'owner_id'    => 3,
                    'created_at'  => date('Y-m-d H:i:s'),
                    'updated_at'  => date('Y-m-d H:i:s'),
                ];
            }
            $this->db->table('projects')->insertBatch($rows);

            $ids[$company['id']] = array_column(
                $this->db->table('projects')->select('id')->where('company_id', $company['id'])->get()->getResultArray(),
                'id'
            );
        }

        return $ids;
    }

    private function seedTasks(array $projectIdsByCompany): void
    {
        $titles = [
            'Prepare monthly management report', 'Update employee onboarding checklist',
            'Fix invoice PDF template issue', 'Client follow-up call', 'Website content refresh',
            'Server maintenance window', 'Vendor payment reconciliation', 'Quarterly board deck',
            'Marketing campaign creative review', 'Office equipment procurement', 'Budget variance analysis',
            'Update HR policy handbook', 'Renew software licenses', 'Prepare client proposal',
            'Data backup verification', 'Review vendor contract terms', 'Team training session planning',
            'Audit expense claims', 'Social media content calendar', 'Network security review',
        ];
        $activeStatuses = ['new', 'assigned', 'in_progress', 'waiting', 'review'];

        // [company_id => ['total' => n, 'completed' => n]]
        $plan = [
            1 => ['total' => 10, 'completed' => 6],
            2 => ['total' => 11, 'completed' => 5],
            3 => ['total' => 8,  'completed' => 6],
            4 => ['total' => 9,  'completed' => 3],
            5 => ['total' => 10, 'completed' => 8],
        ];

        foreach ($this->companies as $company) {
            $cfg  = $plan[$company['id']] ?? ['total' => 8, 'completed' => 4];
            $rows = [];

            for ($i = 0; $i < $cfg['total']; $i++) {
                $projectIds = $projectIdsByCompany[$company['id']] ?? [];
                $projectId  = ! empty($projectIds) && random_int(0, 4) > 0 ? $projectIds[array_rand($projectIds)] : null;
                $assignedTo = $this->userIds[array_rand($this->userIds)];
                $isCompleted = $i < $cfg['completed'];

                if ($isCompleted) {
                    $completedAt = date('Y-m-d H:i:s', strtotime('-' . random_int(0, 45) . ' days'));
                    $startDate   = date('Y-m-d', strtotime($completedAt . ' -' . random_int(3, 14) . ' days'));
                    $dueDate     = date('Y-m-d', strtotime($completedAt . ' -' . random_int(0, 2) . ' days'));
                    $status      = 'completed';
                } else {
                    $completedAt = null;
                    $overdue     = random_int(0, 100) < 40;
                    $startDate   = date('Y-m-d', strtotime('-' . random_int(1, 20) . ' days'));
                    $dueDate     = $overdue
                        ? date('Y-m-d', strtotime('-' . random_int(1, 10) . ' days'))
                        : date('Y-m-d', strtotime('+' . random_int(1, 21) . ' days'));
                    $status = $activeStatuses[array_rand($activeStatuses)];
                }

                $createdAt = date('Y-m-d H:i:s', strtotime($startDate . ' -' . random_int(0, 3) . ' days'));

                $rows[] = [
                    'uuid'          => $this->uuid(),
                    'title'         => $titles[array_rand($titles)],
                    'description'   => null,
                    'company_id'    => $company['id'],
                    'department_id' => $this->departments[array_rand($this->departments)]['id'],
                    'project_id'    => $projectId,
                    'priority'      => ['low', 'medium', 'medium', 'high', 'urgent'][array_rand([0, 1, 2, 3, 4])],
                    'assigned_to'   => $assignedTo,
                    'created_by'    => 3,
                    'start_date'    => $startDate,
                    'due_date'      => $dueDate,
                    'status'        => $status,
                    'completed_at'  => $completedAt,
                    'created_at'    => $createdAt,
                    'updated_at'    => $createdAt,
                ];
            }

            $this->db->table('tasks')->insertBatch($rows);
        }
    }

    private function seedMeetings(): void
    {
        $titles = [
            'Project Planning Meeting', 'Department Review', 'Monthly All Hands', 'Client Kickoff Call',
            'Budget Review', 'Compliance Sync', 'Marketing Strategy Session', 'Vendor Negotiation',
            'Performance Review Meeting', 'Town Hall',
        ];
        $rows = [];

        foreach ($this->companies as $company) {
            // one recent past meeting
            $rows[] = [
                'company_id'   => $company['id'],
                'title'        => $titles[array_rand($titles)],
                'agenda'       => 'Demo meeting seeded for dashboard preview data.',
                'meeting_date' => date('Y-m-d', strtotime('-' . random_int(3, 20) . ' days')),
                'start_time'   => '10:00:00',
                'end_time'     => '11:00:00',
                'location'     => 'Conference Room / Google Meet',
                'mom'          => null,
                'created_by'   => 3,
                'created_at'   => date('Y-m-d H:i:s'),
                'updated_at'   => date('Y-m-d H:i:s'),
            ];

            // two upcoming meetings
            for ($i = 0; $i < 2; $i++) {
                $days = random_int(1, 12);
                $rows[] = [
                    'company_id'   => $company['id'],
                    'title'        => $titles[array_rand($titles)],
                    'agenda'       => 'Demo meeting seeded for dashboard preview data.',
                    'meeting_date' => date('Y-m-d', strtotime("+{$days} days")),
                    'start_time'   => sprintf('%02d:00:00', random_int(9, 16)),
                    'end_time'     => sprintf('%02d:00:00', random_int(9, 16) + 1),
                    'location'     => 'Conference Room / Google Meet',
                    'mom'          => null,
                    'created_by'   => 3,
                    'created_at'   => date('Y-m-d H:i:s'),
                    'updated_at'   => date('Y-m-d H:i:s'),
                ];
            }
        }

        $this->db->table('meetings')->insertBatch($rows);
    }

    private function seedComplianceItems(): void
    {
        $typeIds = array_column($this->db->table('compliance_types')->select('id')->get()->getResultArray(), 'id');
        $rows    = [];

        foreach ($this->companies as $company) {
            // one overdue
            $rows[] = [
                'company_id'           => $company['id'],
                'compliance_type_id'   => $typeIds[array_rand($typeIds)],
                'title'                => null,
                'due_date'             => date('Y-m-d', strtotime('-' . random_int(1, 6) . ' days')),
                'recurrence'           => 'quarterly',
                'responsible_user_id'  => 6,
                'status'               => 'overdue',
                'reminder_days_before' => 7,
                'notes'                => null,
                'filed_at'             => null,
                'previous_item_id'     => null,
                'created_by'           => 6,
                'created_at'           => date('Y-m-d H:i:s'),
                'updated_at'           => date('Y-m-d H:i:s'),
            ];

            // one due soon
            $rows[] = [
                'company_id'           => $company['id'],
                'compliance_type_id'   => $typeIds[array_rand($typeIds)],
                'title'                => null,
                'due_date'             => date('Y-m-d', strtotime('+' . random_int(1, 6) . ' days')),
                'recurrence'           => 'annually',
                'responsible_user_id'  => 6,
                'status'               => 'pending',
                'reminder_days_before' => 7,
                'notes'                => null,
                'filed_at'             => null,
                'previous_item_id'     => null,
                'created_by'           => 6,
                'created_at'           => date('Y-m-d H:i:s'),
                'updated_at'           => date('Y-m-d H:i:s'),
            ];

            // one further out, already filed
            $rows[] = [
                'company_id'           => $company['id'],
                'compliance_type_id'   => $typeIds[array_rand($typeIds)],
                'title'                => null,
                'due_date'             => date('Y-m-d', strtotime('+' . random_int(30, 75) . ' days')),
                'recurrence'           => 'half_yearly',
                'responsible_user_id'  => 6,
                'status'               => 'filed',
                'reminder_days_before' => 7,
                'notes'                => null,
                'filed_at'             => date('Y-m-d H:i:s', strtotime('-' . random_int(1, 10) . ' days')),
                'previous_item_id'     => null,
                'created_by'           => 6,
                'created_at'           => date('Y-m-d H:i:s'),
                'updated_at'           => date('Y-m-d H:i:s'),
            ];
        }

        $this->db->table('compliance_items')->insertBatch($rows);
    }

    private function seedLeaveRequests(): void
    {
        $leaveTypeIds = array_column($this->db->table('leave_types')->select('id')->get()->getResultArray(), 'id');
        $applicants   = [3, 4, 6, 7]; // manager, accountant, compliance officer, employee
        $rows         = [];

        foreach ($applicants as $i => $userId) {
            $pending   = $i < 2;
            $startDate = date('Y-m-d', strtotime('+' . random_int(2, 20) . ' days'));
            $days      = random_int(1, 4);
            $endDate   = date('Y-m-d', strtotime($startDate . " +{$days} days"));

            $rows[] = [
                'user_id'       => $userId,
                'leave_type_id' => $leaveTypeIds[array_rand($leaveTypeIds)],
                'start_date'    => $startDate,
                'end_date'      => $endDate,
                'days'          => $days,
                'reason'        => 'Demo leave request seeded for dashboard preview data.',
                'status'        => $pending ? 'pending' : 'approved',
                'approved_by'   => $pending ? null : 5,
                'approved_at'   => $pending ? null : date('Y-m-d H:i:s'),
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s'),
            ];
        }

        $this->db->table('leave_requests')->insertBatch($rows);
    }

    private function seedExpenses(): void
    {
        // vendor, category, billing_cycle, amount
        $catalog = [
            ['WeWork',            'Rent',              'monthly',   16000],
            ['AWS',               'Cloud Hosting',     'monthly',   9200],
            ['Zoho One',          'Software',          'monthly',   4800],
            ['Airtel Business',   'Internet & Telecom','monthly',   3500],
            ['Tally Solutions',   'Software',          'yearly',    18000],
            ['HDFC Ergo',         'Insurance',         'yearly',    96000],
            ['Google Workspace',  'Software',          'monthly',   3200],
            ['Facebook Ads',      'Marketing',         'monthly',   6500],
            ['IndiGo',            'Travel',            'quarterly', 24000],
            ['Office Depot',      'Office Supplies',   'monthly',   2100],
        ];

        $rows = [];
        foreach ($this->companies as $index => $company) {
            $item = $catalog[$index * 2 % count($catalog)];
            $rows[] = $this->expenseRow($company['id'], $item);
            $item2 = $catalog[($index * 2 + 1) % count($catalog)];
            $rows[] = $this->expenseRow($company['id'], $item2);
        }

        $this->db->table('expenses')->insertBatch($rows);
    }

    private function expenseRow(int $companyId, array $item): array
    {
        [$vendor, $category, $cycle, $amount] = $item;

        return [
            'company_id'     => $companyId,
            'vendor'         => $vendor,
            'category'       => $category,
            'billing_cycle'  => $cycle,
            'amount'         => $amount,
            'renewal_date'   => date('Y-m-d', strtotime('+' . random_int(5, 60) . ' days')),
            'auto_renewal'   => 1,
            'payment_method' => 'Bank Transfer',
            'notes'          => null,
            'status'         => 'active',
            'created_by'     => 4,
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ];
    }
}
