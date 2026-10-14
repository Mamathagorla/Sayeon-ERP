<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #222; }
    h1 { font-size: 18px; margin-bottom: 2px; }
    .meta { color: #666; font-size: 10px; margin-bottom: 8px; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    th { text-align: left; background: #f0f0f0; padding: 6px 8px; font-size: 10px; text-transform: uppercase; color: #555; }
    td { padding: 6px 8px; border-bottom: 1px solid #ddd; }
    .right { text-align: right; }
    .total { font-weight: bold; background: #f7f7f7; }
    .positive { color: #16a34a; }
    .negative { color: #dc2626; }
    .summary { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
    .summary td { border: none; padding: 4px 8px; }
</style>
</head>
<body>
    <h1>Income vs Expense</h1>
    <div class="meta">Period <?= esc(erp_date($from)) ?> to <?= esc(erp_date($to)) ?> &middot; Generated <?= esc($generatedAt) ?></div>

    <table class="summary">
        <tr>
            <td><strong>Total Income:</strong> <?= erp_number($totalIncome, 2) ?></td>
            <td><strong>Total Expenses:</strong> <?= erp_number($totalExpenses, 2) ?></td>
            <td><strong>Net Difference:</strong> <span class="<?= $netDifference >= 0 ? 'positive' : 'negative' ?>"><?= erp_number($netDifference, 2) ?></span></td>
            <td><strong>Expense Ratio:</strong> <?= $expenseRatio === null ? '—' : erp_number($expenseRatio, 1) . '%' ?></td>
        </tr>
    </table>

    <table>
        <thead>
            <tr><th>Period</th><th class="right">Income</th><th class="right">Expense</th><th class="right">Difference</th><th class="right">Trend %</th></tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= esc($r['label']) ?></td>
                <td class="right positive">+<?= erp_number($r['income'], 2) ?></td>
                <td class="right negative">-<?= erp_number($r['expense'], 2) ?></td>
                <td class="right <?= $r['difference'] >= 0 ? 'positive' : 'negative' ?>"><?= $r['difference'] >= 0 ? '+' : '' ?><?= erp_number($r['difference'], 2) ?></td>
                <td class="right"><?php
                    if ($r['trend']['state'] === 'new') {
                        echo 'New';
                    } elseif ($r['trend']['state'] === 'percent') {
                        echo ($r['trend']['percent'] >= 0 ? '+' : '') . erp_number($r['trend']['percent'], 1) . '%';
                    } else {
                        echo '—';
                    }
                ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="total">
                <td>Total</td>
                <td class="right"><?= erp_number($totalIncome, 2) ?></td>
                <td class="right"><?= erp_number($totalExpenses, 2) ?></td>
                <td class="right"><?= erp_number($netDifference, 2) ?></td>
                <td class="right"></td>
            </tr>
        </tbody>
    </table>
</body>
</html>
