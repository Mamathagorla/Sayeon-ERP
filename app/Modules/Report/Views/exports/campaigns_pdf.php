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
    <h1>Campaign Performance Report</h1>
    <div class="meta">Generated <?= esc($generatedAt) ?> · Sayeon · Sanvima Solutions Group</div>

    <div class="summary">
        <div><strong>Total Budget:</strong> <?= number_format($totalBudget, 2) ?></div>
        <div><strong>Total Revenue:</strong> <?= number_format($totalRevenue, 2) ?></div>
        <div><strong>Overall ROI:</strong> <?= $overallRoi !== null ? $overallRoi . '%' : 'N/A' ?></div>
    </div>

    <table>
        <thead><tr><th>Campaign</th><th>Company</th><th>Channel</th><th>Budget</th><th>Leads</th><th>Conversions</th><th>Revenue</th><th>ROI</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($campaigns as $c): ?>
            <tr>
                <td><?= esc($c['name']) ?></td>
                <td><?= esc($c['company_name']) ?></td>
                <td><?= esc(\App\Modules\Marketing\Models\CampaignModel::CHANNEL_LABELS[$c['channel']]) ?></td>
                <td><?= number_format($c['budget'], 2) ?></td>
                <td><?= $c['leads'] ?></td>
                <td><?= $c['conversions'] ?></td>
                <td><?= number_format($c['revenue'], 2) ?></td>
                <td><?= $c['roi'] !== null ? $c['roi'] . '%' : 'N/A' ?></td>
                <td><?= esc(ucfirst($c['status'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
