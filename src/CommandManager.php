<?php

namespace SPSOstrov\AppConsole;

class CommandManager
{
    const FORBIDDEN_EXTENSIONS = [".json"];
    private $rootDir;
    private $scriptsDirs;
    private $envVars;
    
    public function __construct(array $config)
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

    public function getCommand(string $name, ?string $package = null): ?Command
    {
        $commands = $this->getCommands($name, $package, true);
        return $commands[0] ?? null;
    }

    public function getCommands(string $name, ?string $package = null, bool $firstOnly = false): array
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

    public function getAllCommands(bool $includeHidden = false): array
    {
        $commands = [];
        foreach ($this->scriptsDirs as $dir => $package) {
            $names = BinaryFileMapper::instance()->listCommandsInDir($this->rootDir . "/" . $dir);
            foreach ($names as $name) {
                $command = $this->createCommand($dir, $name);
                if ($command !== null && ($includeHidden || !$command->isHidden())) {
                    $commands[$name] = $command;
                }
            }
        }
        ksort($commands);
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
        $fullDir = Path::canonize($this->rootDir . "/" . $dir);
        $binFiles = BinaryFileMapper::instance()->filesForBin($dir, $name);
        if ($binFiles === null) {
            return null;
        }
        $command = new Command($binFiles[0], $binFiles[1], $name, $this->envVars);
        if (!$command->isInvokable()) {
            $command = null;
        }
        return null;
    }

    private function getCompatScriptsDirs()
    {
        return [];
    }
}
