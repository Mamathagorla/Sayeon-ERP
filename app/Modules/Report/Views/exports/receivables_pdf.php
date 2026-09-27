<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
    h1 { font-size: 18px; margin-bottom: 2px; }
    .meta { color: #666; font-size: 10px; margin-bottom: 14px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
    th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; }
    th { background: #f0f0f0; }
    .summary { display: table; width: 100%; margin-bottom: 14px; }
    .summary div { display: table-cell; padding: 6px 10px; }
    .overdue { background: #fbeaea; }
</style>
</head>
<body>
    <h1>Receivables Report</h1>
    <div class="meta">Generated <?= esc($generatedAt) ?> · Sayeon · Sanvima Solutions Group</div>

    <div class="summary">
        <div><strong>Total Outstanding:</strong> <?= number_format($total, 2) ?></div>
        <div><strong>Overdue:</strong> <?= $overdue ?></div>
    </div>

    <table>
        <thead><tr><th>Invoice</th><th>Company</th><th>Customer</th><th>Amount</th><th>Due Date</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($invoices as $i): ?>
            <tr class="<?= $i['status'] === 'overdue' ? 'overdue' : '' ?>">
                <td><?= esc($i['invoice_number']) ?></td>
                <td><?= esc($i['company_name']) ?></td>
                <td><?= esc($i['customer_name']) ?></td>
                <td><?= number_format($i['amount'], 2) ?></td>
                <td><?= $i['due_date'] ? esc(date('d/m/Y', strtotime($i['due_date']))) : '-' ?></td>
                <td><?= esc(ucwords(str_replace('_', ' ', $i['status']))) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
