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
        foreach ($this->scriptsDirs as $dir => $dirConfig) {
            if ($packages !== null && !in_array($dirConfig['package'], $packages)) {
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
        foreach ($this->scriptsDirs as $dir => $dirConfig) {
            $names = BinaryFileMapper::instance()->listCommandsInDir($this->rootDir . "/" . $dir);
            foreach ($names as $name) {
                $command = $this->createCommand($dir, $name, $dirConfig);
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

    private function createCommand($dir, $name, $dirConfig)
    {
        $dir = $this->rootDir . "/" . $dir;
        $fullDir = Path::canonize($dir);
        $binFiles = BinaryFileMapper::instance()->filesForBin($dir, $name);
        if ($binFiles === null) {
            return null;
        }
        $envVars = $this->envVars;
        $envVars['SPSO_APP_PACKAGE_REL_DIR'] = $dirConfig['packageRelDir'];
        $command = new Command($binFiles[0], $binFiles[1], $name, $dirConfig['package'], $envVars, $binFiles[2]);
        if (!$command->isInvokable()) {
            $command = null;
        }
        return $command;
    }
}
