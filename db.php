<?php
/**
 * Ligação à base de dados SQLite e criação/migração automática das tabelas.
 * O ficheiro .db é criado na primeira execução, na pasta data/.
 * Não é preciso instalar nada: o SQLite vem incluído no PHP.
 */

define('DB_PATH', __DIR__ . '/data/todo.sqlite');

function get_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dir = dirname(DB_PATH);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
    // WAL melhora a concorrência de leitura/escrita em SQLite
    try { $pdo->exec('PRAGMA journal_mode = WAL'); } catch (Throwable $e) { /* ignora se o alojamento não permitir */ }

    init_db($pdo);
    migrate_db($pdo);

    return $pdo;
}

function init_db(PDO $pdo): void
{
    $pdo->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS categories (
            id    INTEGER PRIMARY KEY AUTOINCREMENT,
            name  TEXT NOT NULL UNIQUE,
            color TEXT NOT NULL DEFAULT '#3b82f6'
        )
    SQL);

    $pdo->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS tasks (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            title       TEXT NOT NULL,
            done        INTEGER NOT NULL DEFAULT 0,
            category_id INTEGER,
            priority    TEXT NOT NULL DEFAULT 'media',
            due_date    TEXT,
            due_time    TEXT,
            position    INTEGER NOT NULL DEFAULT 0,
            created_at  TEXT NOT NULL DEFAULT (datetime('now')),
            FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
        )
    SQL);

    $pdo->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS subtasks (
            id       INTEGER PRIMARY KEY AUTOINCREMENT,
            task_id  INTEGER NOT NULL,
            title    TEXT NOT NULL,
            done     INTEGER NOT NULL DEFAULT 0,
            position INTEGER NOT NULL DEFAULT 0,
            FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE
        )
    SQL);

    $count = (int) $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn();
    if ($count === 0) {
        $stmt = $pdo->prepare('INSERT INTO categories (name, color) VALUES (?, ?)');
        $stmt->execute(['Pessoal', '#3b82f6']);
        $stmt->execute(['Trabalho', '#ef4444']);
        $stmt->execute(['Compras', '#22c55e']);
    }
}

/**
 * Adiciona colunas em falta a bases de dados criadas por versões anteriores.
 * Assim, quem já tinha a app instalada não perde os dados ao atualizar.
 */
function migrate_db(PDO $pdo): void
{
    $cols = [];
    foreach ($pdo->query('PRAGMA table_info(tasks)') as $c) {
        $cols[$c['name']] = true;
    }
    if (!isset($cols['priority'])) {
        $pdo->exec("ALTER TABLE tasks ADD COLUMN priority TEXT NOT NULL DEFAULT 'media'");
    }
    if (!isset($cols['due_date'])) {
        $pdo->exec('ALTER TABLE tasks ADD COLUMN due_date TEXT');
    }
    if (!isset($cols['due_time'])) {
        $pdo->exec('ALTER TABLE tasks ADD COLUMN due_time TEXT');
    }
    if (!isset($cols['position'])) {
        $pdo->exec('ALTER TABLE tasks ADD COLUMN position INTEGER NOT NULL DEFAULT 0');
    }
}
