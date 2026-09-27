<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
    /* Dompdf-safe: table-based layout only — no flexbox/grid, same
       convention as HR\Views\exports\payslip_pdf.php and
       Report\Views\exports\*_pdf.php. */
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
    h1 { font-size: 18px; margin: 0 0 4px; }
    .muted { color: #666; }
    .right { text-align: right; }
    .letterhead { width: 100%; border-bottom: 2px solid #222; padding-bottom: 10px; margin-bottom: 16px; }
    .letterhead td { vertical-align: top; }
    .tag { font-size: 15px; font-weight: bold; color: #0d9488; }

    table.split { width: 100%; border: 1px solid #d9dfe6; margin-bottom: 16px; }
    table.split td { padding: 10px 14px; font-size: 10px; vertical-align: top; width: 50%; }
    table.split td.left { border-right: 1px solid #d9dfe6; }
    table.split .h3 { font-size: 9px; text-transform: uppercase; color: #666; display: block; margin-bottom: 4px; }
    table.split .name { font-weight: bold; font-size: 11px; }

    table.items { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
    table.items th { background: #f6f8fa; text-align: left; padding: 6px 8px; border: 1px solid #d9dfe6; font-size: 9px; text-transform: uppercase; color: #666; }
    table.items td { padding: 6px 8px; border: 1px solid #d9dfe6; }

    table.totals { width: 45%; border: 1px solid #d9dfe6; }
    table.totals td { padding: 5px 10px; font-size: 10px; border-bottom: 1px solid #eef1f4; }
    table.totals tr.grand td { font-weight: bold; background: #f6f8fa; font-size: 11px; }
    table.totals tr.balance td { font-weight: bold; color: #b91c1c; }

    .status-pill { padding: 2px 8px; border-radius: 10px; font-size: 9px; font-weight: bold; }
    .status-paid { background: #dcfce7; color: #16a34a; }
    .status-overdue { background: #fee2e2; color: #dc2626; }
    .status-default { background: #f1f5f9; color: #475569; }

    .words { font-size: 10px; margin: 12px 0; padding: 8px 12px; background: #f6f8fa; }
    .words .label { color: #666; display: block; font-size: 9px; text-transform: uppercase; margin-bottom: 2px; }

    .sign { margin-top: 40px; text-align: right; font-size: 10px; color: #666; }

    .footer { margin-top: 24px; border-top: 1px solid #d9dfe6; padding-top: 8px; text-align: center; color: #666; font-size: 9px; }
</style>
</head>
<body>

<table class="letterhead">
    <tr>
        <td style="width:60%;">
            <h1><?= esc($company['name'] ?? $invoice['company_name']) ?></h1>
            <?php if (! empty($company['registered_address'])): ?>
                <div class="muted"><?= nl2br(esc($company['registered_address'])) ?></div>
            <?php endif; ?>
            <?php if (! empty($company['gst'])): ?>
                <div class="muted">GSTIN: <?= esc($company['gst']) ?></div>
            <?php endif; ?>
        </td>
        <td style="width:40%;" class="right">
            <div class="tag">INVOICE</div>
            <div class="muted">#<?= esc($invoice['invoice_number']) ?></div>
            <div class="muted">Issue Date: <?= esc(date('d/m/Y', strtotime($invoice['issue_date']))) ?></div>
            <div class="muted">Due Date: <?= $invoice['due_date'] ? esc(date('d/m/Y', strtotime($invoice['due_date']))) : '—' ?></div>
        </td>
    </tr>
</table>

<table class="split">
    <tr>
        <td class="left">
            <span class="h3">Bill To</span>
            <div class="name"><?= esc($invoice['customer_name']) ?></div>
            <?php if (! empty($invoice['customer_address'])): ?><div><?= nl2br(esc($invoice['customer_address'])) ?></div><?php endif; ?>
            <?php if (! empty($invoice['customer_email']) || ! empty($invoice['customer_phone'])): ?>
            <div><?= esc(trim(($invoice['customer_email'] ?? '') . ($invoice['customer_phone'] ? ' · ' . $invoice['customer_phone'] : ''))) ?></div>
            <?php endif; ?>
            <div class="muted">GSTIN: <?= esc($invoice['customer_gstin'] ?? '—') ?></div>
        </td>
        <td>
            <span class="h3">Payment Status</span>
            <span class="status-pill status-<?= $invoice['status'] === 'paid' ? 'paid' : ($invoice['status'] === 'overdue' ? 'overdue' : 'default') ?>">
                <?= esc(strtoupper(str_replace('_', ' ', $invoice['status']))) ?>
            </span>
            <div class="muted" style="margin-top:6px;">GST Type: <?= $invoice['gst_type'] === 'inter_state' ? 'Inter-state (IGST)' : 'Intra-state (CGST+SGST)' ?></div>
        </td>
    </tr>
</table>

<table class="items">
    <thead>
        <tr><th style="width:6%;">#</th><th>Description</th><th class="right" style="width:10%;">Qty</th><th class="right" style="width:14%;">Rate</th><th class="right" style="width:10%;">Tax</th><th class="right" style="width:16%;">Amount</th></tr>
    </thead>
    <tbody>
        <?php if (empty($items)): ?>
        <tr>
            <td>1</td>
            <td><?= esc($invoice['notes'] ?: ('Invoice ' . $invoice['invoice_number'])) ?></td>
            <td class="right">—</td>
            <td class="right">—</td>
            <td class="right"><?= rtrim(rtrim(number_format((float) $invoice['gst_percent'], 2), '0'), '.') ?>%</td>
            <td class="right"><?= number_format((float) $invoice['amount'], 2) ?></td>
        </tr>
        <?php else: ?>
            <?php foreach ($items as $i => $item): ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><?= esc($item['description']) ?></td>
                <td class="right"><?= rtrim(rtrim(number_format((float) $item['quantity'], 2), '0'), '.') ?></td>
                <td class="right"><?= number_format((float) $item['rate'], 2) ?></td>
                <td class="right"><?= rtrim(rtrim(number_format((float) $invoice['gst_percent'], 2), '0'), '.') ?>%</td>
                <td class="right"><?= number_format((float) $item['amount'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<table style="width:100%;">
    <tr>
        <td style="width:55%; vertical-align:top;">
            <div class="muted" style="font-size:10px;"><strong>Payment Method:</strong>
            <?php if ($bankAccount): ?>
                Bank Transfer — <?= esc($bankAccount['bank_name']) ?><?= $bankAccount['branch'] ? ', ' . esc($bankAccount['branch']) : '' ?><br>
                A/C Holder: <?= esc($bankAccount['account_holder']) ?> &middot; A/C No.: •••• <?= esc($bankAccount['account_number_last4']) ?>
                <?php if (! empty($bankAccount['ifsc'])): ?> &middot; IFSC: <?= esc($bankAccount['ifsc']) ?><?php endif; ?>
            <?php else: ?>
                No bank account on file.
            <?php endif; ?>
            </div>
            <?php if (! empty($invoice['notes'])): ?>
                <div class="muted" style="font-size:10px; margin-top:6px;"><strong>Notes:</strong> <?= esc($invoice['notes']) ?></div>
            <?php endif; ?>
        </td>
        <td style="width:45%;">
            <table class="totals">
                <tr><td>Subtotal</td><td class="right"><?= number_format((float) $invoice['subtotal'], 2) ?></td></tr>
                <?php if ((float) $invoice['discount_amount'] > 0): ?>
                <tr><td>Discount (<?= rtrim(rtrim(number_format((float) $invoice['discount_percent'], 2), '0'), '.') ?>%)</td><td class="right">- <?= number_format((float) $invoice['discount_amount'], 2) ?></td></tr>
                <?php endif; ?>
                <?php if ((float) $invoice['cgst_amount'] > 0): ?>
                <tr><td>CGST</td><td class="right"><?= number_format((float) $invoice['cgst_amount'], 2) ?></td></tr>
                <tr><td>SGST</td><td class="right"><?= number_format((float) $invoice['sgst_amount'], 2) ?></td></tr>
                <?php endif; ?>
                <?php if ((float) $invoice['igst_amount'] > 0): ?>
                <tr><td>IGST</td><td class="right"><?= number_format((float) $invoice['igst_amount'], 2) ?></td></tr>
                <?php endif; ?>
                <tr class="grand"><td>Grand Total</td><td class="right"><?= number_format((float) $invoice['amount'], 2) ?></td></tr>
                <tr><td>Paid</td><td class="right"><?= number_format($paid, 2) ?></td></tr>
                <tr class="balance"><td>Balance Due</td><td class="right"><?= number_format($balance, 2) ?></td></tr>
            </table>
        </td>
    </tr>
</table>

<div class="words">
    <span class="label">Amount in Words</span>
    <?= esc(amount_in_words((float) $invoice['amount'])) ?>
</div>

<div class="sign">
    <span><?= esc($bankAccount['authorized_signatories'] ?? 'Authorized Signatory') ?></span>
    <strong><?= esc($company['name'] ?? $invoice['company_name']) ?></strong>
</div>

<div class="footer"><?= esc($company['name'] ?? $invoice['company_name']) ?></div>

</body>
</html>
