<?php

if (! function_exists('company_short_code')) {
    /**
     * companies.short_code for $companyId, or null if unset. This was
     * briefly hardcoded to always return null after short_code was
     * dropped entirely (2026-09-29-150001_DropShortCodeFromCompanies),
     * then reinstated (2026-09-29-165959_ReaddShortCodeToCompanies) for
     * the company-specific short-ID system — every company now has one,
     * backfilled by that same migration.
     */
    function company_short_code(int $companyId): ?string
    {
        $row = db_connect()->table('companies')->select('short_code')->where('id', $companyId)->get()->getRowArray();

        return $row['short_code'] ?? null;
    }
}

if (! function_exists('next_company_sequence_number')) {
    /**
     * The next plain integer in a per-company, per-prefix sequence — a
     * read-only preview (like EmployeeProfileModel::nextEmployeeCode()),
     * not a consumed counter: it scans $table for this company's own
     * rows whose $codeColumn starts with $prefix, takes the highest
     * trailing number, and returns +1 (or 1 if none exist yet). The
     * caller formats the final string (padding/segments differ per
     * entity — e.g. Invoice's format also has a year segment); the real
     * duplicate-prevention is always $table's own UNIQUE key on
     * $codeColumn, exactly as before this helper existed.
     */
    function next_company_sequence_number(string $table, string $codeColumn, int $companyId, string $prefix): int
    {
        $last = db_connect()->table($table)
            ->select($codeColumn)
            ->where('company_id', $companyId)
            ->like($codeColumn, $prefix, 'after')
            ->orderBy('id', 'DESC')
            ->get(1)
            ->getRowArray();

        $next = 1;
        if ($last && preg_match('/(\d+)$/', (string) $last[$codeColumn], $m)) {
            $next = (int) $m[1] + 1;
        }

        return $next;
    }
}
