<?php require __DIR__ . '/db.php'; get_db(); // garante a criação da BD ?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Minhas Tarefas</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="app">
        <header>
            <h1>✓ Minhas Tarefas</h1>
            <p class="subtitle">Organiza as tuas tarefas por categoria</p>
        </header>

        <!-- Adicionar nova tarefa -->
        <form id="task-form" class="card">
            <input type="text" id="task-title" placeholder="Nova tarefa..." maxlength="200" required>
            <select id="task-category"></select>
            <button type="submit">Adicionar</button>
        </form>

        <!-- Filtro por categoria -->
        <div class="filters" id="filters"></div>

        <!-- Lista de tarefas -->
        <ul class="task-list" id="task-list"></ul>
        <p class="empty" id="empty-msg" hidden>Sem tarefas por aqui. 🎉</p>

        <!-- Gestão de categorias -->
        <section class="card categories-manager">
            <h2>Categorias</h2>
            <form id="category-form">
                <input type="text" id="category-name" placeholder="Nova categoria..." maxlength="50" required>
                <input type="color" id="category-color" value="#8b5cf6" title="Escolher cor">
                <button type="submit">Criar</button>
            </form>
            <ul class="category-list" id="category-list"></ul>
        </section>
    </div>

    <script src="assets/app.js"></script>
</body>
</html>
