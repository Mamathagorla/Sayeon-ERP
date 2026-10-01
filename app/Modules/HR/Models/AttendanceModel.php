<?php

namespace App\Modules\HR\Models;

use CodeIgniter\Model;

class AttendanceModel extends Model
{
    public const STATUSES = ['present', 'absent', 'half_day', 'on_leave', 'holiday', 'week_off'];

    protected $table         = 'attendance';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['user_id', 'date', 'check_in', 'check_out', 'status', 'notes'];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    public function todayFor(int $userId): ?array
    {
        return $this->where('user_id', $userId)->where('date', date('Y-m-d'))->first();
    }

    public function filtered(array $filters = [])
    {
        $builder = $this->select('attendance.*, users.name as user_name, users.last_login_at,
                employee_profiles.company_id as company_id, employee_profiles.department_id as department_id,
                companies.name as company_name, departments.name as department_name')
            ->join('users', 'users.id = attendance.user_id')
            ->join('employee_profiles', 'employee_profiles.user_id = attendance.user_id', 'left')
            ->join('companies', 'companies.id = employee_profiles.company_id', 'left')
            ->join('departments', 'departments.id = employee_profiles.department_id', 'left');

        if (! empty($filters['user_id'])) {
            is_array($filters['user_id'])
                ? $builder->whereIn('attendance.user_id', $filters['user_id'])
                : $builder->where('attendance.user_id', $filters['user_id']);
        }
        // Present (even as 0) only when the caller wants company scoping —
        // 0 means "viewer has no company assigned yet", which should
        // match zero rows rather than falling through to showing everyone.
        if (array_key_exists('company_id', $filters)) {
            $builder->where('employee_profiles.company_id', $filters['company_id']);
        }
        if (! empty($filters['department_id'])) {
            $builder->where('employee_profiles.department_id', $filters['department_id']);
        }
        if (! empty($filters['q'])) {
            $builder->like('users.name', $filters['q']);
        }
        if (! empty($filters['date_from'])) {
            $builder->where('attendance.date >=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $builder->where('attendance.date <=', $filters['date_to']);
        }
        if (! empty($filters['status'])) {
            is_array($filters['status'])
                ? $builder->whereIn('attendance.status', $filters['status'])
                : $builder->where('attendance.status', $filters['status']);
        }

        return $builder->orderBy('attendance.date', 'DESC');
    }

    public function monthSummary(int $userId, int $month, int $year): array
    {
        $rows = $this->where('user_id', $userId)
            ->where('MONTH(date)', $month)
            ->where('YEAR(date)', $year)
            ->findAll();

        $summary = array_fill_keys(self::STATUSES, 0);
        foreach ($rows as $r) {
            $summary[$r['status']]++;
        }

        return $summary;
    }

    /**
     * One row per day of the Mon-Sun week containing $mondayDate, with
     * minutes worked derived from check_in/check_out the same way the
     * Attendance History page already does (employee.php) — reused here
     * rather than duplicated as a stored value, so it never drifts out
     * of sync with that page's own numbers.
     */
    public function weekSummary(int $userId, string $mondayDate): array
    {
        $sunday = date('Y-m-d', strtotime($mondayDate . ' +6 days'));

        $rows = $this->where('user_id', $userId)
            ->where('date >=', $mondayDate)
            ->where('date <=', $sunday)
            ->findAll();

        $byDate = [];
        foreach ($rows as $r) {
            $byDate[$r['date']] = $r;
        }

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $date = date('Y-m-d', strtotime($mondayDate . " +{$i} days"));
            $row  = $byDate[$date] ?? null;

            $minutes = ($row && $row['check_in'] && $row['check_out'])
                ? max(0, (int) round((strtotime($row['check_out']) - strtotime($row['check_in'])) / 60))
                : 0;

            $days[] = [
                'date'      => $date,
                'label'     => date('D', strtotime($date)),
                'status'    => $row['status'] ?? null,
                'check_in'  => $row['check_in'] ?? null,
                'check_out' => $row['check_out'] ?? null,
                'minutes'   => $minutes,
            ];
        }

        return $days;
    }
}
