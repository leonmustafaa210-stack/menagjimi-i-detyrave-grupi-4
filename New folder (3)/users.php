<?php
session_start();

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

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

function saveUsers($users)
{
    file_put_contents(__DIR__ . '/users.json', json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function loadTasks()
{
    $path = __DIR__ . '/tasks.json';

    if (!file_exists($path)) {
        return [];
    }

    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

$currentUser = $_SESSION['user'];
$users = loadUsers();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_user') {
        $name = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $password = trim($_POST['password'] ?? '');

        if ($name !== '' && $email !== '' && $password !== '') {
            $exists = false;
            foreach ($users as $user) {
                if (strtolower($user['email']) === $email) {
                    $exists = true;
                    break;
                }
            }

            if (!$exists) {
                $users[] = [
                    'id' => time() + rand(1000, 9999),
                    'name' => $name,
                    'email' => $email,
                    'password' => $password,
                    'role' => 'member'
                ];
                saveUsers($users);
            }
        }
    }

    if ($action === 'delete_user') {
        $email = trim($_POST['email'] ?? '');
        if ($email !== '' && strtolower($email) !== strtolower($currentUser['email'])) {
            $users = array_values(array_filter($users, fn($user) => strtolower($user['email']) !== strtolower($email)));
            saveUsers($users);
        }
    }

    header('Location: users.php');
    exit;
}

$tasks = loadTasks();
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Përdoruesit | TaskFlow</title>
    <style>
        :root {
            --bg: #f4efe9;
            --panel: rgba(255,255,255,0.75);
            --line: rgba(34,30,27,0.1);
            --ink: #1d1a17;
            --muted: #5d564f;
            --soft: #f7efe7;
            --accent: #1d1a17;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: linear-gradient(135deg, #f7f3ee 0%, #efe4d9 100%);
            font-family: Arial, sans-serif;
            color: var(--ink);
        }

        .page {
            max-width: 1100px;
            margin: 34px auto;
            padding: 0 18px 36px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            padding: 18px 22px;
            border-radius: 22px;
            background: rgba(255,255,255,0.72);
            border: 1px solid var(--line);
            box-shadow: 0 18px 40px rgba(25,20,16,0.04);
            margin-bottom: 28px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .brand-mark {
            width: 34px;
            height: 34px;
            display: grid;
            place-items: center;
            border-radius: 10px;
            background: var(--accent);
            color: white;
        }

        .nav {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .nav a {
            text-decoration: none;
            color: #423b36;
            font-weight: 600;
            padding: 10px 12px;
            border-radius: 10px;
        }

        .nav a.active,
        .nav a:hover {
            background: rgba(29,26,23,0.06);
        }

        .logout {
            background: var(--accent);
            color: #fff;
            padding: 10px 16px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 700;
        }

        .layout {
            display: grid;
            grid-template-columns: 360px 1fr;
            gap: 22px;
        }

        .panel {
            background: rgba(255,255,255,0.72);
            border: 1px solid var(--line);
            border-radius: 22px;
            padding: 22px;
            box-shadow: 0 18px 40px rgba(25,20,16,0.04);
        }

        .panel h3 {
            margin: 0 0 18px;
            font-size: 1.3rem;
        }

        .field {
            display: grid;
            gap: 8px;
            margin-bottom: 14px;
        }

        label {
            font-size: 0.9rem;
            font-weight: 700;
            color: #2f2925;
        }

        input {
            width: 100%;
            padding: 12px 14px;
            border-radius: 12px;
            border: 1px solid var(--line);
            background: rgba(255,255,255,0.9);
            font: inherit;
            color: var(--ink);
        }

        .btn {
            width: 100%;
            border: none;
            border-radius: 12px;
            padding: 13px 16px;
            background: var(--accent);
            color: #fff;
            font-weight: 700;
            cursor: pointer;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 14px 10px;
            text-align: left;
            border-bottom: 1px solid rgba(34,30,27,0.08);
        }

        th {
            font-size: 0.78rem;
            letter-spacing: 0.09em;
            text-transform: uppercase;
            color: #5d564f;
        }

        .role {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 999px;
            background: #f3eee9;
            color: #403b37;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .tiny-btn {
            border: 1px solid rgba(34,30,27,0.12);
            background: transparent;
            border-radius: 8px;
            padding: 7px 10px;
            cursor: pointer;
            color: var(--ink);
            font-weight: 600;
        }

        .empty {
            padding: 12px 0;
            color: var(--muted);
        }

        @media (max-width: 900px) {
            .layout {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="page">
        <header class="topbar">
            <div class="brand">
                <span class="brand-mark">T</span>
                <span>TaskFlow</span>
            </div>

            <nav class="nav">
                <a href="Dashboard.php">Dashboard</a>
                <a href="users.php" class="active">Përdoruesit</a>
                <a href="logout.php" class="logout">Dil</a>
            </nav>
        </header>

        <section class="layout">
            <aside class="panel">
                <h3>Shto përdorues</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add_user">

                    <div class="field">
                        <label for="name">Emri</label>
                        <input id="name" name="name" type="text" placeholder="Emri i përdoruesit" required>
                    </div>

                    <div class="field">
                        <label for="email">Email</label>
                        <input id="email" name="email" type="email" placeholder="email@example.com" required>
                    </div>

                    <div class="field">
                        <label for="password">Password</label>
                        <input id="password" name="password" type="password" placeholder="Password" required>
                    </div>

                    <button type="submit" class="btn">Shto përdorues</button>
                </form>
            </aside>

            <main class="panel">
                <h3>Lista e përdoruesve</h3>

                <?php if (empty($users)): ?>
                    <div class="empty">Nuk ka përdorues.</div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Emri</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Detyra</th>
                                <th>Veprime</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $userItem): ?>
                                <?php
                                $taskCount = 0;
                                foreach ($tasks as $task) {
                                    if (strtolower((string)($task['assignee'] ?? '')) === strtolower($userItem['email'])) {
                                        $taskCount++;
                                    }
                                }
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($userItem['name']) ?></td>
                                    <td><?= htmlspecialchars($userItem['email']) ?></td>
                                    <td><span class="role"><?= htmlspecialchars($userItem['role']) ?></span></td>
                                    <td><?= $taskCount ?></td>
                                    <td>
                                        <?php if (strtolower($userItem['email']) !== strtolower($currentUser['email'])): ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action" value="delete_user">
                                                <input type="hidden" name="email" value="<?= htmlspecialchars($userItem['email']) ?>">
                                                <button type="submit" class="tiny-btn">Fshi</button>
                                            </form>
                                        <?php else: ?>
                                            <span style="color:#6f675f; font-size:0.85rem;">Aktual</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </main>
        </section>
    </div>
</body>
</html>
