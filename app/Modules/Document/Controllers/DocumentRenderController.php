<?php

namespace App\Modules\Document\Controllers;

use App\Controllers\BaseController;
use App\Modules\Document\Services\DocumentTypeRegistry;
use App\Modules\Document\Services\TemplatedDocument;

/**
 * Thin download endpoint for documents that have no dedicated action in
 * their own module yet (Salary Structure, Experience / Internship
 * Certificate). Buttons for these sit inside the existing Employee,
 * Payroll and Offboarding pages — there is no document picker page.
 * Everything else (offer letter, payslip, invoice, ...) goes through the
 * module's own existing PDF action, which calls TemplatedDocument itself.
 */
class DocumentRenderController extends BaseController
{
    public function show(string $type, int $id)
    {
        if (! DocumentTypeRegistry::canGenerate($type)) {
            return redirect()->back()->with('error', 'You do not have permission to generate this document.');
        }

        $ctx = (new DocumentTypeRegistry())->resolve($type, $id);
        if ($ctx === null || $this->outOfScope(self::COMPANY_SCOPED_ROLES, $ctx['company_id'])) {
            return redirect()->back()->with('error', 'Record not found.');
        }

        $svc = new TemplatedDocument();
        $doc = $svc->forRecord($type, $id, $this->currentUserId());

        if ($doc['status'] !== TemplatedDocument::OK) {
            TemplatedDocument::flashMissing($doc);

            return redirect()->back()->with('error', $doc['message']);
        }

        return $svc->respond($this->response, $doc, (bool) $this->request->getGet('inline'));
    }
}
