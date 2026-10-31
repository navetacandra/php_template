<?php declare(strict_types=1);

class DB {
    protected static ?PDO $pdo = null;

    protected static function init() {
        $driver = $_ENV["DB_DRIVER"];
        $host = $_ENV["DB_HOST"];
        $port = $_ENV["DB_PORT"];
        $dbname = $_ENV["DB_NAME"];
        $user = $_ENV["DB_USER"];
        $pass = $_ENV["DB_PASS"];


        if($driver === "sqlite") {
            $sqliteFile = __DIR__ . "/../../$dbname";  // project root
            $dsn = "$driver:$sqliteFile";
        } else $dsn = "$driver:host=$host;port=$port;dbname=$dbname";

        try {
            $pdo = new PDO($dsn, $user, $pass, [
               PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
               PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
               PDO::ATTR_PERSISTENT         => true
            ]);

            self::$pdo = $pdo;
        } catch(PDOException $e) {
            require __DIR__ . "/Log.php";

            Log::error("PDO connection failed.", $e->getMessage());
            http_response_code(502);
            die();
        }
    }

    protected static function getConnection(): ?PDO {
        if(self::$pdo === null) self::init();
        return self::$pdo;
    }

    public static function beginTransaction() {
        $pdo = self::getConnection();
        $pdo->beginTransaction();
    }

    public static function commit() {
        $pdo = self::getConnection();
        $pdo->commit();
    }

    public static function rollBack() {
        $pdo = self::getConnection();
        $pdo->rollBack();
    }

    public static function query(string $sql="", array $parameter = [], array $types = []): PDOStatement {
        $pdo = self::getConnection();
        $stmt = $pdo->prepare($sql);

        foreach($parameter as $key => $val) {
            $type = $types[$key] ?? (
                ctype_digit("$val") ? PDO::PARAM_INT : PDO::PARAM_STR
            );
            $stmt->bindValue(is_int($key) ? $key + 1 : $key, $val, $type);
        }

        $stmt->execute();
        return $stmt;
    }
}
