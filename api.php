<?php
/**
 * API JSON para tarefas, subtarefas e categorias.
 * Chamada pelo JavaScript (fetch) através de: api.php?action=...
 */

require __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

$pdo    = get_db();
$action = $_GET['action'] ?? '';

function body(): array
{
    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function out($data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function valid_priority(string $p): string
{
    return in_array($p, ['alta', 'media', 'baixa'], true) ? $p : 'media';
}

// Aceita datas no formato YYYY-MM-DD (ou vazio => null)
function valid_date(?string $d): ?string
{
    $d = trim((string) $d);
    if ($d === '') return null;
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) ? $d : null;
}

// Aceita horas no formato HH:MM (ou vazio => null)
function valid_time(?string $t): ?string
{
    $t = trim((string) $t);
    if ($t === '') return null;
    return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t) ? $t : null;
}

function valid_recurrence(string $r): string
{
    return in_array($r, ['none', 'daily', 'weekly', 'monthly', 'yearly'], true) ? $r : 'none';
}

// Calcula a data da próxima ocorrência a partir de uma data base (ou de hoje)
function next_due(?string $due, string $rec): ?string
{
    $base = $due ?: date('Y-m-d');
    $dt = DateTime::createFromFormat('Y-m-d', $base);
    if (!$dt) return $due;
    switch ($rec) {
        case 'daily':   $dt->modify('+1 day');   break;
        case 'weekly':  $dt->modify('+1 week');  break;
        case 'monthly': $dt->modify('+1 month'); break;
        case 'yearly':  $dt->modify('+1 year');  break;
        default:        return $due;
    }
    return $dt->format('Y-m-d');
}

try {
    switch ($action) {

        // ---------- CATEGORIAS ----------
        case 'categories':
            $rows = $pdo->query('SELECT * FROM categories ORDER BY name COLLATE NOCASE')->fetchAll();
            out($rows);

        case 'add_category':
            $d     = body();
            $name  = trim($d['name'] ?? '');
            $color = trim($d['color'] ?? '#3b82f6');
            if ($name === '') out(['error' => 'O nome da categoria é obrigatório.'], 400);
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
            $cat    = $_GET['category'] ?? 'all';
            $status = $_GET['status'] ?? 'all';   // all | active | done
            $q      = trim($_GET['q'] ?? '');

            $where  = [];
            $params = [];

            if ($cat !== '' && $cat !== 'all') {
                $where[] = 't.category_id = ?';
                $params[] = (int) $cat;
            }
            if ($status === 'active') {
                $where[] = 't.done = 0';
            } elseif ($status === 'done') {
                $where[] = 't.done = 1';
            }
            if ($q !== '') {
                $where[] = 't.title LIKE ?';
                $params[] = '%' . $q . '%';
            }

            $sql = '
                SELECT t.*, c.name AS category_name, c.color AS category_color
                FROM tasks t
                LEFT JOIN categories c ON c.id = t.category_id';
            if ($where) {
                $sql .= ' WHERE ' . implode(' AND ', $where);
            }
            $sql .= ' ORDER BY t.done ASC, t.position ASC, t.created_at DESC';

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $tasks = $stmt->fetchAll();

            // Anexa subtarefas (uma única query)
            if ($tasks) {
                $ids = array_column($tasks, 'id');
                $in  = implode(',', array_fill(0, count($ids), '?'));
                $sub = $pdo->prepare("SELECT * FROM subtasks WHERE task_id IN ($in) ORDER BY position ASC, id ASC");
                $sub->execute($ids);
                $byTask = [];
                foreach ($sub->fetchAll() as $s) {
                    $byTask[$s['task_id']][] = $s;
                }
                foreach ($tasks as &$t) {
                    $t['subtasks'] = $byTask[$t['id']] ?? [];
                }
                unset($t);
            }
            out($tasks);

        case 'add_task':
            $d        = body();
            $title    = trim($d['title'] ?? '');
            $catId    = isset($d['category_id']) && $d['category_id'] !== '' ? (int) $d['category_id'] : null;
            $priority = valid_priority($d['priority'] ?? 'media');
            $due      = valid_date($d['due_date'] ?? null);
            $dueTime  = $due ? valid_time($d['due_time'] ?? null) : null; // hora só faz sentido com data
            $rec      = valid_recurrence($d['recurrence'] ?? 'none');
            if ($title === '') out(['error' => 'O título da tarefa é obrigatório.'], 400);

            // Novas tarefas aparecem no topo (posição = menor - 1)
            $minPos = $pdo->query('SELECT MIN(position) FROM tasks')->fetchColumn();
            $pos    = ($minPos === null) ? 0 : ((int) $minPos - 1);

            $stmt = $pdo->prepare('INSERT INTO tasks (title, category_id, priority, due_date, due_time, recurrence, position) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$title, $catId, $priority, $due, $dueTime, $rec, $pos]);
            out(['id' => (int) $pdo->lastInsertId()], 201);

        case 'update_task':
            $d  = body();
            $id = (int) ($d['id'] ?? 0);
            if ($id <= 0) out(['error' => 'Tarefa inválida.'], 400);
            $title    = trim($d['title'] ?? '');
            $catId    = isset($d['category_id']) && $d['category_id'] !== '' ? (int) $d['category_id'] : null;
            $priority = valid_priority($d['priority'] ?? 'media');
            $due      = valid_date($d['due_date'] ?? null);
            $dueTime  = $due ? valid_time($d['due_time'] ?? null) : null;
            $rec      = valid_recurrence($d['recurrence'] ?? 'none');
            if ($title === '') out(['error' => 'O título da tarefa é obrigatório.'], 400);
            $stmt = $pdo->prepare('UPDATE tasks SET title = ?, category_id = ?, priority = ?, due_date = ?, due_time = ?, recurrence = ? WHERE id = ?');
            $stmt->execute([$title, $catId, $priority, $due, $dueTime, $rec, $id]);
            out(['ok' => true]);

        case 'toggle_task':
            $d  = body();
            $id = (int) ($d['id'] ?? 0);
            $pdo->prepare('UPDATE tasks SET done = 1 - done WHERE id = ?')->execute([$id]);

            // Se acabou de ser CONCLUÍDA e é recorrente, cria a próxima ocorrência.
            $sel = $pdo->prepare('SELECT * FROM tasks WHERE id = ?');
            $sel->execute([$id]);
            $task = $sel->fetch();
            $spawned = null;
            if ($task && (int) $task['done'] === 1 && ($task['recurrence'] ?? 'none') !== 'none') {
                $nextDue = next_due($task['due_date'], $task['recurrence']);
                $minPos  = $pdo->query('SELECT MIN(position) FROM tasks')->fetchColumn();
                $pos     = ($minPos === null) ? 0 : ((int) $minPos - 1);
                $ins = $pdo->prepare('INSERT INTO tasks (title, category_id, priority, due_date, due_time, recurrence, position) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $ins->execute([
                    $task['title'], $task['category_id'], $task['priority'],
                    $nextDue, $task['due_time'], $task['recurrence'], $pos,
                ]);
                $spawned = (int) $pdo->lastInsertId();
                // A tarefa concluída deixa de ser recorrente (fica como histórico);
                // a recorrência continua na nova ocorrência.
                $pdo->prepare("UPDATE tasks SET recurrence = 'none' WHERE id = ?")->execute([$id]);
            }
            out(['ok' => true, 'spawned' => $spawned]);

        case 'delete_task':
            $d  = body();
            $id = (int) ($d['id'] ?? 0);
            $stmt = $pdo->prepare('DELETE FROM tasks WHERE id = ?');
            $stmt->execute([$id]);
            out(['ok' => true]);

        case 'reorder_tasks':
            $d   = body();
            $ids = $d['ids'] ?? [];
            if (!is_array($ids)) out(['error' => 'Lista inválida.'], 400);
            $stmt = $pdo->prepare('UPDATE tasks SET position = ? WHERE id = ?');
            $pdo->beginTransaction();
            foreach (array_values($ids) as $pos => $id) {
                $stmt->execute([(int) $pos, (int) $id]);
            }
            $pdo->commit();
            out(['ok' => true]);

        // ---------- SUBTAREFAS ----------
        case 'add_subtask':
            $d      = body();
            $taskId = (int) ($d['task_id'] ?? 0);
            $title  = trim($d['title'] ?? '');
            if ($taskId <= 0 || $title === '') out(['error' => 'Dados inválidos.'], 400);
            $maxPos = $pdo->prepare('SELECT COALESCE(MAX(position), -1) + 1 FROM subtasks WHERE task_id = ?');
            $maxPos->execute([$taskId]);
            $pos = (int) $maxPos->fetchColumn();
            $stmt = $pdo->prepare('INSERT INTO subtasks (task_id, title, position) VALUES (?, ?, ?)');
            $stmt->execute([$taskId, $title, $pos]);
            out(['id' => (int) $pdo->lastInsertId()], 201);

        case 'toggle_subtask':
            $d  = body();
            $id = (int) ($d['id'] ?? 0);
            $stmt = $pdo->prepare('UPDATE subtasks SET done = 1 - done WHERE id = ?');
            $stmt->execute([$id]);
            out(['ok' => true]);

        case 'delete_subtask':
            $d  = body();
            $id = (int) ($d['id'] ?? 0);
            $stmt = $pdo->prepare('DELETE FROM subtasks WHERE id = ?');
            $stmt->execute([$id]);
            out(['ok' => true]);

        default:
            out(['error' => 'Ação desconhecida.'], 404);
    }
} catch (Throwable $e) {
    out(['error' => 'Erro no servidor: ' . $e->getMessage()], 500);
}
