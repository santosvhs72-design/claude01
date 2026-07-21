# Minhas Tarefas — App Todo com Categorias

Aplicação simples de lista de tarefas (todo) com categorias, feita em **PHP + SQLite**,
pensada para correr num servidor **Apache com hospedagem partilhada** — **sem instalar nada**,
basta enviar os ficheiros por **FTP**.

## Funcionalidades

- ➕ Adicionar e ✅ concluir tarefas
- 🗑 Eliminar tarefas
- 🏷 Organizar tarefas por **categoria** (com cor personalizada)
- 🔍 Filtrar tarefas por categoria
- 💾 Dados guardados em **SQLite** (ficheiro único, criado automaticamente)

## Requisitos do servidor

- PHP 7.4 ou superior (com as extensões **PDO** e **pdo_sqlite** — vêm ativas por omissão
  na esmagadora maioria dos alojamentos)
- Apache (ou qualquer servidor com suporte a PHP)

Não é preciso MySQL, nem instalar dependências, nem `composer`.

## Instalação via FTP

1. Envia **todos os ficheiros e pastas** para a pasta pública do teu alojamento
   (normalmente `public_html/`, `www/` ou `htdocs/`).
2. Garante que a pasta `data/` tem permissões de **escrita** (chmod `755` ou `775`).
   É aqui que o ficheiro `todo.sqlite` é criado na primeira utilização.
3. Abre o site no navegador. Pronto! 🎉

## Estrutura dos ficheiros

```
index.php          → Página principal (interface)
api.php            → API JSON (adicionar/listar/eliminar tarefas e categorias)
db.php             → Ligação SQLite + criação automática das tabelas
assets/style.css   → Estilos
assets/app.js      → Lógica no navegador (fetch/AJAX)
data/              → Base de dados SQLite (protegida por .htaccess)
.htaccess          → Página inicial + configuração
```

## Segurança

A pasta `data/` está protegida por um `.htaccess` que impede o download direto
do ficheiro da base de dados pelo navegador.
