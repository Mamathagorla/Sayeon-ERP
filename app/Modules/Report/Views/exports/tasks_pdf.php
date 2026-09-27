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
    <h1>Task Summary Report</h1>
    <div class="meta">Generated <?= esc($generatedAt) ?> · Sayeon · Sanvima Solutions Group</div>

    <div class="summary">
        <div><strong>Total:</strong> <?= $total ?></div>
        <div><strong>Overdue:</strong> <?= $overdue ?></div>
        <div><strong>Completed:</strong> <?= $byStatus['completed'] ?></div>
    </div>

    <table>
        <thead><tr><th>Title</th><th>Company</th><th>Department</th><th>Assignee</th><th>Priority</th><th>Status</th><th>Due Date</th></tr></thead>
        <tbody>
        <?php foreach ($tasks as $t): ?>
            <tr class="<?= ($t['due_date'] && $t['due_date'] < date('Y-m-d') && ! in_array($t['status'], ['completed', 'cancelled'], true)) ? 'overdue' : '' ?>">
                <td><?= esc($t['title']) ?></td>
                <td><?= esc($t['company_name']) ?></td>
                <td><?= esc($t['department_name']) ?></td>
                <td><?= esc($t['assignee_name'] ?? 'Unassigned') ?></td>
                <td><?= esc(ucfirst($t['priority'])) ?></td>
                <td><?= esc(ucwords(str_replace('_', ' ', $t['status']))) ?></td>
                <td><?= $t['due_date'] ? esc(date('d/m/Y', strtotime($t['due_date']))) : '-' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
