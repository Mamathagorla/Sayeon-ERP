<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title ?? 'Sayeon') ?> · Sanvima Operations</title>

    <!-- Bootstrap 5 + AdminLTE 4 (CDN — see Assumptions §10 re: vendoring these locally for production) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta3/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.11/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <!-- Shared premium theme layer (cards/tables/badges/buttons/sidebar
         grouping/topbar chip) — see public/assets/css/sayeon-theme.css.
         Loaded before the brand <style> block below so that block's
         --sy-navy/--sy-teal tokens and any page's own `styles` section
         still win where they overlap. -->
    <link rel="stylesheet" href="<?= base_url('assets/css/sayeon-theme.css') ?>?v=<?= @filemtime(FCPATH . 'assets/css/sayeon-theme.css') ?: 1 ?>">
    <style>
        /* AdminLTE 4.0.0-beta3's own layout relies on .app-wrapper being
           display:grid with a 1fr middle row to stretch .app-main and
           pin the footer to the bottom. In practice that isn't holding
           on short pages — the header/main/footer column sizes to its
           own content instead, leaving the page's grey body background
           exposed below the footer rather than the footer sitting at
           the true bottom. (AdminLTE's CSS also references
           .app-sidebar-wrapper/.app-main-wrapper divs that would fix
           this via flexbox, but its own official demo markup never
           adds them either — so that path isn't reliably wired in this
           beta build.) Replaced with a small, self-contained flexbox
           layout below instead. This only overrides outer positioning —
           none of AdminLTE's sidebar/nav/card styling is touched. */
        /* .app-wrapper is now a fixed-height (not min-height) flex row
           that never grows past one viewport and clips anything that
           would otherwise push it taller (overflow: hidden) — so the
           *document* never scrolls. Its two children (.app-sidebar,
           .app-main-column) each get their own independent overflow-y
           instead: scrolling one never moves the other, and the topbar/
           page-header stay visually pinned since they're the
           non-scrolling chrome around .app-main's own scroll area. This
           replaces an earlier version of this block that used
           min-height + a from-AdminLTE max-height:none override to let
           the sidebar grow to match tall pages — that made the sidebar's
           long nav list stretch the *whole page* (topbar included) into
           one shared scroll instead of scrolling on its own, which is
           the actual behavior wanted here. */
        .app-wrapper {
            display: flex;
            align-items: stretch;
            height: 100vh;
            height: 100dvh;
            overflow: hidden;
        }
        .app-sidebar {
            flex-shrink: 0;
            overflow-y: auto;
            overflow-x: hidden;
            /* AdminLTE's own sticky/max-height rules for .app-sidebar no
               longer matter once the flex row above is a fixed height —
               align-items:stretch already sizes this to exactly that
               height regardless, so nothing further needs overriding. */
        }
        .app-main-column {
            display: flex;
            flex-direction: column;
            flex: 1 1 auto;
            min-width: 0;
            min-height: 0; /* let this flex item shrink to the row's fixed
                               height instead of growing to fit its
                               content — required for its own overflow
                               rules below to actually take effect */
            overflow: hidden;
        }
        .app-main-column > .app-header,
        .app-main-column > .app-footer {
            flex-shrink: 0;
        }
        .app-main-column .app-main {
            flex: 1 1 auto;
            min-height: 0;
            overflow-y: auto;
            /* .app-content-header (page title + actions) lives inside
               .app-main, right above .app-content — it scrolls together
               with the page content as one unit here. Only .app-header
               (the topbar) above is pinned in place, per what was asked. */
            scroll-behavior: smooth;
            /* NOT applied to .app-sidebar: the inline script right after
               </aside> below sets .app-sidebar's scrollTop directly on
               every page load to restore/preserve position, and with
               scroll-behavior:smooth that assignment animates instead of
               snapping instantly — a visible, unwanted scroll-jump on
               every navigation. .app-main has no such script, so smooth
               scrolling here is safe (covers wheel/keyboard scrolling and
               the "Back to top" button's scrollTo({behavior:'smooth'})). */
        }

        /* ============ Back to top ============
           Fixed to the viewport (not .app-main) so its position doesn't
           depend on which element scrolls — sits in the same bottom-right
           corner regardless. Hidden by default via opacity/visibility
           (not display:none) so the .2s fade has something to animate;
           pointer-events is toggled with it so the invisible button never
           intercepts clicks meant for content underneath it. */
        .sy-back-to-top {
            position: fixed;
            right: 24px;
            bottom: 24px;
            z-index: 1040;
            width: 42px;
            height: 42px;
            border: 1px solid #CBD5E1;
            border-radius: 999px;
            background: var(--sy-surface);
            color: #CBD5E1;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .95rem;
            box-shadow: 0 8px 20px rgba(8, 19, 36, .18);
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transform: translateY(10px);
            transition: opacity .2s ease, transform .2s ease, visibility .2s, background-color .15s ease, color .15s ease, border-color .15s ease;
        }
        .sy-back-to-top.show {
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
            transform: translateY(0);
        }
        .sy-back-to-top:hover,
        .sy-back-to-top:focus-visible {
            background: #E63946;
            border-color: #E63946;
            color: #fff;
        }

        /* ============ Sayeon brand theme ============
           --sy-teal/--sy-teal-dark are kept as the variable *names* every
           rule below (and in sayeon-theme.css) already builds on, but now
           alias --sy-accent-ink — the Sanvima-logo red in its text/button
           form. --sy-accent-warm (the logo navy) is deliberately NOT
           aliased here — it's used directly, and sparingly, for
           secondary/informational chips. --sy-navy is the ink/hover-darken
           tone and is unrelated. */
        :root {
            --sy-navy: #0b1a30;
            --sy-navy-deep: #081324;
            --sy-teal: var(--sy-accent-ink);
            --sy-teal-dark: var(--sy-accent-ink);
        }

        .app-sidebar { display: flex; flex-direction: column; }
        .app-sidebar .sidebar-wrapper { flex: 1 1 auto; }

        .sidebar-brand { padding: 14px 12px; }
        .sidebar-brand .brand-link { display: flex; align-items: center; gap: 12px; }
        .sy-logo-mark {
            width: 38px; height: 38px; border-radius: 10px; flex-shrink: 0;
            background: var(--sy-gradient-hero);
            display: flex; align-items: center; justify-content: center;
            font-weight: 800; font-size: 1.05rem; color: var(--sy-hero-ink);
        }
        .sy-brand-word { line-height: 1.1; }
        .sy-brand-word strong { display: block; font-size: 1.05rem; letter-spacing: 2px; font-weight: 700; color: var(--sy-sidebar-ink); }
        .sy-brand-word span { font-size: .55rem; letter-spacing: 2px; color: var(--sy-sidebar-muted); }

        /* topbar */
        .sy-search { position: relative; max-width: 320px; flex: 1 1 auto; }
        .sy-search > i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--sy-muted); font-size: .85rem; }
        .sy-search input {
            width: 100%; border: 1px solid var(--sy-border); background: var(--sy-subtle-bg); border-radius: 10px;
            padding: 8px 14px 8px 36px; font-size: .85rem; outline: none; color: var(--sy-ink);
        }
        .sy-search input:focus { border-color: var(--sy-accent); background: var(--sy-surface); }
        .sy-search-results {
            position: absolute; top: calc(100% + 6px); left: 0; right: 0;
            background: var(--sy-surface); border: 1px solid var(--sy-border); border-radius: 10px;
            box-shadow: 0 12px 28px rgba(8, 19, 36, .12); max-height: 380px; overflow-y: auto; z-index: 1050;
        }
        .sy-search-results .sy-sr-cat { padding: 8px 14px 4px; font-size: .68rem; font-weight: 700; letter-spacing: .4px; color: var(--sy-muted); text-transform: uppercase; }
        .sy-search-results a.sy-sr-item { display: flex; align-items: center; gap: 10px; padding: 8px 14px; color: var(--sy-ink); text-decoration: none; font-size: .85rem; }
        .sy-search-results a.sy-sr-item:hover { background: var(--sy-hover-bg); }
        .sy-search-results a.sy-sr-item i { color: var(--sy-accent-ink); width: 16px; text-align: center; }
        .sy-search-results a.sy-sr-item .sy-sr-sub { color: var(--sy-muted); font-size: .74rem; margin-left: auto; padding-left: 10px; white-space: nowrap; }
        .sy-search-results .sy-sr-empty { padding: 14px; font-size: .82rem; color: var(--sy-muted); text-align: center; }

        .sy-avatar {
            width: 34px; height: 34px; border-radius: 50%;
            background: var(--sy-gradient-hero);
            color: var(--sy-hero-ink); display: inline-flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: .85rem;
        }
        .sy-avatar-img {
            width: 34px; height: 34px; border-radius: 50%;
            object-fit: cover; display: inline-block;
            border: 2px solid var(--sy-surface); box-shadow: var(--sy-shadow-sm);
        }
        .sy-role-badge {
            background: var(--sy-gradient-hero);
            color: var(--sy-hero-ink); font-weight: 600; font-size: .68rem; letter-spacing: .3px;
        }

        /* ============ Site-wide button/link/form theme ============
           Card/card-header/table/badge base styling already lives in
           sayeon-theme.css (token-driven) — not duplicated here, since a
           hardcoded-hex copy loading after it would silently win the
           cascade and drift out of sync with that file over time.
           What's left here is what that file doesn't already cover:
           Bootstrap's own .btn-primary/.btn-outline-primary/.progress-bar/
           .page-link/etc. base classes (as opposed to the .app-content
           .btn.* pill/radius rules, which do live in the shared file). */
        .btn-primary {
            background: linear-gradient(135deg, var(--sy-teal) 0%, var(--sy-teal-dark) 100%);
            border-color: var(--sy-teal-dark);
        }
        .btn-primary:hover, .btn-primary:focus, .btn-primary:active {
            background: linear-gradient(135deg, var(--sy-teal-dark) 0%, var(--sy-teal-dark) 100%) !important;
            border-color: var(--sy-teal-dark) !important;
        }
        .btn-outline-primary {
            color: var(--sy-teal-dark);
            border-color: var(--sy-teal);
        }
        .btn-outline-primary:hover, .btn-outline-primary:active {
            background: var(--sy-teal) !important;
            border-color: var(--sy-teal) !important;
        }
        .btn-check:checked + .btn-outline-primary,
        .btn-outline-primary.active { background: var(--sy-teal) !important; border-color: var(--sy-teal) !important; }

        .app-content a:not(.btn):not(.dropdown-item):not(.nav-link):not(.page-link) { color: var(--sy-teal-dark); }
        .app-content a:not(.btn):not(.dropdown-item):not(.nav-link):not(.page-link):hover { color: var(--sy-navy); }

        .progress { background-color: var(--sy-border-soft); }
        .progress-bar { background-color: var(--sy-teal); }

        .form-control:focus, .form-select:focus {
            border-color: var(--sy-teal);
            box-shadow: 0 0 0 .2rem var(--sy-accent-soft);
        }
        .form-check-input:checked { background-color: var(--sy-teal-dark); border-color: var(--sy-teal-dark); }

        .nav-tabs .nav-link.active { color: var(--sy-teal-dark); border-bottom: 2px solid var(--sy-teal); font-weight: 600; }
        .nav-tabs .nav-link:hover:not(.active) { color: var(--sy-teal-dark); }

        .page-link { color: var(--sy-teal-dark); background-color: var(--sy-surface); border-color: var(--sy-border); }
        .page-item.active .page-link { background-color: var(--sy-teal-dark); border-color: var(--sy-teal-dark); }
        .page-item.disabled .page-link { background-color: var(--sy-subtle-bg); }

        .table > :not(caption) > * > * { vertical-align: middle; }

        /* Modals/dropdowns are plain Bootstrap components most pages use
           as-is — token-driven here too so they don't stay hardcoded
           light when the rest of the page has gone dark. */
        .modal-content { background-color: var(--sy-surface); color: var(--sy-ink); }
        .modal-header, .modal-footer { border-color: var(--sy-border-soft); }
        .dropdown-menu { background-color: var(--sy-surface); border-color: var(--sy-border); }
        .dropdown-item { color: var(--sy-ink); }
        .dropdown-item:hover, .dropdown-item:focus { background-color: var(--sy-hover-bg); color: var(--sy-ink); }
        .dropdown-divider { border-color: var(--sy-border-soft); }
    </style>
    <?= $this->renderSection('styles') ?>
</head>
<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
<div class="app-wrapper">

    <!-- Sidebar -->
    <aside class="app-sidebar shadow">
        <div class="sidebar-brand">
            <a href="<?= site_url('/') ?>" class="brand-link">
                <span class="sy-logo-mark">S</span>
                <span class="sy-brand-word"><strong>SAYEON</strong><span>ERP SOLUTIONS</span></span>
            </a>
        </div>
        <div class="sidebar-wrapper">
            <nav class="mt-2">
                <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="menu">
                    <?php
                        $nav = $navActive ?? '';
                        // Purely presentational grouping — inserts small
                        // section-label dividers between the existing nav
                        // items below (their own can() guards, order and
                        // hrefs are untouched). Each header's own
                        // visibility mirrors the can() checks already
                        // guarding every item under it, so a group never
                        // shows as an empty/orphan label for a role that
                        // can't see anything in it.
                        $showWorkHeader      = can('task.view') || can('project.view') || can('department.view') || can('meeting.view') || can('compliance.view');
                        $showHrHeader        = can('employee.view') || can('attendance.view') || can('leave.view') || can('payroll.view') || can('performance.view') || can('onboarding.view') || can('offboarding.view');
                        $showFinanceHeader   = can('expense.view') || can('invoice.view') || can('bill.view');
                        $showResourcesHeader = can('website.view') || can('document.view') || can('policy.view') || can('campaign.view');
                    ?>

                    <li class="nav-item">
                        <a href="<?= site_url('/') ?>" class="nav-link <?= $nav === 'dashboard' ? 'active' : '' ?>">
                            <i class="nav-icon fas fa-gauge-high"></i><p>Dashboard</p>
                        </a>
                    </li>
                    <?php if (can('task.view') || can('meeting.view') || can('compliance.view')): ?>
                    <li class="nav-item">
                        <a href="<?= site_url('calendar') ?>" class="nav-link <?= $nav === 'calendar' ? 'active' : '' ?>">
                            <i class="nav-icon fas fa-calendar-days"></i><p>Calendar</p>
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a href="<?= site_url('todos') ?>" class="nav-link <?= $nav === 'todos' ? 'active' : '' ?>">
                            <i class="nav-icon fas fa-square-check"></i><p>To-Do List</p>
                        </a>
                    </li>
                    <?php if (can('company.view')): ?>
                    <li class="nav-header">Organization</li>
                    <li class="nav-item">
                        <a href="<?= site_url('companies') ?>" class="nav-link <?= $nav === 'companies' ? 'active' : '' ?>">
                            <i class="nav-icon fas fa-building"></i><p>Companies</p>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if ($showWorkHeader): ?>
                    <li class="nav-header">Work</li>
                    <?php endif; ?>
                    <?php if (can('task.view')): ?>
                    <li class="nav-item">
                        <a href="<?= site_url('tasks') ?>" class="nav-link <?= $nav === 'tasks' ? 'active' : '' ?>">
                            <i class="nav-icon fas fa-list-check"></i><p>Tasks</p>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (can('project.view')): ?>
                    <li class="nav-item">
                        <a href="<?= site_url('projects') ?>" class="nav-link <?= $nav === 'projects' ? 'active' : '' ?>">
                            <i class="nav-icon fas fa-diagram-project"></i><p>Projects</p>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (can('department.view')): ?>
                    <li class="nav-item">
                        <a href="<?= site_url('departments') ?>" class="nav-link <?= $nav === 'departments' ? 'active' : '' ?>">
                            <i class="nav-icon fas fa-sitemap"></i><p>Departments</p>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (can('meeting.view')): ?>
                    <li class="nav-item">
                        <a href="<?= site_url('meetings') ?>" class="nav-link <?= $nav === 'meetings' ? 'active' : '' ?>">
                            <i class="nav-icon fas fa-handshake"></i><p>Meetings</p>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (can('compliance.view')): ?>
                    <li class="nav-item">
                        <a href="<?= site_url('compliance') ?>" class="nav-link <?= $nav === 'compliance' ? 'active' : '' ?>">
                            <i class="nav-icon fas fa-clipboard-check"></i><p>Compliance</p>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if ($showHrHeader): ?>
                    <li class="nav-header">Human Resources</li>
                    <?php endif; ?>
                    <?php if (can('employee.view')): ?>
                    <li class="nav-item">
                        <a href="<?= site_url('hr/employees') ?>" class="nav-link <?= $nav === 'hr-employees' ? 'active' : '' ?>">
                            <i class="nav-icon fas fa-users"></i><p>Employees</p>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (can('onboarding.view')): ?>
                    <li class="nav-item">
                        <a href="<?= site_url('hr/onboarding') ?>" class="nav-link <?= $nav === 'hr-onboarding' ? 'active' : '' ?>">
                            <i class="nav-icon fas fa-user-plus"></i><p>Onboarding</p>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (can('offboarding.view')): ?>
                    <li class="nav-item">
                        <a href="<?= site_url('hr/offboarding') ?>" class="nav-link <?= $nav === 'hr-offboarding' ? 'active' : '' ?>">
                            <i class="nav-icon fas fa-person-walking-arrow-right"></i><p>Offboarding</p>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (can('attendance.view')): ?>
                    <li class="nav-item">
                        <a href="<?= site_url('hr/attendance') ?>" class="nav-link <?= $nav === 'hr-attendance' ? 'active' : '' ?>">
                            <i class="nav-icon fas fa-clock"></i><p>Attendance</p>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (can('leave.view')): ?>
                    <li class="nav-item">
                        <a href="<?= site_url('hr/leave') ?>" class="nav-link <?= $nav === 'hr-leave' ? 'active' : '' ?>">
                            <i class="nav-icon fas fa-plane-departure"></i><p>Leave</p>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (can('payroll.view')): ?>
                    <li class="nav-item">
                        <a href="<?= site_url('hr/payroll') ?>" class="nav-link <?= $nav === 'hr-payroll' ? 'active' : '' ?>">
                            <i class="nav-icon fas fa-money-check-dollar"></i><p>Payroll</p>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (can('performance.view')): ?>
                    <li class="nav-item">
                        <a href="<?= site_url('hr/performance') ?>" class="nav-link <?= $nav === 'hr-performance' ? 'active' : '' ?>">
                            <i class="nav-icon fas fa-chart-line"></i><p>Performance</p>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (can('purchase_order.view')): ?>
                    <li class="nav-header">Purchase</li>
                    <li class="nav-item">
                        <a href="<?= site_url('purchase-orders') ?>" class="nav-link <?= $nav === 'purchase-orders' ? 'active' : '' ?>">
                            <i class="nav-icon fas fa-file-signature"></i><p>Purchase Orders</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= site_url('purchases') ?>" class="nav-link <?= $nav === 'purchases' ? 'active' : '' ?>">
                            <i class="nav-icon fas fa-cart-shopping"></i><p>Purchases</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= site_url('vendors') ?>" class="nav-link <?= $nav === 'vendors' ? 'active' : '' ?>">
                            <i class="nav-icon fas fa-people-carry-box"></i><p>Vendors</p>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if ($showFinanceHeader): ?>
                    <li class="nav-header">Finance</li>
                    <?php endif; ?>
                    <?php if (can('expense.view')): ?>
                    <li class="nav-item">
                        <a href="<?= site_url('expenses') ?>" class="nav-link <?= $nav === 'expenses' ? 'active' : '' ?>">
                            <i class="nav-icon fas fa-receipt"></i><p>Expenses</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= site_url('recurring-expenses') ?>" class="nav-link <?= $nav === 'recurring-expenses' ? 'active' : '' ?>">
                            <i class="nav-icon fas fa-rotate"></i><p>Recurring Expenses</p>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (can('invoice.view') && can('bill.view')): ?>
                    <li class="nav-item <?= $nav === 'accounting' ? 'menu-open' : '' ?>">
                        <a href="<?= site_url('accounting') ?>" class="nav-link <?= $nav === 'accounting' ? 'active' : '' ?>">
                            <i class="nav-icon fas fa-calculator"></i>
                            <p>Accounting<i class="nav-arrow fas fa-angle-left"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="<?= site_url('accounting/invoices') ?>" class="nav-link">
                                    <i class="nav-icon fas fa-circle" style="font-size:.4rem"></i><p>Invoices</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?= site_url('accounting/bills') ?>" class="nav-link">
                                    <i class="nav-icon fas fa-circle" style="font-size:.4rem"></i><p>Bills</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <?php elseif (can('invoice.view')): ?>
                    <li class="nav-item">
                        <a href="<?= site_url('accounting/invoices') ?>" class="nav-link <?= $nav === 'accounting' ? 'active' : '' ?>">
                            <i class="nav-icon fas fa-file-invoice-dollar"></i><p>Invoices</p>
                        </a>
                    </li>
                    <?php elseif (can('bill.view')): ?>
                    <li class="nav-item">
                        <a href="<?= site_url('accounting/bills') ?>" class="nav-link <?= $nav === 'accounting' ? 'active' : '' ?>">
                            <i class="nav-icon fas fa-file-invoice"></i><p>Bills</p>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if ($showResourcesHeader): ?>
                    <li class="nav-header">Resources</li>
                    <?php endif; ?>
                    <?php if (can('website.view')): ?>
                    <li class="nav-item">
                        <a href="<?= site_url('websites') ?>" class="nav-link <?= $nav === 'websites' ? 'active' : '' ?>">
                            <i class="nav-icon fas fa-server"></i><p>Websites &amp; Hosting</p>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (can('document.view')): ?>
                    <li class="nav-item">
                        <a href="<?= site_url('documents') ?>" class="nav-link <?= $nav === 'documents' ? 'active' : '' ?>">
                            <i class="nav-icon fas fa-folder-open"></i><p>Documents</p>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (can('policy.view')): ?>
                    <li class="nav-item">
                        <a href="<?= site_url('policies') ?>" class="nav-link <?= $nav === 'policies' ? 'active' : '' ?>">
                            <i class="nav-icon fas fa-layer-group"></i><p>Policies &amp; Manuals</p>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (can('campaign.view')): ?>
                    <li class="nav-item">
                        <a href="<?= site_url('campaigns') ?>" class="nav-link <?= $nav === 'campaigns' ? 'active' : '' ?>">
                            <i class="nav-icon fas fa-bullhorn"></i><p>Marketing</p>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (can('report.view')): ?>
                    <li class="nav-header">Insights</li>
                    <li class="nav-item <?= $nav === 'reports' ? 'menu-open' : '' ?>">
                        <a href="#" class="nav-link <?= $nav === 'reports' ? 'active' : '' ?>">
                            <i class="nav-icon fas fa-chart-column"></i>
                            <p>Reports<i class="nav-arrow fas fa-angle-left"></i></p>
                        </a>
                        <?php
                            // Each entry mirrors its report's own route
                            // permission exactly (see Report/Routes.php) —
                            // a role only sees the sub-reports it can
                            // actually open, same gating as the Reports
                            // landing page's own cards.
                            $reportLinks = [
                                ['perm' => 'expense.view',  'label' => 'Expense Summary',      'href' => 'reports/expenses'],
                                ['perm' => 'invoice.view',  'label' => 'Receivables',           'href' => 'reports/receivables'],
                                ['perm' => 'bill.view',     'label' => 'Payables',              'href' => 'reports/payables'],
                                ['perm' => 'invoice.view',  'label' => 'Financial Summary',     'href' => 'reports/financials'],
                                ['perm' => 'invoice.view',  'label' => 'Profit & Loss',          'href' => 'reports/profit-loss'],
                                ['perm' => 'invoice.view',  'label' => 'Income vs Expense',      'href' => 'reports/income-vs-expense'],
                                ['perm' => 'task.view',     'label' => 'Task Summary',          'href' => 'reports/tasks'],
                                ['perm' => 'compliance.view', 'label' => 'Compliance Status',   'href' => 'reports/compliance'],
                                ['perm' => 'company.view',  'label' => 'Company Overview',      'href' => 'reports/companies'],
                                ['perm' => 'campaign.view', 'label' => 'Campaign Performance',  'href' => 'reports/campaigns'],
                            ];
                        ?>
                        <ul class="nav nav-treeview">
                            <?php foreach ($reportLinks as $rl): ?>
                                <?php if (can($rl['perm'])): ?>
                                <li class="nav-item">
                                    <a href="<?= site_url($rl['href']) ?>" class="nav-link">
                                        <i class="nav-icon fas fa-circle" style="font-size:.4rem"></i><p><?= esc($rl['label']) ?></p>
                                    </a>
                                </li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                    <?php endif; ?>

                    <?php if (can('user.view') || can('role.view')): ?>
                    <li class="nav-header">Administration</li>
                    <?php if (can('user.view')): ?>
                    <li class="nav-item">
                        <a href="<?= site_url('auth/users') ?>" class="nav-link <?= $nav === 'users' ? 'active' : '' ?>">
                            <i class="nav-icon fas fa-users-gear"></i><p>Users</p>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (can('role.view')): ?>
                    <li class="nav-item">
                        <a href="<?= site_url('auth/roles') ?>" class="nav-link <?= $nav === 'roles' ? 'active' : '' ?>">
                            <i class="nav-icon fas fa-shield-halved"></i><p>Roles &amp; Permissions</p>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php endif; ?>

                    <li class="nav-header">Help</li>
                    <li class="nav-item">
                        <a href="<?= site_url('help') ?>" class="nav-link <?= $nav === 'help' ? 'active' : '' ?>">
                            <i class="nav-icon fas fa-circle-question"></i><p>Support</p>
                        </a>
                    </li>

                </ul>
            </nav>
        </div>
    </aside>
    <script>
    // Every nav click is a full page load, which resets the sidebar's own
    // scroll to the top — so remember where it was and put it back
    // immediately (inline, right after the sidebar renders, so there's no
    // visible jump). With nothing saved yet, bring the active item into
    // view instead.
    (function () {
        var side = document.querySelector('.app-sidebar');
        if (! side) { return; }
        var KEY = 'sy-sidebar-scroll';
        var saved = null;
        try { saved = sessionStorage.getItem(KEY); } catch (e) { }
        if (saved !== null) {
            side.scrollTop = parseInt(saved, 10) || 0;
        } else {
            var active = side.querySelector('.nav-link.active');
            if (active) { side.scrollTop = Math.max(0, active.offsetTop - side.clientHeight / 2); }
        }
        var save = function () { try { sessionStorage.setItem(KEY, side.scrollTop); } catch (e) { } };
        side.addEventListener('click', save, true);
        window.addEventListener('pagehide', save);
    })();
    </script>

    <div class="app-main-column">
        <!-- Top navbar -->
        <nav class="app-header navbar navbar-expand bg-body">
            <div class="container-fluid">
                <ul class="navbar-nav">
                    <li class="nav-item"><a class="nav-link" data-lte-toggle="sidebar" href="#"><i class="fas fa-bars"></i></a></li>
                </ul>
                <?php if (session('roleSlug') === 'super_admin'): ?>
                <?php $activeCompanyId = session('active_company_id'); ?>
                <form action="<?= site_url('company-switch') ?>" method="post" class="filter-form ms-3 my-auto">
                    <?= csrf_field() ?>
                    <span class="sy-company-chip">
                        <span class="sy-company-dot"><i class="fas fa-building"></i></span>
                        <select name="company_id" class="form-select form-select-sm" title="Viewing company">
                            <option value="" <?= $activeCompanyId === 'all' ? 'selected' : '' ?>>All Companies</option>
                            <?php foreach (db_connect()->table('companies')->select('id, name')->orderBy('name', 'ASC')->get()->getResultArray() as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= ((string) $activeCompanyId === (string) $c['id']) ? 'selected' : '' ?>><?= esc($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </span>
                </form>
                <?php elseif (in_array(session('roleSlug'), ['admin', 'manager', 'accountant', 'hr', 'compliance_officer', 'employee'], true)): ?>
                <?php
                    // Every other role is pinned to their own company
                    // (see AuthController::establishActiveCompany() /
                    // BaseController::companyScopeFor()) — shown
                    // read-only here rather than a switcher, since they
                    // can't change it.
                    $myCompanyName = db_connect()->table('employee_profiles')
                        ->select('companies.name')
                        ->join('companies', 'companies.id = employee_profiles.company_id')
                        ->where('employee_profiles.user_id', session('userId'))
                        ->get()->getRow('name');
                ?>
                <span class="sy-company-chip ms-3 my-auto">
                    <span class="sy-company-dot"><i class="fas fa-building"></i></span>
                    <?= esc($myCompanyName ?? 'No company assigned') ?>
                </span>
                <?php endif; ?>
                <div class="sy-search ms-3 my-auto">
                    <i class="fas fa-magnifying-glass"></i>
                    <input type="text" id="globalSearchInput" autocomplete="off" placeholder="Search employees, companies, tasks…">
                    <div id="globalSearchResults" class="sy-search-results d-none"></div>
                </div>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link position-relative" data-bs-toggle="dropdown" href="#" id="notifBellToggle">
                            <i class="fas fa-bell"></i>
                            <span id="notifBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none" style="font-size:.6rem;">0</span>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end p-0" style="width: 340px; max-height: 420px; overflow-y: auto;" aria-labelledby="notifBellToggle">
                            <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                                <span class="fw-semibold small">Notifications</span>
                                <a href="<?= site_url('notifications') ?>" class="small">View all</a>
                            </div>
                            <div id="notifList" class="list-group list-group-flush">
                                <div class="text-muted small px-3 py-3">Loading…</div>
                            </div>
                        </div>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" data-bs-toggle="dropdown" href="#">
                            <?php if ($avatarPath = session('avatarPath')): ?>
                                <img class="sy-avatar-img" src="<?= base_url($avatarPath) ?>" alt="">
                            <?php else: ?>
                                <span class="sy-avatar"><?= esc(mb_strtoupper(mb_substr((string) session('userName'), 0, 1))) ?></span>
                            <?php endif; ?>
                            <?= esc(session('userName')) ?>
                            <span class="badge sy-role-badge ms-1"><?= esc(session('roleName')) ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="<?= site_url('auth/profile') ?>"><i class="fas fa-id-badge me-2"></i>Profile</a></li>
                            <?php if (ENVIRONMENT === 'development' && session('roleSlug') === 'super_admin'): ?>
                            <li><a class="dropdown-item" href="<?= site_url('auth/switch-profile') ?>"><i class="fas fa-user-group me-2"></i>Switch Profile</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?= site_url('auth/logout') ?>"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- Main content -->
        <main class="app-main">
        <div class="app-content-header">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-sm-6"><h3 class="mb-0"><?= esc($title ?? '') ?></h3></div>
                    <div class="col-sm-6 text-sm-end">
                        <?= $this->renderSection('pageActions') ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="app-content">
            <div class="container-fluid">
                <?php if (session()->getFlashdata('success')): ?>
                    <div class="alert alert-success alert-dismissible"><?= esc(session()->getFlashdata('success')) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                <?php endif; ?>
                <?php if (session()->getFlashdata('error')): ?>
                    <div class="alert alert-danger alert-dismissible"><?= esc(session()->getFlashdata('error')) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                <?php endif; ?>
                <?php if (session()->getFlashdata('errors')): ?>
                    <div class="alert alert-danger alert-dismissible">
                        <ul class="mb-0">
                            <?php foreach (session()->getFlashdata('errors') as $err): ?>
                                <li><?= esc($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?= $this->renderSection('content') ?>
            </div>
        </div>
    </main>

    <footer class="app-footer">
        <div class="float-end small text-muted d-none d-sm-inline">Sayeon</div>
        <strong class="small text-muted">&copy; <?= date('Y') ?> Sanvima Solutions Group.</strong>
    </footer>
    </div>
</div>

<!-- Floating "Back to top" — scrolls .app-main (the actual scroll
     container; window/document never scroll in this layout), shown only
     once the user has scrolled down a bit. See the script block below
     for the show/hide + click behavior. -->
<button type="button" id="backToTop" class="sy-back-to-top" aria-label="Back to top">
    <i class="fas fa-arrow-up"></i>
</button>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta3/dist/js/adminlte.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.11/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.11/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const badge = document.getElementById('notifBadge');
    const list = document.getElementById('notifList');
    const toggle = document.getElementById('notifBellToggle');
    if (! badge || ! list || ! toggle) return;

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function render(data) {
        if (data.unreadCount > 0) {
            badge.textContent = data.unreadCount > 99 ? '99+' : data.unreadCount;
            badge.classList.remove('d-none');
        } else {
            badge.classList.add('d-none');
        }

        if (! data.notifications.length) {
            list.innerHTML = '<div class="text-muted small px-3 py-3">You\'re all caught up.</div>';
            return;
        }

        list.innerHTML = data.notifications.map(function (n) {
            const titleHtml = n.link
                ? '<a href="' + n.link + '" class="text-decoration-none">' + escapeHtml(n.title) + '</a>'
                : escapeHtml(n.title);
            return '<div class="list-group-item px-3 py-2 ' + (n.is_read ? '' : 'bg-body-secondary') + '">' +
                '<div class="small fw-semibold">' + titleHtml + '</div>' +
                '<div class="small text-muted">' + escapeHtml(n.message) + '</div>' +
                '</div>';
        }).join('');
    }

    function load() {
        fetch('<?= site_url('notifications/recent') ?>', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(render)
            .catch(function () { list.innerHTML = '<div class="text-muted small px-3 py-3">Couldn\'t load notifications.</div>'; });
    }

    load();
    toggle.addEventListener('click', load);
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const input   = document.getElementById('globalSearchInput');
    const results = document.getElementById('globalSearchResults');
    if (! input || ! results) return;

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    let timer = null;

    function render(data) {
        if (! data.results.length) {
            results.innerHTML = '<div class="sy-sr-empty">No matches.</div>';
            results.classList.remove('d-none');
            return;
        }

        const byCategory = {};
        data.results.forEach(function (r) {
            (byCategory[r.category] = byCategory[r.category] || []).push(r);
        });

        results.innerHTML = Object.keys(byCategory).map(function (cat) {
            return '<div class="sy-sr-cat">' + escapeHtml(cat) + '</div>' +
                byCategory[cat].map(function (r) {
                    return '<a href="' + r.link + '" class="sy-sr-item">' +
                        '<i class="fas ' + r.icon + '"></i>' +
                        '<span>' + escapeHtml(r.label) + '</span>' +
                        '<span class="sy-sr-sub">' + escapeHtml(r.sublabel) + '</span>' +
                        '</a>';
                }).join('');
        }).join('');
        results.classList.remove('d-none');
    }

    input.addEventListener('input', function () {
        const q = input.value.trim();
        clearTimeout(timer);

        if (q.length < 2) {
            results.classList.add('d-none');
            return;
        }

        timer = setTimeout(function () {
            fetch('<?= site_url('search/live') ?>?q=' + encodeURIComponent(q), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json(); })
                .then(render)
                .catch(function () {
                    results.innerHTML = '<div class="sy-sr-empty">Search failed.</div>';
                    results.classList.remove('d-none');
                });
        }, 250);
    });

    document.addEventListener('click', function (e) {
        if (! e.target.closest('.sy-search')) {
            results.classList.add('d-none');
        }
    });
});
</script>
<script>
document.addEventListener('change', function (e) {
    const field = e.target;
    // :not(.sy-msel-opt) — a multi-select filter's checkboxes must NOT
    // auto-submit on every single click (picking 3 values would reload
    // the page 3 times); their own script below submits once via an
    // explicit Apply button instead.
    if (field.matches('select, input[type="date"], input[type="checkbox"]:not(.sy-msel-opt), input[type="radio"]') && field.closest('form.filter-form')) {
        field.closest('form.filter-form').submit();
    }
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Keeps each multi-select filter's toggle button showing what's
    // picked ("All Status" / "Pending" / "3 selected") — see the
    // .sy-msel-* CSS in sayeon-theme.css for the markup this expects.
    document.querySelectorAll('.sy-msel').forEach(function (wrap) {
        const labelEl = wrap.querySelector('.sy-msel-label');
        const placeholder = wrap.dataset.placeholder || 'All';
        if (! labelEl) return;

        function refresh() {
            const checked = Array.from(wrap.querySelectorAll('.sy-msel-opt:checked'));
            if (checked.length === 0) {
                labelEl.textContent = placeholder;
            } else if (checked.length === 1) {
                labelEl.textContent = checked[0].closest('.sy-msel-item').dataset.label || placeholder;
            } else {
                labelEl.textContent = checked.length + ' selected';
            }
        }

        wrap.querySelectorAll('.sy-msel-opt').forEach(function (cb) {
            cb.addEventListener('change', refresh);
        });
        refresh();
    });
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Tab actions (add checklist item, post a comment, upload an
    // attachment, ...) are plain server-side form POSTs that redirect
    // back to the same page — a full reload, which would otherwise
    // always land back on whichever tab is hardcoded "active" in the
    // markup. Controllers that want a specific tab to stay open append
    // its id as a URL fragment (e.g. #tab-checklist) to the redirect;
    // this activates that tab once the page loads.
    if (location.hash) {
        var trigger = document.querySelector('[data-bs-toggle="tab"][data-bs-target="' + location.hash + '"]');
        if (trigger && window.bootstrap) {
            bootstrap.Tab.getOrCreateInstance(trigger).show();
        }
    }
});
</script>
<script>
// "Back to top" — .app-main is the one element that actually scrolls in
// this layout (see the .app-wrapper/.app-main comments above), so that's
// what's listened to and scrolled, not window.
(function () {
    var main = document.querySelector('.app-main');
    var btn  = document.getElementById('backToTop');
    if (! main || ! btn) { return; }

    var SHOW_AFTER = 300;
    var toggle = function () {
        btn.classList.toggle('show', main.scrollTop > SHOW_AFTER);
    };
    main.addEventListener('scroll', toggle, { passive: true });
    toggle();

    btn.addEventListener('click', function () {
        main.scrollTo({ top: 0, behavior: 'smooth' });
    });
})();
</script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
