<?php
/**
 * API JSON para tarefas e categorias.
 * Chamada pelo JavaScript (fetch) através de: api.php?action=...
 */

require __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

$pdo    = get_db();
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// Lê corpo JSON (para POST/PUT)
function body(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function out($data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    switch ($action) {

        // ---------- CATEGORIAS ----------
        case 'categories':
            $rows = $pdo->query('SELECT * FROM categories ORDER BY name COLLATE NOCASE')->fetchAll();
            out($rows);
            // no break (out() faz exit)

        case 'add_category':
            $d = body();
            $name  = trim($d['name'] ?? '');
            $color = trim($d['color'] ?? '#3b82f6');
            if ($name === '') {
                out(['error' => 'O nome da categoria é obrigatório.'], 400);
            }
            $stmt = $pdo->prepare('INSERT INTO categories (name, color) VALUES (?, ?)');
            try {
                $stmt->execute([$name, $color]);
            } catch (PDOException $e) {
                out(['error' => 'Já existe uma categoria com esse nome.'], 409);
            }
            out(['id' => (int) $pdo->lastInsertId()], 201);

        case 'delete_category':
            $d  = body();
            $id = (int) ($d['id'] ?? 0);
            $stmt = $pdo->prepare('DELETE FROM categories WHERE id = ?');
            $stmt->execute([$id]);
            out(['ok' => true]);

        // ---------- TAREFAS ----------
        case 'tasks':
            $cat = $_GET['category'] ?? '';
            if ($cat !== '' && $cat !== 'all') {
                $stmt = $pdo->prepare('
                    SELECT t.*, c.name AS category_name, c.color AS category_color
                    FROM tasks t
                    LEFT JOIN categories c ON c.id = t.category_id
                    WHERE t.category_id = ?
                    ORDER BY t.done, t.created_at DESC
                ');
                $stmt->execute([(int) $cat]);
                $rows = $stmt->fetchAll();
            } else {
                $rows = $pdo->query('
                    SELECT t.*, c.name AS category_name, c.color AS category_color
                    FROM tasks t
                    LEFT JOIN categories c ON c.id = t.category_id
                    ORDER BY t.done, t.created_at DESC
                ')->fetchAll();
            }
            out($rows);

        case 'add_task':
            $d     = body();
            $title = trim($d['title'] ?? '');
            $catId = isset($d['category_id']) && $d['category_id'] !== ''
                ? (int) $d['category_id'] : null;
            if ($title === '') {
                out(['error' => 'O título da tarefa é obrigatório.'], 400);
            }
            $stmt = $pdo->prepare('INSERT INTO tasks (title, category_id) VALUES (?, ?)');
            $stmt->execute([$title, $catId]);
            out(['id' => (int) $pdo->lastInsertId()], 201);

        case 'toggle_task':
            $d  = body();
            $id = (int) ($d['id'] ?? 0);
            $stmt = $pdo->prepare('UPDATE tasks SET done = 1 - done WHERE id = ?');
            $stmt->execute([$id]);
            out(['ok' => true]);

        case 'delete_task':
            $d  = body();
            $id = (int) ($d['id'] ?? 0);
            $stmt = $pdo->prepare('DELETE FROM tasks WHERE id = ?');
            $stmt->execute([$id]);
            out(['ok' => true]);

        default:
            out(['error' => 'Ação desconhecida.'], 404);
    }
} catch (Throwable $e) {
    out(['error' => 'Erro no servidor: ' . $e->getMessage()], 500);
}
