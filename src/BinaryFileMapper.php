<?php

namespace SPSOstrov\AppConsole;

class BinaryFileMapper
{
    private $forbiddenExtensions;

    public function __construct(array $config)
    {
        $this->forbiddenExtensions = array_merge([".json"], $config['forbidden-extensions'] ?? []);
        var_dump($config);
    }

    public function prefixMatchBin(string $dir, string $commandPrefix): array
    {
        $prefixLength = strlen($commandPrefix);
        if ($prefixLength == 0) {
            return null;
        }

        $foundCommands = [];
        $dd = @opendir($dir);
        if ($dd) {
            while (($file = readdir($dd)) !== false) {
                if ($file === '.' || $file === '..' || $file === '') {
                    continue;
                }
                if ($this->isForbiddenExtension($file)) {
                    continue;
                }

                if (strlen($file) >= $prefixLength && substr($file, 0, $prefixLength) === $commandPrefix) {
                    $foundCommands[] = $file;
                }
            }
            closedir($dd);
        }
        return $foundCommands;
    }

    private function isForbiddenExtension(string $file): bool
    {
        $file = basename($file);
        $lfile = strlen($file);
        foreach ($this->forbiddenExtensions as $ext) {
            $lext = strlen($ext);
            if ($lfile < $lext) {
                continue;
            }
            if (substr($file, $lfile - $lext, $lext) === $ext) {
                return true;
            }
        }
        return false;
    }

    public function filesForBin(string $dir, string $command, bool $includeMetadata = true): ?array
    {
        if ($command === '' || strpos($command, '/') !== false || $this->isForbiddenExtension($command)) {
            return null;
        }
        $bin = $dir . "/" . $command;
        if (!file_exists($bin)) {
            return null;
        }
        if (!$includeMetadata) {
            return [$bin];
        }
        $metadataFile = $bin . ".json";
        $symlink = false;
        if (!file_exists($metadataFile)) {
            $metadataFile = $this->findMetadataBySymlink($dir, $command, $symlink);
        }

        return [$bin, $metadataFile, $symlink];

    }

    private function findMetadataBySymlink(string $dir, string $command, bool &$isSymlink): ?string
    {
        $link = $this->getSymlinkTarget($dir, $command);
        $isSymlink = false;
        while ($link !== null) {
            $isSymlink = true;
            $file = $dir . "/" . $link . ".json";
            if (file_exists($file)) {
                return $file;
            }
            $link = $this->getSymlinkTarget($dir, $link);
        }
        return null;
    }

    private function getSymlinkTarget(string $dir, string $command): ?string
    {
        $link = @readlink($dir . "/" . $command);
        if (!is_string($link)) {
            return null;
        }
        if (substr($link, 0, 2) === "./") {
            $link = substr($link, 2);
        }
        if ($link !== '' && strpos($link, '/') === false) {
            return $link;
        }
        return null;
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
                if ($this->filesForBin($dir, $file, false) === null) {
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
