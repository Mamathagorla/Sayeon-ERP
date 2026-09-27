<?php

namespace App\Modules\Auth\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Route-level RBAC check. Usage in a module's Routes.php:
 *   ['filter' => 'permission:task.create']
 *
 * The user's permission slugs are cached in session at login time
 * (see AuthController::attemptLogin) so this is a single in-memory
 * array lookup per request, not a query.
 */
class PermissionFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $required = $arguments[0] ?? null;

        if ($required === null) {
            return null;
        }

        $session     = session();
        $permissions = $session->get('permissions') ?? [];

        // Super Admin role bypasses granular checks entirely.
        if ($session->get('roleSlug') === 'super_admin') {
            return null;
        }

        if (! in_array($required, $permissions, true)) {
            if ($request->isAJAX()) {
                return service('response')
                    ->setStatusCode(403)
                    ->setJSON(['error' => 'You do not have permission to perform this action.']);
            }

            return redirect()->back()->with('error', 'You do not have permission to perform this action.');
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // no-op
    }
}
