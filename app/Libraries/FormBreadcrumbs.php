<?php

namespace App\Libraries;

/**
 * Breadcrumb trail for create / edit form pages, detail pages and sub-pages, derived from the URL the
 * form is served on, e.g.
 *
 *   hr/employees/create          -> Employees > Add Employee
 *   hr/employees/12/edit         -> Employees > Edit Employee
 *   companies/5/bank-accounts/3/edit -> Companies > Company > Edit Bank Account
 *
 * The last crumb is the page's own $title, so the wording always matches the
 * heading. Returns [] for any page that is not a form page (nothing renders).
 */
class FormBreadcrumbs
{
    /** URL prefixes that group modules but are not pages themselves. */
    private const PREFIXES = ['hr', 'accounting', 'auth'];

    private const LABELS = ['help' => 'Support', 'websites' => 'Websites', 'recurring-expenses' => 'Recurring Expenses'];

    /** Form pages whose URL does not end in create / {id}/edit => the list segments they belong to. */
    private const SPECIAL = [
        '#^hr/attendance/(mark|bulk-upload)$#'           => ['hr', 'attendance'],
        '#^documents/templates/clone$#'                  => ['documents', 'templates'],
        '#^hr/payroll/structure/\d+$#'                   => ['hr', 'payroll'],
    ];

    /** @return list<array{label:string,url:?string}> */
    public static function forPath(string $path, string $title): array
    {
        $path = trim($path, '/');
        $segs = $path === '' ? [] : explode('/', $path);
        $last = end($segs);

        $isForm = true;
        if ($last === 'create' || $last === 'edit') {
            array_pop($segs);
        } else {
            $special = null;
            foreach (self::SPECIAL as $rx => $base) {
                if (preg_match($rx, $path)) {
                    $special = $base;
                    break;
                }
            }
            if ($special !== null) {
                $segs = $special;
            } else {
                // Detail pages (tasks/5) and sub-pages (hr/onboarding/phases) get a trail too; list roots do not.
                $isForm = false;
                $plain  = array_values(array_diff(array_filter($segs, static fn ($x) => ! ctype_digit($x)), self::PREFIXES));
                if (! ctype_digit((string) $last) && count($plain) < 2) {
                    return [];
                }
            }
        }

        // Record pages of nested resources keep only the parent record: companies/5/bank-accounts/3
        $names = array_values(array_filter($segs, static fn ($s) => ! ctype_digit($s)));
        $names = array_values(array_diff($names, self::PREFIXES)) ?: $names;
        $nested = count($names) > 1;
        // A sub-page such as hr/onboarding/phases IS the current page: its own crumb is the title.
        $dropFinal = ! $isForm && ! ctype_digit((string) $last);

        $crumbs = [];
        $walked = [];
        foreach ($segs as $i => $seg) {
            $walked[] = $seg;
            if (ctype_digit($seg) || in_array($seg, self::PREFIXES, true)) {
                continue;
            }
            $hasId   = isset($segs[$i + 1]) && ctype_digit($segs[$i + 1]);
            $isFinal = $seg === end($names);

            if ($isFinal && ($dropFinal || $nested)) {
                continue; // the current page itself, or a nested collection with no list page of its own
            }
            if ($hasId && ! $isFinal) {
                $url   = site_url(implode('/', array_merge($walked, [$segs[$i + 1]])));
                $label = self::singular(self::label($seg));
            } else {
                $url   = site_url(implode('/', $walked));
                $label = self::label($seg);
            }
            $crumbs[] = ['label' => $label, 'url' => $url];
        }

        $crumbs[] = ['label' => $title, 'url' => null];

        return $crumbs;
    }

    private static function label(string $seg): string
    {
        return self::LABELS[$seg] ?? ucwords(str_replace('-', ' ', $seg));
    }

    private static function singular(string $label): string
    {
        return preg_replace(['/ies$/', '/s$/'], ['y', ''], $label) ?? $label;
    }
}
