<?php declare(strict_types=1);

final class Log {
    private const TERMINAL = false; // terminal

    private static function getLogFile(): string {
        if(self::TERMINAL) return 'php://stderr';
        return __DIR__ . "/../../storage/". date("Y_M_d") . ".log";
    }


    private static function log(string $level, mixed ...$message): void {
        global $requestId;

        $msgs = [];
        foreach($message as $msg) $msgs[] = print_r($msg, true);

        $context = [
            "level" => $level,
            "requestId" => $requestId ?? null,
            "timestamp" => date("Y-M-d H:i:s", time()),
            "message" => join(" ", $msgs),
        ];
        $json = json_encode(
            $context,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );

        file_put_contents(
            self::getLogFile(),
            $json . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }

    public static function info(mixed ...$message): void {
        self::log("INFO", ...$message);
    }

    public static function warn(mixed ...$message): void {
        self::log("WARN", ...$message);
    }

    public static function error(mixed ...$message): void {
        self::log("ERROR", ...$message);
    }
}
