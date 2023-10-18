<?php

namespace SPSOstrov\AppConsole;

final class Command
{
    public static function get($name, $config)
    {
        if (preg_match('-/-', $name)) {
            return null;
        }
        foreach ($config['scripts-dirs'] ?? [] as $dir) {
            $command = self::createCommand($config, $dir, $name);
            if ($command !== null) {
                return $command;
            }
        }
        return null;
    }

    public static function getAll($config)
    {
        $commands = [];
        foreach ($config['scripts-dirs'] ?? [] as $dir) {
            $names = self::listCommands($config['rootDir'], $dir);
            foreach ($names as $name) {
                $command = self::createCommand($config, $dir, $name);
                if ($command !== null) {
                    $commands[$name] = $command;
                }
            }
        }
        ksort($commands);
        return $commands;
    }


    private static function listCommands($rootDir, $dir)
    {
        $dd = @opendir($rootDir . "/" . $dir);
        $commands = [];
        if ($dd) {
            while (($file = readdir($dd)) !== false) {
                if ($file !== '.' && $file !== '..' && !preg_match('/\.json$/', $file)) {
                    $commands[] = $file;
                }
            }
            closedir($dd);
        }
        return $commands;
    }

    private static function createCommand($config, $dir, $name)
    {
        $bin = Path::canonize($config['rootDir'] . "/" . $dir . "/" . $name);
        if (is_file($bin) && self::isInvokable($bin)) {
            return new self($bin, $name, $config);
        }
        return null;
    }

    private static function isInvokable($bin)
    {
        return is_executable($bin) && !preg_match('/\.json$/', $bin);
    }

    private $bin;
    private $name;
    private $config;
    private $metadata;

    private function __construct($bin, $name, $config)
    {
        $this->bin = $bin;
        $this->name = $name;
        $this->config = $config;
        $this->metadata = null;
    }

    public function getBin()
    {
        return $this->bin;
    }

    public function getName()
    {
        return $this->name;
    }

    public function invoke($args)
    {
        putenv("SPSO_APP_DIR=" . $this->config['rootDir']);
        putenv("SPSO_APP_BIN=" . Path::canonize($this->config['rootDir'] . "/vendor/bin/app"));
        if (isset($this->config['argv0'])) {
            putenv("SPSO_APP_ARGV0=" . $this->config['argv0']);
        }

        $cmd = escapeshellcmd($this->bin);
        foreach ($args as $arg) {
            $cmd .= " " . escapeshellarg($arg);
        }
        $ret = 1;
        system($cmd, $ret);
        return $ret;
    }

    public function getHelp()
    {
        return $this->metadataTyped("help", "is_string");
    }

    public function getOptions()
    {
        return $this->metadataTyped("options", "is_array") ?? [];
    }

    public function getOperands()
    {
        return $this->metadataTyped("operands", "is_array") ?? [];
    }

    public function getDescription()
    {
        return $this->metadataTyped("description", "is_string");
    }

    public function getPassArgsAsJson()
    {
        return $this->metadata('passArgsAsJson') ? true : false;
    }

    private function metadataTyped($key, $type)
    {
        $data = $this->metadata($key);
        if ($type($data)) {
            return $data;
        }
        return null;
    }

    private function metadata($key)
    {
        if ($this->metadata === null) {
            $this->metadata = $this->loadMetadata();
        }
        return $this->metadata[$key] ?? null;
    }

    private function loadMetadata()
    {
        $data = @file_get_contents($this->bin . ".json");
        if (!is_string($data)) {
            return [];
        }
        $data = @json_decode($data, true);
        if (!is_array($data)) {
            return [];
        }
        return $data;
    }
}
