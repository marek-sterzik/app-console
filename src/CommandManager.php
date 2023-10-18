<?php

namespace SPSOstrov\AppConsole;

class CommandManager
{
    private $rootDir;
    private $scriptsDirs;
    private $envVars;
    
    public function __construct($config)
    {
        $this->rootDir = $config['rootDir'];
        $this->scriptsDirs = $config['scripts-dirs'] ?? [];
        $this->envVars = [
            "SPSO_APP_DIR" => $this->rootDir,
            "SPSO_APP_BIN" => $this->rootDir . "/vendor/bin/app",
        ];
        if (isset($config['argv0'])) {
            $this->envVars["SPSO_APP_ARGV0"] = $config['argv0'];
        }
        if (empty($this->scriptsDirs)) {
            $this->scriptsDirs = $this->getCompatScriptsDirs();
        }
    }

    public function getCommand($name)
    {
        if (preg_match('-/-', $name)) {
            return null;
        }
        foreach ($this->scriptsDirs as $dir => $package) {
            $command = $this->createCommand($dir, $name);
            if ($command !== null) {
                return $command;
            }
        }
        return null;
    }

    public function getAllCommands()
    {
        $commands = [];
        foreach ($this->scriptsDirs as $dir => $package) {
            $names = $this->listCommands($dir);
            foreach ($names as $name) {
                $command = $this->createCommand($dir, $name);
                if ($command !== null) {
                    $commands[$name] = $command;
                }
            }
        }
        ksort($commands);
        return $commands;
    }

    private function listCommands($dir)
    {
        $dd = @opendir($this->rootDir . "/" . $dir);
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

    private function createCommand($dir, $name)
    {
        $bin = Path::canonize($this->rootDir . "/" . $dir . "/" . $name);
        if (is_file($bin) && self::isInvokable($bin)) {
            return new Command($bin, $name, $this->envVars);
        }
        return null;
    }
    
    private function isInvokable($bin)
    {
        return is_executable($bin) && !preg_match('/\.json$/', $bin);
    }

    private function getCompatScriptsDirs()
    {
        return [];
    }
}
