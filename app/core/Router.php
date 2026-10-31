<?php declare(strict_types=1);

$requestId = uniqid();

class Router {
    protected array $routes = [];
    public string $path = '';

    protected static function compilePattern(string $route): array {
        $fields = [];

        $routePattern = str_replace(".", "\.", $route); // replace "/" to "\/" (regex safe)
        $routePattern = str_replace("/", "\/", $route); // replace "/" to "\/" (regex safe)
        $routePattern = str_replace("*", ".*", $routePattern); // repalce "*" to ".*" (match all regex)
        $routePattern = preg_replace_callback(
            "/:(\w+)/",
            function (array $match) use (&$fields): string {
                $fields[] = $match[1];
                return "([^\/]+)";
            },
            $routePattern
        ); // repalce ":param" to "([^\/])" (param catch)

        return [$fields, "/^$routePattern$/"]; // compile to valid pattern
    }

    protected static function getHandler(array $route, string $method): ?array {
        if(!isset($route[$method])) {
            http_response_code(405);
            exit;
        }

        return $route[$method];
    }

    protected static function callHandlers(array $m_handlers, array $params = []) {
        $req = new Request();

        foreach($m_handlers as $handler) {
            if(is_callable($handler)) $handler($req, $params); // call handler
            else if(is_array($handler)) {
                if(count($handler) < 2) continue;
                $className = $handler[0];
                $action = $handler[1];

                require __DIR__ . "/../controllers/$className.php";
                (new $className())->$action($req, $params);
            }
        }
    }

    private function add(string $method, string $route, callable|array ...$handlers) {
        if(isset($this->routes[$route])) {
            $this->routes[$route][$method] = $handlers;
            return;
        }

        $pattern = static::compilePattern($route);
        $this->routes[$route] = [
            "pattern_field" => $pattern[0],
            "pattern" => $pattern[1],
            "path" => $route,
            $method => $handlers,
        ];
    }

    public function get(string $route, callable|array ...$handlers) {
        $this->add("GET", $route, ...$handlers);
    }

    public function post(string $route, callable|array ...$handlers) {
        $this->add("POST", $route, ...$handlers);
    }

    public function put(string $route, callable|array ...$handlers) {
        $this->add("PUT", $route, ...$handlers);
    }

    public function patch(string $route, callable|array ...$handlers) {
        $this->add("PATCH", $route, ...$handlers);
    }

    public function delete(string $route, callable|array ...$handlers) {
        $this->add("DELETE", $route, ...$handlers);
    }

    public function all(string $route, callable|array ...$handlers) {
        $this->get($route, ...$handlers);
        $this->post($route, ...$handlers);
        $this->put($route, ...$handlers);
        $this->patch($route, ...$handlers);
        $this->delete($route, ...$handlers);
    }

    public function run() {
        $method = $_SERVER['REQUEST_METHOD'] ?? "GET";

        if($method === "POST") {
            $overrideMethod = $_POST["_method"] ?? null;

            if(in_array($overrideMethod, ["PUT", "PATCH", "DELETE"], true)) {
                $method = $overrideMethod;
            }
        }

        $base = str_replace("\\", "", trim(dirname($_SERVER['SCRIPT_NAME'])));
        $path = parse_url(
            $_SERVER['REQUEST_URI'] ?? "/",
            PHP_URL_PATH
        ) ?: "/";

        if($method === "GET" && is_file(__DIR__ . "/../../$path")) {
            return false;
        }

        if(strlen($base) > 1) {
            $path = rtrim(substr($path, strlen($base)), '/');
        }

        define("BASE_URL", $base);
        $this->path = $path;

        // Find match url
        foreach($this->routes as $m_path => $route) {
            $pattern = $route["pattern"];
            if($m_path == $path) {
                return static::callHandlers(
                    static::getHandler($route, $method)
                );
            }

            if(preg_match($pattern, $path, $matches)) {
                $params = [];
                for($i = 1; $i < count($matches); $i++) {
                    $field = $route["pattern_field"][$i-1];
                    $params[$field] = $matches[$i];
                }

                return static::callHandlers(
                    static::getHandler($route, $method),
                    $params
                );
            }
        }

        return http_response_code(404);
    }
}
