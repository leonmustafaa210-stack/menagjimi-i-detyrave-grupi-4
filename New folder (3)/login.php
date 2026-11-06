<?php
session_start();

function loadUsers()
{
    $path = __DIR__ . '/users.json';

    if (!file_exists($path)) {
        $defaultUsers = [
            [
                'id' => 1,
                'name' => 'Admin',
                'email' => 'admin@admin.com',
                'password' => 'admin123',
                'role' => 'admin'
            ],
            [
                'id' => 2,
                'name' => 'Test User',
                'email' => 'user@demo.com',
                'password' => 'demo123',
                'role' => 'member'
            ]
        ];

        file_put_contents($path, json_encode($defaultUsers, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $defaultUsers;
    }

    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

if (isset($_SESSION['user'])) {
    header('Location: Dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = trim($_POST['password'] ?? '');

    $users = loadUsers();

    foreach ($users as $user) {
        if (strtolower($user['email']) === $email && $user['password'] === $password) {
            $_SESSION['user'] = $user;
            header('Location: Dashboard.php');
            exit;
        }
    }

    $error = 'Email ose password janë të pasakta.';
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | TaskFlow</title>
    <style>
        :root {
            --bg: #f4efe9;
            --panel: rgba(255,255,255,0.86);
            --panel-strong: #fffdf9;
            --line: rgba(34, 30, 27, 0.12);
            --ink: #1d1a17;
            --muted: #5d564f;
            --soft: #f7efe7;
            --accent: #2a221d;
            --accent-2: #b57d5a;
            --success: #1d8a63;
            --danger: #c94845;
            --shadow: 0 28px 70px rgba(34, 26, 21, 0.12);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, #f6f1eb 0%, #f4e7dd 100%);
            font-family: Arial, sans-serif;
            color: var(--ink);
        }

        .page-shell {
            width: min(1120px, calc(100vw - 32px));
            min-height: 700px;
            display: grid;
            grid-template-columns: 1.05fr 0.95fr;
            border-radius: 30px;
            overflow: hidden;
            background: rgba(255,255,255,0.35);
            box-shadow: var(--shadow);
            border: 1px solid rgba(34,30,27,0.08);
        }

        .welcome-panel {
            position: relative;
            padding: 42px 42px 30px;
            background: linear-gradient(135deg, #fbf9f7 0%, #f1e8df 100%);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .brand-mark {
            width: 32px;
            height: 32px;
            border-radius: 10px;
            display: grid;
            place-items: center;
            background: var(--accent);
            color: #fff;
            font-size: 0.95rem;
        }

        .welcome-copy {
            max-width: 430px;
            margin-top: 48px;
        }

        .eyebrow {
            margin: 0 0 16px;
            font-size: 0.72rem;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: rgba(29,26,23,0.7);
            font-weight: 700;
        }

        .welcome-copy h1 {
            margin: 0;
            font-size: clamp(2.7rem, 4vw, 5rem);
            line-height: 0.9;
            letter-spacing: -0.06em;
        }

        .welcome-description {
            margin-top: 18px;
            max-width: 360px;
            font-size: 1.06rem;
            line-height: 1.7;
            color: var(--muted);
        }

        .welcome-note {
            display: flex;
            align-items: center;
            gap: 18px;
            margin-top: 18px;
        }

        .note-line {
            width: 62px;
            height: 2px;
            background: rgba(29,26,23,0.7);
            border-radius: 999px;
        }

        .welcome-note p {
            margin: 0;
            line-height: 1.6;
            color: rgba(29,26,23,0.9);
        }

        .form-panel {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px 40px;
            background: rgba(255,255,255,0.55);
        }

        .form-wrap {
            width: min(100%, 440px);
        }

        .form-heading h2 {
            margin: 0;
            font-size: clamp(2.1rem, 3vw, 3rem);
            letter-spacing: -0.05em;
        }

        .form-intro {
            margin-top: 12px;
            color: var(--muted);
            line-height: 1.7;
        }

        .auth-form {
            margin-top: 28px;
            display: grid;
            gap: 18px;
        }

        .field {
            display: grid;
            gap: 8px;
        }

        label {
            font-size: 0.88rem;
            font-weight: 700;
            color: #2f2925;
        }

        input {
            width: 100%;
            padding: 14px 16px;
            border-radius: 14px;
            border: 1px solid var(--line);
            background: rgba(255,255,255,0.82);
            font-size: 1rem;
            color: var(--ink);
            outline: none;
            transition: 150ms ease;
        }

        input:focus {
            border-color: rgba(181,125,90,0.8);
            box-shadow: 0 0 0 4px rgba(181,125,90,0.12);
        }

        .btn {
            border: none;
            border-radius: 14px;
            background: linear-gradient(135deg, #1f1d1b 0%, #41352e 100%);
            color: #fff;
            font-weight: 700;
            font-size: 1rem;
            padding: 15px 18px;
            cursor: pointer;
            transition: transform 0.2s ease, opacity 0.2s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .error {
            padding: 12px 14px;
            border-radius: 12px;
            background: rgba(201, 72, 69, 0.08);
            border: 1px solid rgba(201,72,69,0.12);
            color: var(--danger);
            font-size: 0.95rem;
        }

        .demo-box {
            margin-top: 22px;
            padding: 14px 16px;
            border-radius: 12px;
            background: var(--soft);
            border: 1px solid rgba(34,30,27,0.08);
            color: var(--muted);
            line-height: 1.7;
            font-size: 0.96rem;
        }

        @media (max-width: 860px) {
            .page-shell {
                grid-template-columns: 1fr;
            }

            .welcome-panel {
                padding-bottom: 20px;
            }

            .welcome-copy {
                margin-top: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="page-shell">
        <aside class="welcome-panel">
            <div class="brand">
                <span class="brand-mark">T</span>
                <span>TaskFlow</span>
            </div>

            <div class="welcome-copy">
                <p class="eyebrow">Menaxhimi i detyrave</p>
                <h1>Detyrat<br>në një vend.</h1>
                <p class="welcome-description">
                    Organizoni punët, ndiqni statusin e secilës detyrë dhe menaxhoni përdoruesit e sistemit në një dashboard modern dhe të thjeshtë.
                </p>
            </div>

            <div class="welcome-note">
                <span class="note-line"></span>
                <p><strong>Ky sistem</strong> ju ndihmon të jeni më të organizuar çdo ditë.</p>
            </div>
        </aside>

        <main class="form-panel">
            <div class="form-wrap">
                <div class="form-heading">
                    <h2>Mirë se vini</h2>
                    <p class="form-intro">Hyr në panelin e menaxhimit të detyrave.</p>
                </div>

                <form method="POST" class="auth-form">
                    <div class="field">
                        <label for="email">Email</label>
                        <input id="email" type="email" name="email" value="admin@admin.com" required>
                    </div>

                    <div class="field">
                        <label for="password">Password</label>
                        <input id="password" type="password" name="password" value="admin123" required>
                    </div>

                    <?php if ($error): ?>
                        <div class="error"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <button class="btn" type="submit">Kyçu në sistem</button>
                </form>

                <div class="demo-box">
                    <strong>Demo kredencialet:</strong><br>
                    Admin: admin@admin.com / admin123<br>
                    Përdorues: user@demo.com / demo123
                </div>
            </div>
        </main>
    </div>
</body>
</html>
