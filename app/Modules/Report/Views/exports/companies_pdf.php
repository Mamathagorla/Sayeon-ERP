<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
    h1 { font-size: 18px; margin-bottom: 2px; }
    .meta { color: #666; font-size: 10px; margin-bottom: 14px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: right; }
    th:first-child, td:first-child { text-align: left; }
    th { background: #f0f0f0; }
    .bad { color: #a1262f; font-weight: bold; }
</style>
</head>
<body>
    <h1>Company Overview Report</h1>
    <div class="meta">Generated <?= esc($generatedAt) ?> · Sayeon · Sanvima Solutions Group</div>

    <table>
        <thead>
            <tr><th>Company</th><th>Active Tasks</th><th>Overdue Tasks</th><th>Completed Tasks</th><th>Projects</th><th>Compliance Pending</th><th>Compliance Overdue</th><th>Upcoming Meetings</th></tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= esc($r['company']) ?></td>
                <td><?= $r['tasks_active'] ?></td>
                <td class="<?= $r['tasks_overdue'] > 0 ? 'bad' : '' ?>"><?= $r['tasks_overdue'] ?></td>
                <td><?= $r['tasks_completed'] ?></td>
                <td><?= $r['projects'] ?></td>
                <td><?= $r['compliance_pending'] ?></td>
                <td class="<?= $r['compliance_overdue'] > 0 ? 'bad' : '' ?>"><?= $r['compliance_overdue'] ?></td>
                <td><?= $r['meetings_upcoming'] ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
