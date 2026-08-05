<?php

declare(strict_types=1);

namespace Fando\Keeper\Db;

final class Database
{
    private static ?\PDO $instance = null;

    public static function connect(array $config): \PDO
    {
        if (self::$instance === null) {
            self::$instance = new \PDO(
                $config['dsn'],
                $config['username'],
                $config['password'],
                [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                ]
            );
        }
        return self::$instance;
    }
}
