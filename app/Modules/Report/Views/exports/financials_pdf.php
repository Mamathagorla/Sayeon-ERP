<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #222; }
    h1 { font-size: 18px; margin-bottom: 2px; }
    .meta { color: #666; font-size: 10px; margin-bottom: 8px; }
    .note { color: #666; font-size: 10px; margin-bottom: 18px; font-style: italic; }
    table { width: 100%; border-collapse: collapse; }
    td { padding: 6px 8px; border-bottom: 1px solid #ddd; }
    .right { text-align: right; }
    .total { font-weight: bold; background: #f0f0f0; }
</style>
</head>
<body>
    <h1>Financial Summary</h1>
    <div class="meta">Period <?= esc(date('d/m/Y', strtotime($from))) ?> to <?= esc(date('d/m/Y', strtotime($to))) ?> · Generated <?= esc($generatedAt) ?> · Sayeon · Sanvima Solutions Group</div>
    <div class="note">Simplified, informational snapshot — not a formal Profit &amp; Loss statement or Balance Sheet.</div>

    <table>
        <tr><td>Revenue Collected (cash in)</td><td class="right"><?= number_format($cashIn, 2) ?></td></tr>
        <tr><td>Bills Paid (cash out)</td><td class="right"><?= number_format($cashOut, 2) ?></td></tr>
        <tr><td>Operating Expenses (in period)</td><td class="right"><?= number_format($periodExpenses, 2) ?></td></tr>
        <tr class="total"><td>Net Profit / Loss</td><td class="right"><?= number_format($netProfitLoss, 2) ?></td></tr>
        <tr class="total"><td>Net Cash Flow</td><td class="right"><?= number_format($netCashFlow, 2) ?></td></tr>
        <tr><td>Outstanding Receivables</td><td class="right"><?= number_format($outstandingReceivables, 2) ?></td></tr>
        <tr><td>Outstanding Payables</td><td class="right"><?= number_format($outstandingPayables, 2) ?></td></tr>
    </table>
</body>
</html>
