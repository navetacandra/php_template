<?php declare(strict_types=1);

class Request {
    protected array $headers = [];

    public function json(): array {
        $data = json_decode(file_get_contents("php://input"), true);
        if(json_last_error() == JSON_ERROR_NONE) {
            return (array) $data;
        }
        return [];
    }

    public function header(string $field): ?string {
        // initial header extraction
        if(count($this->headers) < 1) {
            // do nothing when success/false
            if($this->headers = getallheaders()) {
            } else if($this->headers = apache_request_headers()) {
            }
        }
        return $this->headers[$field] ?? null;
    }

    public function all(): array {
        $reqData = [...$_REQUEST];

        foreach($_FILES as $k_file => $m_files) {
            $reqData[$k_file] = [];
            if(!is_array($m_files['error'])) {
                $reqData[$k_file] = $m_files;
                continue;
            }

            $keys = array_keys($m_files);
            $fileCount = count($m_files['error']);
            for($i = 0; $i < $fileCount; $i++) {
                foreach($keys as $k) {
                    $reqData[$k_file][$i][$k] = $m_files[$k][$i];
                }
            }
        }

        return $reqData;
    }

    public function get(string|array $field=''): mixed {
        if($field == '') return $_GET;
        if(is_string($field)) return $_GET[$field] ?? null;

        $get = [];
        foreach($field as $f) {
            $get[$f] = $_GET[$f] ?? null;
        }
        return $get;
    }

    public function post(string|array $field=''): mixed {
        if($field == '') return $_POST;
        if(is_string($field)) return $_POST[$field] ?? null;

        $post = [];
        foreach($field as $f) {
            $post[$f] = $_POST[$f] ?? null;
        }
        return $post;
    }

    public function file(string|array $field): array {
        if(is_string($field)) {
            $m_files = $_FILES[$field];
            if(!is_array($m_files['error'])) return $m_files;

            $files = [];
            $keys = array_keys($m_files);
            $fileCount = count($m_files['error']);
            for($i = 0; $i < $fileCount; $i++) {
                foreach($keys as $k) {
                    $files[$i][$k] = $m_files[$k][$i];
                }
            }

            return $files;
        } else {
            $files = [];
            foreach($field as $f) {
                $files[$f] = $this->file($f);
            }

            return $files;
        }
    }
}
