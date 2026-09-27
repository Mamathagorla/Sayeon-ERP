<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign In · Sayeon</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.2/css/all.min.css">
    <style>
        :root {
            --navy: #242a33;
            --navy-deep: #1a1e25;
            /* Brand red (--teal / --teal-dark) and charcoal (--navy).
               The --teal names are kept because the rest of this page
               already builds on them. */
            --teal: #d62431;
            --teal-dark: #b81c28;
            --ink: #10243f;
            --muted: #6b7a90;
        }

        * { box-sizing: border-box; }

        body {
            background: #eef2f5;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px 16px;
            color: var(--ink);
        }

        .auth-shell {
            display: flex;
            width: 100%;
            max-width: 1080px;
            background: #fff;
            border-radius: 28px;
            box-shadow: 0 40px 80px rgba(8, 19, 36, .25);
            overflow: hidden;
        }

        /* ================= LEFT BRAND PANEL ================= */
        .brand-panel {
            flex: 0 0 46%;
            position: relative;
            background: linear-gradient(160deg, var(--navy) 0%, var(--navy-deep) 100%);
            color: #fff;
            padding: 44px 42px 32px;
            overflow: hidden;
        }

        .brand-wave {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            height: 140px;
            opacity: .5;
            pointer-events: none;
        }

        .brand-header {
            display: flex;
            align-items: center;
            gap: 14px;
            position: relative;
            z-index: 1;
        }

        .logo-mark {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--teal) 0%, var(--teal-dark) 100%);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 1.3rem;
            flex-shrink: 0;
        }

        .brand-word { line-height: 1.1; }
        .brand-word strong { font-size: 1.35rem; letter-spacing: 3px; font-weight: 700; display: block; }
        .brand-word span { font-size: .62rem; letter-spacing: 3px; color: #a4abb6; }

        .brand-tagline {
            position: relative;
            z-index: 1;
            font-size: 2rem;
            font-weight: 700;
            line-height: 1.25;
            margin: 30px 0 14px;
        }

        .brand-tagline .accent { color: var(--teal); }

        .brand-tagline-rule {
            width: 46px;
            height: 4px;
            background: var(--teal);
            border-radius: 2px;
            margin-bottom: 16px;
            position: relative;
            z-index: 1;
        }

        .brand-desc {
            position: relative;
            z-index: 1;
            color: #b9bfc9;
            font-size: .88rem;
            line-height: 1.6;
            max-width: 320px;
            margin-bottom: 12px;
        }

        /* -------- network diagram -------- */
        .network {
            position: relative;
            width: 260px;
            height: 250px;
            margin: 8px auto 6px;
            z-index: 1;
        }

        .network svg.links { position: absolute; inset: 0; width: 100%; height: 100%; }

        .node {
            position: absolute;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            transform: translate(-50%, -50%);
        }

        .node .tile {
            width: 34px;
            height: 34px;
            border-radius: 9px;
            background: linear-gradient(135deg, #363c47, #2a2f38);
            border: 1px solid rgba(255, 255, 255, .12);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--teal);
            font-size: .85rem;
        }

        .node .label {
            font-size: .58rem;
            letter-spacing: .3px;
            background: rgba(255, 255, 255, .08);
            padding: 2px 8px;
            border-radius: 10px;
            color: #dfe3e9;
            white-space: nowrap;
        }

        .node.n1 { left: 50%; top: 8%; }
        .node.n2 { left: 6%; top: 46%; }
        .node.n3 { left: 88%; top: 38%; }
        .node.n4 { left: 20%; top: 90%; }
        .node.n5 { left: 76%; top: 90%; }

        .hub {
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            width: 64px;
            height: 64px;
            border-radius: 16px;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0 0 6px rgba(214, 36, 49, .18), 0 0 24px rgba(214, 36, 49, .55);
            z-index: 2;
        }

        .hub span {
            font-weight: 800;
            font-size: 1.4rem;
            background: linear-gradient(135deg, var(--teal), var(--teal-dark));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        /* -------- feature row -------- */
        .brand-features {
            position: relative;
            z-index: 1;
            display: flex;
            justify-content: space-between;
            gap: 10px;
            margin-top: 14px;
            padding-top: 18px;
            border-top: 1px solid rgba(255, 255, 255, .1);
        }

        .feature { text-align: center; flex: 1 1 0; }
        .feature i { color: var(--teal); font-size: 1.1rem; margin-bottom: 6px; display: block; }
        .feature strong { display: block; font-size: .78rem; font-weight: 700; }
        .feature small { display: block; font-size: .62rem; color: #9aa1ad; line-height: 1.3; margin-top: 2px; }

        /* ================= RIGHT FORM PANEL ================= */
        .form-panel {
            flex: 1 1 auto;
            position: relative;
            padding: 46px 52px 30px;
            display: flex;
            flex-direction: column;
            background:
                radial-gradient(circle at 8px 8px, #fdeced 1.5px, transparent 1.5px) top right / 90px 90px no-repeat,
                #fff;
        }

        .form-logo {
            width: 62px;
            height: 62px;
            border-radius: 50%;
            background: #fff;
            box-shadow: 0 6px 18px rgba(214, 36, 49, .25);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
        }

        .form-logo .logo-mark { width: 42px; height: 42px; border-radius: 10px; }

        .form-title { text-align: center; font-weight: 700; color: var(--ink); margin-bottom: 4px; }
        .form-subtitle { text-align: center; color: var(--muted); font-size: .85rem; margin-bottom: 18px; }

        .dot-divider {
            width: 100%;
            max-width: 220px;
            height: 1px;
            background: #e6ebf0;
            margin: 0 auto 22px;
            position: relative;
        }

        .dot-divider::after {
            content: '';
            position: absolute;
            left: 50%;
            top: -3px;
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--teal);
            transform: translateX(-50%);
        }

        .field-label {
            font-size: .8rem;
            font-weight: 600;
            color: var(--ink);
            margin-bottom: 6px;
            display: block;
        }

        .field-box {
            display: flex;
            align-items: center;
            gap: 10px;
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            padding: 11px 14px;
            margin-bottom: 18px;
            transition: border-color .15s ease;
        }

        .field-box:focus-within { border-color: var(--teal); }
        .field-box i, .field-box .fa-building { color: var(--muted); width: 16px; text-align: center; }

        .field-box input, .field-box select {
            border: none;
            outline: none;
            flex: 1 1 auto;
            font-size: .92rem;
            background: transparent;
            color: var(--ink);
            appearance: none;
        }

        .field-box .toggle-eye { cursor: pointer; color: var(--muted); }

        .form-row-single {
            display: flex;
            justify-content: flex-end;
            margin: -8px 0 18px;
        }

        .forgot-link { font-size: .8rem; color: var(--teal-dark); font-weight: 600; }

        .btn-signin {
            background: linear-gradient(135deg, var(--teal) 0%, var(--teal-dark) 100%);
            color: #fff;
            border: none;
            border-radius: 12px;
            padding: 13px;
            font-weight: 700;
            letter-spacing: .5px;
            box-shadow: 0 10px 22px rgba(214, 36, 49, .3);
            width: 100%;
        }

        .btn-signin:hover { color: #fff; filter: brightness(1.04); }

        .skyline { margin-top: auto; opacity: .9; }

        .copyright {
            text-align: center;
            font-size: .74rem;
            color: var(--muted);
            margin: 14px 0 0;
        }

        @media (max-width: 860px) {
            .brand-panel { display: none; }
            .auth-shell { max-width: 440px; }
            .form-panel { padding: 40px 30px 26px; }
        }
    </style>
</head>
<body>
<div class="auth-shell">

    <div class="brand-panel">
        <div class="brand-header">
            <div class="logo-mark">S</div>
            <div class="brand-word"><strong>SAYEON</strong><span>ERP SOLUTIONS</span></div>
        </div>

        <h1 class="brand-tagline">One Platform.<br>Every <span class="accent">Business.</span></h1>
        <div class="brand-tagline-rule"></div>
        <p class="brand-desc">Sayeon ERP empowers you to manage multiple companies, streamline operations and grow your business – all in one place.</p>

        <div class="network">
            <svg class="links" viewBox="0 0 260 250">
                <g stroke="#d62431" stroke-width="1.4" stroke-dasharray="4 4" opacity=".55">
                    <line x1="130" y1="125" x2="130" y2="30"></line>
                    <line x1="130" y1="125" x2="24" y2="115"></line>
                    <line x1="130" y1="125" x2="228" y2="95"></line>
                    <line x1="130" y1="125" x2="60" y2="225"></line>
                    <line x1="130" y1="125" x2="200" y2="225"></line>
                </g>
            </svg>

            <div class="node n1"><div class="tile"><i class="fas fa-building"></i></div><div class="label">Company A</div></div>
            <div class="node n2"><div class="tile"><i class="fas fa-building"></i></div><div class="label">Company B</div></div>
            <div class="node n3"><div class="tile"><i class="fas fa-building"></i></div><div class="label">Company C</div></div>
            <div class="node n4"><div class="tile"><i class="fas fa-building"></i></div><div class="label">Company D</div></div>
            <div class="node n5"><div class="tile"><i class="fas fa-building"></i></div><div class="label">Company E</div></div>

            <div class="hub"><span>S</span></div>
        </div>

        <div class="brand-features">
            <div class="feature">
                <i class="fas fa-shield-halved"></i>
                <strong>Secure</strong>
                <small>Enterprise grade security</small>
            </div>
            <div class="feature">
                <i class="fas fa-chart-line"></i>
                <strong>Scalable</strong>
                <small>Built to grow with your business</small>
            </div>
            <div class="feature">
                <i class="fas fa-people-group"></i>
                <strong>Connected</strong>
                <small>All your companies. One ecosystem.</small>
            </div>
        </div>
    </div>

    <div class="form-panel">
        <div class="form-logo"><div class="logo-mark">S</div></div>
        <h2 class="form-title">Welcome back!</h2>
        <p class="form-subtitle">Sign in to access your Sayeon ERP account</p>
        <div class="dot-divider"></div>

        <?php if (session()->getFlashdata('error')): ?>
            <div class="alert alert-danger py-2 small"><?= esc(session()->getFlashdata('error')) ?></div>
        <?php endif; ?>
        <?php if (session()->getFlashdata('errors')): ?>
            <div class="alert alert-danger py-2">
                <ul class="mb-0 small">
                    <?php foreach (session()->getFlashdata('errors') as $err): ?><li><?= esc($err) ?></li><?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form action="<?= site_url('auth/attempt-login') ?>" method="post">
            <?= csrf_field() ?>

            <label class="field-label">Email / Username</label>
            <div class="field-box">
                <i class="fas fa-user"></i>
                <input type="email" name="email" placeholder="Enter your email or username" value="<?= esc(old('email')) ?>" required autofocus>
            </div>

            <label class="field-label">Password</label>
            <div class="field-box">
                <i class="fas fa-lock"></i>
                <input type="password" name="password" id="passwordField" placeholder="Enter your password" required>
                <i class="fas fa-eye toggle-eye" id="togglePassword"></i>
            </div>

            <div class="form-row-single">
                <span class="forgot-link">Forgot password?</span>
            </div>

            <button type="submit" class="btn-signin"><i class="fas fa-right-to-bracket me-2"></i>SIGN IN</button>
        </form>

        <div class="skyline">
            <svg viewBox="0 0 500 90" preserveAspectRatio="none" width="100%" height="70">
                <g fill="#d62431" opacity=".18">
                    <rect x="10" y="40" width="34" height="50"></rect>
                    <rect x="50" y="20" width="26" height="70"></rect>
                    <rect x="82" y="50" width="22" height="40"></rect>
                    <rect x="120" y="10" width="30" height="80"></rect>
                    <rect x="158" y="45" width="24" height="45"></rect>
                    <rect x="200" y="30" width="28" height="60"></rect>
                    <rect x="236" y="55" width="20" height="35"></rect>
                    <rect x="270" y="15" width="32" height="75"></rect>
                    <rect x="310" y="40" width="24" height="50"></rect>
                    <rect x="342" y="25" width="28" height="65"></rect>
                    <rect x="378" y="50" width="22" height="40"></rect>
                    <rect x="410" y="12" width="30" height="78"></rect>
                    <rect x="448" y="42" width="26" height="48"></rect>
                </g>
            </svg>
        </div>

        <p class="copyright">&copy; <?= date('Y') ?> Sayeon ERP. All rights reserved.</p>
    </div>
</div>

<script>
    document.getElementById('togglePassword').addEventListener('click', function () {
        const field = document.getElementById('passwordField');
        const isHidden = field.type === 'password';
        field.type = isHidden ? 'text' : 'password';
        this.classList.toggle('fa-eye');
        this.classList.toggle('fa-eye-slash');
    });
</script>
</body>
</html>
