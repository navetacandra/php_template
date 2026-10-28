<?php declare(strict_types=1);

class Storage {
    private static function storagePath(string $path): string {
        return __DIR__ . '/../../storage/' . $path;
    }

    public static function exists(string $path): bool {
        return file_exists(self::storagePath($path));
    }

    public static function unlink(string $path): bool {
        $path = self::storagePath($path);
        if(!self::exists($path)) return true;
        if(unlink($path)) return true;
        return false;
    }

    public static function get(string $path): mixed {
        return file_get_contents(self::storagePath($path));
    }

    public static function put(string $path, string $content, int $flags = 0): bool {
        $path = self::storagePath($path);
        if(file_put_contents($path, $content, $flags)) return true;
        return false;
    }

    public static function save(string $from, string $dest): bool {
        if(
            move_uploaded_file(
                self::storagePath($from),
                self::storagePath($dest)
            )
        ) return true;
        return false;
    }
}
