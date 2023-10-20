<?php

namespace SPSOstrov\AppConsole;

class BinaryFileMapper
{
    private static $instance = null;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function filesForBin(string $dir, string $command): ?array
    {
        if (strpos($command, '/') !== false || preg_match('/\.json$/', $command)) {
            return null;
        }
        $bin = $dir . "/" . $command;
        if (!file_exists($bin)) {
            return null;
        }
        $metadataFile = $bin . ".json";
        if (!file_exists($metadataFile)) {
            $metadataFile = null;
        }

        return [$bin, $metadataFile];

    }

    public function listCommandsInDir(string $dir): array
    {
        $dd = @opendir($dir);
        $commands = [];
        if ($dd) {
            while (($file = readdir($dd)) !== false) {
                if ($file === '.' || $file === '..' || $file === '') {
                    continue;
                }
                if ($this->filesForBin($dir, $file) === null) {
                    continue;
                }
                $commands[] = $file;
            }
            closedir($dd);
        }
        sort($commands);
        return $commands;
    }
}
