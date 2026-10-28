<?php declare(strict_types=1);

class Validator {
    private const RULES = [
        'required'  => [self::class, 'validateRequired'],
        'string'    => [self::class, 'validateString'],
        'int'       => [self::class, 'validateInt'],
        'float'     => [self::class, 'validateFloat'],
        'email'     => [self::class, 'validateEmail'],
        'min'       => [self::class, 'validateMinMax'],
        'max'       => [self::class, 'validateMinMax'],
        'enum'      => [self::class, 'validateEnum'],
        'pattern'   => [self::class, 'validatePattern'],
        'pdf'       => [self::class, 'validatePdf'],
        'image'     => [self::class, 'validateImage'],
        'mime'      => [self::class, 'validateMime'],
        'min_size'  => [self::class, 'validateFileSize'],
        'max_size'  => [self::class, 'validateFileSize'],
        'min_files' => [self::class, 'validateFileCount'],
        'max_files' => [self::class, 'validateFileCount'],
    ];

    private const BOUND_RULES = [
        'min'       => true,
        'max'       => true,
        'min_size'  => true,
        'max_size'  => true,
        'min_files' => true,
        'max_files' => true,
    ];

    private const PDF_MIME = 'application/pdf';

    private static array $parsedRules = [];

    protected static function validateRequired(mixed $data): bool {
        if (!isset($data)) return false;
        return !(is_string($data) && trim($data) === '');
    }

    protected static function validateString(mixed $data): bool {
        return is_string($data);
    }

    protected static function validateNumeric(mixed $data): bool {
        return is_numeric($data);
    }

    protected static function validateInt(mixed $data): bool {
        return is_int($data);
    }

    protected static function validateFloat(mixed $data): bool {
        return is_float($data);
    }

    protected static function validateEmail(mixed $data): bool {
        return is_string($data) && filter_var($data, FILTER_VALIDATE_EMAIL) !== false;
    }

    protected static function validateMinMax(mixed $data, string $bound, int $length): bool {
        if (is_int($data) || is_float($data)) {
            $value = $data;
        } elseif (is_string($data)) {
            $value = strlen($data);
        } else {
            return false;
        }

        return $bound === 'min'
            ? $value >= $length
            : $value <= $length;
    }

    protected static function validateEnum(mixed $data, string $list): bool {
        return is_string($data) && in_array($data, explode(',', $list), true);
    }

    protected static function validatePattern(mixed $data, string $pattern): bool {
        return is_string($data) && preg_match("/$pattern/", $data) === 1;
    }

    protected static function isFile(array $data): bool {
        return !array_is_list($data) && isset($data['error']);
    }

    protected static function isFileList(array $data): bool {
        if ($data === [] || !array_is_list($data)) {
            return false;
        }

        foreach ($data as $file) {
            if (!is_array($file) || !isset($file['error'])) {
                return false;
            }
        }

        return true;
    }

    protected static function fileList(mixed $data): ?array {
        if (!is_array($data) || $data === []) {
            return null;
        }

        if (array_is_list($data)) {
            return self::isFileList($data) ? $data : null;
        }

        return isset($data['error']) ? [$data] : null;
    }

    protected static function validatePdf(mixed $data): bool {
        return self::validateMime($data, self::PDF_MIME);
    }

    protected static function validateImage(mixed $data): bool {
        $files = self::fileList($data);
        if ($files === null) {
            return false;
        }

        foreach ($files as $file) {
            if (!str_starts_with((string) ($file['type'] ?? ''), 'image/')) {
                return false;
            }
        }

        return true;
    }

    protected static function validateMime(mixed $data, string $list): bool {
        $files = self::fileList($data);
        if ($files === null) {
            return false;
        }

        $allowed = explode(',', $list);

        foreach ($files as $file) {
            if (!in_array((string) ($file['type'] ?? ''), $allowed, true)) {
                return false;
            }
        }

        return true;
    }

    protected static function validateFileSize(mixed $data, string $bound, int $size): bool {
        $files = self::fileList($data);
        if ($files === null) {
            return false;
        }

        $isMin = $bound === 'min_size';

        foreach ($files as $file) {
            $fileSize = (int) ($file['size'] ?? 0);

            if ($isMin ? $fileSize < $size : $fileSize > $size) {
                return false;
            }
        }

        return true;
    }

    protected static function validateFileCount(mixed $data, string $bound, int $count): bool {
        $files = self::fileList($data);
        if ($files === null) {
            return false;
        }

        return $bound === 'min_files'
            ? count($files) >= $count
            : count($files) <= $count;
    }

    public static function validate(array $data, array $fieldAndRules): ?array {
        $errors = [];

        foreach ($fieldAndRules as $field => $rules) {
            $result = self::validateField($data[$field] ?? null, $rules);

            if ($result !== null) {
                $errors[$field] = $result;
            }
        }

        return $errors === [] ? null : $errors;
    }

    private static function validateField(mixed $data, array $rules): ?string {
        $isRequired = isset($rules['required']);
        $isEmpty = !$isRequired && !self::validateRequired($data);

        foreach ($rules as $rule => $message) {
            if ($isEmpty && $rule !== 'required') {
                return null;
            }

            if (!self::passes($data, $rule)) {
                return $message;
            }
        }

        return null;
    }

    private static function passes(mixed $data, string $rule): bool {
        [$name, $argument] = self::$parsedRules[$rule] ??= self::parseRule($rule);

        $check = self::RULES[$name] ?? null;

        if ($check === null) {
            return true;
        }

        if ($argument === null) {
            return $check($data);
        }

        if (isset(self::BOUND_RULES[$name])) {
            return $check($data, $name, $argument);
        }

        return $check($data, $argument);
    }

    private static function parseRule(string $rule): array {
        $separator = strpos($rule, ':');

        if ($separator === false) {
            return [$rule, null];
        }

        $name = substr($rule, 0, $separator);
        $value = substr($rule, $separator + 1);

        return [$name, is_numeric($value) ? (int) $value : $value];
    }
}
