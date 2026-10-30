<?php declare(strict_types=1);

class Cache {
    private static string $tmpDir = '';

    private static function init() {
        if(self::$tmpDir === '') {
            self::$tmpDir = sys_get_temp_dir() . '/php-cache';
            if(!is_dir(self::$tmpDir)) {
                mkdir(self::$tmpDir, 0755, true);
            }
        }
    }

    private static function getPath(string $key): string {
        self::init();

        $filename = md5($key);
        return self::$tmpDir . "/$filename";
    }

    public static function get(string $key): ?string {
        $file = static::getPath($key);
        if(!is_file($file)) return null;

        $content = file_get_contents($file);
        if($content === false) return null;

        $cache = json_decode($content);
        if(!is_array($cache) || !isset($cache['expires']) || !isset($cache['data'])) return null;

        if($cache['expires'] !== -1 && time() >= $cache['expires']) {
            unlink($file);
            return null;
        }

        return $cache['data'];
    }

    public static function set(string $key, string $data, int $ttl = 0): bool {
        $file = static::getPath($key);
        $cache = json_encode([
            'expires' => time() + $ttl,
            'data' => $data
        ]);

        if($cache === false) return false;
        return file_put_contents($file, $cache, LOCK_EX) !== false;
    }

    public static function getOrSet(string $key, string $data, int $ttl = 0): string {
        $cached = self::get($key);
        if($cached !== null) return $cached;

        self::set($key, $data, $ttl);
        return $data;
    }

    public static function delete(string $key): bool {
        $file = self::getPath($key);
        if(!is_file($file)) return true;

        return unlink($file);
    }
}
