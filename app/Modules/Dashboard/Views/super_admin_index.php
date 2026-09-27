<?= $this->extend('layouts/main') ?>

<?php
/**
 * Super Admin's own dashboard layout — same data DashboardController
 * already computes for every role (see index.php, the shared view
 * every other role still uses), just presented with the richer,
 * denser panel layout Super Admin's org-wide, cross-company view
 * benefits from. No new permissions, no new routes: every number and
 * link here comes from a variable this controller already computes
 * (the "Needs Attention" / "Financial Overview" figures are the one
 * addition, and they're plain read-only queries against models every
 * other module already uses — see DashboardController::index()).
 *
 * Two small inline SVG helpers — no charting lib needed for a strip
 * this small (Chart.js below is reserved for the Task Status donut,
 * which genuinely benefits from it).
 */
if (! function_exists('sy_sparkline')) {
    function sy_sparkline(array $series, string $color, int $w = 100, int $h = 30): string
    {
        $n = count($series);
        if ($n < 2) {
            return '';
        }

        $max   = max($series);
        $min   = min($series);
        $range = max($max - $min, 1);
        $stepX = $w / ($n - 1);
        $points = [];

        foreach (array_values($series) as $i => $v) {
            $x        = round($i * $stepX, 1);
            $y        = round($h - (($v - $min) / $range) * ($h - 6) - 3, 1);
            $points[] = "{$x},{$y}";
        }

        $polyline   = implode(' ', $points);
        $areaPoints = "0,{$h} {$polyline} {$w},{$h}";

        return '<svg viewBox="0 0 ' . $w . ' ' . $h . '" width="100%" height="' . $h . '" preserveAspectRatio="none">'
            . '<polyline points="' . $areaPoints . '" fill="' . $color . '" opacity="0.14" stroke="none"></polyline>'
            . '<polyline points="' . $polyline . '" fill="none" stroke="' . $color . '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></polyline>'
            . '</svg>';
    }
}
if (! function_exists('sy_bar_spark')) {
    function sy_bar_spark(array $series, string $color, int $w = 100, int $h = 30): string
    {
        $n = count($series);
        if ($n < 1) {
            return '';
        }

        $max   = max(max($series), 1);
        $gap   = 3;
        $barW  = max((($w - ($gap * ($n - 1))) / $n), 2);
        $bars  = '';

        foreach (array_values($series) as $i => $v) {
            $barH = max((($v / $max) * ($h - 2)), 2);
            $x    = round($i * ($barW + $gap), 1);
            $y    = round($h - $barH, 1);
            $bars .= '<rect x="' . $x . '" y="' . $y . '" width="' . round($barW, 1) . '" height="' . round($barH, 1) . '" rx="1.5" fill="' . $color . '"></rect>';
        }

        return '<svg viewBox="0 0 ' . $w . ' ' . $h . '" width="100%" height="' . $h . '" preserveAspectRatio="none">' . $bars . '</svg>';
    }
}

$priorityBadge = ['High' => 'danger', 'Medium' => 'warning', 'Low' => 'secondary'];

// "Needs Attention" — one chip per category, only rendered when that
// category actually has items (per-category, not an all-or-nothing
// section), each linking to the real filtered list/detail route the
// rest of the app already uses. Order: Overdue Tasks, Compliance
// Alerts, Overdue Invoices — Pending Approvals (leave requests) and
// Upcoming Meetings don't get a slot here; that data is still
// computed by the controller and still fully visible/actionable on
// the Leave and Meetings pages themselves, this is just one dashboard
// shortcut card.
$needsAttention = [];

if ($overdueTasks > 0) {
    $needsAttention[] = [
        'icon'  => 'fa-list-check', 'color' => '#cc1f2c', 'bg' => '#fdeced',
        'label' => 'Overdue Tasks', 'count' => $overdueTasks,
        'link'  => site_url('tasks?overdue=1'),
        'items' => array_map(static fn (array $t) => ['title' => $t['title'], 'link' => site_url('tasks/' . $t['id'])], array_slice($overdueTasksList, 0, 3)),
    ];
}
if ($canViewCompliance && $complianceAlertCount > 0) {
    $needsAttention[] = [
        'icon'  => 'fa-clipboard-check', 'color' => '#cc1f2c', 'bg' => '#fdeced',
        'label' => 'Compliance Alerts', 'count' => $complianceAlertCount,
        'link'  => site_url('compliance?status=overdue'),
        'items' => array_map(static fn (array $c) => ['title' => $c['title'] ?: $c['type_name'], 'link' => site_url('compliance/' . $c['id'])], array_slice($complianceAlerts, 0, 3)),
    ];
}
if ($overdueInvoiceCount > 0) {
    $needsAttention[] = [
        'icon'  => 'fa-file-invoice-dollar', 'color' => '#cc1f2c', 'bg' => '#fdeced',
        'label' => 'Overdue Invoices', 'count' => $overdueInvoiceCount,
        'link'  => site_url('accounting/invoices?status=overdue'),
        'items' => array_map(static fn (array $i) => ['title' => $i['invoice_number'] . ' — ' . $i['customer_name'], 'link' => site_url('accounting/invoices/' . $i['id'])], array_slice($overdueInvoices, 0, 3)),
    ];
}
$hasStatusData = ($completedTasks + $activeTasks + $overdueTasks) > 0;
?>

<?= $this->section('content') ?>

<style>
    /* Needs Attention: a fixed content budget (icon row + label + up to
       3 preview lines) regardless of which category has fewer than 3
       items today, so the cards in the row never look mismatched in
       height. */
    .sy-attention-card { min-height: 150px; }
    .sy-company-pager a { color: var(--sy-ink); text-decoration: none; }
    .sy-company-pager a.disabled { pointer-events: none; opacity: .35; }
</style>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <p class="text-muted mb-0">Welcome back, <strong><?= esc(session('userName')) ?></strong>.</p>
    <span class="sy-week-chip"><i class="fas fa-calendar-days me-2" style="color:var(--sy-accent-ink)"></i><?= esc($weekRangeLabel) ?></span>
</div>

<!-- KPI cards: Revenue, Expenses, Outstanding, Pending Tasks — the
     "glance" layer, all four on the coral hero gradient per explicit
     request. -->
<div class="row row-cols-2 row-cols-lg-4 g-3 mb-3">
    <div class="col">
        <div class="sy-stat-card sy-hero">
            <div class="sy-stat-icon" style="background:rgba(255,255,255,.55);color:var(--sy-hero-ink)"><i class="fas fa-sack-dollar"></i></div>
            <div class="sy-stat-label">REVENUE (MTD)</div>
            <div class="sy-stat-value"><?= number_format($financeRevenue, 2) ?></div>
            <div class="sy-stat-trend">This month, all companies</div>
        </div>
    </div>
    <div class="col">
        <div class="sy-stat-card sy-hero">
            <div class="sy-stat-icon" style="background:rgba(255,255,255,.55);color:var(--sy-hero-ink)"><i class="fas fa-file-invoice-dollar"></i></div>
            <div class="sy-stat-label">EXPENSES (MONTHLY)</div>
            <div class="sy-stat-value"><?= number_format($financeExpenses, 2) ?></div>
            <div class="sy-stat-trend">This month, all companies</div>
        </div>
    </div>
    <div class="col">
        <div class="sy-stat-card sy-hero">
            <div class="sy-stat-icon" style="background:rgba(255,255,255,.55);color:var(--sy-hero-ink)"><i class="fas fa-hourglass-half"></i></div>
            <div class="sy-stat-label">OUTSTANDING</div>
            <div class="sy-stat-value"><?= number_format($financeOutstanding, 2) ?></div>
            <div class="sy-stat-trend">Awaiting payment</div>
        </div>
    </div>
    <div class="col">
        <div class="sy-stat-card sy-hero">
            <div class="sy-stat-icon" style="background:rgba(255,255,255,.55);color:var(--sy-hero-ink)"><i class="fas fa-list-check"></i></div>
            <div class="sy-stat-label">PENDING TASKS</div>
            <div class="sy-stat-value"><?= $activeTasks ?></div>
            <?php if ($taskTrend): ?>
                <div class="sy-stat-trend">
                    <i class="fas fa-arrow-<?= $taskTrend['direction'] ?>"></i> <?= abs($taskTrend['percent']) ?>% from last week
                </div>
            <?php endif; ?>
            <div class="sy-stat-spark"><?= sy_bar_spark($activeTasksSeries, '#d62431') ?></div>
        </div>
    </div>
</div>

<!-- Needs Attention -->
<div class="sy-card mb-3">
    <div class="sy-card-header"><strong><i class="fas fa-triangle-exclamation me-2 text-danger"></i>Needs Attention</strong></div>
    <div class="sy-card-body">
        <?php if (empty($needsAttention)): ?>
            <p class="text-muted small mb-0"><i class="fas fa-circle-check text-success me-1"></i>You're all caught up — nothing needs attention right now.</p>
        <?php else: ?>
        <div class="row row-cols-1 row-cols-md-2 row-cols-xl-4 g-3">
            <?php foreach ($needsAttention as $na): ?>
            <div class="col">
                <a href="<?= $na['link'] ?>" class="text-decoration-none text-reset d-block">
                    <div class="sy-attention-card" style="border-left: 3px solid <?= $na['color'] ?>">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="sy-stat-icon" style="width:36px;height:36px;font-size:.9rem;background:<?= $na['bg'] ?>;color:<?= $na['color'] ?>"><i class="fas <?= $na['icon'] ?>"></i></span>
                            <div>
                                <div class="fw-bold" style="font-size:1.15rem;color:<?= $na['color'] ?>"><?= (int) $na['count'] ?></div>
                                <div class="text-muted small"><?= esc($na['label']) ?></div>
                            </div>
                        </div>
                        <?php foreach ($na['items'] as $item): ?>
                            <div class="small text-truncate text-body"><i class="fas fa-circle me-1" style="font-size:.35rem;color:<?= $na['color'] ?>;vertical-align:middle"></i><?= esc($item['title']) ?></div>
                        <?php endforeach; ?>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Today's Priorities + Financial Overview -->
<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="sy-card h-100">
            <div class="sy-card-header"><strong>Today's Priorities</strong></div>
            <div class="sy-card-body p-0 sy-priorities-body">
                <?php if (empty($priorities)): ?>
                    <p class="text-muted small mb-0 p-3">Nothing urgent right now.</p>
                <?php else: ?>
                <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Item</th><th>Due</th><th>Priority</th></tr></thead>
                    <tbody>
                    <?php foreach ($priorities as $p): ?>
                        <tr>
                            <td><a href="<?= $p['link'] ?>" class="text-decoration-none fw-semibold text-dark"><?= esc($p['title']) ?></a></td>
                            <td class="text-muted small"><?= esc($p['subtitle']) ?></td>
                            <td><span class="badge bg-<?= $priorityBadge[$p['priority']] ?? 'secondary' ?>"><?= esc($p['priority']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="sy-card h-100">
            <div class="sy-card-header"><strong>Financial Overview</strong> <a href="<?= site_url('reports/financials') ?>">Details</a></div>
            <div class="sy-card-body">
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <span class="text-muted small"><i class="fas fa-arrow-down text-success me-1"></i>Revenue (MTD)</span>
                    <span class="fw-semibold"><?= number_format($financeRevenue, 2) ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <span class="text-muted small"><i class="fas fa-arrow-up text-danger me-1"></i>Expenses (monthly)</span>
                    <span class="fw-semibold"><?= number_format($financeExpenses, 2) ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center py-2">
                    <span class="text-muted small"><i class="fas fa-hourglass-half text-warning me-1"></i>Outstanding</span>
                    <span class="fw-semibold"><?= number_format($financeOutstanding, 2) ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Company-wise Progress + Task Status -->
<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="sy-card h-100">
            <div class="sy-card-header"><strong>Company-wise Progress</strong> <a href="<?= site_url('companies') ?>">View All</a></div>
            <div class="sy-card-body">
                <?php if (empty($companyProgress)): ?>
                    <p class="text-muted small mb-0"><?= $companyTotal > 0 ? 'No companies on this page.' : 'No companies yet.' ?></p>
                <?php else: ?>
                <div class="row row-cols-1 g-3">
                    <?php foreach ($companyProgress as $cp): ?>
                    <div class="col">
                        <a href="<?= site_url('companies/' . $cp['id']) ?>" class="text-decoration-none text-reset d-block sy-progress-row">
                            <div class="d-flex justify-content-between align-items-baseline mb-1">
                                <span class="fw-semibold"><?= esc($cp['name']) ?></span>
                                <span class="text-muted small"><?= $cp['percent'] ?>%</span>
                            </div>
                            <div class="progress mb-1" style="height:8px;">
                                <div class="progress-bar" role="progressbar" style="width:<?= $cp['percent'] ?>%;background:var(--sy-accent-ink)" aria-valuenow="<?= $cp['percent'] ?>" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <div class="text-muted small"><?= $cp['completed'] ?> completed of <?= $cp['total'] ?> total tasks</div>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if ($companyPages > 1): ?>
                <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top sy-company-pager">
                    <a class="small <?= $companyPage <= 1 ? 'disabled' : '' ?>" href="<?= site_url('') . '?cp=' . ($companyPage - 1) ?>"><i class="fas fa-chevron-left me-1"></i>Prev</a>
                    <span class="text-muted small">Page <?= $companyPage ?> of <?= $companyPages ?> · <?= $companyTotal ?> compan<?= $companyTotal === 1 ? 'y' : 'ies' ?></span>
                    <a class="small <?= $companyPage >= $companyPages ? 'disabled' : '' ?>" href="<?= site_url('') . '?cp=' . ($companyPage + 1) ?>">Next<i class="fas fa-chevron-right ms-1"></i></a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="sy-card h-100">
            <div class="sy-card-header"><strong>Task Status</strong></div>
            <div class="sy-card-body d-flex flex-column align-items-center">
                <?php if ($hasStatusData): ?>
                    <canvas id="taskStatusDonut" width="160" height="160" style="max-width:170px;cursor:pointer"></canvas>
                    <div class="d-flex flex-wrap justify-content-center gap-3 mt-3 small">
                        <a href="<?= site_url('tasks?status=completed') ?>" class="text-decoration-none text-reset"><span class="d-inline-block rounded-circle me-1" style="width:8px;height:8px;background:#5b6472"></span>Completed (<?= (int) $completedTasks ?>)</a>
                        <a href="<?= site_url('tasks?active=1') ?>" class="text-decoration-none text-reset"><span class="d-inline-block rounded-circle me-1" style="width:8px;height:8px;background:#aab1bd"></span>Active (<?= (int) $activeTasks ?>)</a>
                        <a href="<?= site_url('tasks?overdue=1') ?>" class="text-decoration-none text-reset"><span class="d-inline-block rounded-circle me-1" style="width:8px;height:8px;background:#d62431"></span>Overdue (<?= (int) $overdueTasks ?>)</a>
                    </div>
                <?php else: ?>
                    <div class="sy-empty-chart"><i class="fas fa-chart-pie mb-2"></i><span>No data available</span></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const donutCtx = document.getElementById('taskStatusDonut');
    if (donutCtx) {
        // Same 3 links the legend below uses — clicking a slice goes to
        // that same filtered task list, not just clicking its label.
        const statusLinks = [
            '<?= site_url('tasks?status=completed') ?>',
            '<?= site_url('tasks?active=1') ?>',
            '<?= site_url('tasks?overdue=1') ?>',
        ];
        new Chart(donutCtx, {
            type: 'doughnut',
            data: {
                labels: ['Completed', 'Active', 'Overdue'],
                datasets: [{
                    data: [<?= (int) $completedTasks ?>, <?= (int) $activeTasks ?>, <?= (int) $overdueTasks ?>],
                    backgroundColor: ['#5b6472', '#aab1bd', '#d62431'],
                    borderWidth: 0,
                }],
            },
            options: {
                cutout: '72%',
                plugins: { legend: { display: false } },
                onClick: function (evt, elements) {
                    if (elements.length) {
                        window.location.href = statusLinks[elements[0].index];
                    }
                },
            },
        });
    }
});
</script>
<?= $this->endSection() ?>
