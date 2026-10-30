<?php declare(strict_types=1);

final class Env {
    public static function load(string $path) {
        if(!file_exists($path)) {
            require __DIR__ . "/Log.php";

            Log::warn("env file doesn't exists");
            return;
        }

        $raw = file_get_contents($path);
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);

        foreach(preg_split("/\r?\n/", $raw) as $line) { // evaluate line by line
            if(strlen($line) == 0) continue; // skip blank

            $parsed = self::parseValue($line);
            if($parsed == null) continue; // skip null parse
            $_ENV[$parsed[0]] = $parsed[1];
        }
    }

    private static function parseValue(string $line): ?array {
        $line = trim($line);
        $commented = str_starts_with($line, '#');
        if($commented) return null; // commented

        $pos = strpos($line, '=');
        $key = substr($line, 0, $pos);
        $value = substr($line, $pos + 1);

        if(preg_match('/^"((?:\\"|[^"])*?)".*?$/', $value, $match)) { // only capture inside double-quote
            $value = trim($match[1]);
        } else if(preg_match("/^'((?:\\'|[^'])*?)'.*?$/", $value, $match)) { // only capture inside single-quote
            $value = trim($match[1]);
        } else {
            $hash = strpos($line, '#');
            if($hash !== false) $value = substr($value, 0, $hash); // remove hash

            $value = trim($value);
            self::boolParse($value);
            self::numericParse($value);
        }

        return [$key, $value];
    }

    private static function numericParse(mixed &$val): bool {
        if(is_numeric($val)) {
            if(strpos($val, '.') && strpos($val, 'e')) $val = floatval($val);
            else $val = intval($val);
            return true;
        }
        return false;
    }

    private static function boolParse(mixed &$val): bool {
        if(in_array(strtolower($val), ["true", "false"])) {
            $val = strtolower($val) === "true";
            return true;
        }
        return false;
    }
}
