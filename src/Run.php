<?php

namespace SPSOstrov\AppConsole;

use Exception;

class Run
{
    private static $forkMode = null;
    private static $stdin = null;

    public static function stdin(?string $stdin): void
    {
        self::$stdin = $stdin;
    }

    public static function run(...$args): int
    {
        $args = self::resolveArgs($args);
        if (self::$forkMode === null) {
            self::$forkMode = self::detectForkMode();
        }
        if (self::$forkMode && self::$stdin === null) {
            return self::runFork(...$args);
        } else {
            $ret = self::runPassthru(...$args);
            self::$stdin = null;
            return $ret;
        }
    }

    public static function exec(...$args): void
    {
        $args = self::resolveArgs($args);
        if (self::$forkMode === null) {
            self::$forkMode = self::detectForkMode();
        }
        if (self::$stdin !== null) {
            self::$stdin = null;
            throw new Exception("Cannot exec with stdin set");
        }
        if (self::$forkMode) {
            self::doExec(...$args);
        } else {
            $ret = self::runPassthru(...$args);
            exit($ret);
        }
    }

    public static function app(...$args): int
    {
        $args = self::resolveArgs($args);
        return self::runApp(false, $args);
    }

    public static function appExec(...$args): void
    {
        $args = self::resolveArgs($args);
        self::runApp(true, $args);
    }

    private static function runApp(bool $exec, array $args): int
    {
        $app = getenv("SPSO_APP_BIN");
        if (!is_string($app)) {
            throw new Exception("Cannot determine the app-console command");
        }
        $args = array_merge([$app], $args);
        if ($exec) {
            self::exec(...$args);
        } else {
            return self::run(...$args);
        }
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
        if (self::$stdin === null) {
            $ret = 0;
            if (passthru($cmd, $ret) === false) {
                $ret = 255;
            }
        } else {
            $descriptorspec = array(
                0 => ["pipe", "r"],
            );

            $process = proc_open($cmd, $descriptorspec, $pipes);

            if ($process) {

                fwrite($pipes[0], self::$stdin);
                fclose($pipes[0]);

                $ret = proc_close($process);
                $ret = ($ret < 0) ? 255 : $ret;
            } else {
                $ret = 255;
            }
        }
        return $ret;
    }

    private static function runFork(...$args): int
    {
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
            self::doExec(...$args);
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

    private static function doExec(...$args): void
    {
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
