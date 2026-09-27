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
    <h1>Payables Report</h1>
    <div class="meta">Generated <?= esc($generatedAt) ?> · Sayeon · Sanvima Solutions Group</div>

    <div class="summary">
        <div><strong>Total Outstanding:</strong> <?= number_format($total, 2) ?></div>
        <div><strong>Overdue:</strong> <?= $overdue ?></div>
    </div>

    <table>
        <thead><tr><th>Bill</th><th>Company</th><th>Vendor</th><th>Amount</th><th>Due Date</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($bills as $b): ?>
            <tr class="<?= $b['status'] === 'overdue' ? 'overdue' : '' ?>">
                <td><?= esc($b['bill_number']) ?></td>
                <td><?= esc($b['company_name']) ?></td>
                <td><?= esc($b['vendor_name']) ?></td>
                <td><?= number_format($b['amount'], 2) ?></td>
                <td><?= $b['due_date'] ? esc(date('d/m/Y', strtotime($b['due_date']))) : '-' ?></td>
                <td><?= esc(ucwords(str_replace('_', ' ', $b['status']))) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
