<?php
session_start();

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

function loadTasks()
{
    $path = __DIR__ . '/tasks.json';

    if (!file_exists($path)) {
        $defaultTasks = [
            [
                'id' => 1,
                'title' => 'Përditëso faqen e produktit',
                'description' => 'Rifresko përmbajtjen dhe informacionet bazë të faqes.',
                'status' => 'pending',
                'assignee' => 'admin@admin.com',
                'due_date' => date('Y-m-d', strtotime('+2 days'))
            ],
            [
                'id' => 2,
                'title' => 'Rishiko dokumentacionin',
                'description' => 'Kontrollo dokumentin e projektit dhe përditëso detaje të nevojshme.',
                'status' => 'in_progress',
                'assignee' => 'user@demo.com',
                'due_date' => date('Y-m-d', strtotime('+5 days'))
            ],
            [
                'id' => 3,
                'title' => 'Raportimi i javës',
                'description' => 'Përmbledh arritjet dhe sfidat e javës së kaluar.',
                'status' => 'completed',
                'assignee' => 'admin@admin.com',
                'due_date' => date('Y-m-d', strtotime('-1 day'))
            ]
        ];

        file_put_contents($path, json_encode($defaultTasks, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $defaultTasks;
    }

    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

function saveTasks($tasks)
{
    file_put_contents(__DIR__ . '/tasks.json', json_encode($tasks, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function getTaskStats($tasks)
{
    $stats = [
        'total' => count($tasks),
        'pending' => 0,
        'in_progress' => 0,
        'completed' => 0
    ];

    foreach ($tasks as $task) {
        $status = $task['status'] ?? 'pending';

        if ($status === 'completed') {
            $stats['completed']++;
        } elseif ($status === 'in_progress') {
            $stats['in_progress']++;
        } else {
            $stats['pending']++;
        }
    }

    return $stats;
}

$user = $_SESSION['user'];
$tasks = loadTasks();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_task') {
        $title = trim($_POST['title'] ?? '');
        if ($title !== '') {
            $tasks[] = [
                'id' => time() + rand(1000, 9999),
                'title' => $title,
                'description' => trim($_POST['description'] ?? ''),
                'status' => in_array($_POST['status'] ?? 'pending', ['pending', 'in_progress', 'completed'], true) ? $_POST['status'] : 'pending',
                'assignee' => trim($_POST['assignee'] ?? '') ?: $user['email'],
                'due_date' => $_POST['due_date'] ?? date('Y-m-d')
            ];
            saveTasks($tasks);
        }
    }

    if ($action === 'delete_task') {
        $id = (int)($_POST['id'] ?? 0);
        $tasks = array_values(array_filter($tasks, fn($task) => (int)$task['id'] !== $id));
        saveTasks($tasks);
    }

    if ($action === 'change_status') {
        $id = (int)($_POST['id'] ?? 0);
        $newStatus = $_POST['status'] ?? 'pending';

        foreach ($tasks as &$task) {
            if ((int)$task['id'] === $id) {
                $task['status'] = in_array($newStatus, ['pending', 'in_progress', 'completed'], true) ? $newStatus : 'pending';
                break;
            }
        }

        saveTasks($tasks);
    }

    header('Location: Dashboard.php');
    exit;
}

$filter = $_GET['filter'] ?? 'all';
if ($filter !== 'all') {
    $tasks = array_values(array_filter($tasks, fn($task) => ($task['status'] ?? 'pending') === $filter));
}

$stats = getTaskStats(loadTasks());
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | TaskFlow</title>
    <style>
        :root {
            --bg: #f4efe9;
            --panel: rgba(255,255,255,0.75);
            --line: rgba(34,30,27,0.1);
            --ink: #1d1a17;
            --muted: #5d564f;
            --soft: #f7efe7;
            --accent: #1d1a17;
            --accent-soft: #efe1d6;
            --success: #1d8a63;
            --warning: #b9782d;
            --info: #2b66c8;
            --danger: #c94845;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #f7f3ee 0%, #efe4d9 100%);
            color: var(--ink);
        }

        .dashboard-shell {
            max-width: 1220px;
            margin: 32px auto;
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
            box-shadow: 0 18px 40px rgba(25, 20, 16, 0.04);
            margin-bottom: 26px;
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
            color: #fff;
        }

        .top-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .nav {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .nav a {
            color: #423b36;
            text-decoration: none;
            font-weight: 600;
            padding: 10px 12px;
            border-radius: 10px;
        }

        .nav a.active,
        .nav a:hover {
            background: rgba(29,26,23,0.06);
        }

        .user-pill {
            padding: 10px 14px;
            border-radius: 999px;
            background: var(--soft);
            color: #413a35;
            font-weight: 600;
        }

        .logout {
            text-decoration: none;
            background: var(--accent);
            color: #fff;
            padding: 10px 16px;
            border-radius: 10px;
            font-weight: 700;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: rgba(255,255,255,0.75);
            border: 1px solid var(--line);
            border-radius: 20px;
            padding: 20px 18px;
            box-shadow: 0 14px 30px rgba(25,20,16,0.03);
        }

        .stat-label {
            margin-bottom: 8px;
            color: var(--muted);
            font-size: 0.9rem;
        }

        .stat-value {
            font-size: 2.1rem;
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

        .form-grid {
            display: grid;
            gap: 14px;
        }

        .field {
            display: grid;
            gap: 8px;
        }

        label {
            font-size: 0.9rem;
            font-weight: 700;
            color: #2f2925;
        }

        input, select, textarea {
            width: 100%;
            padding: 12px 14px;
            border-radius: 12px;
            border: 1px solid var(--line);
            background: rgba(255,255,255,0.9);
            font: inherit;
            color: var(--ink);
        }

        textarea {
            min-height: 96px;
            resize: vertical;
        }

        .btn {
            border: none;
            border-radius: 12px;
            padding: 13px 16px;
            background: var(--accent);
            color: #fff;
            font-weight: 700;
            cursor: pointer;
        }

        .filters {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }

        .filter-link {
            text-decoration: none;
            color: #3d3632;
            background: #f2eee9;
            padding: 8px 12px;
            border-radius: 999px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .filter-link.active {
            background: var(--accent);
            color: #fff;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            text-align: left;
            vertical-align: top;
            padding: 14px 10px;
            border-bottom: 1px solid rgba(34,30,27,0.08);
        }

        th {
            font-size: 0.78rem;
            letter-spacing: 0.09em;
            text-transform: uppercase;
            color: #5d564f;
        }

        .task-title {
            font-weight: 700;
            margin-bottom: 6px;
        }

        .task-desc {
            color: var(--muted);
            font-size: 0.92rem;
            line-height: 1.6;
        }

        .status-badge {
            display: inline-block;
            padding: 7px 10px;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .status-pending { background: #f7e6ca; color: var(--warning); }
        .status-in_progress { background: #dfeaff; color: var(--info); }
        .status-completed { background: #dff5ea; color: var(--success); }

        .tiny-btn {
            display: inline-block;
            background: transparent;
            border: 1px solid rgba(34,30,27,0.12);
            border-radius: 8px;
            padding: 8px 10px;
            cursor: pointer;
            color: var(--ink);
            font-weight: 600;
            text-decoration: none;
        }

        .empty {
            text-align: center;
            color: var(--muted);
            padding: 18px 0;
        }

        @media (max-width: 980px) {
            .layout {
                grid-template-columns: 1fr;
            }

            .stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 640px) {
            .topbar {
                flex-direction: column;
                align-items: flex-start;
            }

            .stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-shell">
        <header class="topbar">
            <div class="brand">
                <span class="brand-mark">T</span>
                <span>TaskFlow</span>
            </div>

            <div class="top-actions">
                <nav class="nav">
                    <a href="Dashboard.php" class="active">Dashboard</a>
                    <a href="users.php">Përdoruesit</a>
                </nav>
                <span class="user-pill">Përshëndetje, <?= htmlspecialchars($user['name']) ?></span>
                <a href="logout.php" class="logout">Dil</a>
            </div>
        </header>

        <section class="stats">
            <div class="stat-card">
                <div class="stat-label">Totali</div>
                <div class="stat-value"><?= $stats['total'] ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Pending</div>
                <div class="stat-value"><?= $stats['pending'] ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Në progres</div>
                <div class="stat-value"><?= $stats['in_progress'] ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Të kompletuara</div>
                <div class="stat-value"><?= $stats['completed'] ?></div>
            </div>
        </section>

        <section class="layout">
            <aside class="panel">
                <h3>Shto detyrë të re</h3>
                <form method="POST" class="form-grid">
                    <input type="hidden" name="action" value="add_task">

                    <div class="field">
                        <label for="title">Titulli</label>
                        <input id="title" name="title" type="text" placeholder="Titulli i detyrës" required>
                    </div>

                    <div class="field">
                        <label for="description">Përshkrimi</label>
                        <textarea id="description" name="description" placeholder="Shkruaj detajet e detyrës"></textarea>
                    </div>

                    <div class="field">
                        <label for="assignee">Përdoruesi</label>
                        <input id="assignee" name="assignee" type="text" placeholder="email@example.com">
                    </div>

                    <div class="field">
                        <label for="status">Statusi</label>
                        <select id="status" name="status">
                            <option value="pending">Pending</option>
                            <option value="in_progress">Në progres</option>
                            <option value="completed">Të kompletuara</option>
                        </select>
                    </div>

                    <div class="field">
                        <label for="due_date">Data e dorëzimit</label>
                        <input id="due_date" name="due_date" type="date" value="<?= date('Y-m-d', strtotime('+3 days')) ?>">
                    </div>

                    <button class="btn" type="submit">Shto detyrë</button>
                </form>
            </aside>

            <main class="panel">
                <h3>Lista e detyrave</h3>

                <div class="filters">
                    <a class="filter-link <?= $filter === 'all' ? 'active' : '' ?>" href="Dashboard.php?filter=all">Të gjitha</a>
                    <a class="filter-link <?= $filter === 'pending' ? 'active' : '' ?>" href="Dashboard.php?filter=pending">Pending</a>
                    <a class="filter-link <?= $filter === 'in_progress' ? 'active' : '' ?>" href="Dashboard.php?filter=in_progress">Në progres</a>
                    <a class="filter-link <?= $filter === 'completed' ? 'active' : '' ?>" href="Dashboard.php?filter=completed">Të kompletuara</a>
                </div>

                <?php if (empty($tasks)): ?>
                    <div class="empty">Nuk ka detyra për këtë filtrim.</div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Detyra</th>
                                <th>Përdorues</th>
                                <th>Data</th>
                                <th>Statusi</th>
                                <th>Veprime</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tasks as $task): ?>
                                <?php $status = $task['status'] ?? 'pending'; ?>
                                <tr>
                                    <td>
                                        <div class="task-title"><?= htmlspecialchars($task['title']) ?></div>
                                        <div class="task-desc"><?= htmlspecialchars($task['description'] ?: 'Pa përshkrim') ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($task['assignee']) ?></td>
                                    <td><?= htmlspecialchars($task['due_date']) ?></td>
                                    <td>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="change_status">
                                            <input type="hidden" name="id" value="<?= (int)$task['id'] ?>">
                                            <select name="status" onchange="this.form.submit()">
                                                <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                                                <option value="in_progress" <?= $status === 'in_progress' ? 'selected' : '' ?>>Në progres</option>
                                                <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Të kompletuara</option>
                                            </select>
                                        </form>
                                    </td>
                                    <td>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="delete_task">
                                            <input type="hidden" name="id" value="<?= (int)$task['id'] ?>">
                                            <button type="submit" class="tiny-btn">Fshi</button>
                                        </form>
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
