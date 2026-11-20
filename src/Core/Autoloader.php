<?php
declare(strict_types=1);

namespace App\Core;

final class Autoloader
{
    public static function register(string $baseDir): void
    {
        spl_autoload_register(function (string $class) use ($baseDir): void {
            $prefix = 'App\\';
            if (!str_starts_with($class, $prefix)) {
                return;
            }
            $relative = substr($class, strlen($prefix));
            $file = $baseDir . '/' . str_replace('\\', '/', $relative) . '.php';
            if (is_file($file)) {
                require $file;
            }
        });
    }
}
