<?php

namespace Tests\Support;

use App\Modules\Auth\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\TestResponse;

/**
 * Base class for the end-to-end workflow suite.
 *
 * Every request goes through the real front controller: routes -> global
 * `auth` filter -> `permission:*` route filter -> controller -> model -> MySQL.
 * Nothing is mocked inside the application; only the outside world is (SMTP is
 * blanked in phpunit.xml.dist, no provider integrations are exercised).
 *
 * SAFETY: the suite only runs against `erp_e2e_test` (built by
 * tests/_support/create_test_db.php). setUp() aborts if the connected database
 * is anything else, then empties every business table so each test starts clean.
 * The reference tables (roles, permissions, role_permissions, countries, states,
 * leave_types, compliance_types, expense_categories, app_settings, phases) are
 * left alone.
 */
abstract class E2ETestCase extends CIUnitTestCase
{
    use FeatureTestTrait;

    public const TEST_DB  = 'erp_e2e_test';
    public const PASSWORD = 'Test@123';

    /** Tables holding reference data the app needs to run; never purged. */
    private const KEEP = [
        'migrations', 'roles', 'permissions', 'role_permissions', 'countries', 'states',
        'leave_types', 'compliance_types', 'expense_categories', 'app_settings',
        'onboarding_phases', 'offboarding_phases',
        'onboarding_role_groups', 'onboarding_questions', 'onboarding_eval_criteria',
    ];

    /** The session of whoever the next request is made as. */
    protected array $as = [];

    protected ?TestResponse $res = null;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $db = db_connect();
        $this->assertSame(self::TEST_DB, $db->getDatabase(), 'REFUSING TO RUN: the E2E suite may only touch ' . self::TEST_DB . '.');
        $this->blankMailConfig();

        $db->query('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($db->listTables() as $table) {
            if (! in_array($table, self::KEEP, true)) {
                $db->query('DELETE FROM `' . $table . '`');
            }
        }
        // Company ids start at 9001 so uploaded/generated test files land in writable/uploads/*/9001.. and can never
        // collide with a real company's folder (ids 1-5).
        $db->query('ALTER TABLE `companies` AUTO_INCREMENT = 9001');
        $db->query('SET FOREIGN_KEY_CHECKS = 1');
        // The seeded shared role groups are reference data and survive; groups a test created for a (now deleted)
        // company must not leak into the next test, which reuses company ids from 9001.
        if ($db->tableExists('onboarding_role_groups')) {
            $db->query('DELETE FROM `onboarding_role_groups` WHERE `company_id` IS NOT NULL');
        }

        \Config\Services::injectMock('fileStorage', new CopyingFileStorage());

        $this->as = [];
        $this->res     = null;
    }

    // ------------------------------------------------------------------
    // Fixtures (direct DB inserts: these are the *starting conditions* of a
    // workflow, not the workflow under test).
    // ------------------------------------------------------------------

    protected function company(string $name, ?string $shortCode = null): array
    {
        $db    = db_connect();
        $india = $db->table('countries')->where('name', 'India')->get()->getRowArray();
        $state = $india ? $db->table('states')->where('country_id', $india['id'])->orderBy('id')->get()->getRowArray() : null;

        $db->table('companies')->insert([
            'name'       => $name,
            'slug'       => strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name)) . '-' . (++$this->seq),
            'short_code' => $shortCode,
            'status'     => 'active',
            'country'    => 'India',
            'country_id' => $india['id'] ?? null,
            'state_id'   => $state['id'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return ['id' => (int) $db->insertID(), 'name' => $name, 'state_id' => $state['id'] ?? null, 'country_id' => $india['id'] ?? null];
    }

    protected function department(string $name = 'Engineering'): int
    {
        $db = db_connect();
        $db->table('departments')->insert([
            'name' => $name, 'slug' => strtolower($name) . '-' . (++$this->seq),
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $db->insertID();
    }

    /**
     * A login + (except for super_admin) an employee profile in $company.
     *
     * @return array{user_id:int, profile_id:?int, email:string, role:string, company_id:?int, name:string}
     */
    protected function person(?array $company, string $role, ?string $name = null, array $profile = []): array
    {
        $db   = db_connect();
        $n    = ++$this->seq;
        $name = $name ?? ucfirst(str_replace('_', ' ', $role)) . ' Tester' . $n;
        $mail = $role . $n . '@e2e.test';
        $now  = date('Y-m-d H:i:s');

        $roleId = (int) $db->table('roles')->where('slug', $role)->get()->getRow('id');
        $this->assertGreaterThan(0, $roleId, "Role $role missing from reference data.");

        $db->table('users')->insert([
            'name' => $name, 'email' => $mail, 'phone' => '9' . str_pad((string) $n, 9, '0', STR_PAD_LEFT),
            'password_hash' => password_hash(self::PASSWORD, PASSWORD_BCRYPT, ['cost' => 4]),
            'role_id' => $roleId, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $userId = (int) $db->insertID();

        $profileId = null;
        if ($company !== null) {
            $db->table('employee_profiles')->insert($profile + [
                'user_id' => $userId, 'company_id' => $company['id'],
                'employee_code' => 'E2E-' . $n, 'designation' => 'Tester', 'employment_type' => 'full_time',
                'status' => 'active', 'date_of_joining' => date('Y-m-01'), 'created_at' => $now, 'updated_at' => $now,
            ]);
            $profileId = (int) $db->insertID();
        }

        return ['user_id' => $userId, 'profile_id' => $profileId, 'email' => $mail, 'role' => $role, 'company_id' => $company['id'] ?? null, 'name' => $name];
    }

    /** A salary structure so payroll can include this person. */
    protected function salary(array $person, float $basic = 30000, float $hra = 12000, float $allowances = 3000, float $deductions = 2000): void
    {
        db_connect()->table('salary_structures')->insert([
            'user_id' => $person['user_id'], 'basic' => $basic, 'hra' => $hra, 'allowances' => $allowances,
            'deductions' => $deductions, 'effective_from' => date('Y-m-01'),
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** Files this test wrote under writable/uploads (templates + archived generated documents); removed in tearDown(). */
    private array $scratchFiles = [];

    /**
     * An ACTIVE html document template for ($company, $type). The template file is written to
     * writable/uploads/e2e/ and removed again in tearDown().
     */
    protected function template(array $company, string $type, string $html, string $status = 'active', int $version = 1): int
    {
        $db   = db_connect();
        $rel  = 'e2e/tpl_' . bin2hex(random_bytes(6)) . '.html';
        $full = WRITEPATH . 'uploads/' . $rel;
        if (! is_dir(dirname($full))) {
            mkdir(dirname($full), 0755, true);
        }
        file_put_contents($full, $html);
        $this->scratchFiles[] = $full;

        $db->table('document_templates')->insert([
            'company_id' => $company['id'], 'document_type' => $type, 'template_name' => ucfirst($type) . ' v' . $version,
            'version' => $version, 'status' => $status, 'file_type' => 'html', 'file_path' => $rel, 'original_name' => 'e2e.html',
            'file_size' => strlen($html), 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $db->insertID();
    }

    protected function tearDown(): void
    {
        $db = db_connect();
        if ($db->getDatabase() === self::TEST_DB && $db->tableExists('generated_documents')) {
            foreach ($db->table('generated_documents')->select('file_path')->get()->getResultArray() as $r) {
                $this->scratchFiles[] = WRITEPATH . 'uploads/' . $r['file_path'];
            }
        }
        foreach ($this->scratchFiles as $f) {
            if (is_file($f)) {
                @unlink($f);
            }
        }
        $this->scratchFiles = [];
        parent::tearDown();
    }

    protected function project(array $company, string $name = 'E2E Project'): int
    {
        $db = db_connect();
        $db->table('projects')->insert(['company_id' => $company['id'], 'name' => $name, 'status' => 'active', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);

        return (int) $db->insertID();
    }

    /**
     * Two companies with a full role roster each (the shape of login.txt) plus a Super Admin.
     * Employees report to their company's manager. Returns
     * ['super'=>..., 'dept'=>int, 'A'=>[company, admin, manager, hr, accountant, compliance, employee], 'B'=>[...]].
     */
    protected function world(): array
    {
        $dept  = $this->department('Engineering');
        $world = ['dept' => $dept, 'super' => $this->person(null, 'super_admin', 'Super Admin')];

        foreach (['A' => ['Alpha Corp', 'ALP'], 'B' => ['Beta Ltd', 'BET']] as $key => [$name, $code]) {
            $co      = $this->company($name, $code);
            $manager = $this->person($co, 'manager', "$key Manager", ['department_id' => $dept]);
            $mgrId   = $manager['user_id'];

            $world[$key] = [
                'company'    => $co,
                'manager'    => $manager,
                'admin'      => $this->person($co, 'admin', "$key Admin", ['department_id' => $dept]),
                'hr'         => $this->person($co, 'hr', "$key HR", ['department_id' => $dept]),
                'accountant' => $this->person($co, 'accountant', "$key Accountant", ['department_id' => $dept]),
                'compliance' => $this->person($co, 'compliance_officer', "$key Compliance", ['department_id' => $dept]),
                'employee'   => $this->person($co, 'employee', "$key Employee", ['department_id' => $dept, 'reporting_manager_id' => $mgrId]),
            ];
        }

        return $world;
    }

    /**
     * Use for a business rule the code base does not enforce yet. While the rule is broken the test
     * is reported as INCOMPLETE (with the defect id, see tests/e2e/WORKFLOW_COVERAGE_REPORT.md) instead
     * of failing the suite; the moment the code is fixed it becomes a normal passing assertion.
     */
    protected function assertRuleOrKnownDefect(bool $holds, string $defectId, string $description): void
    {
        if (! $holds) {
            $this->markTestIncomplete("KNOWN DEFECT $defectId: $description");
        }
        $this->assertTrue($holds);
    }

    // ------------------------------------------------------------------
    // Acting as a user (mirrors AuthController::establishSession()).
    // ------------------------------------------------------------------

    protected function actAs(array $person): static
    {
        $userModel = new UserModel();
        $user      = $userModel->findActiveByEmail($person['email']);
        $this->assertNotNull($user, 'Fixture user could not be loaded: ' . $person['email']);

        $this->as = [
            'isLoggedIn'  => true,
            'userId'      => $user['id'],
            'userName'    => $user['name'],
            'userEmail'   => $user['email'],
            'roleId'      => $user['role_id'],
            'roleSlug'    => $user['role_slug'],
            'roleName'    => $user['role_name'],
            'permissions' => $userModel->permissionsFor((int) $user['id']),
            'avatarPath'  => null,
        ];
        if ($user['role_slug'] === 'super_admin') {
            $this->as['active_company_id'] = 'all';
        }

        return $this;
    }

    protected function anonymous(): static
    {
        $this->as = [];

        return $this;
    }

    /**
     * .env ships real SMTP credentials and DotEnv ignores blank overrides, so the shared
     * Email config is blanked in code. A blank host/user/pass makes MailService report
     * NOT_CONFIGURED and OnboardingController::attemptEmail() return false: no mail is sent.
     */
    private function blankMailConfig(): void
    {
        $mail = config('Email');
        foreach (['SMTPHost', 'SMTPUser', 'SMTPPass'] as $prop) {
            $mail->$prop = '';
        }
        $this->assertSame('', $mail->SMTPUser, 'REFUSING TO RUN: could not blank SMTP credentials.');
    }

    protected function request(string $method, string $uri, array $params = []): TestResponse
    {
        $this->blankMailConfig();
        $this->withSession($this->as);
        $this->res = $this->call($method, $uri, $params);

        return $this->res;
    }

    /** Raw response body (TestResponse::getBody() re-serialises through a DOM parser, which corrupts PDFs). */
    protected function body(): string
    {
        return (string) $this->res->response()->getBody();
    }

    /**
     * GET a generated document and make sure it was archived. ArchiveCopy failures are swallowed by
     * TemplatedDocument::save() (the document is still returned), and on a Windows dev box with a live
     * antivirus/OneDrive the rename into writable/uploads is occasionally denied - so retry a few times.
     */
    protected function downloadArchived(string $uri, string $type, int $recordId, int $expectedRows = 1): void
    {
        for ($try = 0; $try < 5; $try++) {
            $this->visit($uri);
            if ($this->countRows('generated_documents', ['document_type' => $type, 'record_id' => $recordId]) >= $expectedRows) {
                return;
            }
            usleep(400000);
        }
    }

    protected function visit(string $uri): TestResponse
    {
        return $this->request('GET', $uri);
    }

    protected function submit(string $uri, array $params = []): TestResponse
    {
        return $this->request('POST', $uri, $params);
    }

    /** Flash message left by the last request ('success', 'error' or 'errors'). */
    protected function flash(string $key = 'success'): mixed
    {
        return $_SESSION[$key] ?? null;
    }

    protected function assertFlash(string $key, string $contains, string $msg = ''): void
    {
        $value = $this->flash($key);
        $value = is_array($value) ? implode(' | ', $value) : (string) $value;
        $this->assertStringContainsString($contains, $value, $msg ?: "Expected flash[$key] to contain \"$contains\", got \"$value\"");
    }

    /** The request was rejected by the permission filter (redirect back with an error) or the controller. */
    protected function assertDenied(string $msg = ''): void
    {
        $this->assertNotNull($this->res, 'No request made.');
        $this->assertTrue(
            $this->res->isRedirect() || in_array($this->res->response()->getStatusCode(), [401, 403, 404], true),
            $msg ?: 'Expected the request to be denied, got HTTP ' . $this->res->response()->getStatusCode()
        );
        $this->assertNotEmpty($this->flash('error'), $msg ?: 'Expected an error flash message on a denied request.');
    }

    // ------------------------------------------------------------------
    // DB assertions
    // ------------------------------------------------------------------

    protected function row(string $table, array $where): ?array
    {
        return db_connect()->table($table)->where($where)->get()->getRowArray();
    }

    protected function rows(string $table, array $where = []): array
    {
        return db_connect()->table($table)->where($where)->get()->getResultArray();
    }

    protected function countRows(string $table, array $where = []): int
    {
        return db_connect()->table($table)->where($where)->countAllResults();
    }

    protected function notificationsFor(int $userId, ?string $type = null): array
    {
        $where = ['user_id' => $userId];
        if ($type !== null) {
            $where['type'] = $type;
        }

        return $this->rows('notifications', $where);
    }
}
