# PHP Micro-Framework — Technical Documentation

Complete reference for the micro-framework in this repository. It documents the
core classes in `app/core/`, the bootstrap sequence, the request lifecycle, and
the conventions for building features on top of them.

> **Audience:** developers using this project as an application template.
> **Project overview:** see [README.md](README.md).

---

## Table of Contents

1. [Architecture & Request Lifecycle](#1-architecture--request-lifecycle)
2. [Requirements, Installation & Structure](#2-requirements-installation--structure)
3. [Loading Model](#3-loading-model)
4. [Environment — `Env`](#4-environment--env)
5. [Logging — `Log`](#5-logging--log)
6. [Database — `DB`](#6-database--db)
7. [HTTP Input — `Request`](#7-http-input--request)
8. [HTTP Output & Views — `Response`](#8-http-output--views--response)
9. [Routing — `Router`](#9-routing--router)
10. [Data Access — `Repository`](#10-data-access--repository)
11. [Layering — `Service` & `Controller`](#11-layering--service--controller)
12. [Validation — `Validator`](#12-validation--validator)
13. [Files — `Storage`](#13-files--storage)
14. [Caching — `Cache`](#14-caching--cache)
15. [Complete Feature Example](#15-complete-feature-example)
16. [Web Server, Public Files & URLs](#16-web-server-public-files--urls)
17. [Limitations & Gotchas](#17-limitations--gotchas)

---

## 1. Architecture & Request Lifecycle

This is a **micro-framework**, not a full-stack framework. It provides a small
set of core services and leaves application structure, dependency wiring,
authentication, migrations, and deployment choices to you.

Key design choices:

- **No Composer, no autoloader.** Classes are in the global namespace and loaded
  with `require_once`.
- **No container, no annotations, no configuration objects.** Wiring is explicit
  PHP in constructors and `routes.php`.
- **No middleware pipeline.** Multiple handlers per route and plain PHP are the
  extension points.
- **Plain PHP views.** No template engine; views are included PHP files.

### Bootstrap

The front controller is intentionally small:

```php
// index.php
<?php declare(strict_types=1);

// Main
require_once __DIR__ . "/app/core/Env.php";
require_once __DIR__ . "/app/core/Database.php";

// Handler
require_once __DIR__ . "/app/core/Repository.php";
require_once __DIR__ . "/app/core/Service.php";
require_once __DIR__ . "/app/core/Controller.php";

// HTTP
require_once __DIR__ . "/app/core/Request.php";
require_once __DIR__ . "/app/core/Response.php";
require_once __DIR__ . "/app/core/Router.php";

Env::load(__DIR__ . "/.env");

$router = new Router();
require_once __DIR__ . "/routes.php";
$router->run();
```

The default bootstrap loads only the classes needed for routing, database access,
and the base layers. **`Log`, `Storage`, `Validator`, and `Cache` are not loaded
by `index.php`.** Require them explicitly when used:

```php
require_once __DIR__ . "/app/core/Log.php";
require_once __DIR__ . "/app/core/Storage.php";
require_once __DIR__ . "/app/core/Validator.php";
require_once __DIR__ . "/app/core/Cache.php";
```

`Env` and `DB` contain lazy `require` calls for `Log` on their failure paths.
`Router.php` creates the global `$requestId` used by `Log`; because the router
file is loaded before `Env::load()` runs, a request ID is available during
bootstrap.

### Lifecycle

```text
HTTP request
  → index.php (or public/index.php)
  → default core requires     → Env, DB, repository/service/controller, HTTP classes
  → Env::load(.env)           → populate $_ENV
  → new Router()
  → require routes.php        → register routes
  → Router::run()
      → resolve method/path
      → define BASE_URL
      → extract :params
      → instantiate Request once
      → invoke handlers in order
          → Controller → Service → Repository → DB
          → Response::json/redirect/render
  → response sent
```

`Log`, `Storage`, `Validator`, and `Cache` are optional core services. They are
**not** loaded by `index.php` and must be required explicitly if your handlers
use them.

---

## 2. Requirements, Installation & Structure

### Requirements

| Requirement | Notes |
|---|---|
| PHP **>= 8.1** | Uses `array_is_list()`, `str_contains()`, union types, `mixed`, and `declare(strict_types=1)` |
| PDO extension | `pdo_mysql` by default; any PDO-supported driver can be configured through `DB_DRIVER` |
| Web server | Apache with `mod_rewrite` for the bundled `.htaccess`; PHP built-in server is fine for development |
| Database | MySQL/MariaDB by default; schema is created manually |

There is no Composer dependency, no Node build step, and no migration/seed/CLI
tooling.

### Directory structure

```text
.
├── index.php                  # Front controller
├── routes.php                 # Route declarations
├── .env                       # Local configuration (git-ignored)
├── .env.example               # Configuration template
├── .htaccess                  # Rewrite rules for the project-root setup
├── public/
│   ├── index.php              # Alternative entry point for a public/ document root
│   └── storage/               # Publicly served files
├── storage/                   # Internal storage (Storage class and logs)
└── app/
    ├── .htaccess              # Deny from all
    ├── controllers/           # Controllers (loaded lazily by the router)
    ├── services/              # Services
    ├── repositories/          # Repositories
    ├── views/                 # Views: <name>.view.php
    └── core/
        ├── Log.php            # Log (optional)
        ├── Env.php            # Env
        ├── Database.php       # DB
        ├── Storage.php        # Storage (optional)
        ├── Validator.php      # Validator (optional)
        ├── Repository.php     # Repository
        ├── Service.php        # Service
        ├── Controller.php     # Controller
        ├── Request.php        # Request
        ├── Response.php       # Response
        ├── Router.php         # Router
        └── Cache.php          # Cache (not loaded by default)
```

### Installation

1. Clone/copy the project and create `.env` from `.env.example`.
2. Fill in every `DB_*` variable. There are **no fallback defaults**.
3. Create the database and tables manually.
4. Serve the project root or `public/` (see [section 16](#16-web-server-public-files--urls)).
5. Register application routes in `routes.php`.

For quick development:

```bash
php -S localhost:8000 index.php
```

---

## 3. Loading Model

### Default core classes

`index.php` requires the default core classes manually, grouped by purpose:

```php
// Main
require_once __DIR__ . "/app/core/Env.php";
require_once __DIR__ . "/app/core/Database.php";

// Handler
require_once __DIR__ . "/app/core/Repository.php";
require_once __DIR__ . "/app/core/Service.php";
require_once __DIR__ . "/app/core/Controller.php";

// HTTP
require_once __DIR__ . "/app/core/Request.php";
require_once __DIR__ . "/app/core/Response.php";
require_once __DIR__ . "/app/core/Router.php";
```

`Repository` uses `DB`, and the service/controller base classes must exist before
application subclasses are loaded. `Router` uses `Request` and `Response`.

### Optional core services

The following files exist but are **not required by `index.php`**:

| File | Class | Load it when |
|---|---|---|
| `app/core/Log.php` | `Log` | You call `Log::info()` / `warn()` / `error()` directly |
| `app/core/Storage.php` | `Storage` | You use file helpers |
| `app/core/Validator.php` | `Validator` | You validate request data |
| `app/core/Cache.php` | `Cache` | You use the cache |

```php
require_once __DIR__ . "/app/core/Log.php";
require_once __DIR__ . "/app/core/Storage.php";
require_once __DIR__ . "/app/core/Validator.php";
require_once __DIR__ . "/app/core/Cache.php";
```

Add these to `index.php` before `routes.php`, or to the top of `routes.php` before
any handler that uses them. `Env` and `Database` attempt a lazy `require` of
`Log.php` only on their own failure paths; application code should load `Log`
explicitly.

### Controllers

Controllers referenced by a route as `["UserController", "store"]` are loaded
lazily by the router:

```php
// Router internals
require_once __DIR__ . "/../controllers/$className.php";
(new $className())->$action($request, $params);
```

Therefore controllers used as `["UserController", "store"]` do **not** need to
be required in `index.php` or `routes.php`; the router requires them at dispatch
time. The file name must match the class name exactly: `UserController` →
`app/controllers/UserController.php`.

### Repositories and services

Repositories and services are **not** loaded automatically. Require them
explicitly, either in `index.php` before `routes.php`, or at the top of
`routes.php`:

```php
// routes.php
require_once __DIR__ . "/app/repositories/UserRepository.php";
require_once __DIR__ . "/app/services/UserService.php";
```

A service is only usable after its repository class has been loaded.

### Naming and namespaces

All classes are in the **global namespace**. Use the file-name convention:

```text
UserRepository   → app/repositories/UserRepository.php
UserService      → app/services/UserService.php
UserController   → app/controllers/UserController.php
sample view      → app/views/sample.view.php
```

Namespaced classes are not supported by the router's controller loader.

---

## 4. Environment — `Env`

`Env` is a minimal `.env` parser. It reads a file line by line and writes values
into `$_ENV`. It does **not** call `putenv()` or read `getenv()`.

### API

```php
Env::load(string $path);
```

- If the file does not exist, the method lazy-loads `Log.php` with
  `require __DIR__ . "/Log.php"`, writes `Log::warn("env file doesn't exists")`,
  and returns. Loading continues; `DB_*` values will only be needed when the
  database is first used.
- A UTF-8 BOM at the beginning of the file is removed.
- Lines are split on `\n` or `\r\n`.
- Truly empty lines are skipped.
- Lines whose trimmed form starts with `#` are treated as comments.
- Every other line is split at the **first** `=` into key and value.
- The key is used as-is (keys are not trimmed independently).
- Values are trimmed.

### Value parsing

| Form | Result |
|---|---|
| `KEY=value` | Unquoted value; trailing inline `# comment` is removed |
| `KEY="value # not a comment"` | Text inside double quotes, trimmed |
| `KEY='value # not a comment'` | Text inside single quotes, trimmed |
| `KEY=true` / `KEY=false` | Boolean `true` / `false` |
| `KEY=123` | Integer `123` |
| `KEY=1.5` | **Currently becomes integer `1`** |
| `KEY=` | Empty string |

Numeric parsing is based on `is_numeric()` followed by `intval()`. Only values
containing both `.` and `e` are cast with `floatval()`. Quote values if you need
exact strings or predictable decimals:

```dotenv
APP_DEBUG=true
DB_PORT=3306
DB_NAME=my_app
DB_USER=root
DB_PASS="p@ss#word"
```

### Configuration keys

The framework itself consumes the following keys:

| Variable | Consumed by | Meaning |
|---|---|---|
| `DB_DRIVER` | `DB::init()` | PDO driver / DSN prefix (e.g. `mysql`) |
| `DB_HOST` | `DB::init()` | Database host |
| `DB_PORT` | `DB::init()` | Database port |
| `DB_NAME` | `DB::init()` | Database name |
| `DB_USER` | `DB::init()` | Username |
| `DB_PASS` | `DB::init()` | Password |

`APP_DEBUG` is present in `.env.example` but is **not read by the framework**.

All six `DB_*` keys must be defined. `DB::init()` reads them directly from
`$_ENV` without fallback values; missing keys generate PHP warnings and an
invalid DSN.

> Apache `SetEnv` is not used by this loader. Configuration belongs in `.env`.

---

## 5. Logging — `Log`

`Log` writes one JSON object per line to:

```text
storage/system.log
```

`Log` is **not loaded by the default bootstrap**. Require it before using it:

```php
require_once __DIR__ . "/app/core/Log.php";
```

`Env::load()` and `DB::init()` lazy-load `Log.php` with
`require __DIR__ . "/Log.php"` on their own failure paths, so requiring it in the
bootstrap is only necessary when application code calls `Log` directly.

### API

```php
Log::info(mixed ...$message): void
Log::warn(mixed ...$message): void
Log::error(mixed ...$message): void
```

All three methods accept any number of values. Each argument is converted with
`print_r($value, true)` and the results are joined with a single space into the
log entry's `message` string.

```php
Log::info("User created", $id);
Log::error("Payment failed", $e->getCode(), $e->getMessage());
Log::warn("Payload", ["order_id" => $orderId]);
```

Each line has this shape:

```json
{"level":"INFO","requestId":"6addac2f41d8c","timestamp":"2026-Oct-25 07:13:51","message":"User created 42"}
```

- `level` is `INFO`, `WARN`, or `ERROR`.
- `requestId` is the global `uniqid()` created when `Router.php` is loaded.
- `timestamp` uses `date("Y-M-d H:i:s")`.
- `message` is the joined `print_r` output of all supplied arguments. Arrays and
  objects are rendered as `print_r` text, not as structured JSON fields.
- There is no separate `details` field.
- Writes use `FILE_APPEND | LOCK_EX`.
- JSON encoding uses `JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR`.
  Invalid UTF-8 in the rendered message can therefore throw an exception.

Operational notes:

- `storage/` must be writable by PHP.
- There is no log rotation, level filtering, or configurable destination.
- The path is hardcoded in `Log.php`.
- If `Log` is used before `Router.php` is included, `requestId` is `null`.

---

## 6. Database — `DB`

`DB` is a static, lazily connected PDO wrapper.

### Connection

The connection is created on the first call to `query()` or a transaction
method, then reused for the rest of the request:

```php
protected static ?PDO $pdo = null;
```

`DB::init()` builds the DSN as:

```text
{DB_DRIVER}:host={DB_HOST};port={DB_PORT};dbname={DB_NAME}
```

PDO options:

| Option | Value |
|---|---|
| `PDO::ATTR_ERRMODE` | `PDO::ERRMODE_EXCEPTION` |
| `PDO::ATTR_DEFAULT_FETCH_MODE` | `PDO::FETCH_ASSOC` |
| `PDO::ATTR_PERSISTENT` | `true` |

On failure, `DB::init()` lazy-loads `Log.php` with `require __DIR__ . "/Log.php"`,
logs the PDO error code and message with `Log::error()`, sets HTTP status `502`,
and calls `die()` with no body.

No charset is added to the DSN. Add one in `Database.php` if your setup
requires it.

### Query API

```php
DB::query(string $sql = "", array $parameter = [], array $types = []): PDOStatement
```

The method prepares the SQL, binds every parameter, executes the statement, and
returns the `PDOStatement`.

```php
$rows = DB::query("SELECT * FROM users WHERE active = 1")->fetchAll();
$row  = DB::query("SELECT * FROM users WHERE id = :id", ["id" => 42])->fetch();
$id   = DB::query("INSERT INTO users (name) VALUES (?)", [1 => "Sari"])->lastInsertId();
$n    = DB::query("UPDATE users SET name = :name WHERE id = :id", [
    "name" => "Sari",
    "id"   => 42,
])->rowCount();
```

### Parameter binding rules

`DB::query()` binds each array entry with `PDOStatement::bindValue()`. The
default type is `PDO::PARAM_STR`.

**Named placeholders** — keys may be written with or without the leading colon:

```php
DB::query("SELECT * FROM users WHERE id = :id", ["id" => 42]);
DB::query("SELECT * FROM users WHERE id = :id", [":id" => 42]);
```

**Positional placeholders** — keys must be **1-based**, because they are passed
directly to `bindValue()`:

```php
// Correct
DB::query("SELECT * FROM users WHERE age > ? AND city = ?", [1 => 18, 2 => "Bandung"]);

// Incorrect: key 0 raises a ValueError in PDO::bindValue()
DB::query("SELECT * FROM users WHERE age > ? AND city = ?", [18, "Bandung"]);
```

Repository-generated parameter arrays are associative and therefore work with
named placeholders.

### Explicit parameter types

`$types` is keyed like `$parameter`:

```php
DB::query(
    "SELECT * FROM users WHERE id = :id",
    ["id" => "42"],
    ["id" => PDO::PARAM_INT]
);

DB::query(
    "SELECT * FROM users LIMIT ? OFFSET ?",
    [1 => 10, 2 => 20],
    [1 => PDO::PARAM_INT, 2 => PDO::PARAM_INT]
);
```

Use `PDO::PARAM_INT` where the driver requires a real integer (for example
`LIMIT`/`OFFSET`). Everything else defaults to a string.

### Transactions

The example below uses `Log`; require `app/core/Log.php` explicitly if it is not
already loaded.

```php
DB::beginTransaction();
try {
    DB::query("UPDATE accounts SET balance = balance - ? WHERE id = ?", [1 => 100, 2 => 1]);
    DB::query("UPDATE accounts SET balance = balance + ? WHERE id = ?", [1 => 100, 2 => 2]);
    DB::commit();
} catch (Throwable $e) {
    DB::rollBack();
    Log::error("Transfer failed", $e->getMessage());
    Response::json(["status" => "error", "message" => "Transfer failed"], 500);
}
```

There is no query builder, ORM, migration system, or schema tooling. Use SQL or
the base repository.

---

## 7. HTTP Input — `Request`

The router creates one `Request` per dispatched route and passes it to every
handler in that dispatch. `Response::render()` creates a separate `Request`
instance for the view.

### API

| Method | Signature | Behavior |
|---|---|---|
| `json` | `json(): array` | Decodes the raw request body (`php://input`) as JSON |
| `header` | `header(string $field): ?string` | Returns a header value or `null` |
| `all` | `all(): array` | Merges `$_REQUEST` with normalized `$_FILES` |
| `get` | `get(string\|array $field = ''): mixed` | Query-string access |
| `post` | `post(string\|array $field = ''): mixed` | Form-body access |
| `file` | `file(string\|array $field): array` | Uploaded file(s) |

### `get()` and `post()`

```php
$request->get();                 // all of $_GET
$request->get("sort");           // $_GET["sort"] ?? null
$request->get(["sort", "page"]); // ["sort" => ..., "page" => ...]

$request->post();                // all of $_POST
$request->post("name");          // $_POST["name"] ?? null
$request->post(["name", "email"]);
```

Missing keys return `null`; there are no default values. An empty string `''`
means "return the whole array".

### `json()`

```php
$body = $request->json();
```

- The `Content-Type` header is not checked; the raw body is always decoded.
- Invalid JSON returns `[]`.
- Valid JSON that is not an object/array is cast to an array.
- Valid JSON `null` becomes `[]`.

### `header()`

```php
$authorization = $request->header("Authorization");
```

Header lookup is **case-sensitive** and uses the exact key returned by
`getallheaders()` (with `apache_request_headers()` as fallback). The header map
is cached after the first call.

### `all()`

```php
$data = $request->all();
```

This starts with `$_REQUEST` (query string, form body, and cookies, depending on
PHP's `request_order`) and then adds uploaded files:

- A single upload (`avatar`) is stored as the raw `$_FILES["avatar"]` array.
- A multiple upload (`avatar[]`) is normalized into a list of per-file arrays.

### `file()`

```php
$one  = $request->file("avatar");        // single file array or list
$many = $request->file(["avatar", "cv"]); // ["avatar" => ..., "cv" => ...]
```

For a single upload, the raw file entry is returned:

```php
[
    "name"     => "photo.jpg",
    "type"     => "image/jpeg",
    "size"     => 12345,
    "tmp_name" => "...",
    "error"    => 0,
]
```

For `name="avatar[]"`, the result is a list of entries with the same keys.
Unlike `get()`/`post()`, `file()` does not guard a missing field: accessing a
field that is not present in `$_FILES` emits a warning/error.

### Example

```php
$router->post("/login", function (Request $request, array $params) {
    $body  = $request->json();
    $name  = $request->post("name");
    $sort  = $request->get("sort");
    $token = $request->header("Authorization");
    $files = $request->file("avatar");
});
```

Route parameters are **not** available on `Request`; they are passed separately
as `$params`.

---

## 8. HTTP Output & Views — `Response`

`Response` is the single response API. `json()` and `redirect()` terminate the
request with `exit`; `render()` outputs a view and returns normally.

### API

| Method | Signature | Behavior |
|---|---|---|
| `status` | `status(int $status)` | Sets the HTTP status code if headers are not already sent |
| `json` | `json(array $data, int $status = 200)` | Sets `Content-Type: application/json`, echoes `json_encode($data)`, exits |
| `redirect` | `redirect(string $dest, int $status = 302)` | Sets `Location: $dest`, exits |
| `render` | `render(string $view, array $data = [], int $status = 200)` | Includes `app/views/<view>.view.php` |

### JSON

```php
Response::json(["status" => "success", "data" => $payload]);
Response::json(["status" => "error", "message" => "Not found"], 404);
```

Only arrays are accepted. There are no JSON encoding flags or options, so
slashes are escaped with PHP's defaults.

### Redirect

```php
$base = rtrim(BASE_URL, "/");
Response::redirect($base . "/login");
```

`BASE_URL` is defined by `Router::run()` before handlers execute. It is the
directory part of the executing script:

- project at domain root → `BASE_URL` is `/`
- project in `/php_template` → `BASE_URL` is `/php_template`

At the domain root, `BASE_URL . "/login"` produces `//login`, which is a
protocol-relative URL. Use `rtrim(BASE_URL, "/")` when building URLs.

### Views

`Response::render()` renders:

```text
app/views/<view>.view.php
```

Dots map to subdirectories:

| View name | File |
|---|---|
| `sample` | `app/views/sample.view.php` |
| `admin/users` | `app/views/admin/users.view.php` |

Inside the view:

- every key in `$data` is extracted into a local variable;
- a fresh `$request` instance is available;
- output is plain PHP, no escaping is applied automatically.

```php
// controller
Response::render("sample", ["title" => "Hello"]);
```

```php
<!-- app/views/sample.view.php -->
<h1><?= htmlspecialchars($title) ?></h1>
<p><b>User-Agent:</b> <?= htmlspecialchars($request->header("User-Agent") ?? "") ?></p>
```

`render()` does not exit and does not use output buffering or a layout system.
The status and `Content-Type: text/html` header are set only when headers have
not already been sent.

---

## 9. Routing — `Router`

Routes are registered on the `$router` instance in `routes.php`. `index.php`
requires `routes.php` after creating `$router` and before calling `run()`.
The bundled `routes.php`, `SampleController`, and `tes` view are demo files;
replace or remove them when building your application.

```php
// routes.php
$router->get("/", function (Request $request, array $params) {
    Response::render("home");
});

$router->post("/users", ["UserController", "store"]);
```

### Registration methods

```php
$router->get(string $route, callable|array ...$handlers);
$router->post(string $route, callable|array ...$handlers);
$router->put(string $route, callable|array ...$handlers);
$router->patch(string $route, callable|array ...$handlers);
$router->delete(string $route, callable|array ...$handlers);
```

All registration methods return `void` and support multiple handlers.

### Handlers

Every handler receives:

```php
(Request $request, array $params)
```

A handler can be:

- any callable (`fn`, closure, function name, invokable object), or
- an array `[ControllerClass, "method"]`.

```php
$router->get("/ping", fn (Request $request, array $params) =>
    Response::json(["status" => "success", "data" => ["pong" => true]])
);
```

Handlers run in registration order. `Response::json()` and `Response::redirect()`
exit, so later handlers do not run. `Response::render()` and ordinary handlers
return, allowing the next handler to execute:

```php
$router->get("/dashboard", $auth, $loadUser, fn () => Response::render("dashboard"));
```

The controller array form is loaded and instantiated at dispatch time:

```php
$router->post("/users", ["UserController", "store"]);
```

The router:

1. requires `app/controllers/<ControllerClass>.php`,
2. instantiates the class with **no constructor arguments**,
3. calls the method with `($request, $params)`.

> **Do not require controller files for `["ClassController", "method"]` routes.**
> The router loads `app/controllers/ClassController.php` automatically. Explicit
> requires are only needed for repositories, services, and optional core classes.

Controller methods may omit parameters; PHP ignores extra arguments passed to
user-defined methods. Keep the explicit `(Request $request, array $params)`
signature for clarity.

### Parameters and wildcards

```php
$router->get("/users/:id", function (Request $request, array $params) {
    $id = $params["id"]; // /users/42 → "42"
});

$router->get("/users/:userId/posts/:postId", function (Request $request, array $params) {
    // $params["userId"], $params["postId"]
});

$router->get("/files/*", function (Request $request, array $params) {
    // * matches anything, including slashes
});
```

- `:name` captures one or more non-slash characters (`([^\/]+)`).
- `*` becomes `.*` and can match across slashes.
- Captured values are named after the placeholder and passed in `$params`.
- All values are strings.

### Matching rules

| Rule | Detail |
|---|---|
| Supported verbs | `GET`, `POST`, `PUT`, `PATCH`, `DELETE` |
| Method override | On `POST`, a form field `_method=PUT\|PATCH\|DELETE` changes the dispatched verb |
| Matching order | Exact path check first per route, then regex; the first matching route wins |
| Registration order | Route order is preserved; register static paths before parameterized ones |
| Anchoring | Patterns are anchored with `^...$`; trailing slashes must match exactly |
| Existing files | For `GET`, if the path maps to an existing file under the project root, `run()` returns `false` |
| No match | `http_response_code(404)` with an empty body |
| Wrong method | Also `404`; there is no automatic `405 Method Not Allowed` |

The method override only reads `$_POST["_method"]`; it does not inspect a JSON
body or an `X-HTTP-Method-Override` header.

### Pattern compilation caveats

`compilePattern()` does not call `preg_quote()`. It escapes `/`, replaces `*`
with `.*`, and replaces `:name` with a capture group. Other regex metacharacters
in a route (`.`, `+`, `?`, `(`, `)`, `[`, `]`, etc.) are interpreted as regex.

The code also writes an escaped-dot pattern and then immediately overwrites it
with the original route, so `.` is not escaped. Avoid regex metacharacters in
literal paths unless you intentionally want regex behavior.

### `BASE_URL`

`Router::run()` computes:

```php
$base = str_replace("\\", "", trim(dirname($_SERVER["SCRIPT_NAME"])));
define("BASE_URL", $base);
```

It then strips the base directory from the request path before matching. Use
`BASE_URL` in redirects and generated links so subdirectory installs work. See
[section 8](#8-http-output--views--response) for the root-install double-slash
caveat.

### Router return value

- Existing GET file: returns `false`.
- Matched route: returns the handler result (usually `null`).
- No match: returns the result of `http_response_code(404)`.

`index.php` ignores the return value. Static `public/` files are normally served
by the `.htaccess` rewrite rule before PHP is invoked.

### No middleware or route groups

There are no route groups, named routes, route middleware, CORS handling, or
dependency injection. Implement cross-cutting checks as extra handlers:

```php
$auth = function (Request $request, array $params) {
    if ($request->header("Authorization") === null) {
        Response::json(["status" => "error", "message" => "Unauthorized"], 401);
    }
};

$router->get("/admin", $auth, ["AdminController", "index"]);
```

Because `Response::json()` exits, the protected handler never runs after an auth
failure.

---

## 10. Data Access — `Repository`

`Repository` is a static CRUD base class. Subclasses configure the table and
allowed columns with static properties:

```php
class UserRepository extends Repository {
    protected static string $table    = "users";
    protected static array  $fillable = ["name", "email", "password"];
}
```

There is **no `$idColumn` property** and no `findOneById()` method. Primary-key
lookups are expressed as conditions:

```php
UserRepository::findOne(["id" => 42]);
```

### API

All methods are static and use late static binding.

| Method | Signature | Returns | Description |
|---|---|---|---|
| `all` | `all(): array` | `array` | All rows |
| `find` | `find(array $data): array` | `array` | All rows matching every condition |
| `findOne` | `findOne(array $data): array` | `array` | First matching row (`LIMIT 1`) |
| `insert` | `insert(array $data): PDOStatement` | statement | Insert one row |
| `update` | `update(array $data, array $condition): PDOStatement` | statement | Update rows matching the condition |
| `delete` | `delete(array $condition): PDOStatement` | statement | Delete rows matching the condition |

### Queries

```php
UserRepository::all();
UserRepository::find(["role" => "admin", "active" => 1]);
UserRepository::findOne(["email" => "a@b.c"]);
```

Conditions are combined with `AND` and use named placeholders. Values are bound
as strings by default.

> **`findOne()` and missing rows:** the method is declared to return `array`, but
> `PDOStatement::fetch()` returns `false` when no row matches. In that case PHP
> raises a `TypeError` because `false` is not an array. Use `find()` when a miss
> is expected, or add your own nullable wrapper.

### Operator conditions — current limitation

The code recognizes a two-element array as an operator/value pair:

```php
UserRepository::find(["age" => [">", 18]]);
```

However, this branch is **not fully parameterized**:

- the operand is interpolated directly into SQL (`age > 18`);
- the column is also added to the bound parameters even though the generated
  SQL contains no `:age` placeholder.

Passing such a query to `DB::query()` can therefore raise a PDO parameter error.
Prefer raw SQL with placeholders for non-equality comparisons:

```php
$rows = DB::query("SELECT * FROM users WHERE age > ?", [1 => 18])->fetchAll();
```

### Insert

Columns come from the keys of the data array. Every key must exist in
`$fillable`, otherwise an `ErrorException` is thrown:

```text
Invalid column <name> while insert.
```

```php
UserRepository::insert([
    "name"     => "Budi",
    "email"    => "budi@mail.com",
    "password" => password_hash("secret", PASSWORD_DEFAULT),
]);

$id = UserRepository::insert(["name" => "Budi"])->lastInsertId();
```

Partial inserts are allowed. An empty data array produces invalid SQL. Column
names are not quoted, so avoid reserved words or quote them in a custom method.

### Update

`update()` takes the data to set first and the condition second:

```php
UserRepository::update(
    ["name" => "Budi Santoso", "active" => 1],
    ["id" => 42]
);
```

Only the keys supplied in `$data` are updated. Every supplied key must be in
`$fillable`, otherwise:

```text
Invalid column <name> while update.
```

The generated statement merges data and condition parameters. If the same column
name appears in both arrays, the condition value overwrites the data value in the
merged parameter list. Avoid using the same key in both arrays.

### Delete

```php
UserRepository::delete(["id" => 42]);
UserRepository::delete(["active" => 0, "role" => "guest"]);
```

Conditions are required. An empty condition array produces invalid SQL.

### Custom repository methods

```php
class UserRepository extends Repository {
    protected static string $table    = "users";
    protected static array  $fillable = ["name", "email", "password"];

    public static function findActiveAdmins(): array {
        return DB::query(
            "SELECT id, name, email FROM users WHERE role = ? AND active = 1 ORDER BY name",
            [1 => "admin"]
        )->fetchAll();
    }
}
```

There is no ordering, pagination, joining, or eager-loading API. Use `DB::query()`
for anything beyond simple equality conditions.

---

## 11. Layering — `Service` & `Controller`

The base classes are deliberately minimal:

```php
class Service {
    protected Repository $repository;
}

class Controller {
    protected Service $service;
}
```

They exist to make the intended dependency direction explicit:

```text
Controller → Service → Repository → DB
```

Wire them manually in constructors:

```php
class UserService extends Service {
    public function __construct() {
        $this->repository = new UserRepository();
    }
}

class UserController extends Controller {
    public function __construct() {
        $this->service = new UserService();
    }
}
```

Notes:

- The router instantiates controllers with no constructor arguments.
- Typed properties are uninitialized until you assign them. Accessing
  `$this->service` or `$this->repository` before assignment raises an
  `Error`.
- Repository methods are static. A service may call `UserRepository::find(...)`
  directly; assigning `$this->repository` is a convention, not a requirement.
- There is no automatic injection, container, or lifecycle management.

---

## 12. Validation — `Validator`

`Validator` validates an array of data against a map of `field => [rule => message]`.
It returns all field errors at once, with one message per field.

`Validator` is **not loaded by the default bootstrap**. Require it before use:

```php
require_once __DIR__ . "/app/core/Validator.php";
```

### API

```php
Validator::validate(array $data, array $fieldAndRules): ?array
```

| Result | Meaning |
|---|---|
| `null` | Every field passed |
| `array` | `field => first failing message for that field` |

There is **no `validateAll()` and no `fromHTTPRequest()`**. Pass request data
explicitly:

```php
$errors = Validator::validate($request->all(), [
    "name"  => [
        "required" => "Name is required!",
        "max:100"  => "Name is too long!",
    ],
    "email" => [
        "required" => "Email is required!",
        "email"    => "Invalid email!",
    ],
]);

if ($errors !== null) {
    Response::json(["status" => "error", "errors" => $errors], 422);
}
```

`$request->all()` includes normalized uploaded files, so file rules can use the
same entry point. A missing field is validated as `null`.

### Rules

| Rule | Validates |
|---|---|
| `required` | Value is set and is not a blank string |
| `string` | `is_string()` |
| `numeric` | `is_numeric()` (numeric strings pass) |
| `int` | Strict PHP `is_int()` |
| `float` | Strict PHP `is_float()` |
| `email` | `filter_var(..., FILTER_VALIDATE_EMAIL)` |
| `uuid` | `8-4-4-4-12` hexadecimal UUID, case-insensitive |
| `min:N` | Number >= `N`, or string length (bytes) >= `N` |
| `max:N` | Number <= `N`, or string length (bytes) <= `N` |
| `enum:a,b,c` | String exactly matches one of the comma-separated values |
| `pattern:REGEX` | String matches `/<REGEX>/` |
| `image` | Every file has a `type` starting with `image/` |
| `pdf` | Every file has `type` exactly `application/pdf` |
| `mime:type1,type2` | Every file type is in the exact list |
| `min_size:N` | Every file size >= `N` bytes |
| `max_size:N` | Every file size <= `N` bytes |
| `min_files:N` | At least `N` uploaded files |
| `max_files:N` | At most `N` uploaded files |

Unknown rule names are silently treated as passing.

### Validation behavior

- Rules are evaluated in array order; the **first failing rule** for a field
  supplies its message.
- If a field is empty/`null` and `required` is **not** among its rules, it passes
  automatically at the first non-required rule.
- If `required` is present, it should normally be first: if another rule appears
  before it, that earlier rule can fail first on `null`.
- `int` and `float` check the actual PHP type. HTTP input is always a string, so
  use `numeric` or `pattern:\d+` for request values and cast afterwards.
- `min`/`max` measure string **byte length**, not character count.
- `pattern` expects the regex body without delimiters; the validator wraps it in
  `/.../`.
- File rules accept either one normalized file array or a list of them.
- `image`, `pdf`, and `mime` inspect the client-provided `type` field, not the
  file's actual contents with `finfo`.
- There is no nested/array-field dot notation and no custom-rule registration.

### File validation example

```php
$errors = Validator::validate($request->all(), [
    "avatar" => [
        "required"  => "Avatar is required!",
        "image"     => "Avatar must be an image!",
        "max_size:2097152" => "Avatar must be at most 2 MB!",
    ],
    "documents" => [
        "max_files:3"            => "At most 3 documents!",
        "mime:application/pdf"   => "Documents must be PDFs!",
    ],
]);
```

---

## 13. Files — `Storage`

`Storage` reads and writes paths rooted at the project's internal `storage/`
directory. It does **not** point at `public/storage/`.

`Storage` is **not loaded by the default bootstrap**. Require it before use:

```php
require_once __DIR__ . "/app/core/Storage.php";
```

```text
Storage::put("exports/report.csv", ...) → <project>/storage/exports/report.csv
```

### API

| Method | Signature | Behavior |
|---|---|---|
| `exists` | `exists(string $path): bool` | Whether the path exists under `storage/` |
| `get` | `get(string $path): mixed` | `file_get_contents()` result (string or `false`) |
| `put` | `put(string $path, string $content, int $flags = 0): bool` | Writes a file; returns success |
| `unlink` | `unlink(string $path): bool` | Intended to delete a file |
| `save` | `save(string $from, string $dest): bool` | Intended to move an uploaded file |

### `put()`

```php
Storage::put("notes/today.txt", "hello");
Storage::put("logs/app.log", $line . PHP_EOL, FILE_APPEND | LOCK_EX);
```

`put()` does **not** create parent directories. Create them beforehand or the
write fails. It overwrites by default.

### `get()` and `exists()`

```php
if (Storage::exists("notes/today.txt")) {
    $text = Storage::get("notes/today.txt");
}
```

`get()` on a missing file emits a PHP warning and returns `false`.

### `unlink()` — current limitation

The implementation prefixes the supplied path and then calls `exists()` with the
already-prefixed path, so the existence check does not see the target file. As a
result, `unlink()` currently returns `true` even when it does not delete
anything. Do not rely on it for file removal; call `unlink()` on the resolved
path yourself or fix the helper.

### `save()` — current limitation

Both `$from` and `$dest` are resolved under `storage/`:

```php
move_uploaded_file(
    self::storagePath($from),
    self::storagePath($dest)
);
```

A normal upload's absolute `tmp_name` (for example `C:\tmp\php123.tmp`) is
therefore prefixed with the storage root and will not resolve to the uploaded
file. The helper is only viable when both paths are already meaningful relative
to `storage/`. For standard uploads, move the file with your own code.

### Storage vs. public files

- `storage/` is internal and not intended for direct web access.
- `public/storage/` is the publicly served directory.
- The `Storage` class has no public-directory variant and does not copy files
  between the two.
- Both `storage/.htaccess` and `public/storage/.htaccess` disable directory
  indexing.

---

## 14. Caching — `Cache`

`Cache` is a file-backed cache stored in the system temporary directory:

```text
<sys_get_temp_dir()>/php-cache/
```

It is **not required by `index.php`**. Load it explicitly before use:

```php
require_once __DIR__ . "/app/core/Cache.php";
```

The same applies to `Log`, `Storage`, and `Validator`; see
[section 3](#3-loading-model).

### API

| Method | Signature | Behavior |
|---|---|---|
| `get` | `get(string $key): ?string` | Returns the stored string, or `null` on miss/expiry |
| `set` | `set(string $key, string $data, int $ttl = 0): bool` | Stores a string with a TTL in seconds |
| `remember` | `remember(string $key, int $ttl, callable $procedure): mixed` | Returns cached value or computes, stores, and returns it |
| `delete` | `delete(string $key): bool` | Deletes one key or a wildcard pattern |

### Keys and files

Keys are converted to paths by replacing `:` with `/`:

```text
user:42 → <cache>/user/<md5("42")>
```

- Directories are created automatically.
- The final path segment is MD5-hashed unless that segment is exactly `*`.
- Keys containing `:` therefore create nested cache directories.

### `set()` and `get()`

```php
Cache::set("weather:bandung", json_encode($weather), 600);

$encoded = Cache::get("weather:bandung"); // string or null
$weather = $encoded === null ? null : json_decode($encoded, true);
```

`set()` stores a JSON envelope:

```json
{"expires": 1760000000, "data": "..."}
```

Important details:

- Expiration is `time() + $ttl`.
- `ttl = 0` expires immediately, because `time() >= time()`.
- `get()` supports an `expires` value of `-1` as "never expires", but
  `set()`/`remember()` cannot produce `-1`; use a large positive TTL instead.
- `get()` deletes an expired entry and returns `null`.

### `remember()`

```php
$users = Cache::remember("users:all", 300, function () {
    return UserRepository::all();
});
```

- On hit: reads the stored JSON and decodes it to the original value.
- On miss: calls the procedure, JSON-encodes the result, stores it, and returns
  the value.
- If the value cannot be JSON-encoded, it is returned without being cached.
- The procedure's return value should therefore be JSON-serializable.

Note that raw `get()` returns the stored string. For values written by
`remember()`, that string is the JSON representation, so decode it yourself if
you use `get()` directly.

### `delete()` and wildcards

```php
Cache::delete("user:42");
Cache::delete("user:*");
Cache::delete("weather:*");
```

Wildcard patterns use `glob()`. A terminal `*` segment is left unhashed so the
glob can match files. Deleting a missing non-wildcard key returns `true`.

### Operational notes

- There is no garbage collection, size limit, or cache namespace beyond the key.
- Writes use `LOCK_EX`; reads are not locked.
- Cache entries live in the system temp directory, not in `storage/`.
- The cache directory is shared by every application using the same temp path.

---

## 15. Complete Feature Example

End-to-end "list and create users" feature.

### Repository

`app/repositories/UserRepository.php`:

```php
<?php declare(strict_types=1);

class UserRepository extends Repository {
    protected static string $table    = "users";
    protected static array  $fillable = ["name", "email", "password"];
}
```

### Service

`app/services/UserService.php`:

```php
<?php declare(strict_types=1);

class UserService extends Service {
    public function __construct() {
        $this->repository = new UserRepository();
    }

    public function create(array $input): string {
        return (string) UserRepository::insert([
            "name"     => trim((string) $input["name"]),
            "email"    => strtolower(trim((string) $input["email"])),
            "password" => password_hash((string) $input["password"], PASSWORD_DEFAULT),
        ])->lastInsertId();
    }
}
```

### Controller

`app/controllers/UserController.php`:

```php
<?php declare(strict_types=1);

class UserController extends Controller {
    public function __construct() {
        $this->service = new UserService();
    }

    public function index(Request $request, array $params): void {
        Response::json([
            "status" => "success",
            "data"   => UserRepository::all(),
        ]);
    }

    public function store(Request $request, array $params): void {
        $errors = Validator::validate($request->all(), [
            "name" => [
                "required" => "Name is required!",
                "max:100"  => "Name is too long!",
            ],
            "email" => [
                "required" => "Email is required!",
                "email"    => "Invalid email!",
            ],
            "password" => [
                "required" => "Password is required!",
                "min:8"    => "Password must be at least 8 characters!",
            ],
        ]);

        if ($errors !== null) {
            Response::json(["status" => "error", "errors" => $errors], 422);
        }

        $id = $this->service->create($request->all());

        Response::json([
            "status" => "success",
            "data"   => ["id" => $id],
        ], 201);
    }
}
```

### Routes

`routes.php`:

```php
<?php declare(strict_types=1);

require_once __DIR__ . "/app/core/Validator.php";
require_once __DIR__ . "/app/repositories/UserRepository.php";
require_once __DIR__ . "/app/services/UserService.php";

$router->get("/users",  ["UserController", "index"]);
$router->post("/users", ["UserController", "store"]);
```

`UserController` is loaded by the router, so there is no
`require_once` for `UserController.php`. The repository and service must be
required because there is no autoloader. `Validator.php` is also required
explicitly because it is an optional core class.

### Lookup and update example

```php
$router->get("/users/:id", function (Request $request, array $params) {
    $rows = UserRepository::find(["id" => $params["id"]]);

    if ($rows === []) {
        Response::json(["status" => "error", "message" => "Not found"], 404);
    }

    Response::json(["status" => "success", "data" => $rows[0]]);
});

$router->patch("/users/:id", function (Request $request, array $params) {
    UserRepository::update(
        ["name" => $request->post("name")],
        ["id" => $params["id"]]
    );

    Response::json(["status" => "success"]);
});
```

---

## 16. Web Server, Public Files & URLs

### Project-root document root (bundled setup)

```apache
RewriteEngine On

# Block direct access to PHP files
RewriteRule \.php$ - [F,END,NC]

# Existing files/directories inside public/
RewriteCond %{DOCUMENT_ROOT}/php_template/public/$1 -f
RewriteRule ^(.+)$ public/$1 [END]

# Everything else goes to the front controller
RewriteRule ^ index.php [END]
```

Consequences:

- Direct requests to `.php` files are forbidden.
- Existing files under `public/` are served from `public/`.
- Everything else is routed through the root `index.php`.
- The `RewriteCond` hardcodes `php_template` as the project directory. If you
  clone into a differently named directory, update that path or the static-file
  mapping will not work.

### `public/` as document root

`public/index.php` is:

```php
<?php declare(strict_types=1);

require __DIR__ . "/../index.php";
```

When the web server document root is `public/`, static files are served directly
and `public/index.php` boots the framework. Application code remains outside the
document root.

### Internal directories

- `app/.htaccess`, `app/core/.htaccess`, `app/controllers/.htaccess`, etc. deny
  access with `Deny from all`.
- `storage/` is internal and contains logs and `Storage` files.
- `public/storage/` is the public file area and disables directory indexing.
- The `Storage` class writes to `storage/`, **not** `public/storage/`.

### Upload and static-file guidance

- Create destination directories before using `Storage::put()`.
- Do not serve uploaded files directly from `storage/`.
- To publish a file, place/copy it under `public/storage/` and build its URL from
  `BASE_URL`.
- `BASE_URL` is `/` at the domain root and `/subdir` in a subdirectory. Prefer:

  ```php
  $base = rtrim(BASE_URL, "/");
  $url  = $base . "/storage/file.png";
  ```

### Development server

For route/API testing:

```bash
php -S localhost:8000 index.php
```

The built-in server does not reproduce the Apache rewrite behavior for static
files; use Apache when testing the public-file setup.

---

## 17. Limitations & Gotchas

1. **No autoloader and no namespaces** — repositories/services must be
   `require_once`d manually; controllers are loaded by the router by exact class
   name. `Log`, `Storage`, `Validator`, and `Cache` are **not** loaded by the
   default bootstrap and must be required explicitly.

2. **Explicit boot order matters** — the default bootstrap loads `Env`, `DB`,
   the base layers, then the HTTP classes. `Database` needs the PDO extension;
   `Repository` uses `DB`; base classes must exist before application subclasses.
   `Router.php` is loaded before `Env::load()`, which provides the global
   `$requestId` used by `Log`.

3. **Environment configuration is `.env` only** — `Env` populates `$_ENV`; it
   does not use `getenv()` or Apache `SetEnv`. All `DB_*` keys require explicit
   values; there are no fallback defaults. Decimal values may be cast to integers
   by the parser, so quote exact values.

4. **Database binding is 1-based for positional placeholders** —
   `[18, "Bandung"]` passes keys `0, 1` to `bindValue()` and fails. Use
   `[1 => 18, 2 => "Bandung"]` or named placeholders. Values default to
   `PDO::PARAM_STR`; pass explicit types for `LIMIT`/`OFFSET`.

5. **Database failure is fail-fast** — a connection error lazy-loads `Log.php`,
   logs the PDO error code and message, sets status `502`, and calls `die()` with
   an empty body. Do not expose environment details in production.

6. **No `findOneById()` or `$idColumn`** — use condition arrays:
   `findOne(["id" => 42])`, `update($data, ["id" => 42])`,
   `delete(["id" => 42])`.

7. **`findOne()` can raise a `TypeError` on a miss** — the method returns
   `array`, while PDO returns `false` for no row. Use `find()` and check for an
   empty array when the row may not exist.

8. **Repository operator conditions are not safely parameterized** — the
   operand is interpolated into SQL and an unused parameter is added. Prefer
   `DB::query()` with placeholders for `>`, `<`, `LIKE`, etc.

9. **`insert()`/`update()` columns come from array keys** — every key must be in
   `$fillable`, otherwise an `ErrorException` is thrown. Empty conditions or empty
   data produce invalid SQL. Updating a column that is also in the condition can
   overwrite the value due to parameter merging.

10. **`update()`/`delete()` take condition arrays** — they are not
    `update($id, $data)` or `delete($id)`.

11. **`Validator` has only `validate()`** — there is no `validateAll()` or
    `fromHTTPRequest()`. It always receives an explicit data array. Rules run in
    order and produce one message per field.
    - `int`/`float` are strict PHP types; HTTP strings fail.
    - `min`/`max` on strings count bytes.
    - File type checks trust the client-provided `type`, not the file contents.
    - Unknown rules pass silently.

12. **`Response::json()` and `redirect()` exit** — call them as the final
    statement. `render()` outputs and returns, so execution can continue.

13. **Routing has no middleware, groups, or named routes** — use multiple
    handlers. Handlers receive one shared `Request` instance per dispatch.

14. **Route matching is first-match and pattern-based** — register static paths
    before parameterized ones. `/users/me` after `/users/:id` will never match.
    Patterns are anchored; trailing slashes matter. Unsupported methods and
    unmatched paths both return `404`, never `405`.

15. **Route patterns are not `preg_quote`d** — regex metacharacters in literal
    paths are active. Dots are not escaped due to the current compilation code.
    Keep literal routes simple.

16. **`BASE_URL` can be `/`** — at the domain root, concatenating `"/path"`
    directly produces `//path`. Use `rtrim(BASE_URL, "/") . "/path"`.

17. **Method override is form-only** — `_method` is read from `$_POST`; JSON
    bodies and headers are ignored.

18. **Headers are case-sensitive** — `header("Authorization")` must match the
    exact case exposed by the SAPI.

19. **`Request::file()` expects the field to exist** — unlike `get()`/`post()`,
    it does not guard a missing `$_FILES` key.

20. **`Storage::put()` does not create directories** — create the destination
    directory first. `Storage::unlink()` and `Storage::save()` have current
    implementation limitations described in [section 13](#13-files--storage).

21. **Internal storage is not public** — `Storage` uses `storage/`; web-accessible
    files belong in `public/storage/`.

22. **Cache is manual and file-based** — require `Cache.php`, remember that
    `get()` returns a string, `ttl = 0` expires immediately, and `set()` cannot
    create the `-1` "never expires" sentinel. There is no garbage collection.

23. **Logging is file-based and unrotated** — `storage/` must be writable. JSON
    encoding uses `JSON_THROW_ON_ERROR`.

24. **No schema/migration/seed tooling** — manage the database manually. There
    are no tests, CLI commands, container, or dependency-injection features.
