'use strict';

const API = 'api.php';

// Estado
let categories = [];
let currentFilter = 'all';

// Utilitários de chamadas à API
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
    return String(s).replace(/[&<>"']/g, c => (
        { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
    ));
}

// ---------- Categorias ----------
async function loadCategories() {
    categories = await apiGet('categories');
    renderCategorySelect();
    renderFilters();
    renderCategoryList();
}

function renderCategorySelect() {
    const sel = document.getElementById('task-category');
    sel.innerHTML = '<option value="">(Sem categoria)</option>' +
        categories.map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
}

function renderFilters() {
    const box = document.getElementById('filters');
    const chips = [`<button class="filter-chip ${currentFilter === 'all' ? 'active' : ''}" data-cat="all">Todas</button>`];
    for (const c of categories) {
        chips.push(
            `<button class="filter-chip ${currentFilter == c.id ? 'active' : ''}" data-cat="${c.id}">${escapeHtml(c.name)}</button>`
        );
    }
    box.innerHTML = chips.join('');
    box.querySelectorAll('.filter-chip').forEach(btn => {
        btn.addEventListener('click', () => {
            currentFilter = btn.dataset.cat;
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
            if (currentFilter == btn.dataset.id) currentFilter = 'all';
            await loadCategories();
            await loadTasks();
        });
    });
}

// ---------- Tarefas ----------
async function loadTasks() {
    const tasks = await apiGet('tasks', { category: currentFilter });
    const ul = document.getElementById('task-list');
    const empty = document.getElementById('empty-msg');

    if (!Array.isArray(tasks) || tasks.length === 0) {
        ul.innerHTML = '';
        empty.hidden = false;
        return;
    }
    empty.hidden = true;

    ul.innerHTML = tasks.map(t => `
        <li class="task-item ${t.done == 1 ? 'done' : ''}" data-id="${t.id}">
            <span class="check" title="Concluir">${t.done == 1 ? '✓' : ''}</span>
            <span class="title">${escapeHtml(t.title)}</span>
            ${t.category_name
                ? `<span class="badge" style="background:${escapeHtml(t.category_color)}">${escapeHtml(t.category_name)}</span>`
                : ''}
            <button class="delete" title="Eliminar">✕</button>
        </li>
    `).join('');

    ul.querySelectorAll('.task-item').forEach(li => {
        const id = Number(li.dataset.id);
        li.querySelector('.check').addEventListener('click', async () => {
            await apiPost('toggle_task', { id });
            loadTasks();
        });
        li.querySelector('.delete').addEventListener('click', async () => {
            await apiPost('delete_task', { id });
            loadTasks();
        });
    });
}

// ---------- Formulários ----------
document.getElementById('task-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const title = document.getElementById('task-title').value.trim();
    const categoryId = document.getElementById('task-category').value;
    if (!title) return;
    const r = await apiPost('add_task', { title, category_id: categoryId });
    if (r.error) { alert(r.error); return; }
    document.getElementById('task-title').value = '';
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

// ---------- Arranque ----------
(async function init() {
    await loadCategories();
    await loadTasks();
})();
