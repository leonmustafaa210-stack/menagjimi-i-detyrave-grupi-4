-- Skema fillestare e bazës së të dhënave SQLite për aplikacionin Klasa ime.
-- Krijimi IF NOT EXISTS lejon që aplikacioni të nisë edhe kur databaza ekziston.

-- Të gjitha llogaritë ruhen këtu; fjalëkalimet ruhen vetëm si hash.
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT NOT NULL COLLATE NOCASE UNIQUE,
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL CHECK (role IN ('teacher', 'student')),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Klasat i përkasin mësuesit krijues dhe kanë kod unik për regjistrimin e nxënësve.
CREATE TABLE IF NOT EXISTS courses (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    teacher TEXT NOT NULL,
    description TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    owner_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    class_code TEXT
);

-- Tabela lidh nxënësit me klasat ku janë regjistruar.
CREATE TABLE IF NOT EXISTS course_members (
    course_id INTEGER NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
    student_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    joined_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (course_id, student_id)
);

-- Detyrat ruhen në klasën që i ka publikuar.
CREATE TABLE IF NOT EXISTS assignments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    course_id INTEGER NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
    title TEXT NOT NULL,
    details TEXT NOT NULL DEFAULT '',
    due_date TEXT,
    status TEXT NOT NULL DEFAULT 'pending'
        CHECK (status IN ('pending', 'completed')),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Çdo nxënës mund të dorëzojë një përgjigje për detyrë dhe ta përditësojë.
CREATE TABLE IF NOT EXISTS submissions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    assignment_id INTEGER NOT NULL REFERENCES assignments(id) ON DELETE CASCADE,
    student_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    body TEXT NOT NULL,
    submitted_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (assignment_id, student_id)
);

-- Indekset e klasave krijohen nga PHP pasi migrimi t'i shtojë kolonat e reja,
-- që kjo skemë të mund të përdoret edhe me tabelat e versioneve të mëparshme.
CREATE INDEX IF NOT EXISTS assignments_course_id_index ON assignments(course_id);
CREATE INDEX IF NOT EXISTS submissions_student_id_index ON submissions(student_id);
