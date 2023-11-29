<?php

namespace SPSOstrov\AppConsole;

use Exception;

class Run
{
    private static $forkMode = null;

    public static function run(...$args): int
    {
        if (self::$forkMode === null) {
            self::$forkMode = self::detectForkMode();
        }
        if (self::$forkMode) {
            return self::runFork(...$args);
        } else {
            return self::runPassthru(...$args);
        }
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

    public static function forkMode(?bool $forkMode = null): bool
    {
        if (self::$forkMode === null) {
            self::$forkMode = self::detectForkMode();
        }
        $oldForkMode = self::$forkMode;
        if ($forkMode !== null) {
            self::$forkMode = $forkMode;
        }
        return $oldForkMode;
    }

    private static function runPassthru(...$args): int
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

    private static function runFork(...$args): int
    {
        $args = self::resolveArgs($args);
        if (empty($args)) {
            throw new Exception("Running command needs to specify the command name");
        }
        $child = pcntl_fork();
        if ($child < 0) {
            throw new Exception("Fork failed");
        }
        if ($child > 0) {
            $status = null;
            pcntl_waitpid($child, $status);
            return pcntl_wexitstatus($status);
        } else {
            self::exec(...$args);
        }
    }

    private static function detectForkMode(): bool
    {
        if (ini_get('safe_mode')) {
            return false;
        }
        $disabled = ini_get('disable_functions');
        if ($disabled) {
            $disabled = array_map('trim', explode(',', $disabled));
            return !in_array('pcntl_fork', $disabled) && !in_array('pcntl_exec', $disabled);
        }
        return true;
    }

    private static function exec(...$args): void
    {
        $args = self::resolveArgs($args);
        if (empty($args)) {
            throw new Exception("Command exec needs to specify the command name");
        }
        $command = array_shift($args);
        if (strpos($command, '/') === false) {
            $found = false;
            foreach (explode(":", getenv("PATH") ?: "") as $path) {
                if ($path === "") {
                    continue;
                }
                $cmd = $path . "/" . $command;
                if (is_file($cmd)) {
                    $command = $cmd;
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                throw new Exception(sprintf("Command not found: %s", $command));
            }
        }
        pcntl_exec($command, $args);
        throw new Exception(sprintf("Cannot execute command: %s", $command));
    }

    private static function resolveArgs(array $args): array
    {
        if (count($args) === 1 && is_array($args[0])) {
            return array_values($args[0]);
        }
        return $args;
    }
}
