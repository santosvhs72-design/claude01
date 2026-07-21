'use strict';

const API = 'api.php';

// Estado + preferências (persistidas em localStorage)
let categories = [];
let currentFilter = localStorage.getItem('filter') || 'all';
let currentStatus = localStorage.getItem('status') || 'all';
let searchQuery = '';
const expanded = new Set(); // ids de tarefas com subtarefas visíveis

const PRIORITIES = [
    { v: 'alta',  label: '🔴 Alta' },
    { v: 'media', label: '🟡 Média' },
    { v: 'baixa', label: '🟢 Baixa' },
];

// ---------- API helpers ----------
async function apiGet(action, params = {}) {
    const qs = new URLSearchParams({ action, ...params }).toString();
    const res = await fetch(`${API}?${qs}`);
    return res.json();
}
async function apiPost(action, data = {}) {
    const res = await fetch(`${API}?action=${action}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data),
    });
    return res.json();
}

function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => (
        { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
    ));
}

// ---------- Prazos ----------
function todayStr() {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}
function dueInfo(due) {
    if (!due) return null;
    const today = todayStr();
    let cls = '', label = due;
    if (due < today) cls = 'overdue';
    else if (due === today) { cls = 'today'; label = 'Hoje'; }
    // formato dd/mm
    const [y, m, d] = due.split('-');
    if (label === due) label = `${d}/${m}`;
    return { cls, label: (cls === 'overdue' ? '⚠ ' : '📅 ') + label };
}

// ---------- Tema ----------
function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    document.getElementById('theme-toggle').textContent = theme === 'dark' ? '☀️' : '🌙';
    localStorage.setItem('theme', theme);
}

// ---------- Categorias ----------
async function loadCategories() {
    categories = await apiGet('categories');
    renderCategorySelect();
    renderFilters();
    renderCategoryList();
}

function categoryOptions(selectedId) {
    return '<option value="">(Sem categoria)</option>' + categories.map(c =>
        `<option value="${c.id}" ${String(selectedId) === String(c.id) ? 'selected' : ''}>${escapeHtml(c.name)}</option>`
    ).join('');
}
function priorityOptions(selected) {
    return PRIORITIES.map(p =>
        `<option value="${p.v}" ${p.v === selected ? 'selected' : ''}>${p.label}</option>`
    ).join('');
}

function renderCategorySelect() {
    document.getElementById('task-category').innerHTML = categoryOptions('');
}

function renderFilters() {
    const box = document.getElementById('filters');
    const chip = (cat, label) =>
        `<button class="filter-chip ${String(currentFilter) === String(cat) ? 'active' : ''}" data-cat="${cat}">${escapeHtml(label)}</button>`;
    box.innerHTML = chip('all', 'Todas') + categories.map(c => chip(c.id, c.name)).join('');
    box.querySelectorAll('.filter-chip').forEach(btn => {
        btn.addEventListener('click', () => {
            currentFilter = btn.dataset.cat;
            localStorage.setItem('filter', currentFilter);
            renderFilters();
            loadTasks();
        });
    });
}

function renderCategoryList() {
    const ul = document.getElementById('category-list');
    if (categories.length === 0) {
        ul.innerHTML = '<li style="color:var(--muted)">Ainda não há categorias.</li>';
        return;
    }
    ul.innerHTML = categories.map(c => `
        <li>
            <span class="dot" style="background:${escapeHtml(c.color)}"></span>
            <span class="cat-name">${escapeHtml(c.name)}</span>
            <button class="delete" data-id="${c.id}" title="Eliminar categoria">🗑</button>
        </li>
    `).join('');
    ul.querySelectorAll('.delete').forEach(btn => {
        btn.addEventListener('click', async () => {
            if (!confirm('Eliminar esta categoria? As tarefas ficam sem categoria.')) return;
            await apiPost('delete_category', { id: Number(btn.dataset.id) });
            if (String(currentFilter) === btn.dataset.id) {
                currentFilter = 'all';
                localStorage.setItem('filter', 'all');
            }
            await loadCategories();
            await loadTasks();
        });
    });
}

// ---------- Tarefas ----------
async function loadTasks() {
    const tasks = await apiGet('tasks', { category: currentFilter, status: currentStatus, q: searchQuery });
    const ul = document.getElementById('task-list');
    const empty = document.getElementById('empty-msg');

    if (!Array.isArray(tasks) || tasks.length === 0) {
        ul.innerHTML = '';
        empty.hidden = false;
        return;
    }
    empty.hidden = true;
    ul.innerHTML = '';
    tasks.forEach(t => ul.appendChild(renderTask(t)));
}

function renderTask(t) {
    const li = document.createElement('li');
    li.className = `task-item prio-${t.priority || 'media'} ${t.done == 1 ? 'done' : ''}`;
    li.dataset.id = t.id;
    li.draggable = true;

    const subs = Array.isArray(t.subtasks) ? t.subtasks : [];
    const subDone = subs.filter(s => s.done == 1).length;
    const di = dueInfo(t.due_date);
    const isOpen = expanded.has(Number(t.id));

    li.innerHTML = `
        <div class="task-row">
            <span class="drag-handle" title="Arrastar para reordenar">⠿</span>
            <span class="check" title="Concluir">${t.done == 1 ? '✓' : ''}</span>
            <span class="title" title="Clica para editar">${escapeHtml(t.title)}</span>
            <div class="task-meta">
                ${di ? `<span class="due ${di.cls}">${escapeHtml(di.label)}</span>` : ''}
                ${t.category_name ? `<span class="badge" style="background:${escapeHtml(t.category_color)}">${escapeHtml(t.category_name)}</span>` : ''}
            </div>
            <div class="actions">
                <button class="sub-toggle" title="Subtarefas">🗒${subs.length ? ` ${subDone}/${subs.length}` : ''}</button>
                <button class="edit" title="Editar">✏️</button>
                <button class="del" title="Eliminar">✕</button>
            </div>
        </div>
        <div class="subtasks" ${isOpen ? '' : 'hidden'}></div>
    `;

    // Concluir
    li.querySelector('.check').addEventListener('click', async () => {
        await apiPost('toggle_task', { id: Number(t.id) });
        loadTasks();
    });
    // Eliminar
    li.querySelector('.del').addEventListener('click', async () => {
        await apiPost('delete_task', { id: Number(t.id) });
        loadTasks();
    });
    // Editar (botão ou clique no título)
    li.querySelector('.edit').addEventListener('click', () => openEdit(li, t));
    li.querySelector('.title').addEventListener('click', () => openEdit(li, t));
    // Subtarefas
    const subBox = li.querySelector('.subtasks');
    li.querySelector('.sub-toggle').addEventListener('click', () => {
        const nowOpen = subBox.hidden;
        subBox.hidden = !nowOpen;
        if (nowOpen) { expanded.add(Number(t.id)); renderSubtasks(subBox, t.id, subs); }
        else expanded.delete(Number(t.id));
    });
    if (isOpen) renderSubtasks(subBox, t.id, subs);

    attachDrag(li);
    return li;
}

// ---------- Edição inline ----------
function openEdit(li, t) {
    const row = li.querySelector('.task-row');
    row.style.display = 'none';
    const form = document.createElement('form');
    form.className = 'edit-form';
    form.innerHTML = `
        <input type="text" class="e-title" value="${escapeHtml(t.title)}" maxlength="200" required>
        <div class="row">
            <select class="e-cat">${categoryOptions(t.category_id)}</select>
            <select class="e-prio">${priorityOptions(t.priority || 'media')}</select>
            <input type="date" class="e-due" value="${escapeHtml(t.due_date || '')}">
        </div>
        <div class="row buttons">
            <button type="button" class="btn-secondary e-cancel">Cancelar</button>
            <button type="submit">Guardar</button>
        </div>
    `;
    li.insertBefore(form, row.nextSibling);
    form.querySelector('.e-title').focus();

    const close = () => { form.remove(); row.style.display = ''; };
    form.querySelector('.e-cancel').addEventListener('click', close);
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const r = await apiPost('update_task', {
            id: Number(t.id),
            title: form.querySelector('.e-title').value.trim(),
            category_id: form.querySelector('.e-cat').value,
            priority: form.querySelector('.e-prio').value,
            due_date: form.querySelector('.e-due').value,
        });
        if (r.error) { alert(r.error); return; }
        loadTasks();
    });
}

// ---------- Subtarefas ----------
function renderSubtasks(box, taskId, subs) {
    const total = subs.length;
    const done = subs.filter(s => s.done == 1).length;
    const pct = total ? Math.round((done / total) * 100) : 0;

    box.innerHTML = `
        ${total ? `<div class="sub-progress"><span style="width:${pct}%"></span></div>` : ''}
        <ul class="sub-list">
            ${subs.map(s => `
                <li class="sub-item ${s.done == 1 ? 'done' : ''}" data-id="${s.id}">
                    <span class="sub-check">${s.done == 1 ? '✓' : ''}</span>
                    <span class="sub-title">${escapeHtml(s.title)}</span>
                    <button class="sub-del" title="Eliminar">✕</button>
                </li>`).join('')}
        </ul>
        <form class="sub-add">
            <input type="text" placeholder="Nova subtarefa..." maxlength="200" required>
            <button type="submit">+</button>
        </form>
    `;

    box.querySelectorAll('.sub-item').forEach(li => {
        const id = Number(li.dataset.id);
        li.querySelector('.sub-check').addEventListener('click', async () => {
            await apiPost('toggle_subtask', { id });
            await refreshSubtasks(box, taskId);
        });
        li.querySelector('.sub-del').addEventListener('click', async () => {
            await apiPost('delete_subtask', { id });
            await refreshSubtasks(box, taskId);
        });
    });

    box.querySelector('.sub-add').addEventListener('submit', async (e) => {
        e.preventDefault();
        const input = e.target.querySelector('input');
        const title = input.value.trim();
        if (!title) return;
        await apiPost('add_subtask', { task_id: taskId, title });
        input.value = '';
        await refreshSubtasks(box, taskId);
    });
}

// Recarrega apenas as subtarefas de uma tarefa (e atualiza o contador na lista)
async function refreshSubtasks(box, taskId) {
    const tasks = await apiGet('tasks', { category: 'all', status: 'all' });
    const t = tasks.find(x => Number(x.id) === Number(taskId));
    const subs = t ? t.subtasks : [];
    renderSubtasks(box, taskId, subs);
    // Atualiza o contador no botão
    const li = box.closest('.task-item');
    if (li) {
        const done = subs.filter(s => s.done == 1).length;
        li.querySelector('.sub-toggle').textContent = `🗒${subs.length ? ` ${done}/${subs.length}` : ''}`;
    }
}

// ---------- Drag & drop (reordenar) ----------
let dragEl = null;
function attachDrag(li) {
    li.addEventListener('dragstart', () => { dragEl = li; li.classList.add('dragging'); });
    li.addEventListener('dragend', async () => {
        li.classList.remove('dragging');
        document.querySelectorAll('.drag-over').forEach(e => e.classList.remove('drag-over'));
        dragEl = null;
        const ids = [...document.querySelectorAll('.task-item')].map(e => Number(e.dataset.id));
        await apiPost('reorder_tasks', { ids });
    });
    li.addEventListener('dragover', (e) => {
        e.preventDefault();
        if (!dragEl || dragEl === li) return;
        const list = li.parentNode;
        const rect = li.getBoundingClientRect();
        const after = e.clientY > rect.top + rect.height / 2;
        list.insertBefore(dragEl, after ? li.nextSibling : li);
    });
}

// ---------- Formulários principais ----------
document.getElementById('task-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const title = document.getElementById('task-title').value.trim();
    if (!title) return;
    const r = await apiPost('add_task', {
        title,
        category_id: document.getElementById('task-category').value,
        priority: document.getElementById('task-priority').value,
        due_date: document.getElementById('task-due').value,
    });
    if (r.error) { alert(r.error); return; }
    document.getElementById('task-title').value = '';
    document.getElementById('task-due').value = '';
    loadTasks();
});

document.getElementById('category-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const name = document.getElementById('category-name').value.trim();
    const color = document.getElementById('category-color').value;
    if (!name) return;
    const r = await apiPost('add_category', { name, color });
    if (r.error) { alert(r.error); return; }
    document.getElementById('category-name').value = '';
    await loadCategories();
});

// Pesquisa (com atraso)
let searchTimer = null;
document.getElementById('search').addEventListener('input', (e) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        searchQuery = e.target.value.trim();
        loadTasks();
    }, 250);
});

// Filtro de estado
document.getElementById('status-filter').addEventListener('click', (e) => {
    const btn = e.target.closest('.status-btn');
    if (!btn) return;
    currentStatus = btn.dataset.status;
    localStorage.setItem('status', currentStatus);
    document.querySelectorAll('.status-btn').forEach(b => b.classList.toggle('active', b === btn));
    loadTasks();
});

// Tema
document.getElementById('theme-toggle').addEventListener('click', () => {
    const cur = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    applyTheme(cur);
});

// ---------- Arranque ----------
(async function init() {
    applyTheme(localStorage.getItem('theme') || 'light');
    // Restaura o estado ativo dos filtros
    document.querySelectorAll('.status-btn').forEach(b =>
        b.classList.toggle('active', b.dataset.status === currentStatus));
    await loadCategories();
    await loadTasks();
})();
