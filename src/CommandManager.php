<?php

namespace SPSOstrov\AppConsole;

class CommandManager
{
    const FORBIDDEN_EXTENSIONS = [".json"];
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

    public function getCommand($name, $package = null)
    {
        $commands = $this->getCommands($name, $package, true);
        return $commands[0] ?? null;
    }

    public function getCommands($name, $package = null, $firstOnly = false)
    {
        $commands = [];
        if (preg_match('-/-', $name)) {
            return [];
        }
        foreach ($this->scriptsDirs as $dir => $dirPackage) {
            if ($package !== null && $package !== $dirPackage) {
                continue;
            }
            $command = $this->createCommand($dir, $name);
            if ($command !== null) {
                $commands[] = $command;
                if ($firstOnly) {
                    break;
                }
            }
        }
        return $commands;
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
                if (substr($file, 0, 1) === '.' || $file === '') {
                    continue;
                }
                if ($this->hasForbiddenExtension($file)) {
                    continue;
                }
                $commands[] = $file;
            }
            closedir($dd);
        }
        return $commands;
    }

    private function hasForbiddenExtension($file)
    {
        $fl = strlen($file);
        foreach (self::FORBIDDEN_EXTENSIONS as $ext) {
            $el = strlen($ext);
            if ($fl <= $el) {
                continue;
            }
            if (substr($file, $fl - $el, $el) !== $ext) {
                continue;
            }
            return true;
        }
        return false;
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
