<?php

namespace App\Controllers;

use App\Modules\Compliance\Models\ComplianceItemModel;
use App\Modules\Support\Models\SupportTicketModel;
use App\Modules\Task\Models\TaskModel;

/**
 * Single gated download endpoint for locally-stored uploads (task
 * attachments, compliance attachments, company documents, bank account
 * documents). Files under writable/uploads are NOT web-accessible
 * directly — they're only reachable through this route, so the 'auth'
 * filter (applied globally) protects them the same as any other page.
 *
 * Every uploader stores files under a "<module>/<ownerId>/..." path
 * (see TaskController::uploadAttachment, ComplianceController::
 * uploadAttachment, DocumentController::store, BankAccountController::
 * store) — isAuthorized() uses that same convention to resolve which
 * company owns the file and re-runs the identical companyScopeFor()
 * check the owning module's own controller already applies, so a
 * scoped viewer can't reach another company's upload just by knowing
 * (or guessing) its stored path.
 */
class FileController extends BaseController
{
    public function download()
    {
        $path = $this->request->getGet('path');

        if (! $path || str_contains($path, '..')) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        if (! $this->isAuthorized($path)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $storage = service('fileStorage');

        if (! method_exists($storage, 'absolutePath')) {
            // S3 driver — redirect to a presigned URL instead of streaming.
            return redirect()->to($storage->url($path));
        }

        $fullPath = $storage->absolutePath($path);

        if (! is_file($fullPath)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return $this->response->download($fullPath, null);
    }

    /**
     * Resolves the company that owns $path and checks it against the
     * current viewer's scope, same as the owning module's own
     * show/edit would. Super Admin (companyScopeFor() === null unless
     * they've picked a company) always passes.
     */
    private function isAuthorized(string $path): bool
    {
        $segments = explode('/', $path);
        $module   = $segments[0] ?? '';
        $ownerId  = isset($segments[1]) ? (int) $segments[1] : 0;

        if ($ownerId <= 0) {
            return false;
        }

        switch ($module) {
            case 'tasks':
                $task = (new TaskModel())->find($ownerId);

                return $task !== null && ! $this->outOfScope(self::COMPANY_SCOPED_ROLES, (int) $task['company_id']);

            case 'compliance':
                $item = (new ComplianceItemModel())->find($ownerId);

                return $item !== null && ! $this->outOfScope(self::COMPANY_SCOPED_ROLES, (int) $item['company_id']);

            case 'support_tickets':
                $ticket = (new SupportTicketModel())->find($ownerId);

                return $ticket !== null && ! $this->outOfScope(self::COMPANY_SCOPED_ROLES, (int) $ticket['company_id']);

            case 'company_documents':
            case 'bank-accounts':
                // $ownerId here IS the company id — that's the prefix
                // both uploaders store under.
                return ! $this->outOfScope(self::COMPANY_SCOPED_ROLES, $ownerId);

            default:
                return false;
        }
    }
}
