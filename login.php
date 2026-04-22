<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/includes/db.php';

$mode = ($_GET['mode'] ?? $_POST['mode'] ?? 'user') === 'admin' ? 'admin' : 'user';
$rememberedEmail = trim((string) ($_COOKIE['smartcart_remember_email'] ?? ''));
$rememberedRole = ($_COOKIE['smartcart_remember_role'] ?? 'user') === 'admin' ? 'admin' : 'user';
$emailValue = '';
$toast = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode = ($_POST['mode'] ?? 'user') === 'admin' ? 'admin' : 'user';
    $emailValue = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $rememberMe = isset($_POST['remember_me']);

    if (!filter_var($emailValue, FILTER_VALIDATE_EMAIL) || $password === '') {
        $toast = ['type' => 'error', 'message' => 'Please enter a valid email and password.'];
    } else {
        if ($mode === 'admin') {
            $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND role = 'admin' LIMIT 1");
            $redirectTo = 'admin/dashboard.php';
        } else {
            $stmt = $conn->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
            $redirectTo = 'pages/index.php';
        }

        $stmt->execute([$emailValue]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, (string) ($user['password'] ?? ''))) {
            unset($_SESSION['user_id'], $_SESSION['admin_id']);

            if ($mode === 'admin') {
                $_SESSION['admin_id'] = (int) $user['id'];
            } else {
                $_SESSION['user_id'] = (int) $user['id'];
            }

            if ($rememberMe) {
                setcookie('smartcart_remember_email', $emailValue, time() + (86400 * 30), '/');
                setcookie('smartcart_remember_role', $mode, time() + (86400 * 30), '/');
            } else {
                setcookie('smartcart_remember_email', '', time() - 3600, '/');
                setcookie('smartcart_remember_role', '', time() - 3600, '/');
            }

            header('Location: ' . $redirectTo);
            exit();
        }

        $toast = [
            'type' => 'error',
            'message' => $mode === 'admin' ? 'Invalid admin credentials.' : 'Invalid email or password.',
        ];
    }
}

if ($emailValue === '' && $rememberedEmail !== '' && $rememberedRole === $mode) {
    $emailValue = $rememberedEmail;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartCart Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        :root { --orange:#ff7a00; --orange-soft:#ffb15c; --text:#f8fbff; --muted:rgba(248,251,255,.72); --dark-card:rgba(11,16,29,.78); --white:rgba(255,255,255,.92); --shadow:0 30px 80px rgba(7,12,20,.32);}
        * { box-sizing:border-box; }
        body { margin:0; min-height:100vh; font-family:"Segoe UI",Tahoma,Geneva,Verdana,sans-serif; color:var(--text); background:radial-gradient(circle at 20% 20%, rgba(255,122,0,.35), transparent 24%),radial-gradient(circle at 80% 10%, rgba(255,255,255,.28), transparent 18%),radial-gradient(circle at 80% 80%, rgba(255,122,0,.18), transparent 25%),linear-gradient(135deg,#0d121e 0%,#1a2437 45%,#ff7a00 140%); display:grid; place-items:center; padding:24px; overflow-x:hidden; }
        .auth-shell { width:min(1120px,100%); display:grid; grid-template-columns:1.05fr .95fr; border-radius:32px; overflow:hidden; box-shadow:var(--shadow); background:rgba(8,14,24,.4); backdrop-filter:blur(22px); border:1px solid rgba(255,255,255,.1); }
        .auth-showcase { padding:56px; background:linear-gradient(160deg, rgba(255,122,0,.16), rgba(255,255,255,.07)); }
        .brand-chip { display:inline-flex; align-items:center; gap:10px; padding:10px 16px; border-radius:999px; background:rgba(255,255,255,.08); border:1px solid rgba(255,255,255,.16); margin-bottom:28px; font-weight:700; letter-spacing:.04em; }
        .auth-showcase h1 { margin:0 0 18px; font-size:clamp(2.4rem, 4vw, 4.1rem); line-height:1.02; }
        .auth-showcase p { margin:0; max-width:540px; color:var(--muted); font-size:1.02rem; line-height:1.8; }
        .showcase-grid { display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:16px; margin-top:34px; }
        .showcase-card { padding:18px; border-radius:22px; background:rgba(255,255,255,.08); border:1px solid rgba(255,255,255,.13); backdrop-filter:blur(12px); }
        .showcase-card i { color:var(--orange-soft); margin-bottom:12px; }
        .showcase-card strong { display:block; margin-bottom:8px; }
        .showcase-card span { color:var(--muted); font-size:.95rem; }
        .auth-panel { padding:34px; background:rgba(255,255,255,.08); display:grid; align-content:center; }
        .login-card { position:relative; border-radius:28px; padding:28px; background:var(--white); color:#10203a; border:1px solid rgba(255,255,255,.42); box-shadow:0 24px 50px rgba(16,32,58,.18); overflow:hidden; }
        .login-card.admin-mode { background:var(--dark-card); color:#f7fbff; border-color:rgba(255,122,0,.28); }
        .segment { display:grid; grid-template-columns:repeat(2,1fr); gap:8px; padding:6px; border-radius:999px; background:rgba(16,32,58,.08); margin-bottom:22px; }
        .segment button { border:0; border-radius:999px; padding:12px 14px; font-weight:700; cursor:pointer; background:transparent; color:inherit; }
        .segment button.active { background:linear-gradient(135deg, #ff7a00, #ffae4c); color:#fff; }
        .login-badge { display:inline-flex; align-items:center; gap:8px; padding:8px 12px; border-radius:999px; background:rgba(255,122,0,.12); color:#a95000; font-size:.82rem; font-weight:700; margin-bottom:16px; }
        .login-card.admin-mode .login-badge { background:rgba(255,122,0,.16); color:#ffbd78; }
        h2 { margin:0; font-size:2rem; }
        .subtitle { margin:10px 0 24px; color:rgba(16,32,58,.68); line-height:1.7; }
        .login-card.admin-mode .subtitle { color:rgba(247,251,255,.72); }
        .form-grid { display:grid; gap:16px; }
        .field { position:relative; }
        .field input { width:100%; height:60px; border-radius:18px; border:1px solid rgba(16,32,58,.12); padding:24px 52px 12px 48px; background:rgba(255,255,255,.82); font-size:1rem; outline:none; }
        .login-card.admin-mode .field input { background:rgba(255,255,255,.07); color:#fff; border-color:rgba(255,255,255,.12); }
        .field label { position:absolute; left:48px; top:19px; color:rgba(16,32,58,.56); pointer-events:none; transition:.22s ease; }
        .login-card.admin-mode .field label { color:rgba(255,255,255,.58); }
        .field input:focus + label, .field input:not(:placeholder-shown) + label { top:10px; font-size:.74rem; letter-spacing:.05em; }
        .field .icon, .field .toggle-pass { position:absolute; top:50%; transform:translateY(-50%); color:rgba(16,32,58,.56); }
        .login-card.admin-mode .field .icon, .login-card.admin-mode .field .toggle-pass { color:rgba(255,255,255,.62); }
        .field .icon { left:18px; }
        .field .toggle-pass { right:18px; border:0; background:transparent; cursor:pointer; }
        .helper-row { display:flex; justify-content:space-between; gap:14px; align-items:center; flex-wrap:wrap; font-size:.95rem; }
        .helper-row a { text-decoration:none; color:#ff7a00; font-weight:700; }
        .submit-btn { height:58px; border:0; border-radius:18px; color:#fff; font-size:1rem; font-weight:800; cursor:pointer; background:linear-gradient(135deg, #ff7a00, #ffae4c); box-shadow:0 18px 34px rgba(255,122,0,.28); }
        .submit-btn.is-loading { opacity:.86; pointer-events:none; }
        .btn-spinner { display:none; width:18px; height:18px; border-radius:50%; border:2px solid rgba(255,255,255,.35); border-top-color:#fff; animation:spin .7s linear infinite; margin-left:8px; }
        .submit-btn.is-loading .btn-spinner { display:inline-block; }
        .social-strip { display:grid; grid-template-columns:repeat(3, 1fr); gap:12px; margin-top:18px; }
        .social-btn { display:inline-flex; justify-content:center; align-items:center; gap:8px; height:48px; border-radius:16px; text-decoration:none; color:inherit; border:1px solid rgba(16,32,58,.12); background:rgba(255,255,255,.7); font-weight:700; }
        .login-card.admin-mode .social-btn { background:rgba(255,255,255,.06); border-color:rgba(255,255,255,.12); }
        .footer-links { display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-top:22px; font-size:.92rem; }
        .footer-links a { color:inherit; text-decoration:none; opacity:.84; }
        .toast { position:fixed; top:24px; right:24px; min-width:280px; max-width:calc(100vw - 32px); padding:14px 18px; border-radius:16px; color:#fff; box-shadow:0 18px 34px rgba(10,14,22,.22); z-index:40; background:linear-gradient(135deg, #ff5a63, #ff7a00); }
        @keyframes spin { to { transform:rotate(360deg);} }
        @media (max-width:980px) { .auth-shell { grid-template-columns:1fr; } .auth-showcase { padding:34px 28px 12px; } }
        @media (max-width:640px) { body { padding:14px; } .auth-showcase, .auth-panel { padding:20px; } .login-card { padding:20px; } .showcase-grid, .social-strip { grid-template-columns:1fr; } }
    </style>
</head>
<body>
    <?php if ($toast): ?><div class="toast" id="loginToast"><?= htmlspecialchars($toast['message']) ?></div><?php endif; ?>
    <div class="auth-shell">
        <section class="auth-showcase">
            <div class="brand-chip"><i class="fa-solid fa-bag-shopping"></i><span>SMARTCART PREMIUM ACCESS</span></div>
            <h1>Modern access for shoppers and administrators.</h1>
            <p>Secure sign-in, premium storefront styling, smoother transitions, and an upgraded AI shopping experience built for your localhost eCommerce workflow.</p>
            <div class="showcase-grid">
                <div class="showcase-card"><i class="fa-solid fa-lock"></i><strong>Protected sessions</strong><span>Prepared statements, role checks, and clean redirects for both user and admin flows.</span></div>
                <div class="showcase-card"><i class="fa-solid fa-wand-magic-sparkles"></i><strong>Premium visuals</strong><span>Glassmorphism, animated gradient borders, floating labels, and polished feedback states.</span></div>
                <div class="showcase-card"><i class="fa-solid fa-user-shield"></i><strong>Admin control</strong><span>Dark orange admin styling with a dedicated security badge and protected dashboard redirect.</span></div>
                <div class="showcase-card"><i class="fa-solid fa-robot"></i><strong>AI-ready shop</strong><span>Natural-language product search, order tracking, and recommendation modules are built into the store.</span></div>
            </div>
        </section>
        <section class="auth-panel">
            <div class="login-card <?= $mode === 'admin' ? 'admin-mode' : '' ?>" id="loginCard">
                <div class="segment"><button type="button" class="<?= $mode === 'user' ? 'active' : '' ?>" data-mode-switch="user">User Login</button><button type="button" class="<?= $mode === 'admin' ? 'active' : '' ?>" data-mode-switch="admin">Admin Login</button></div>
                <div class="login-badge" id="modeBadge"><i class="fa-solid <?= $mode === 'admin' ? 'fa-user-shield' : 'fa-user' ?>"></i><span><?= $mode === 'admin' ? 'Admin access only' : 'Customer access portal' ?></span></div>
                <h2 id="modeTitle"><?= $mode === 'admin' ? 'Admin Sign In' : 'Welcome Back' ?></h2>
                <p class="subtitle" id="modeSubtitle"><?= $mode === 'admin' ? 'Continue to the control room with elevated access and dashboard tools.' : 'Login to shop, manage orders, track deliveries, and chat with the assistant.' ?></p>
                <form method="POST" id="loginForm">
                    <input type="hidden" name="mode" id="modeInput" value="<?= htmlspecialchars($mode) ?>">
                    <div class="form-grid">
                        <div class="field"><i class="fa-solid fa-envelope icon"></i><input type="email" name="email" id="email" placeholder=" " value="<?= htmlspecialchars($emailValue) ?>" required><label for="email">Email Address</label></div>
                        <div class="field"><i class="fa-solid fa-lock icon"></i><input type="password" name="password" id="password" placeholder=" " required><label for="password">Password</label><button type="button" class="toggle-pass" id="togglePassword" aria-label="Show password"><i class="fa-regular fa-eye"></i></button></div>
                        <div class="helper-row"><label><input type="checkbox" name="remember_me" value="1" <?= $emailValue !== '' ? 'checked' : '' ?>> Remember me</label><a href="#" onclick="return false;">Forgot password?</a></div>
                        <button type="submit" class="submit-btn" id="submitBtn"><span id="submitText"><?= $mode === 'admin' ? 'Secure Admin Login' : 'Login to Store' ?></span><span class="btn-spinner" aria-hidden="true"></span></button>
                    </div>
                </form>
                <div class="social-strip"><a href="#" class="social-btn" onclick="return false;"><i class="fa-brands fa-google"></i> Google</a><a href="#" class="social-btn" onclick="return false;"><i class="fa-brands fa-facebook-f"></i> Facebook</a><a href="#" class="social-btn" onclick="return false;"><i class="fa-brands fa-apple"></i> Apple</a></div>
                <div class="footer-links"><a href="pages/index.php">Back to Store</a><a href="pages/register.php">Create account</a></div>
            </div>
        </section>
    </div>
    <script>
        (function () {
            const loginCard = document.getElementById('loginCard');
            const modeInput = document.getElementById('modeInput');
            const modeTitle = document.getElementById('modeTitle');
            const modeSubtitle = document.getElementById('modeSubtitle');
            const modeBadge = document.getElementById('modeBadge');
            const submitText = document.getElementById('submitText');
            const switches = document.querySelectorAll('[data-mode-switch]');
            const passwordInput = document.getElementById('password');
            const passwordToggle = document.getElementById('togglePassword');
            const submitBtn = document.getElementById('submitBtn');
            const form = document.getElementById('loginForm');
            const toast = document.getElementById('loginToast');
            const modeMeta = { user:{ title:'Welcome Back', subtitle:'Login to shop, manage orders, track deliveries, and chat with the assistant.', badgeIcon:'fa-user', badgeText:'Customer access portal', submit:'Login to Store' }, admin:{ title:'Admin Sign In', subtitle:'Continue to the control room with elevated access and dashboard tools.', badgeIcon:'fa-user-shield', badgeText:'Admin access only', submit:'Secure Admin Login' } };
            function applyMode(mode) {
                const meta = modeMeta[mode];
                modeInput.value = mode;
                loginCard.classList.toggle('admin-mode', mode === 'admin');
                modeTitle.textContent = meta.title;
                modeSubtitle.textContent = meta.subtitle;
                modeBadge.innerHTML = '<i class="fa-solid ' + meta.badgeIcon + '"></i><span>' + meta.badgeText + '</span>';
                submitText.textContent = meta.submit;
                switches.forEach((button) => button.classList.toggle('active', button.getAttribute('data-mode-switch') === mode));
                const nextUrl = new URL(window.location.href);
                nextUrl.searchParams.set('mode', mode);
                window.history.replaceState({}, '', nextUrl);
            }
            switches.forEach((button) => button.addEventListener('click', () => applyMode(button.getAttribute('data-mode-switch'))));
            passwordToggle.addEventListener('click', () => {
                const isPassword = passwordInput.type === 'password';
                passwordInput.type = isPassword ? 'text' : 'password';
                passwordToggle.innerHTML = isPassword ? '<i class="fa-regular fa-eye-slash"></i>' : '<i class="fa-regular fa-eye"></i>';
            });
            form.addEventListener('submit', () => submitBtn.classList.add('is-loading'));
            if (toast) {
                window.setTimeout(() => {
                    toast.style.opacity = '0';
                    toast.style.transform = 'translateY(-10px)';
                    toast.style.transition = 'all .3s ease';
                    window.setTimeout(() => toast.remove(), 320);
                }, 2800);
            }
            applyMode(modeInput.value);
        }());
    </script>
</body>
</html>
