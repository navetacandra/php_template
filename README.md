# PHP Micro-Framework

A tiny, **zero-dependency** PHP micro-framework for small JSON APIs and
server-rendered applications. It is intentionally explicit: no Composer, no
autoloader, no build step, and no hidden container. Core classes live in the
global namespace and are loaded with `require_once`.

The framework gives you a small set of composable pieces:

- a router with parameters, wildcards, and multiple handlers per route
- a lazy PDO connection with prepared statements and transactions
- static repository, service, and controller base classes
- request/response helpers, view rendering, and validation
- an `.env` loader, JSON-line logger, file cache, and local storage helper

```php
// routes.php
$router->get("/ping", fn () => Response::json([
    "status" => "success",
    "data" => ["pong" => true],
]));
```

---

## Features

| Component | What it provides |
|---|---|
| **Router** | `GET`, `POST`, `PUT`, `PATCH`, `DELETE`, plus `all()`; `:param` segments; `*` wildcards; multiple handlers; form `_method` override; automatic `404`/`405`; controller lazy-loading; subdirectory-aware `BASE_URL` |
| **Database** | Lazy persistent PDO singleton, `DB::query()` with bound parameters and optional PDO types, transactions |
| **Repository** | Static CRUD base (`all`, `find`, `findOne`, `insert`, `update`, `delete`) configured by `$table` and `$fillable` |
| **Service / Controller** | Minimal typed base classes enforcing an explicit `Controller → Service → Repository` layering |
| **Request** | Query/post access, JSON body decoding, headers, uploaded files, merged request data |
| **Response / Views** | JSON, redirects, status codes, and plain-PHP view rendering from `app/views` |
| **Validator** | Rule/message maps for strings, numbers, e-mail, UUIDs, enums, patterns, and file uploads |
| **Storage** | File helpers rooted at the project `storage/` directory |
| **Cache** | File-backed cache with TTL, `remember()`, and wildcard deletion |
| **Environment** | `.env` parser populating `$_ENV` |
| **Logging** | JSON-line logs with a per-request ID in `storage/system.log` |

Everything is plain PHP `>= 8.1` with `declare(strict_types=1)` and no
third-party dependencies.

## Requirements

- PHP **>= 8.1** with PDO and a matching driver (MySQL/MariaDB by default)
- Apache with `mod_rewrite` for the bundled `.htaccess` setup, or the PHP
  built-in server for development
- A database and schema created manually — there are no migrations or seeds

## Quick Start

```bash
git clone https://github.com/navetacandra/php_template.git php_template
cd php_template
```

1. Copy `.env.example` to `.env` and fill in the required `DB_*` values:

   ```dotenv
   DB_DRIVER=mysql
   DB_HOST=localhost
   DB_PORT=3306
   DB_NAME=my_app
   DB_USER=root
   DB_PASS=root
   ```

2. Create the database and tables with any SQL client.

3. Point your web server at the project root or at `public/`:
   - **Project root** — the bundled `.htaccess` maps existing files in
     `public/` and sends everything else to `index.php`.
   - **`public/` as document root** — `public/index.php` includes the root
     `index.php`.

   For quick API testing during development:

   ```bash
   php -S localhost:8000 index.php
   ```

4. Add routes in `routes.php` and application classes under `app/`.
   The bundled `routes.php` (`/` and `/ping`) and `tes` view are demo files;
   replace them with your application routes and classes. Controllers referenced
   as `["ControllerClass", "method"]` are loaded automatically by the router; only
   repositories, services, and optional core services (`Log`, `Storage`,
   `Validator`, `Cache`) need explicit `require_once`.

> The bundled root `.htaccess` contains the folder name `php_template` in its
> static-file rewrite condition. Change it if your project directory has a
> different name.

Full API documentation: **[DOCS.md](DOCS.md)**.

## Project Structure

```text
.
├── index.php                 # Front controller: core requires, Env::load, router, routes, run
├── routes.php                # Route declarations (required by index.php)
├── .env                      # Local configuration (ignored by git)
├── .env.example              # Configuration template
├── .htaccess                 # Front-controller/static-file rewrite rules
├── public/
│   ├── index.php             # Optional entry point when public/ is the document root
│   └── storage/              # Publicly served files (index listing disabled)
├── storage/                  # Internal storage root used by Storage and logs
└── app/
    ├── .htaccess             # Deny from all
    ├── controllers/          # HTTP controllers (loaded lazily by the router)
    ├── services/             # Business logic
    ├── repositories/         # Data access
    ├── views/                # Plain-PHP views (*.view.php)
    └── core/
        ├── Log.php           # Log — JSON-line logger (required manually)
        ├── Env.php           # Env — .env loader
        ├── Database.php      # DB — lazy PDO wrapper
        ├── Storage.php       # Storage — file helpers (required manually)
        ├── Validator.php     # Validator — rule-based validation (required manually)
        ├── Repository.php    # Repository — static CRUD base
        ├── Service.php       # Service — base service
        ├── Controller.php    # Controller — base controller
        ├── Request.php       # Request — HTTP input
        ├── Response.php      # Response — JSON/redirect/view
        ├── Router.php        # Router — routing and dispatch
        └── Cache.php         # Cache — file cache (required manually)
```

Architecture:

```text
Route → Controller → Service → Repository → DB (PDO)
```

## Request Lifecycle

1. `index.php` requires the core classes in dependency order.
2. `Env::load()` reads `.env` into `$_ENV`.
3. A `Router` is created and `routes.php` registers routes on it.
4. `$router->run()` resolves the method and path, defines `BASE_URL`, extracts
   route parameters, and invokes the matching handler(s).
5. Handlers build a `Response` (`json()`, `redirect()`, or `render()`) or return
   data to the next handler.

The default bootstrap loads `Env`, `DB`, the base layers, and the HTTP classes.
`Log`, `Storage`, `Validator`, and `Cache` are optional and must be
`require_once`d before use.

## Documentation

| Document | Content |
|---|---|
| [DOCS.md](DOCS.md) | Complete reference: bootstrap, environment, logging, database, request/response, router, repository, services/controllers, validator, storage, cache, deployment, and limitations |
| README.md | Project overview (this file) |

## License

Released under the [MIT License](LICENSE).
