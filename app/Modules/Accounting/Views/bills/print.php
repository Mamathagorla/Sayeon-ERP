<?php
// No per-bill "how we paid the vendor" field exists — the most
// recent recorded payment's method is the closest honest substitute
// (matches the wireframe's "Payment Method" line without inventing a
// bank-details block Bills have no data for, unlike Invoices showing
// OUR OWN bank details for the customer to pay INTO).
$lastPayment = $payments[count($payments) - 1] ?? null;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?= esc($title) ?></title>
    <style>
        :root { --ink: #10243f; --muted: #6b7a90; --teal: #0d9488; --line: #d9dfe6; }
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, Segoe UI, Roboto, Arial, sans-serif;
            color: var(--ink);
            margin: 0;
            padding: 32px;
            background: #eef2f5;
        }
        .sheet {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            padding: 40px 44px;
        }
        .toolbar {
            max-width: 800px;
            margin: 0 auto 14px;
            text-align: right;
        }
        .toolbar a, .toolbar button {
            display: inline-block;
            background: var(--teal);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 9px 20px;
            font-size: .9rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            margin-left: 8px;
        }
        .toolbar a.secondary { background: #fff; color: var(--teal); border: 1px solid var(--teal); }
        .letterhead {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid var(--ink);
            padding-bottom: 16px;
            margin-bottom: 20px;
        }
        .letterhead h1 { font-size: 1.5rem; margin: 0 0 4px; color: var(--ink); }
        .letterhead .addr { font-size: .82rem; color: var(--muted); max-width: 380px; }
        .letterhead .doc-label { text-align: right; }
        .letterhead .doc-label .tag { font-size: 1.1rem; font-weight: 700; letter-spacing: 1px; color: var(--teal); }
        .letterhead .doc-label .num { font-size: .82rem; color: var(--muted); margin-top: 2px; }
        .letterhead .doc-label .dates { font-size: .78rem; color: var(--muted); margin-top: 8px; }

        .split-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
            border: 1px solid var(--line);
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 24px;
        }
        .split-col { padding: 14px 16px; }
        .split-col:first-child { border-right: 1px solid var(--line); }
        .split-col h3 { font-size: .72rem; text-transform: uppercase; letter-spacing: .5px; color: var(--muted); margin: 0 0 8px; }
        .split-col .name { font-weight: 700; font-size: .95rem; margin-bottom: 2px; }
        .split-col .line { font-size: .85rem; color: var(--ink); }
        .split-col .line.muted { color: var(--muted); }

        table.doc-table { width: 100%; border-collapse: collapse; margin-bottom: 24px; font-size: .85rem; }
        table.doc-table th {
            background: #f6f8fa; text-align: left; padding: 9px 12px;
            border: 1px solid var(--line); font-size: .74rem; text-transform: uppercase; color: var(--muted);
        }
        table.doc-table td { padding: 9px 12px; border: 1px solid var(--line); }
        table.doc-table td.num, table.doc-table th.num { text-align: right; }

        .bottom-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 20px; }
        .totals-box { border: 1px solid var(--line); border-radius: 8px; overflow: hidden; align-self: start; }
        .totals-row { display: flex; justify-content: space-between; padding: 9px 16px; font-size: .85rem; border-bottom: 1px solid var(--line); }
        .totals-row:last-child { border-bottom: none; }
        .totals-row.total { font-weight: 700; background: #f6f8fa; }
        .totals-row.balance { font-weight: 700; color: #b91c1c; }

        .pay-info { font-size: .85rem; line-height: 1.7; }
        .pay-info .label { color: var(--muted); font-size: .72rem; text-transform: uppercase; letter-spacing: .5px; display: block; margin-bottom: 2px; }

        .words { font-size: .85rem; margin: 16px 0; padding: 10px 14px; background: #f6f8fa; border-radius: 8px; }
        .words .label { color: var(--muted); font-size: .72rem; text-transform: uppercase; letter-spacing: .5px; display: block; margin-bottom: 2px; }

        .signature { margin-top: 40px; text-align: right; }
        .signature .line { border-top: 1px solid var(--ink); width: 220px; margin: 0 0 6px auto; }

        .status-pill {
            display: inline-block; padding: 2px 10px; border-radius: 20px; font-size: .72rem; font-weight: 700; letter-spacing: .3px;
        }
        .status-paid { background: #dcfce7; color: #16a34a; }
        .status-overdue { background: #fee2e2; color: #dc2626; }
        .status-default { background: #f1f5f9; color: #475569; }

        .footer { border-top: 1px solid var(--line); margin-top: 28px; padding-top: 14px; text-align: center; font-size: .78rem; color: var(--muted); }

        @media print {
            body { background: #fff; padding: 0; }
            .toolbar { display: none; }
            .sheet { padding: 0; max-width: none; }
        }
    </style>
</head>
<body>

<div class="toolbar">
    <a href="<?= site_url('accounting/bills/' . $bill['id'] . '/pdf') ?>" class="secondary">Download PDF</a>
    <button onclick="window.print()">Print</button>
</div>

<div class="sheet">
    <div class="letterhead">
        <div>
            <h1><?= esc($company['name'] ?? $bill['company_name']) ?></h1>
            <?php if (! empty($company['registered_address'])): ?>
                <div class="addr"><?= nl2br(esc($company['registered_address'])) ?></div>
            <?php endif; ?>
            <?php if (! empty($company['gst'])): ?>
                <div class="addr">GSTIN: <?= esc($company['gst']) ?></div>
            <?php endif; ?>
        </div>
        <div class="doc-label">
            <div class="tag">BILL</div>
            <div class="num">#<?= esc($bill['bill_number']) ?></div>
            <div class="dates">
                Bill Date: <?= esc(date('d M Y', strtotime($bill['issue_date']))) ?><br>
                Due Date: <?= $bill['due_date'] ? esc(date('d M Y', strtotime($bill['due_date']))) : '—' ?>
            </div>
        </div>
    </div>

    <div class="split-grid">
        <div class="split-col">
            <h3>Vendor</h3>
            <div class="name"><?= esc($bill['vendor_name']) ?></div>
            <?php if (! empty($bill['vendor_address'])): ?><div class="line"><?= nl2br(esc($bill['vendor_address'])) ?></div><?php endif; ?>
            <div class="line <?= $bill['vendor_gstin'] ? '' : 'muted' ?>">GSTIN: <?= esc($bill['vendor_gstin'] ?? '—') ?></div>
        </div>
        <div class="split-col">
            <h3>Payment Status</h3>
            <span class="status-pill status-<?= $bill['status'] === 'paid' ? 'paid' : ($bill['status'] === 'overdue' ? 'overdue' : 'default') ?>">
                <?= esc(strtoupper(str_replace('_', ' ', $bill['status']))) ?>
            </span>
            <div class="line muted" style="margin-top:8px;">GST Type: <?= $bill['gst_type'] === 'inter_state' ? 'Inter-state (IGST)' : 'Intra-state (CGST+SGST)' ?></div>
        </div>
    </div>

    <table class="doc-table">
        <thead><tr><th style="width:8%">SL.No</th><th>Description</th><th class="num" style="width:10%">Qty</th><th class="num" style="width:14%">Rate</th><th class="num" style="width:12%">Tax</th><th class="num" style="width:16%">Amount</th></tr></thead>
        <tbody>
            <?php if (empty($items)): ?>
            <tr>
                <td>1</td>
                <td><?= esc($bill['notes'] ?: ('Bill ' . $bill['bill_number'])) ?></td>
                <td class="num">—</td>
                <td class="num">—</td>
                <td class="num"><?= rtrim(rtrim(number_format((float) $bill['gst_percent'], 2), '0'), '.') ?>%</td>
                <td class="num"><?= number_format((float) $bill['amount'], 2) ?></td>
            </tr>
            <?php else: ?>
                <?php foreach ($items as $i => $item): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= esc($item['description']) ?></td>
                    <td class="num"><?= rtrim(rtrim(number_format((float) $item['quantity'], 2), '0'), '.') ?></td>
                    <td class="num"><?= number_format((float) $item['rate'], 2) ?></td>
                    <td class="num"><?= rtrim(rtrim(number_format((float) $bill['gst_percent'], 2), '0'), '.') ?>%</td>
                    <td class="num"><?= number_format((float) $item['amount'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="bottom-grid">
        <div class="totals-box">
            <div class="totals-row"><span>Subtotal</span><span><?= number_format((float) $bill['subtotal'], 2) ?></span></div>
            <?php if ((float) $bill['discount_amount'] > 0): ?>
            <div class="totals-row"><span>Discount (<?= rtrim(rtrim(number_format((float) $bill['discount_percent'], 2), '0'), '.') ?>%)</span><span>- <?= number_format((float) $bill['discount_amount'], 2) ?></span></div>
            <?php endif; ?>
            <?php if ((float) $bill['cgst_amount'] > 0): ?>
            <div class="totals-row"><span>CGST</span><span><?= number_format((float) $bill['cgst_amount'], 2) ?></span></div>
            <div class="totals-row"><span>SGST</span><span><?= number_format((float) $bill['sgst_amount'], 2) ?></span></div>
            <?php endif; ?>
            <?php if ((float) $bill['igst_amount'] > 0): ?>
            <div class="totals-row"><span>IGST</span><span><?= number_format((float) $bill['igst_amount'], 2) ?></span></div>
            <?php endif; ?>
            <div class="totals-row total"><span>Grand Total</span><span><?= number_format((float) $bill['amount'], 2) ?></span></div>
            <div class="totals-row"><span>Paid</span><span><?= number_format($paid, 2) ?></span></div>
            <div class="totals-row balance"><span>Balance Due</span><span><?= number_format($balance, 2) ?></span></div>
        </div>
        <div class="pay-info">
            <span class="label">Payment Method</span>
            <?= $lastPayment ? esc($lastPayment['method'] ?: 'Recorded, method not noted') : 'Not yet paid' ?>
            <?php if (! empty($bill['notes'])): ?>
                <div style="margin-top:10px;"><span class="label">Notes / Terms</span><?= nl2br(esc($bill['notes'])) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="words">
        <span class="label">Amount in Words</span>
        <?= esc(amount_in_words((float) $bill['amount'])) ?>
    </div>

    <div class="signature">
        <div class="line"></div>
        Authorized Approval
    </div>

    <div class="footer">
        <?= esc($company['name'] ?? $bill['company_name']) ?>
    </div>
</div>

</body>
</html>
