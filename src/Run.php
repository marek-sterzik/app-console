<?php

namespace SPSOstrov\AppConsole;

use Exception;

class Run
{
    public static function run(...$args): int
    {
        $args = self::resolveArgs($args);
        if (empty($args)) {
            throw new Exception("Running command needs to specify the command name");
        }
        $cmd = escapeshellcmd(array_shift($args));
        foreach ($args as $arg) {
            if (!is_scalar($arg)) {
                throw new Exception("Invalid argument passed to a command");
            }
            $cmd .= " " . escapeshellarg((string)$arg);
        }
        $ret = 0;
        if (passthru($cmd, $ret) === false) {
            $ret = 255;
        }
        return $ret;
    }

    public static function app(...$args): int
    {
        $args = self::resolveArgs($args);
        $app = getenv("SPSO_APP_BIN");
        if (!is_string($app)) {
            throw new Exception("Cannot determine the app-console command");
        }
        return self::run(...array_merge([$app], $args));
    }

    private static function resolveArgs(array $args): array
    {
        if (count($args) === 1 && is_array($args[0])) {
            return array_values($args[0]);
        }
        return $args;
    }
}
