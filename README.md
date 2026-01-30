# UChat — Laravel + Ollama Chat (Streaming + Archive RAG)

UChat is a local‑first ChatGPT‑style web app built with Laravel. It talks to a locally running open‑source LLM (via Ollama), supports streaming responses, Markdown tables and Mermaid diagrams (sanitized), right‑to‑left text, and an optional archive‑grounded RAG mode backed by Postgres (pgvector + full‑text search).

## Features

- Streaming chat to a local LLM (Ollama)
- Chats CRUD: create, rename, delete, list
- Per‑chat settings: model (selectable from env‑configured list) + persona
- Archive RAG mode: hybrid retrieval (semantic + lexical) with filters and source metadata
- Markdown rendering with tables and Mermaid diagrams (sanitized)
- Automatic RTL/LTR detection (Arabic/Hebrew supported)
- Auto‑title from the chat start (first user + assistant)
- Sidebar: resizable and collapsible; subtle gradient background
- Auth (session‑based via Laravel Breeze), Sign in - تسجيل الدخول / Create account - إنشاء حساب UI
- Admin role (block/unblock users), seeded admin
- Modular front‑end (vanilla JS ES modules under `public/js/chat/`)

## What Makes UChat Unique

- Local‑first stack: Laravel + locally hosted Ollama models with streaming SSE and per‑chat model overrides.
- Persona routing system: Auto - تلقائي uses an LLM classifier with keyword fallback; personas override generation settings (temperature/top_p) per chat.
- Archive RAG pipeline: optional archive mode injects system context from hybrid search (Postgres HNSW vectors + lexical scoring) with alpha/beta weighting and filters (category, country, city, date range, breaking).
- Source‑aware answers: archive context includes citation rules, and structured sources are attached to message metadata for UI display.
- Smarter auto‑titles: titles are generated from the conversation start (first user + assistant) with a heuristic fallback.
- Journalist‑friendly UI: RTL/LTR auto‑direction, sanitized Markdown + Mermaid, archive badges, sources panels, and copy/download actions.

## Requirements

- PHP 8.2+
- Composer
- Node.js + npm (for Vite assets)
- A database (PostgreSQL recommended; migrations create `uuid-ossp`, `unaccent`, and `vector` extensions)
- Ollama running locally (default `http://127.0.0.1:11434`)

## Quick Start

1) Install dependencies

- `composer install`
- `npm install`
- (Optional) `php artisan key:generate` (if `APP_KEY` missing)

2) Configure `.env`

- Copy `.env.example` to `.env` and fill in values.
- LLM
  - `LLM_BASE_URL=http://127.0.0.1:11434`
  - `LLM_MODEL=gpt-oss:20b` (no trailing spaces)
  - `LLM_MODEL2=openchat` (second selectable model)
  - `LLM_NUM_PREDICT=1024` (max output tokens; increase if replies cut off)
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

- Email - البريد الإلكتروني: `admin@example.com`
- Password - كلمة المرور: `A@admin123`

4) Ensure models are present in Ollama

- `ollama pull gpt-oss:20b`
- `ollama pull openchat`
- Start Ollama if needed:
  - `ollama serve`
  - or `node scripts/start-ollama.js`

5) Run the app

- `npm run dev` (Vite)
- `php artisan serve` (Laravel)
- Open the URL shown (e.g., `http://127.0.0.1:8000`)

## Optional: Build the Archive Index (Chunk + Embed)

If you ingest news into the `news` table, a DB trigger enqueues items into `news_ingest_queue`. To process the queue and build `news_chunks` embeddings:

- `php artisan ingest:queue`

Optional summaries (used by the RAG context builder when available):

- `php artisan summaries:backfill --limit=100`
- `php artisan summaries:queue`

## Using the App

- If not signed in, the home view shows an empty state with Sign in - تسجيل الدخول and Create account - إنشاء حساب buttons.
- Click the user icon in the top bar to open the account menu (Profile - الملف الشخصي, Admin Control - إدارة المشرف, Sign out - تسجيل الخروج).
- Bottom‑left shows sign‑in status (Signed in - مسجّل الدخول / Not signed in - غير مسجّل الدخول) and the current account.
- Create chats from the sidebar (New chat - محادثة جديدة) or start typing to create implicitly.
- Choose a model (Model - النموذج) and persona (Persona - الشخصية) from the top bar. Changing them while a chat is selected updates just that chat.
- Personas available: Auto - تلقائي, Assistant - مساعد, Author - كاتب, Reporter - مراسل, Summarizer - المختصر.
- The assistant streams replies live. Markdown tables and Mermaid blocks render nicely; RTL text auto‑aligns.
- Collapse/expand and resize the sidebar from the top‑left chevron and divider.

## Architecture Overview

Back‑end (Laravel)

- LLM client: `app/Services/LlmClient.php`
- Routes: `routes/web.php` (includes `/api/*` endpoints under session auth)
- Controllers:
  - `app/Http/Controllers/ChatController.php` (chats CRUD)
  - `app/Http/Controllers/Messages/MessageIndexController.php` (list messages)
  - `app/Http/Controllers/Messages/MessageSendController.php` (non‑stream chat)
  - `app/Http/Controllers/Messages/MessageStreamController.php` (SSE streaming chat)
  - `app/Http/Controllers/HybridSearchController.php` (archive search page)
  - `app/Http/Controllers/FilterOptionsController.php` (distinct filter options)
  - `app/Http/Controllers/Admin/UserAdminController.php` (admin user management)
- Archive services:
  - `app/Services/HybridSearchService.php` (calls `hybrid_search_docs(...)`)
  - `app/Services/ArchiveRagService.php` (builds `<<ARCHIVE>>` context + sources)
  - `app/Services/ArchiveQueryRewriter.php` (optional query rewrite)
  - `app/Services/ArchiveUseDecider.php` + `app/Services/ArchiveFilterRouter.php` (auto routing)
  - `app/Services/NewsSummaryService.php` + summary queue workers (optional)
- Config: `config/llm.php` (base URL, model names, system prompt)
- RAG config: `config/rag.php`
- Migrations: `database/migrations/*` (UUID ids, JSONB, pgvector + SQL search functions)
- Seeders: `database/seeders/AdminSeeder.php`, `DemoSeeder.php`, `DatabaseSeeder.php`

Front‑end (vanilla JS ES modules)

- Entry point: `public/js/chat/main.js` (bootstraps UI, loads data)
- Modules grouped by concern under `public/js/chat/`:
  - `core/` — low‑level helpers and state
    - `dom.js`, `state.js`, `rtl.js`, `util.js`
  - `api/` — HTTP utilities
    - `api.js` (fetch wrappers and helpers)
  - `ui/` — presentation helpers
    - `ui.js` (bubbles, chat list), `markdown.js`, `emptyState.js`
  - `features/` — self‑contained feature logic
    - `sidebar.js`, `composer.js`, `stream.js`

Conventions

- JS: small modules, clear names, JSDoc where helpful. Avoid global state except `core/state.js`.
- PHP: thin controllers, docblocks on public endpoints; reusable utility in `app/Support`.
- Styling: one accent color via CSS vars; light/dark handled in the main layout.
- Views: Blade components render the layout and slots
  - Layout: `resources/views/layouts/app.blade.php`
  - Chat page: `resources/views/pages/chat/index.blade.php`
  - Chat components: `resources/views/components/chat/*`

## API Reference (selected)

Auth

- Session auth via Laravel Breeze (routes in `routes/auth.php`); users sign in via the UI.

Chats and messages (require an authenticated session cookie)

- `GET /api/chats` — list chats (id, title, settings, created_at)
- `POST /api/chats` — create chat `{ settings: { model } }` → chat
- `PATCH /api/chats/{id}` — partial update `{ title? , settings? }`
- `DELETE /api/chats/{id}` — delete chat + cascade messages
- `GET /api/chats/{id}/messages` — list messages
- `POST /api/messages` — add message (non‑stream)
- `POST /api/messages/stream` — stream assistant reply (SSE)
- `GET /api/filter-options` — distinct values for filters (categories/countries/cities/date range)

Public

- `GET /api/models` — returns `[LLM_MODEL, LLM_MODEL2]`

Admin (admin session required)

- `GET /api/admin/users` — list users
- `POST /api/admin/users/{user}/block`
- `POST /api/admin/users/{user}/unblock`

Headers

- Most endpoints are called from the web UI and rely on cookies + CSRF:
  - `Accept: application/json`
  - `X-CSRF-TOKEN: <csrf>`
  - `X-Requested-With: XMLHttpRequest`
- Streaming uses `Accept: text/event-stream`.

## LLM Configuration

- Default base URL: `LLM_BASE_URL` (`http://127.0.0.1:11434`)
- Models: `LLM_MODEL` and `LLM_MODEL2` define the dropdown options and defaults.
- System prompt: `LLM_SYSTEM` (used on every conversation start)
- Max output tokens: `LLM_NUM_PREDICT` (increase if responses stop early)

## Troubleshooting

- 401/403/419 issues while calling `/api/*`
  - Make sure you are signed in (session cookie required).
  - For POST/PATCH/DELETE requests, ensure your request includes `X-CSRF-TOKEN` (the web UI already does).
- Windows: `php artisan db` error (TTY not supported)
  - Use `php artisan db:seed` (or `--class=`) instead of `php artisan db`.
- Postgres: `uuid-ossp`
  - Ensure your DB user can `CREATE EXTENSION "uuid-ossp";` or pre‑install the extension.
- Streaming displays literal pipes (`|`)
  - Ask the model to output raw Markdown tables instead of fenced code blocks. GFM tables render and are sanitized.
- Assistant reply disappears after streaming
  - Fixed: streamed content is preserved; we refresh only the chat list (for titles).

## Security Notes

- Session auth + CSRF protection are used for `/api/*` endpoints (intended for the web UI).
- Chat ownership checks are enforced server-side before writing messages.
- Markdown output is sanitized with DOMPurify. Only common attributes and tags are allowed.
- Admin block prevents blocked users from accessing protected areas.

## License

This project is provided as‑is, without warranty. Add a license file if you plan to distribute it.
