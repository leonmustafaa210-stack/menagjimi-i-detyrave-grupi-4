<?php
declare(strict_types=1);

// Përfundon një kërkesë JSON dhe mbyll ekzekutimin e API-së.
function respond(int $status, array $payload): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        http_response_code(500);
        echo '{"error":"Përgjigjja nuk mund të përgatitet."}';
        exit;
    }

    echo $json;
    exit;
}

// Kontrollon kolonat ekzistuese që migrimi të mos ekzekutohet dy herë.
function column_exists(PDO $pdo, string $table, string $column): bool
{
    foreach ($pdo->query("PRAGMA table_info({$table})")->fetchAll() as $item) {
        if ($item['name'] === $column) {
            return true;
        }
    }

    return false;
}

// Hap databazën, krijon skemën fillestare dhe migrimin për databazat e vjetra.
function connect_database(): PDO
{
    $databasePath = getenv('CLASSROOM_DATABASE');
    if ($databasePath === false || $databasePath === '') {
        $databasePath = __DIR__ . DIRECTORY_SEPARATOR . 'classroom.db';
    }

    $directory = dirname($databasePath);
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('Dosja për bazën e të dhënave nuk mund të krijohet.');
    }

    $pdo = new PDO('sqlite:' . $databasePath, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');

    // Schema.sql është skema e lexueshme; kolonat më poshtë shtohen për databazat ekzistuese.
    $schemaPath = __DIR__ . DIRECTORY_SEPARATOR . 'schema.sql';
    $schema = file_get_contents($schemaPath);
    if ($schema === false) {
        throw new RuntimeException('Skedari schema.sql nuk mund të lexohet.');
    }
    $pdo->exec($schema);

    // Ruaj klasat e krijuara para shtimit të llogarive dhe kodeve të klasave.
    if (!column_exists($pdo, 'courses', 'owner_id')) {
        $pdo->exec(
            'ALTER TABLE courses ADD COLUMN owner_id INTEGER REFERENCES users(id) ON DELETE SET NULL'
        );
    }
    if (!column_exists($pdo, 'courses', 'class_code')) {
        $pdo->exec('ALTER TABLE courses ADD COLUMN class_code TEXT');
    }

    // Krijo indekset pasi kolonat e reja të jenë të pranishme edhe në migrimet e vjetra.
    $pdo->exec(
        'CREATE UNIQUE INDEX IF NOT EXISTS courses_class_code_unique
         ON courses(class_code) WHERE class_code IS NOT NULL'
    );

    return $pdo;
}

// Lexon vetëm objekte JSON me madhësi të arsyeshme nga trupi i kërkesës.
function read_json(): array
{
    $body = file_get_contents('php://input');
    if ($body === false || strlen($body) > 65536) {
        respond(400, ['error' => 'Kërkesa është bosh ose shumë e madhe.']);
    }

    try {
        $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        respond(400, ['error' => 'Të dhënat JSON nuk janë të vlefshme.']);
    }

    if (!is_array($payload)) {
        respond(400, ['error' => 'Të dhënat duhet të jenë objekt JSON.']);
    }

    return $payload;
}

// Kontrollon që fushat tekstuale të jenë të sakta, jo bosh kur kërkohen dhe brenda kufirit.
function text_field(
    array $payload,
    string $field,
    string $label,
    int $maximum,
    bool $required
): string {
    $value = $payload[$field] ?? '';
    if (!is_string($value)) {
        respond(400, ['error' => "Fusha {$label} nuk është e vlefshme."]);
    }

    $value = trim($value);
    $length = preg_match_all('/./us', $value);
    if ($length === false) {
        respond(400, ['error' => "Fusha {$label} nuk është e vlefshme."]);
    }
    if ($required && $value === '') {
        respond(400, ['error' => "Plotësoni fushën: {$label}."]);
    }
    if ($length > $maximum) {
        respond(400, ['error' => "Fusha {$label} duhet të ketë maksimumi {$maximum} karaktere."]);
    }

    return $value;
}

// Siguron që përdoruesi të jetë i kyçur dhe, kur kërkohet, të ketë rolin e duhur.
function require_user(string $role = ''): array
{
    $user = $_SESSION['user'] ?? null;
    if (!is_array($user) || !isset($user['id'], $user['name'], $user['email'], $user['role'])) {
        respond(401, ['error' => 'Hyr në llogarinë tënde për të vazhduar.']);
    }
    if ($role !== '' && $user['role'] !== $role) {
        respond(403, ['error' => 'Ky veprim nuk lejohet për rolin tënd.']);
    }

    return $user;
}

// Mbron kërkesat që ndryshojnë të dhënat nga kërkesat e falsifikuara në shfletues.
function verify_csrf(): void
{
    $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $expected = $_SESSION['csrf_token'] ?? '';
    if (!is_string($provided) || !is_string($expected) || $expected === '' || !hash_equals($expected, $provided)) {
        respond(403, ['error' => 'Sesioni ka skaduar. Rifresko faqen dhe provo përsëri.']);
    }
}

// Gjen një klasë dhe të dhënat e mësuesit që e zotëron.
function find_course(PDO $pdo, int $courseId): ?array
{
    $query = $pdo->prepare(
        'SELECT c.id, c.name, u.name AS teacher, c.description, c.class_code, c.owner_id
         FROM courses c
         INNER JOIN users u ON u.id = c.owner_id
         WHERE c.id = ?'
    );
    $query->execute([$courseId]);
    $course = $query->fetch();

    return $course === false ? null : $course;
}

// Mësuesi sheh klasat e veta; nxënësi sheh vetëm klasat ku është anëtar.
function require_course_access(PDO $pdo, int $courseId, array $user): array
{
    $course = find_course($pdo, $courseId);
    if ($course === null) {
        respond(404, ['error' => 'Klasa nuk u gjet.']);
    }
    if ($user['role'] === 'teacher' && (int) $course['owner_id'] === (int) $user['id']) {
        return $course;
    }
    if ($user['role'] === 'student') {
        $membership = $pdo->prepare(
            'SELECT 1 FROM course_members WHERE course_id = ? AND student_id = ?'
        );
        $membership->execute([$courseId, $user['id']]);
        if ($membership->fetchColumn() !== false) {
            return $course;
        }
    }

    respond(404, ['error' => 'Klasa nuk u gjet.']);
}

// Gjen detyrën vetëm pasi verifikon hyrjen e përdoruesit në klasën e saj.
function assignment_for_user(PDO $pdo, int $assignmentId, array $user): array
{
    $query = $pdo->prepare(
        'SELECT a.id, a.course_id, a.title, a.details, a.due_date, a.created_at
         FROM assignments a
         INNER JOIN courses c ON c.id = a.course_id
         WHERE a.id = ?'
    );
    $query->execute([$assignmentId]);
    $assignment = $query->fetch();
    if ($assignment === false) {
        respond(404, ['error' => 'Detyra nuk u gjet.']);
    }

    require_course_access($pdo, (int) $assignment['course_id'], $user);

    return $assignment;
}

// Kontrollon që kodi i klasës të mos përdoret nga një klasë tjetër.
function class_code_exists(PDO $pdo, string $code): bool
{
    $query = $pdo->prepare('SELECT 1 FROM courses WHERE class_code = ?');
    $query->execute([$code]);

    return $query->fetchColumn() !== false;
}

// Krijon një kod ftese të rastësishëm dhe unik për klasën e re.
function generate_class_code(PDO $pdo): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do {
        $code = '';
        for ($index = 0; $index < 8; $index++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
    } while (class_code_exists($pdo, $code));

    return $code;
}

// Vendos cookie të sesionit dhe mbrojtjet e sigurisë për hyrjen e përdoruesit.
$cookieOptions = [
    'httponly' => true,
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'samesite' => 'Lax',
    'path' => '/',
];
session_name('klasa_ime_session');
session_set_cookie_params($cookieOptions);
ini_set('session.use_strict_mode', '1');
session_start();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = is_string($path) ? $path : '/';

try {
    // Hap databazën dhe sigurohu që tabelat/migrimet janë gati para trajtimit të API-së.
    $pdo = connect_database();

    // Jep përdoruesin aktual dhe token CSRF që ndërfaqja t'i përdorë në formularët e saj.
    if ($method === 'GET' && $path === '/api/auth') {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        respond(200, [
            'user' => $_SESSION['user'] ?? null,
            'csrf_token' => $_SESSION['csrf_token'],
        ]);
    }

    if (str_starts_with($path, '/api/') && $method !== 'GET' && $method !== 'HEAD') {
        verify_csrf();
    }

    // Regjistron llogarinë, ruan hash-in e fjalëkalimit dhe nis sesionin e përdoruesit.
    if ($method === 'POST' && $path === '/api/register') {
        $payload = read_json();
        $name = text_field($payload, 'name', 'emri', 100, true);
        $email = strtolower(text_field($payload, 'email', 'email-i', 254, true));
        $password = $payload['password'] ?? null;
        $role = $payload['role'] ?? null;

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            respond(400, ['error' => 'Shkruaj një adresë email-i të vlefshme.']);
        }
        if (!is_string($password) || strlen($password) < 8) {
            respond(400, ['error' => 'Fjalëkalimi duhet të ketë të paktën 8 karaktere.']);
        }
        if (strlen($password) > 72) {
            respond(400, ['error' => 'Fjalëkalimi nuk mund të kalojë 72 byte.']);
        }
        if (!in_array($role, ['teacher', 'student'], true)) {
            respond(400, ['error' => 'Zgjidh nëse je mësues apo nxënës.']);
        }

        $query = $pdo->prepare(
            'INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)'
        );
        try {
            $query->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                respond(409, ['error' => 'Ky email është regjistruar tashmë.']);
            }
            throw $exception;
        }

        $user = [
            'id' => (int) $pdo->lastInsertId(),
            'name' => $name,
            'email' => $email,
            'role' => $role,
        ];
        session_regenerate_id(true);
        $_SESSION['user'] = $user;
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        respond(201, ['user' => $user, 'csrf_token' => $_SESSION['csrf_token']]);
    }

    // Kontrollon fjalëkalimin e ruajtur dhe kyç përdoruesin në sesion të ri.
    if ($method === 'POST' && $path === '/api/login') {
        $payload = read_json();
        $email = strtolower(text_field($payload, 'email', 'email-i', 254, true));
        $password = $payload['password'] ?? null;
        if (!is_string($password) || $password === '') {
            respond(400, ['error' => 'Plotëso email-in dhe fjalëkalimin.']);
        }

        $query = $pdo->prepare(
            'SELECT id, name, email, password_hash, role FROM users WHERE email = ?'
        );
        $query->execute([$email]);
        $account = $query->fetch();
        if ($account === false || !password_verify($password, $account['password_hash'])) {
            respond(401, ['error' => 'Email-i ose fjalëkalimi nuk është i saktë.']);
        }

        $user = [
            'id' => (int) $account['id'],
            'name' => $account['name'],
            'email' => $account['email'],
            'role' => $account['role'],
        ];
        session_regenerate_id(true);
        $_SESSION['user'] = $user;
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        respond(200, ['user' => $user, 'csrf_token' => $_SESSION['csrf_token']]);
    }

    // Mbyll llogarinë aktuale dhe rinis identifikuesin e sesionit.
    if ($method === 'POST' && $path === '/api/logout') {
        unset($_SESSION['user']);
        session_regenerate_id(true);
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        respond(200, ['user' => null, 'csrf_token' => $_SESSION['csrf_token']]);
    }

    // Kthen vetëm klasat e mësuesit aktual ose klasat ku është regjistruar nxënësi.
    if ($method === 'GET' && $path === '/api/courses') {
        $user = require_user();
        if ($user['role'] === 'teacher') {
            $query = $pdo->prepare(
                "SELECT c.id, c.name, u.name AS teacher, c.description, c.class_code,
                        COUNT(DISTINCT a.id) AS assignment_count,
                        COUNT(DISTINCT m.student_id) AS student_count
                 FROM courses c
                 INNER JOIN users u ON u.id = c.owner_id
                 LEFT JOIN assignments a ON a.course_id = c.id
                 LEFT JOIN course_members m ON m.course_id = c.id
                 WHERE c.owner_id = ?
                 GROUP BY c.id
                 ORDER BY c.created_at DESC, c.id DESC"
            );
            $query->execute([$user['id']]);
        } else {
            $query = $pdo->prepare(
                "SELECT c.id, c.name, u.name AS teacher, c.description, c.class_code,
                        COUNT(DISTINCT a.id) AS assignment_count
                 FROM course_members m
                 INNER JOIN courses c ON c.id = m.course_id
                 INNER JOIN users u ON u.id = c.owner_id
                 LEFT JOIN assignments a ON a.course_id = c.id
                 WHERE m.student_id = ?
                 GROUP BY c.id
                 ORDER BY m.joined_at DESC, c.id DESC"
            );
            $query->execute([$user['id']]);
        }
        respond(200, $query->fetchAll());
    }

    // Lejon vetëm një mësues të krijojë klasë dhe gjeneron kodin e hyrjes për nxënësit.
    if ($method === 'POST' && $path === '/api/courses') {
        $user = require_user('teacher');
        $payload = read_json();
        $name = text_field($payload, 'name', 'emri i klasës', 100, true);
        $description = text_field($payload, 'description', 'përshkrimi', 500, false);
        $code = generate_class_code($pdo);

        $query = $pdo->prepare(
            'INSERT INTO courses (name, teacher, description, owner_id, class_code)
             VALUES (?, ?, ?, ?, ?)'
        );
        $query->execute([$name, $user['name'], $description, $user['id'], $code]);
        respond(201, find_course($pdo, (int) $pdo->lastInsertId()) ?? []);
    }

    // Regjistron nxënësin në klasën që përputhet me kodin e dhënë.
    if ($method === 'POST' && $path === '/api/courses/join') {
        $user = require_user('student');
        $payload = read_json();
        $code = strtoupper(text_field($payload, 'class_code', 'kodi i klasës', 8, true));
        if (preg_match('/\A[A-HJ-NP-Z2-9]{8}\z/', $code) !== 1) {
            respond(400, ['error' => 'Kodi i klasës nuk është i vlefshëm.']);
        }

        $query = $pdo->prepare('SELECT id FROM courses WHERE class_code = ?');
        $query->execute([$code]);
        $courseId = $query->fetchColumn();
        if ($courseId === false) {
            respond(404, ['error' => 'Nuk u gjet klasë me këtë kod.']);
        }

        $query = $pdo->prepare(
            'INSERT OR IGNORE INTO course_members (course_id, student_id) VALUES (?, ?)'
        );
        $query->execute([(int) $courseId, $user['id']]);
        if ($query->rowCount() === 0) {
            respond(409, ['error' => 'Je regjistruar tashmë në këtë klasë.']);
        }

        respond(200, find_course($pdo, (int) $courseId) ?? []);
    }

    // Kthen detyrat dhe numrin e dorëzimeve për mësuesin, ose përgjigjen e vetë nxënësit.
    if (preg_match('~\A/api/courses/([1-9][0-9]*)\z~', $path, $matches) === 1 && $method === 'GET') {
        $user = require_user();
        $courseId = (int) $matches[1];
        $course = require_course_access($pdo, $courseId, $user);
        unset($course['owner_id']);

        if ($user['role'] === 'teacher') {
            $query = $pdo->prepare(
                "SELECT a.id, a.course_id, a.title, a.details, a.due_date, a.created_at,
                        COUNT(s.id) AS submission_count
                 FROM assignments a
                 LEFT JOIN submissions s ON s.assignment_id = a.id
                 WHERE a.course_id = ?
                 GROUP BY a.id
                 ORDER BY a.due_date IS NULL, a.due_date, a.id DESC"
            );
            $query->execute([$courseId]);
            $members = $pdo->prepare('SELECT COUNT(*) FROM course_members WHERE course_id = ?');
            $members->execute([$courseId]);
            $course['student_count'] = (int) $members->fetchColumn();
        } else {
            $query = $pdo->prepare(
                'SELECT a.id, a.course_id, a.title, a.details, a.due_date, a.created_at,
                        s.body AS submission_body, s.submitted_at
                 FROM assignments a
                 LEFT JOIN submissions s
                    ON s.assignment_id = a.id AND s.student_id = ?
                 WHERE a.course_id = ?
                 ORDER BY a.due_date IS NULL, a.due_date, a.id DESC'
            );
            $query->execute([$user['id'], $courseId]);
        }
        respond(200, ['course' => $course, 'assignments' => $query->fetchAll()]);
    }

    // Lejon mësuesin e klasës të publikojë detyrë me afat opsional.
    if (preg_match('~\A/api/courses/([1-9][0-9]*)/assignments\z~', $path, $matches) === 1 && $method === 'POST') {
        $user = require_user('teacher');
        $courseId = (int) $matches[1];
        $course = require_course_access($pdo, $courseId, $user);
        $payload = read_json();
        $title = text_field($payload, 'title', 'titulli i detyrës', 150, true);
        $details = text_field($payload, 'details', 'udhëzimet', 2000, false);
        $dueDate = $payload['due_date'] ?? '';
        if (!is_string($dueDate)) {
            respond(400, ['error' => 'Data e dorëzimit nuk është e vlefshme.']);
        }
        $dueDate = trim($dueDate);
        if ($dueDate !== '') {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $dueDate);
            if ($date === false || $date->format('Y-m-d') !== $dueDate) {
                respond(400, ['error' => 'Data e dorëzimit nuk është e vlefshme.']);
            }
        } else {
            $dueDate = null;
        }

        $query = $pdo->prepare(
            'INSERT INTO assignments (course_id, title, details, due_date) VALUES (?, ?, ?, ?)'
        );
        $query->execute([$courseId, $title, $details, $dueDate]);
        respond(201, [
            'id' => (int) $pdo->lastInsertId(),
            'course_id' => $courseId,
            'title' => $title,
            'details' => $details,
            'due_date' => $dueDate,
            'submission_count' => 0,
            'class_name' => $course['name'],
        ]);
    }

    // Kthen dorëzimet e detyrës për mësuesin e klasës.
    if (preg_match('~\A/api/assignments/([1-9][0-9]*)/submissions\z~', $path, $matches) === 1
        && $method === 'GET') {
        $user = require_user('teacher');
        $assignment = assignment_for_user($pdo, (int) $matches[1], $user);
        $query = $pdo->prepare(
            'SELECT s.id, s.body, s.submitted_at, u.name AS student_name, u.email AS student_email
             FROM submissions s
             INNER JOIN users u ON u.id = s.student_id
             WHERE s.assignment_id = ?
             ORDER BY s.submitted_at DESC, u.name'
        );
        $query->execute([$assignment['id']]);
        respond(200, ['submissions' => $query->fetchAll()]);
    }

    // Regjistron përgjigjen e nxënësit ose përditëson dorëzimin e tij ekzistues.
    if (preg_match('~\A/api/assignments/([1-9][0-9]*)/submissions\z~', $path, $matches) === 1
        && $method === 'POST') {
        $user = require_user('student');
        $assignment = assignment_for_user($pdo, (int) $matches[1], $user);
        $payload = read_json();
        $body = text_field($payload, 'body', 'përgjigjja', 10000, true);

        $query = $pdo->prepare(
            'INSERT INTO submissions (assignment_id, student_id, body)
             VALUES (?, ?, ?)
             ON CONFLICT(assignment_id, student_id)
             DO UPDATE SET body = excluded.body, submitted_at = CURRENT_TIMESTAMP'
        );
        $query->execute([$assignment['id'], $user['id'], $body]);

        $query = $pdo->prepare(
            'SELECT id, assignment_id, body, submitted_at
             FROM submissions WHERE assignment_id = ? AND student_id = ?'
        );
        $query->execute([$assignment['id'], $user['id']]);
        respond(200, ['submission' => $query->fetch()]);
    }

    if ($path === '/api' || str_starts_with($path, '/api/')) {
        respond(404, ['error' => 'Adresa nuk u gjet.']);
    }

    respond(404, ['error' => 'Adresa nuk u gjet.']);
// Regjistro gabimet në server dhe kthe përgjigje të kuptueshme pa zbuluar hollësi të brendshme.
} catch (PDOException $exception) {
    error_log('Classroom database error: ' . $exception->getMessage());
    respond(500, ['error' => 'Ndodhi një gabim me bazën e të dhënave.']);
} catch (Throwable $exception) {
    error_log('Classroom application error: ' . $exception->getMessage());
    respond(500, ['error' => 'Ndodhi një gabim në aplikacion.']);
}
