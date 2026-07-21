<?php
require __DIR__ . '/db.php';
get_db(); // garante a criação/migração da BD
// Cache-busting: a versão muda sempre que o ficheiro é alterado,
// forçando o browser a descarregar o CSS/JS novo (útil em deploys por FTP).
$cssV = @filemtime(__DIR__ . '/assets/style.css') ?: time();
$jsV  = @filemtime(__DIR__ . '/assets/app.js') ?: time();
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Minhas Tarefas</title>

    <!-- PWA -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#4f46e5">
    <link rel="icon" type="image/png" href="assets/icon-192.png">
    <link rel="apple-touch-icon" href="assets/icon-192.png">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Tarefas">

    <link rel="stylesheet" href="assets/style.css?v=<?= $cssV ?>">
</head>
<body>
    <div class="app">
        <header>
            <div class="header-top">
                <h1>✓ Minhas Tarefas</h1>
                <div class="header-actions">
                    <button id="reminders-toggle" class="icon-btn" title="Lembretes de tarefas">🔕</button>
                    <button id="theme-toggle" class="icon-btn" title="Alternar tema">🌙</button>
                </div>
            </div>
            <p class="subtitle">Organiza as tuas tarefas por categoria, prazo e prioridade</p>
        </header>

        <!-- Secção: criar tarefa -->
        <section class="card section">
            <h2 class="section-title">➕ Nova tarefa</h2>
            <form id="task-form">
                <div class="task-form-main">
                    <input type="text" id="task-title" placeholder="O que precisas de fazer?" maxlength="200" required>
                    <button type="submit">Adicionar</button>
                </div>
                <div class="task-form-opts">
                    <select id="task-category" title="Categoria"></select>
                    <select id="task-priority" title="Prioridade">
                        <option value="alta">🔴 Alta</option>
                        <option value="media" selected>🟡 Média</option>
                        <option value="baixa">🟢 Baixa</option>
                    </select>
                    <input type="date" id="task-due" title="Data limite">
                    <input type="time" id="task-due-time" title="Hora de término (opcional)">
                    <select id="task-recurrence" title="Repetição">
                        <option value="none" selected>🔁 Não repete</option>
                        <option value="daily">Diariamente</option>
                        <option value="weekly">Semanalmente</option>
                        <option value="monthly">Mensalmente</option>
                        <option value="yearly">Anualmente</option>
                    </select>
                </div>
            </form>
        </section>

        <!-- Secção: lista de tarefas -->
        <section class="card section">
            <h2 class="section-title">📋 As minhas tarefas</h2>
            <div class="toolbar">
                <input type="search" id="search" placeholder="🔍 Pesquisar tarefas...">
                <div class="status-filter" id="status-filter">
                    <button class="status-btn active" data-status="all">Todas</button>
                    <button class="status-btn" data-status="active">Ativas</button>
                    <button class="status-btn" data-status="done">Concluídas</button>
                </div>
                <select id="sort" title="Ordenar">
                    <option value="manual">↕️ Ordem manual</option>
                    <option value="created">🗓 Data de criação</option>
                    <option value="due">⏰ Prazo</option>
                    <option value="name">🔤 Nome (A–Z)</option>
                </select>
            </div>
            <div class="filters" id="filters"></div>
            <ul class="task-list" id="task-list"></ul>
            <p class="empty" id="empty-msg" hidden>Sem tarefas por aqui. 🎉</p>
        </section>

        <!-- Secção: categorias -->
        <section class="card section categories-manager">
            <h2 class="section-title">🏷 Categorias</h2>
            <form id="category-form">
                <input type="text" id="category-name" placeholder="Nova categoria..." maxlength="50" required>
                <input type="color" id="category-color" value="#8b5cf6" title="Escolher cor">
                <button type="submit">Criar</button>
            </form>
            <ul class="category-list" id="category-list"></ul>
        </section>
    </div>

    <script src="assets/app.js?v=<?= $jsV ?>"></script>
</body>
</html>
