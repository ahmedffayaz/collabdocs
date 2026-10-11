# Architecture and product choices

## Product slice

Ajaia Docs treats a document as a small, durable workspace object. The home screen emphasizes recent work and ownership. The editor uses a focused paper surface with an unobtrusive formatting toolbar, autosave state, and an access panel. Import creates a document so a file becomes editable immediately.

## Stack and data

- Laravel 12 serves the page and JSON endpoints. Vue 3 owns the interactive workspace; Tiptap supplies structured rich-text editing.
- Controllers delegate document and demo-account queries to repositories. Reusable support classes sanitize editor HTML and prepare document payloads; one response helper keeps API responses consistent.
- API endpoints have Laravel route names. The Blade entry view passes their generated URLs to Vue, avoiding hard-coded endpoint paths in the client.
- `App.vue` is the Vue root and renders the documents page. Shared header, footer, and toast components are registered in `app.js`; `documentService` handles application requests and response unwrapping, while `api/api.js` owns HTTP endpoint calls.
- MySQL stores documents and a `document_shares` join table. Documents keep HTML with non-approved tags and attributes removed, which preserves headings, emphasis, and lists without introducing a larger JSON schema for this scope.
- Each document has one owner. A share row grants `editor` or `viewer` access. Authorization is checked in the controller before reads and writes.
- Three seeded accounts and a session-backed account switcher make collaboration reviewable without spending the timebox on login, password reset, or email delivery.
- Markdown is rendered as HTML; plain text is escaped and paragraphized. `.docx` files are converted to HTML in the browser. Imports are limited to 2 MB, and complex Word layout or embedded images may not be preserved.

## Priorities and tradeoffs

The implementation prioritizes the create/edit/save/reopen loop and permission behavior over real-time cursors, comments, document history, and complex invitation delivery. Autosave is debounced and remains explicit in the UI. Share permissions are intentionally simple and the demo account switcher is clearly a prototype shortcut. A production version should replace that switcher with authenticated users, strengthen HTML sanitization, add concurrency handling, and move uploads to managed storage.

## Verification

Feature tests exercise editor access and verify that viewers can read but cannot write. Local review should include account switching, title and content autosave, Markdown/text import, and refresh persistence.
