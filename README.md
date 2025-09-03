# UChat — Laravel + Ollama Chat (Streaming)

UChat is a lightweight ChatGPT‑style web app built with Laravel. It talks to a locally running open‑source LLM (via Ollama), supports streaming responses, Markdown tables and Mermaid diagrams, right‑to‑left text, per‑chat model selection, and a clean, modular front‑end.

## Features

- Streaming chat to a local LLM (Ollama)
- Chats CRUD: create, rename, delete, list
- Per‑chat settings: model (selectable from env‑configured list)
- Markdown rendering with tables and Mermaid diagrams (sanitized)
- Automatic RTL/LTR detection (Arabic/Hebrew supported)
- Auto‑title from the first user message
- Sidebar: resizable and collapsible; subtle gradient background
- Auth (token‑based), Sign in/Sign up modal UI
- Admin role (block/unblock users), seeded admin
- Modular front‑end (vanilla JS ES modules under `public/js/chat/`)

## Requirements

- PHP 8.2+
- Composer
- A database (PostgreSQL recommended; migrations include `uuid-ossp` and JSONB)
- Ollama running locally (default `http://127.0.0.1:11434`)

## Quick Start

1) Install dependencies

- `composer install`
- (Optional) `php artisan key:generate` (if `APP_KEY` missing)

2) Configure `.env`

- LLM
  - `LLM_BASE_URL=http://127.0.0.1:11434`
  - `LLM_MODEL=gpt-oss:20b` (no trailing spaces)
  - `LLM_MODEL2=openchat` (second selectable model)
- Database (example for Postgres)
  - `DB_CONNECTION=pgsql`
  - `DB_HOST=127.0.0.1`
  - `DB_PORT=5432`
  - `DB_DATABASE=ai_chat_db`
  - `DB_USERNAME=...`
  - `DB_PASSWORD=...`

3) Migrate and seed

- `php artisan migrate`
- `php artisan db:seed` (creates an admin user and demo data)

Admin credentials

- Email: `admin@example.com`
- Password: `A@admin123`

4) Ensure models are present in Ollama

- `ollama pull gpt-oss:20b`
- `ollama pull openchat`

5) Run the app

- `php artisan serve`
- Open the URL shown (e.g., `http://127.0.0.1:8000`)

## Using the App

- If not signed in, the home view shows an empty state with Sign in and Create account buttons.
- Click the user icon in the top bar to open the account menu (Sign in, Sign up, Sign out, Switch account).
- Bottom‑left shows sign‑in status and the current account.
- Create chats from the sidebar (New chat) or start typing to create implicitly.
- Choose a model from the top bar. Changing the model while a chat is selected updates just that chat.
- The assistant streams replies live. Markdown tables and Mermaid blocks render nicely; RTL text auto‑aligns.
- Collapse/expand and resize the sidebar from the top‑left chevron and divider.

## Architecture Overview

Back‑end (Laravel)

- LLM client: `app/Services/LlmClient.php`
- REST routes: `routes/api.php`
- Controllers: `app/Http/Controllers/*`
  - `ChatController` (chats CRUD)
  - `MessageController` (list + send, with streaming endpoint)
  - `AuthController` (login, register, logout, me)
  - `Admin/UserAdminController` (list users, block/unblock)
- Auth middleware (token): `app/Http/Middleware/AuthToken.php`
- Config: `config/llm.php` (base URL, model names, system prompt)
- Migrations: `database/migrations/*` (UUID ids, JSONB, auth fields)
- Seeders: `database/seeders/AdminSeeder.php`, `DemoSeeder.php`, `DatabaseSeeder.php`

Front‑end (vanilla JS ES modules)

- Entry point: `public/js/chat/main.js` (bootstraps UI, loads data)
- Modules: `public/js/chat/`
  - `api.js` — fetch wrappers with token + 401 retry
  - `auth.js` — token storage, login/register/logout
  - `authUI.js` — user menu + modal (login/sign‑up)
  - `stream.js` — streaming send (SSE), smooth renderer
  - `ui.js` — message bubbles, chat list items
  - `emptyState.js` — signed‑out welcome state (CTAs)
  - `markdown.js` — Markdown + sanitize + Mermaid
  - `rtl.js` — direction detection and application
  - `sidebar.js` — resize + collapse behavior
  - `composer.js` — auto‑resize + RTL for the input
  - `dom.js`, `state.js`, `util.js` — helpers
- Views: Blade components render the layout and slots
  - Layout: `resources/views/components/layouts/app.blade.php`
  - Chat page: `resources/views/chat.blade.php`
  - Chat components: `resources/views/components/chat/*`
  - Auth modal: `resources/views/components/auth/modal.blade.php`

## API Reference (selected)

Auth

- `POST /api/auth/register` — body `{ name, email, password }` → `{ token, user }`
- `POST /api/auth/login` — body `{ email, password }` → `{ token, user }`
- `POST /api/auth/logout` — requires `Authorization: Bearer <token>`
- `GET /api/auth/me` — returns `{ id, name, email, role }`

Chats and messages (all require `Authorization: Bearer <token>`)

- `GET /api/chats` — list chats (id, title, settings, created_at)
- `POST /api/chats` — create chat `{ settings: { model } }` → chat
- `PATCH /api/chats/{id}` — partial update `{ title? , settings? }`
- `DELETE /api/chats/{id}` — delete chat + cascade messages
- `GET /api/chats/{id}/messages` — list messages
- `POST /api/messages` — add message (non‑stream)
- `POST /api/messages/stream` — stream assistant reply (SSE)
- `GET /api/models` — returns `[LLM_MODEL, LLM_MODEL2]`

Admin (admin token required)

- `GET /api/admin/users` — list users
- `POST /api/admin/users/{user}/block`
- `POST /api/admin/users/{user}/unblock`

Headers

- Most endpoints require `Authorization: Bearer <token>` and `Accept: application/json`.
- Streaming uses `Accept: text/event-stream`.

## LLM Configuration

- Default base URL: `LLM_BASE_URL` (`http://127.0.0.1:11434`)
- Models: `LLM_MODEL` and `LLM_MODEL2` define the dropdown options and defaults.
- System prompt: `LLM_SYSTEM` (used on every conversation start)

## Troubleshooting

- “Not signed in” or 401 loop
  - Clear token in DevTools Console:
    - `localStorage.removeItem('apiToken'); location.reload();`
  - Sign in via the top‑bar user menu or empty state buttons.
- Windows: `php artisan db` error (TTY not supported)
  - Use `php artisan db:seed` (or `--class=`) instead of `php artisan db`.
- Postgres: `uuid-ossp`
  - Ensure your DB user can `CREATE EXTENSION "uuid-ossp";` or pre‑install the extension.
- Streaming displays literal pipes (`|`)
  - Ask the model to output raw Markdown tables instead of fenced code blocks. GFM tables render and are sanitized.
- Assistant reply disappears after streaming
  - Fixed: streamed content is preserved; we refresh only the chat list (for titles).

## Security Notes

- Token auth is stored in localStorage for simplicity; prefer HTTPS and token rotation in production.
- Markdown output is sanitized with DOMPurify. Only common attributes and tags are allowed.
- Admin block prevents blocked users from authenticating to protected APIs.

## License

This project is provided as‑is, without warranty. Add a license file if you plan to distribute it.

