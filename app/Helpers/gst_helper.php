<?php

if (! function_exists('gst_default_percent')) {
    /**
     * Default GST% pre-filled on a new invoice. One place to change it —
     * was previously a literal `18` inside invoices/form.php.
     */
    function gst_default_percent(): float
    {
        return 18.0;
    }
}

if (! function_exists('gst_rate_slabs')) {
    /**
     * India's standard GST slabs, offered as quick-pick options on the
     * invoice form's GST% field (which stays a free numeric input too,
     * for any rate outside these). Change this array — not application
     * code — to add/remove a slab.
     */
    function gst_rate_slabs(): array
    {
        return [0, 5, 12, 18, 28];
    }
}

if (! function_exists('determine_gst_type')) {
    /**
     * The actual GST rule this feature adds: same state as the selling
     * company → intra-state (CGST+SGST); a different state → inter-state
     * (IGST). Returns null — "can't tell, leave it to whoever is filling
     * the form" — when either side's state isn't known yet, which is
     * true for every invoice/company that predates this feature and for
     * any customer outside India (no state master data exists there).
     * Callers (InvoiceController) keep the manually-chosen gst_type in
     * that case, so nothing already working changes behaviour.
     */
    function determine_gst_type(?int $companyStateId, ?int $customerStateId): ?string
    {
        if ($companyStateId === null || $customerStateId === null) {
            return null;
        }

        return $companyStateId === $customerStateId ? 'intra_state' : 'inter_state';
    }
}

if (! function_exists('gstin_matches_state_prefix')) {
    /**
     * True unless a GSTIN's first two digits actively contradict the
     * selected state's known GST state code — never invents or completes
     * a GSTIN itself (see states.gst_state_code, only ever set for
     * India's own states/UTs). Passes (true) whenever there's nothing to
     * check against: no GSTIN entered yet, or the selected state/country
     * has no GST state code at all (i.e. not an Indian state) — the
     * field then behaves exactly as free text, same as before this
     * feature existed.
     */
    function gstin_matches_state_prefix(?string $gstin, ?string $expectedPrefix): bool
    {
        if ($expectedPrefix === null || $expectedPrefix === '' || $gstin === null || $gstin === '') {
            return true;
        }

        return str_starts_with(strtoupper($gstin), $expectedPrefix);
    }
}
