# Ajaia Docs

A small collaborative document workspace built with Laravel 12, Vue 3, Tiptap, and MySQL. It focuses on a calm writing surface, autosave, useful file import, and a sharing flow that is easy to review.

## Run locally

Requirements: PHP 8.2+, Composer, Node.js 20+, npm, and a running MySQL server.

```sh
composer install
npm install
copy .env.example .env       # Windows PowerShell: Copy-Item .env.example .env
php artisan key:generate
```

Start MySQL and create the application database. For example, in phpMyAdmin or a MySQL client:

```sql
CREATE DATABASE ajaia_docs CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Set the database host, username, and password in `.env` (`DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD`). Then create the tables and seeded demo workspace:

```sh
php artisan migrate --seed
```

The provided environment files use MySQL on `127.0.0.1:3306`, database `ajaia_docs`, and the common local XAMPP default of `root` with no password. Change the credentials to match your MySQL setup. Then start the app and asset server in separate terminals:

```sh
php artisan serve
npm run dev
```

Open `http://127.0.0.1:8000`. For a production asset bundle, run `npm run build` and serve the Laravel `public` directory through a PHP host.

## Demo accounts

There is no password login in this timeboxed demo. The account switcher at the bottom left simulates the signed-in user; the server stores the selected demo user in the session.

| Name | Email | Seeded access |
| --- | --- | --- |
| Alex Morgan | `alex@ajaia.test` | Owner of the sample document |
| Sam Rivera | `sam@ajaia.test` | Editor on the sample document |
| Jordan Lee | `jordan@ajaia.test` | Available to invite |

The sample document starts shared with Sam as an editor. From Alex's account, share with Jordan as an editor or viewer. Switch to Jordan to inspect the shared workspace and permission behavior.

## Features and limits

- Create, rename, reopen, and autosave documents. Rich text supports bold, italic, underline, headings, and ordered or unordered lists.
- Export a document, including unsaved editor changes, as a downloadable PDF.
- Import `.md`, `.txt`, and `.docx` files up to 2 MB as new editable documents. Word files are converted in the browser; embedded images and advanced Word layout may not be preserved.
- Share with another seeded demo account as an editor or viewer. Owners manage access; editors can edit; viewers are read-only.
- Documents and access records persist in MySQL. The server removes unapproved HTML tags and attributes before saving editor content.
- The API uses a consistent JSON envelope. Named Laravel routes supply endpoint URLs to the Vue client.
- This is a single-process demo with a simulated account selector, not production authentication. Real deployments need authentication, per-user authorization, CSRF/session configuration, and managed database/storage settings.

## Tests

```sh
php artisan test
```

Feature tests cover editor sharing and viewer write protection.

## Deployment and review

No public deployment URL is configured in this workspace. The app can be deployed to any PHP 8.2+ host with MySQL; configure the production environment, run migrations and seeders, and build Vite assets. `SUBMISSION.md` tracks the deliverables and the items that still require a deployment account.
