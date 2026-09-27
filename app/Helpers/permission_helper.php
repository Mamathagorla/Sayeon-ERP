<?php

if (! function_exists('can')) {
    /**
     * True if the current session can perform $permission — mirrors
     * PermissionFilter's own check (including the Super Admin bypass),
     * so anything gated by this always matches what the route itself
     * would actually allow.
     */
    function can(string $permission): bool
    {
        if (session('roleSlug') === 'super_admin') {
            return true;
        }

        return in_array($permission, session('permissions') ?? [], true);
    }
}
