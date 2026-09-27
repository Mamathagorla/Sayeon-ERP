<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
    h1 { font-size: 18px; margin-bottom: 2px; }
    .meta { color: #666; font-size: 10px; margin-bottom: 8px; }
    .note { color: #666; font-size: 10px; margin-bottom: 18px; font-style: italic; }
    table { width: 100%; border-collapse: collapse; }
    td, th { padding: 5px 7px; border-bottom: 1px solid #ddd; }
    th { text-align: left; }
    .right { text-align: right; }
    .section { font-weight: bold; background: #f0f0f0; }
    .total { font-weight: bold; background: #f0f0f0; }
    .neg { color: #b00020; }
    .muted { color: #888; }
</style>
</head>
<body>
    <h1>Profit &amp; Loss</h1>
    <div class="meta"><?= esc($perMonth[0]['label']) ?> &ndash; <?= esc($perMonth[count($perMonth) - 1]['label']) ?> &middot; Generated <?= esc($generatedAt) ?></div>
    <div class="note">Revenue/Vendor Bills are accrual-based (issued in each month, any payment status). Each expense category is a monthly-equivalent snapshot as of month end — not a period sum.</div>

    <table>
        <tr>
            <th>Line Item</th>
            <th>Category</th>
            <?php foreach ($perMonth as $m): ?>
                <th class="right"><?= esc($m['label']) ?></th>
            <?php endforeach; ?>
            <th class="right">Total</th>
        </tr>

        <tr class="section"><td colspan="<?= 3 + count($perMonth) ?>">REVENUE</td></tr>
        <tr>
            <td>Invoiced Revenue</td><td class="muted">Income</td>
            <?php foreach ($perMonth as $m): ?>
                <td class="right"><?= number_format($m['revenue'], 2) ?></td>
            <?php endforeach; ?>
            <td class="right"><?= number_format($totalRevenue, 2) ?></td>
        </tr>

        <tr class="section"><td colspan="<?= 3 + count($perMonth) ?>">EXPENSES</td></tr>
        <?php foreach ($categories as $cat): ?>
        <tr>
            <td><?= esc($cat) ?></td><td class="muted">Operating</td>
            <?php foreach ($perMonth as $m): ?>
                <td class="right neg">-<?= number_format($m['byCategory'][$cat] ?? 0, 2) ?></td>
            <?php endforeach; ?>
            <td class="right neg">-<?= number_format($categoryTotals[$cat], 2) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if ($totalVendorBills > 0): ?>
        <tr>
            <td>Vendor Bills</td><td class="muted">Payables</td>
            <?php foreach ($perMonth as $m): ?>
                <td class="right neg">-<?= number_format($m['vendorBills'], 2) ?></td>
            <?php endforeach; ?>
            <td class="right neg">-<?= number_format($totalVendorBills, 2) ?></td>
        </tr>
        <?php endif; ?>
        <tr class="total">
            <td colspan="2">Total Expenses</td>
            <?php foreach ($perMonth as $m): ?>
                <td class="right">-<?= number_format($m['totalExpenses'], 2) ?></td>
            <?php endforeach; ?>
            <td class="right">-<?= number_format($totalExpenses, 2) ?></td>
        </tr>

        <tr class="total">
            <td colspan="2">NET PROFIT</td>
            <?php foreach ($perMonth as $m): ?>
                <td class="right"><?= number_format($m['netProfit'], 2) ?></td>
            <?php endforeach; ?>
            <td class="right"><?= number_format($netProfit, 2) ?></td>
        </tr>
        <tr>
            <td colspan="<?= 2 + count($perMonth) ?>">Margin (on Total)</td>
            <td class="right"><?= $margin !== null ? esc($margin) . '%' : '—' ?></td>
        </tr>
    </table>
</body>
</html>
