# Minhas Tarefas — App Todo com Categorias

Aplicação simples de lista de tarefas (todo) com categorias, feita em **PHP + SQLite**,
pensada para correr num servidor **Apache com hospedagem partilhada** — **sem instalar nada**,
basta enviar os ficheiros por **FTP**.

## Funcionalidades

- ➕ Adicionar, ✏️ editar (inline) e ✅ concluir tarefas
- 🗑 Eliminar tarefas
- 🏷 Organizar tarefas por **categoria** (com cor personalizada)
- ⏰ **Prazos** com data e **hora de término opcional**
- ⏳ **Tempo em falta** por tarefa (ex.: "Faltam 2d 3h" / "Atrasada há 5h"), atualizado automaticamente
- 🔁 **Tarefas recorrentes** (diária/semanal/mensal/anual): ao concluir, a próxima ocorrência é criada automaticamente
- 🔴🟡🟢 **Prioridades** (alta/média/baixa) com marca de cor na tarefa
- 🗒 **Subtarefas** (checklist) com barra de progresso
- ↕️ **Arrastar para reordenar** (drag & drop)
- 🔍 **Pesquisa** por texto + **filtros combinados** (categoria + estado: todas/ativas/concluídas)
- 🌙 **Modo escuro** com preferência guardada
- 📱 Design **responsivo** (funciona bem no telemóvel)
- 💾 Dados guardados em **SQLite** (ficheiro único, criado e migrado automaticamente)

> A base de dados é **migrada automaticamente**: se já tinhas uma versão anterior
> instalada, as novas colunas são adicionadas sem perder os teus dados.

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
