<?php

namespace SPSOstrov\AppConsole;
use SPSOstrov\GetOpt\Options;
use SPSOstrov\GetOpt\AsciiTable;
use SPSOstrov\GetOpt\Formatter;
use SPSOstrov\GetOpt\DefaultFormatter;

use Exception;

class App
{
    private $config;
    private $commandManager;
    private $formatter;

    public function __construct($composerAutoloadPath)
    {
        $rootDir = dirname(Path::canonize(dirname($composerAutoloadPath)));
        $this->config = (new RuntimeConfigGenerator())->generateConfig();
        $this->config['rootDir'] = $rootDir;
        $this->config['argv0'] = null;

        $this->commandManager = null;

        $this->formatter = new DefaultFormatter();
        $this->formatter->setWidth(120);
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
        
        $packages = $options['package'] ?? [];
        if (empty($packages)) {
            $packages = null;
        }

        $all = $options['all'] ?? false;

        if ($options['__help__'] ?? false) {
            if ($command === null) {
                $this->printGlobalHelp();
            } else {
                $cmd = $this->commandManager->getSingleCommand($command, $packages);
                if ($cmd !== null) {
                    $this->printCommandHelp($cmd);
                } else {
                    fprintf(STDERR, "Help not available");
                }
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

        $commands = $this->commandManager->getCommands($command, $packages, !$all);

        $invokePlugin = $this->commandManager->getSingleCommand(".invoke");

        // In fact the reversed order is the primary order in the data structures
        // and therefore we need to reverse when one requests the non-reversed
        // order
        if (!($options['reverse'] ?? false)) {
            $commands = array_reverse($commands);
        }

        if (empty($commands) && !$all) {
            fprintf(STDERR, "Unknown command: %s\n", $command);
            return 1;
        }
        
        $finalRet = 0;
        foreach ($commands as $commandObj) {
            $ret = $this->invokeCommand($commandObj, $args, $invokePlugin);
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

    private function invokeCommand(Command $command, array $args, ?Command $invokePlugin)
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

        $invoker = null;
        if ($invokePlugin !== null) {
            $invoker = $invokePlugin->getInvokerBinary($command);
        }

        $args = $command->transformArguments($options, $args);

        return $command->invoke($args, $invoker);
    }

    private function printGlobalHelp()
    {
        $getopt = $this->createGlobalGetOpt();
        fprintf(STDERR, $getopt->getHelpFormatted($this->formatter)."\n");
        $commandRows = [];
        foreach ($this->commandManager->getAllCommands() as $commandName => $command) {
            $commandRows[] = [
                $commandName,
                $command->getDescription(),
            ];
        }
        if (empty($commandRows)) {
            $commands = null;
            fprintf(STDERR, $this->wrapText("No commands are currently registered in the app console."));
        } else {
            $table = (new AsciiTable())->column([0, 2])->column()->width($this->formatter->getWidth(true));
            $commands = $table->render($commandRows);
            fprintf(STDERR, $this->formatter->formatBlock("Available commands:", $commands));
        }
        
    }

    private function wrapText(?string $text, ?string $caption = null, bool $indent = false): string
    {
        $table = (new AsciiTable())->column()->width($this->formatter->getWidth($indent));
        $result = $table->render([[$text]]);
        if ($indent) {
            $result = $this->formatter->formatBlock($caption, $result);
        }
        return $result;
    }

    private function printCommandHelp(Command $command): void
    {
        $description = $command->getDescription();
        $getopt = $this->createCommandGetOpt($command, false);
        fprintf(STDERR, $getopt->getHelpFormatted($this->formatter));

        
        $help = $command->getHelp();
        if ($help !== null) {
            fprintf(STDERR, "\n".$this->wrapText($help, "Description:", true));
        }
    }

    private function printVersionInfo(): void
    {
        fprintf(STDERR, "Error: version info not yet available!\n");
    }

    private function getControlOpts(bool $forSubCommand): array
    {
        $options = [
            'h|help[__help__]        Show help',
            'v|version[__version__]  Show version',
            '$command?               Command to be called',
            '$args*                  Command arguments',
        ];
        if (!$forSubCommand) {
            $options = array_merge($options, [
                'a|all       Run all commands of the given name',
                'r|reverse   Run the commands in a reverse order',
                'p|package*  Run only the command from a specific package',
            ]);
        }
        return $options;
    }

    private function createGlobalGetOpt(): Options
    {
        $options = new Options($this->getControlOpts(false));
        $options->setArgv0($this->config['argv0']);
        return $options;
    }

    private function createCommandGetOpt(Command $command, bool $mergeControl): Options
    {
        $options = $command->getOptions();
        $strictMode = empty($options) ? false : true;
        $options = new Options($options);
        $options->setStrictMode($strictMode);
        $options->setArgv0($this->config['argv0'] . " " . $command->getName());
        if ($mergeControl) {
            foreach ($this->getControlOpts(true) as $option) {
                $options->registerOption($option, false);
            }
        }
        return $options;
    }
}
