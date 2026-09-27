<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Shared base for every module controller. Keeps common helpers
 * (current company/user context, activity logging) in one place
 * instead of duplicating them per module.
 */
abstract class BaseController extends Controller
{
    /**
     * Sentinel stored in session('active_company_id') to mean "no
     * restriction" (Super Admin's default / "All Companies" selection).
     * See companyScopeFor() for why this can't just be a literal null.
     */
    protected const ACTIVE_COMPANY_ALL = 'all';

    /**
     * Every role except Super Admin — the canonical "who must be pinned
     * to their own company" list for companyScopeFor()/outOfScope()
     * calls across every module. Module controllers used to hand-pick a
     * subset (usually just ['admin']) per call site, which meant a role
     * left off the list by omission got companyScopeFor() === null (no
     * restriction) and could see/act on every company's data even
     * though the module's own permission map grants that role access
     * (e.g. Accountant on Invoices, HR on Employees). Passing this
     * constant everywhere the check means "which company can this
     * viewer see" closes that class of bug in one place — a role that
     * doesn't actually reach a given controller (blocked earlier by the
     * permission filter) is unaffected by being listed here too.
     */
    protected const COMPANY_SCOPED_ROLES = ['admin', 'manager', 'accountant', 'hr', 'compliance_officer', 'employee'];

    protected $helpers = ['form', 'url', 'text'];

    /**
     * @var CLIRequest|IncomingRequest
     */
    protected $request;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
    }

    protected function currentUserId(): ?int
    {
        return session()->get('userId');
    }

    /**
     * Company a viewer with one of $scopedRoles is restricted to, or
     * null for no restriction. Returns 0 (not null) when the viewer has
     * no employee_profiles row yet — callers must treat that as "match
     * zero rows", not "no restriction", or an unprovisioned account
     * would see everyone's data instead of nobody's.
     *
     * Super Admin is checked first, unconditionally — their topbar
     * switcher selection (or the 'all' sentinel for "All Companies")
     * applies to every module this is called from, not just the ones
     * whose caller remembered to list 'super_admin' in $scopedRoles.
     * That used to be opt-in per call site, which meant picking a
     * company in the topbar silently did nothing on modules that only
     * scoped 'admin' (Bills, Documents, most Reports, ...) — Super Admin
     * kept seeing every company there regardless of the switcher.
     *
     * For every other role, prefers session('active_company_id') when
     * it's been set (Company Admin's fixed scope — see
     * AuthController::establishSession()). Falls back to a live
     * employee_profiles lookup for roles that never get that session key
     * set at all (e.g. Manager/HR's leave/attendance approval scoping),
     * so their behavior is unchanged.
     *
     * 'all' (not null/not omitted) is the stored sentinel for "no
     * restriction" — CI4's Session::has() is isset()-based, so a literal
     * null value would make has() report the key as absent and fall
     * through to the per-request lookup instead.
     */
    protected function companyScopeFor(array $scopedRoles): ?int
    {
        $roleSlug = session('roleSlug');

        if ($roleSlug === 'super_admin') {
            $value = session('active_company_id');

            return ($value === null || $value === self::ACTIVE_COMPANY_ALL) ? null : (int) $value;
        }

        if (! in_array($roleSlug, $scopedRoles, true)) {
            return null;
        }

        if (session()->has('active_company_id')) {
            $value = session('active_company_id');

            return $value === self::ACTIVE_COMPANY_ALL ? null : (int) $value;
        }

        $companyId = db_connect()->table('employee_profiles')
            ->select('company_id')
            ->where('user_id', $this->currentUserId())
            ->get()
            ->getRow('company_id');

        return (int) ($companyId ?? 0);
    }

    /**
     * Narrows a "Company" filter/select dropdown's option list to match
     * the viewer's current scope — a single company when one is active
     * (Company Admin always; Super Admin once they've picked one from
     * the topbar switcher), or every company when unrestricted ("All
     * Companies"). Without this, a scoped viewer could still see every
     * other company's name listed as a pickable option even though
     * selecting one has no effect — the underlying query re-applies the
     * same scope regardless of what's submitted.
     */
    protected function scopedCompanyOptions(array $allCompanies): array
    {
        $scope = $this->companyScopeFor(self::COMPANY_SCOPED_ROLES);

        if ($scope === null) {
            return $allCompanies;
        }

        return array_values(array_filter($allCompanies, static fn (array $c) => (int) $c['id'] === $scope));
    }

    /**
     * Same idea as scopedCompanyOptions(), for an "Assigned To" /
     * "Responsible" / "Participants" user dropdown — narrows it to
     * people who actually belong (via employee_profiles) to the
     * viewer's current company scope, so e.g. a Company Admin scoped to
     * Festive doesn't see Sayeon's staff listed as pickable assignees.
     * Unrestricted ("All Companies") viewers still see everyone, same
     * as before this existed.
     *
     * Passes every non-super-admin role to companyScopeFor() — that
     * method treats an omitted role as "not scoped, show everyone", so
     * a Manager or HR viewer (whose scope comes from their own
     * employee_profiles row, not the topbar) would otherwise fall
     * through to seeing every company's staff in this dropdown.
     */
    protected function scopedUserOptions(array $allUsers): array
    {
        $scope = $this->companyScopeFor(self::COMPANY_SCOPED_ROLES);

        if ($scope === null) {
            return $allUsers;
        }

        $employeeUserIds = array_map('intval', array_column(
            db_connect()->table('employee_profiles')->select('user_id')->where('company_id', $scope)->get()->getResultArray(),
            'user_id'
        ));

        return array_values(array_filter($allUsers, static fn (array $u) => in_array((int) $u['id'], $employeeUserIds, true)));
    }

    /**
     * True when a viewer scoped by $scopedRoles is looking at a record
     * outside their own company — treat the same as "not found" so
     * scope leaks via URL-guessing don't reveal the record exists.
     */
    protected function outOfScope(array $scopedRoles, ?int $recordCompanyId): bool
    {
        $scope = $this->companyScopeFor($scopedRoles);

        return $scope !== null && $scope !== (int) $recordCompanyId;
    }

    /**
     * Records an entry in activity_logs. Called by module controllers
     * after create/update/delete so every module gets an audit trail
     * without reimplementing logging.
     */
    protected function logActivity(string $module, string $action, ?int $recordId, string $description): void
    {
        db_connect()->table('activity_logs')->insert([
            'user_id'    => $this->currentUserId(),
            'module'     => $module,
            'action'     => $action,
            'record_id'  => $recordId,
            'description' => $description,
            'ip_address' => $this->request->getIPAddress(),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
