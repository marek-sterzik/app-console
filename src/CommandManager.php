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
            "SPSO_APP_AUTOLOAD_PHP" => $this->rootDir . "/vendor/autoload.php",
        ];
        if (isset($config['argv0'])) {
            $this->envVars["SPSO_APP_ARGV0"] = $config['argv0'];
        }
        if (empty($this->scriptsDirs)) {
            $this->scriptsDirs = $this->getCompatScriptsDirs();
        }
    }

    public function getSingleCommand(
        string $name,
        ?array $packages = null,
        bool $allowPrefixMatch = true
    ): ?Command {
        $commands = $this->getCommands($name, $packages, true, $allowPrefixMatch);
        return empty($commands) ? null : $commands[0];
    }

    public function getCommands(
        string $name,
        ?array $packages = null,
        bool $firstOnly = false,
        bool $allowPrefixMatch = true
    ): array {
        $searchIn = [];
        foreach ($this->scriptsDirs as $dir => $dirPackage) {
            if ($packages !== null && !in_array($dirPackage, $packages)) {
                continue;
            }
            $searchIn[] = $dir;
        }

        $commands = [];
        foreach ($searchIn as $dir) {
            $command = $this->createCommand($dir, $name, $this->scriptsDirs[$dir]);
            if ($command !== null) {
                $commands[] = $command;
                if ($firstOnly) {
                    break;
                }
            }
        }
        
        /* do the prefix match if required */
        if (empty($commands) && $allowPrefixMatch) {
            $foundCommand = null;
            foreach ($searchIn as $dir) {
                $command = BinaryFileMapper::instance()->prefixMatchBin($this->rootDir . "/" . $dir, $name);
                if ($command !== null) {
                    if ($foundCommand !== null && $foundCommand !== $command) {
                        $foundCommand = null;
                        break;
                    }
                    $foundCommand = $command;
                }
            }
            if ($foundCommand !== null) {
                foreach ($searchIn as $dir) {
                    $command = $this->createCommand($dir, $foundCommand, $this->scriptsDirs[$dir]);
                    if ($command !== null && !$command->isHidden()) {
                        $commands[] = $command;
                        if ($firstOnly) {
                            break;
                        }
                    }
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
                $command = $this->createCommand($dir, $name, $package);
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

    private function createCommand($dir, $name, $package)
    {
        $dir = $this->rootDir . "/" . $dir;
        $fullDir = Path::canonize($dir);
        $binFiles = BinaryFileMapper::instance()->filesForBin($dir, $name);
        if ($binFiles === null) {
            return null;
        }
        $envVars = $this->envVars;
        $envVars['SPSO_APP_PACKAGE_REL_DIR'] = $dir;
        $command = new Command($binFiles[0], $binFiles[1], $name, $package, $envVars);
        if (!$command->isInvokable()) {
            $command = null;
        }
        return $command;
    }

    private function getCompatScriptsDirs()
    {
        return [];
    }
}
