<?php declare(strict_types=1);

class Repository {
    protected static string $table = "example";
    protected static array $fillable = [];

    private static function buildWhereClause(array &$data): array {
        $conditions = [];
        $params = [];
        foreach($data as $col => $val) {
            if(is_array($val)) {
                [$operator, $value] = $val;
                $conditions[] = "$col $operator $value";
                $params[$col] = $value;
            } else {
                $conditions[] = "$col = :$col";
                $params[$col] = $val;
            }
        }

        return [implode(" AND ", $conditions), $params];
    }

    private static function assertFillable(array &$keys, string $action): bool {
        $diff = array_diff($keys, static::$fillable);
        if(count($diff) == 0) return true;

        throw new ErrorException("Invalid column " . implode(", ", $diff) . " while $action.");
    }

    public static function all(): array {
        $table = static::$table;
        return DB::query("SELECT * FROM $table")->fetchAll();
    }

    public static function find(array $data): array {
        $table = static::$table;
        [$whereClause, $params] = static::buildWhereClause($data);

        return DB::query("SELECT * FROM $table WHERE $whereClause", $params)->fetchAll();
    }

    public static function findOne(array $data): array {
        $table = static::$table;
        [$whereClause, $params] = static::buildWhereClause($data);
        return DB::query("SELECT * FROM $table WHERE $whereClause LIMIT 1", $params)->fetch();
    }

    public static function insert(array $data): PDOStatement {
        $keys = array_keys($data);
        static::assertFillable($keys, 'insert');

        $table = static::$table;
        $cols = implode(", ", $keys);
        $params = implode(", ", array_map(fn ($col) => ":$col", $keys));

        return DB::query("INSERT INTO $table ($cols) VALUES ($params)", $data);
    }

    public static function update(array $data, array $condition): PDOStatement {
        $keys = array_keys($data);
        static::assertFillable($keys, 'update');

        $table = static::$table;
        $cols = implode(", ", array_map(fn ($col) => "$col=:$col", $keys));
        [$whereClause, $params] = static::buildWhereClause($condition);

        return DB::query("UPDATE $table SET $cols WHERE $whereClause", [...$data, ...$params]);
    }

    public static function delete(array $condition): PDOStatement {
        $table = static::$table;
        [$whereClause, $params] = static::buildWhereClause($condition);
        return DB::query("DELETE FROM $table WHERE $whereClause", $params);
    }
}
