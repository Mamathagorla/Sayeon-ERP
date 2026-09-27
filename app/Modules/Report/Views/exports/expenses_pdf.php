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
</style>
</head>
<body>
    <h1>Expense Report — <?= esc($periodLabel) ?></h1>
    <div class="meta">Generated <?= esc($generatedAt) ?> · Sayeon · Sanvima Solutions Group</div>

    <div class="summary">
        <div><strong>Total recorded:</strong> <?= number_format($total, 2) ?></div>
        <div><strong>Monthly-Equivalent (active):</strong> <?= number_format($monthlyTotal, 2) ?></div>
        <div><strong><?= $periodType === 'year' ? 'Yearly Total' : 'Annualized' ?>:</strong> <?= number_format($yearlyTotal, 2) ?></div>
    </div>

    <table>
        <thead><tr><th>Vendor</th><th>Company</th><th>Category</th><th>Billing Cycle</th><th>Amount</th><th>Renewal Date</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($expenses as $e): ?>
            <tr>
                <td><?= esc($e['vendor']) ?></td>
                <td><?= esc($e['company_name']) ?></td>
                <td><?= esc($e['category']) ?></td>
                <td><?= esc(ucfirst($e['billing_cycle'])) ?></td>
                <td><?= number_format($e['amount'], 2) ?></td>
                <td><?= $e['renewal_date'] ? esc(date('d/m/Y', strtotime($e['renewal_date']))) : '-' ?></td>
                <td><?= esc(ucfirst($e['status'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
