/* Service worker da PWA "Minhas Tarefas" */
const CACHE = 'tarefas-v3';

// Shell da aplicação (ficheiros estáticos essenciais)
const SHELL = [
    './',
    './index.php',
    './manifest.json',
    './assets/style.css',
    './assets/app.js',
    './assets/icon-192.png',
    './assets/icon-512.png',
    './assets/icon-maskable-512.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE)
            .then((c) => c.addAll(SHELL))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

// Clicar na notificação foca (ou abre) a aplicação
self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((list) => {
            for (const c of list) {
                if ('focus' in c) return c.focus();
            }
            if (self.clients.openWindow) return self.clients.openWindow('./index.php');
        })
    );
});

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') return;

    const url = new URL(req.url);
    if (url.origin !== self.location.origin) return;

    // API: sempre pela rede (dados sempre atualizados). Não interceta.
    if (url.pathname.endsWith('api.php')) return;

    // Navegação: rede primeiro; se offline, devolve o shell em cache.
    if (req.mode === 'navigate') {
        event.respondWith(
            fetch(req).catch(() =>
                caches.match('./index.php').then((r) => r || caches.match('./'))
            )
        );
        return;
    }

    // Estáticos (CSS/JS/ícones): cache primeiro, com atualização em segundo plano.
    event.respondWith(
        caches.match(req, { ignoreSearch: true }).then((cached) => {
            const network = fetch(req)
                .then((res) => {
                    if (res && res.ok) {
                        const copy = res.clone();
                        caches.open(CACHE).then((c) => c.put(req, copy));
                    }
                    return res;
                })
                .catch(() => cached);
            return cached || network;
        })
    );
});
