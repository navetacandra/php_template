<?php declare(strict_types=1);

class Response {
    public static function status(int $status) {
        if(!headers_sent()) {
            http_response_code($status);
        }
    }

    public static function text(string $data, int $status = 200) {
        static::status($status);
        header("Content-Type: text/plain");
        print_r($data);
    }

    public static function json(array $data, int $status = 200) {
        static::status($status);
        header("Content-Type: application/json");
        echo json_encode($data);
        exit;
    }

    public static function redirect(string $dest, int $status = 302) {
        static::status($status);
        header("Location: $dest");
        exit;
    }

    public static function render(string $view, array $data = [], int $status = 200) {
        if(!headers_sent()) {
            static::status($status);
            header("Content-Type: text/html");
        }

        extract($data);
        $request = new Request();

        require __DIR__ . '/../views/' . str_replace('.', '/', $view) . '.view.php';
    }
}
