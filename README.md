# Redis Admin Panel

A modern Redis management panel built with Laravel 13, Inertia.js, and Vue 3.

It provides a clean UI for browsing Redis databases, inspecting key contents, editing values, managing TTLs, renaming or deleting keys, and performing bulk operations. The app also includes authentication, profile management, theme switching, localization, and adjustable dashboard width preferences.

## Features

- Browse Redis databases and namespaces from a tree-based dashboard
- Inspect `string`, `hash`, `list`, `set`, and `zset` values
- Edit values, update TTL, rename keys, and delete keys
- Bulk delete keys and flush an entire Redis database
- Light, dark, and system theme support
- English and Turkish localization
- Adjustable dashboard width (`normal` / `wide`)
- Laravel auth flow with profile management

## Tech Stack

- Laravel 13
- PHP 8.3+
- Inertia.js
- Vue 3
- Vite
- Tailwind CSS
- Predis
- Ziggy

## Requirements

- PHP 8.3 or newer
- Composer
- Node.js 20+ and npm
- A running Redis server

## Installation

1. Clone the repository.

```bash
git clone <your-repository-url>
cd redis-admin
```

2. Install dependencies.

```bash
composer install
npm install
```

3. Create your environment file.

```bash
cp .env.example .env
php artisan key:generate
```

4. Configure the database for Laravel auth data.

The default `.env.example` uses SQLite. Create the database file if needed:

```bash
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
```

Then run migrations:

```bash
php artisan migrate
```

5. Configure Redis connection settings in `.env`.

```env
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

6. Start the development servers.

```bash
composer run dev
```

This starts:

- Laravel application server
- Queue listener
- Laravel Pail log stream
- Vite dev server

## Available Scripts

```bash
composer run dev
composer run test
npm run dev
npm run build
```

## Running Tests

```bash
php artisan test
```

## Production Build

```bash
npm run build
```

## Configuration Notes

- Default UI language is English
- Supported languages: English and Turkish
- Supported themes: light, dark, system
- Dashboard width preference is stored per browser using cookies and local storage
- Redis operations are protected behind authenticated and verified user routes

## Project Structure

- `app/Http/Controllers/RedisAdminController.php`: Redis dashboard and key management actions
- `resources/js/Pages/Dashboard.vue`: Main Redis admin interface
- `resources/js/Components/ThemeLangSelector.vue`: Settings dropdown for language, theme, and width
- `resources/js/composables/useSettings.js`: Client-side preference persistence
- `app/Http/Middleware/ApplyUserPreferences.php`: Server-side locale and preference bootstrap

## Security

This panel can perform destructive Redis operations such as deleting keys and flushing databases. Do not expose it publicly without proper authentication, HTTPS, and access controls.

## License

This project is open-sourced under the MIT license.
