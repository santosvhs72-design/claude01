<?php
/**
 * Ligação à base de dados SQLite e criação automática das tabelas.
 * O ficheiro .db é criado na primeira execução, na mesma pasta.
 * Não é preciso instalar nada: o SQLite vem incluído no PHP.
 */

// Caminho para o ficheiro da base de dados (fica ao lado deste script)
define('DB_PATH', __DIR__ . '/data/todo.sqlite');

function get_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    // Garante que a pasta de dados existe
    $dir = dirname(DB_PATH);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    // Ativa as chaves estrangeiras
    $pdo->exec('PRAGMA foreign_keys = ON');

    init_db($pdo);

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
            created_at  TEXT NOT NULL DEFAULT (datetime('now')),
            FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
        )
    SQL);

    // Se não existir nenhuma categoria, cria algumas de exemplo
    $count = (int) $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn();
    if ($count === 0) {
        $stmt = $pdo->prepare('INSERT INTO categories (name, color) VALUES (?, ?)');
        $stmt->execute(['Pessoal', '#3b82f6']);
        $stmt->execute(['Trabalho', '#ef4444']);
        $stmt->execute(['Compras', '#22c55e']);
    }
}
