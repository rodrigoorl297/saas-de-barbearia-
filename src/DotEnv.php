<?php
namespace App;

class DotEnv
{
    /**
     * Carrega variáveis de um arquivo .env simples (chave=valor).
     */
    public static function load(string $path): void
    {
        if (!is_file($path) || !is_readable($path)) {
            return;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return;
        }
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (!str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if ($value !== '' && ($value[0] === '"' || $value[0] === "'")) {
                $value = trim($value, "\"'");
            }
            if ($key === '') {
                continue;
            }
            if (getenv($key) === false) {
                putenv($key . '=' . $value);
                $_ENV[$key] = $value;
            }
        }
    }

    public static function getBool(string $key, bool $default = false): bool
    {
        $v = getenv($key);
        if ($v === false || $v === '') {
            return $default;
        }
        return in_array(strtolower(trim((string)$v)), ['1', 'true', 'yes', 'on'], true);
    }

    public static function getString(string $key, string $default = ''): string
    {
        $v = getenv($key);
        if ($v === false || $v === '') {
            return $default;
        }
        return (string)$v;
    }
}
