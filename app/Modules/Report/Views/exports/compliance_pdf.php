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
    <h1>Compliance Status Report</h1>
    <div class="meta">Generated <?= esc($generatedAt) ?> · Sayeon · Sanvima Solutions Group</div>

    <div class="summary">
        <div><strong>Total:</strong> <?= $total ?></div>
        <div><strong>Overdue:</strong> <?= $byStatus['overdue'] ?></div>
        <div><strong>Filed:</strong> <?= $byStatus['filed'] ?></div>
    </div>

    <table>
        <thead><tr><th>Title</th><th>Company</th><th>Type</th><th>Responsible</th><th>Status</th><th>Due Date</th></tr></thead>
        <tbody>
        <?php foreach ($items as $i): ?>
            <tr class="<?= $i['status'] === 'overdue' ? 'overdue' : '' ?>">
                <td><?= esc($i['title']) ?></td>
                <td><?= esc($i['company_name']) ?></td>
                <td><?= esc($i['type_name']) ?></td>
                <td><?= esc($i['responsible_name'] ?? 'Unassigned') ?></td>
                <td><?= esc(ucwords(str_replace('_', ' ', $i['status']))) ?></td>
                <td><?= esc(date('d/m/Y', strtotime($i['due_date']))) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
