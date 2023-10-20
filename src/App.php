<?php

namespace SPSOstrov\AppConsole;
use SPSOstrov\GetOpt\Options;
use Exception;

class App
{
    private $config;
    private $commandManager;

    public function __construct($composerAutoloadPath)
    {
        $rootDir = dirname(Path::canonize(dirname($composerAutoloadPath)));
        $this->config = (new RuntimeConfig($rootDir))->get();
        $this->config['rootDir'] = $rootDir;
        $this->config['argv0'] = null;

        $this->commandManager = null;
    }

    public function run($argv)
    {
        $this->config['argv0'] = $argv[0];
        array_shift($argv);
        
        $this->commandManager = new CommandManager($this->config);

        $getopt = $this->createGlobalGetOpt();


        try {
            $options = $getopt->parseArgs($argv);
        } catch (Exception $e) {
            fprintf(STDERR, "Error: Cannot parse options: %s\n", $e->getMessage());
            fprintf(STDERR, "For help use: %s --help\n", $this->config['argv0']);
            return 1;
        }
        $command = $options['command'] ?? null;
        $args = $options['args'] ?? [];

        if ($options['__help__'] ?? false) {
            if ($command === null) {
                $this->printGlobalHelp();
            } else {
                $commandObj = $this->commandManager->getCommand($command);
                $this->printCommandHelp($commandObj);
            }
            return 1;
        }

        if ($options['__version__'] ?? false) {
            $this->printVersionInfo();
            return 1;
        }

        if ($command === null) {
            $this->printGlobalHelp();
            return 1;
        }

        $commands = $this->commandManager->getCommands($command, null, true);

        if (empty($commands)) {
            fprintf(STDERR, "Unknown command: %s\n", $command);
            return 1;
        }
        
        $finalRet = 0;
        foreach ($commands as $commandObj) {
            $ret = $this->invokeCommand($commandObj, $args);
            if ($ret !== 0) {
                if ($finalRet === 0 || $finalRet === $ret) {
                    $finalRet = $ret;
                } else {
                    $finalRet = 1;
                }
            }
        }

        return $finalRet;
    }

    private function invokeCommand($command, $args)
    {
        foreach ($command->getMetadataErrors() as $error) {
            fprintf(STDERR, "Warning: invalid metadata: %s\n", $error);
        }

        $getopt = $this->createCommandGetOpt($command, true);
        try {
            $options = $getopt->parseArgs($args);
        } catch (Exception $e) {
            fprintf(STDERR, "Error: Cannot parse options: %s\n", $e->getMessage());
            fprintf(STDERR, "For help use: %s %s --help\n", $this->config['argv0'], $command->getName());
            return 1;
        }

        if ($options['__help__'] ?? false) {
            $this->printCommandHelp($command);
            return 1;
        }

        if ($options['__version__'] ?? false) {
            $this->printVersionInfo();
            return 1;
        }

        $args = $command->transformArguments($options, $args);

        return $command->invoke($args);
    }

    private function createCommandsDescriptor()
    {
        $descriptor = [];
        $maxLen = 0;
        foreach ($this->commandManager->getAllCommands() as $name => $command) {
            $maxLen = max($maxLen, strlen($name));
            $descriptor[] = ["command" => $name, "spaces" => "", "description" => $command->getDescription()];
        }
        foreach ($descriptor as &$desc) {
            $n = $maxLen - strlen($desc['command']);
            $desc['spaces'] = str_repeat(" ", $n);
        }
        return $descriptor;
    }

    private function printGlobalHelp()
    {
        fprintf(STDERR, "Usage:\n  " . $this->config['argv0'] . " <command> [options] [args]\n\n");
        $commands = $this->createCommandsDescriptor();
        if (!empty($commands)) {
            fprintf(STDERR, "Available commands:\n");
        } else {
            fprintf(STDERR, "No commands are currently registered in the app console.\n");
        }
        foreach ($commands as $command) {
            if ($command['description'] !== null) {
                fprintf(STDERR, "  %s%s  %s\n", $command['command'], $command['spaces'], $command['description']);
            } else {
                fprintf(STDERR, "  %s\n", $command['command']);
            }
        }
        
    }

    private function printCommandHelp($command)
    {
        $description = $command->getDescription();
        fprintf(STDERR, "Usage:\n  " . $this->config['argv0'] . " " . $command->getName() . " [options] [args]\n");
        if ($description !== null) {
            fprintf(STDERR, "\n$description\n\n");
        }
        $help = $command->getHelp();
        if ($help !== null) {
            fprintf(STDERR, "$help\n");
        }
    }

    private function printVersionInfo()
    {
        fprintf(STDERR, "Error: version info not yet available!\n");
    }

    private function getControlOpts()
    {
        return [
            'h|help[__help__] Show help',
            'v|version[__version__] Show version',
            '$command? Command to be called',
            '$args* Command arguments',
        ];
    }

    private function createGlobalGetOpt()
    {
        return new Options($this->getControlOpts());
    }

    private function createCommandGetOpt($command, $mergeControl)
    {
        $options = $command->getOptions();
        $strictMode = empty($options) ? false : true;
        $options = new Options($options);
        $options->setStrictMode($strictMode);
        if ($mergeControl) {
            foreach ($this->getControlOpts() as $option) {
                $options->registerOption($option, false);
            }
        }
        return $options;
    }
}
