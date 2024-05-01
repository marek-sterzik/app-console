<?php

namespace SPSOstrov\AppConsole;

class CommandManager
{
    private $rootDir;
    private $scriptsDirs;
    private $envVars;
    private $binaryFileMapper;
    
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
        $this->binaryFileMapper = new BinaryFileMapper($config);
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
            $foundCommands = [];
            foreach ($searchIn as $dir) {
                $cmds = $this->binaryFileMapper->prefixMatchBin($this->rootDir . "/" . $dir, $name);
                foreach ($cmds as $command) {
                    if (!isset($foundCommands[$command])) {
                        $foundCommands[$command] = [];
                    }
                    $foundCommands[$command][] = $dir;
                }
            }
            $foundCommand = null;
            $foundCommandObject = null;
            $foundDirs = null;
            foreach ($foundCommands as $command => $dirs) {
                $commandObject = $this->createCommand(array_shift($dirs), $command, $this->scriptsDirs[$dir]);
                if ($commandObject !== null && !$commandObject->isHidden()) {
                    if ($foundCommand !== null) {
                        $foundCommand = null;
                        $foundCommandObject = null;
                        $foundDirs = null;
                        break;
                    }
                    $foundCommand = $command;
                    $foundCommandObject = $commandObject;
                    $foundDirs = $dirs;
                }
            }
            if ($foundCommandObject !== null) {
                $commands[] = $foundCommandObject;
                if (!$firstOnly) {
                    foreach ($foundDirs as $dir) {
                        $command = $this->createCommand($dir, $foundCommand, $this->scriptsDirs[$dir]);
                        if ($command !== null) {
                            $commands[] = $command;
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
            $names = $this->binaryFileMapper->listCommandsInDir($this->rootDir . "/" . $dir);
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

    private function createCommand($dir, $name, $dirConfig)
    {
        $dir = $this->rootDir . "/" . $dir;
        $fullDir = Path::canonize($dir);
        $binFiles = $this->binaryFileMapper->filesForBin($dir, $name);
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
