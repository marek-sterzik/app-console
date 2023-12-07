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
    private $quiet;

    public function __construct(string $composerAutoloadPath, string $argv0)
    {
        $rootDir = dirname(Path::canonize(dirname($composerAutoloadPath)));
        $this->config = (new RuntimeConfigGenerator())->generateConfig();
        $this->config['rootDir'] = $rootDir;
        $this->config['argv0'] = $argv0;

        $this->commandManager = new CommandManager($this->config);

        $this->formatter = new DefaultFormatter();
        $this->formatter->setWidth(120);

        $this->quiet = false;
    }

    private function message(...$args)
    {
        if (!$this->quiet) {
            fprintf(STDERR, ...$args);
        }
    }

    public function run($argv)
    {
        $quiet = $this->quiet;
        $ret = $this->doRun($argv);
        $this->quiet = $quiet;
        return $ret;
    }

    private function doRun($argv)
    {
        $getopt = $this->createGlobalGetOpt();

        try {
            $options = $getopt->parseArgs($argv);
        } catch (Exception $e) {
            $this->message("Error: Cannot parse options: %s\n", $e->getMessage());
            $this->message("For help use: %s --help\n", $this->config['argv0']);
            return 1;
        }
        $command = $options['command'] ?? null;
        $args = $options['args'] ?? [];
        
        $packages = $options['package'] ?? [];
        if (empty($packages)) {
            $packages = null;
        }

        $all = $options['all'] ?? false;
        $mode = $options['mode'] ?? null;
        $this->quiet = $options['quiet'] ?? false;
        if ($mode === 'l') {
            $mode = 'list';
        }

        $allowPrefixMatch = !$all && $mode === null;

        if ($options['__help__'] ?? false) {
            if ($command === null) {
                $this->printGlobalHelp();
            } else {
                $cmd = $this->commandManager->getSingleCommand($command, $packages);
                if ($cmd !== null) {
                    $this->printCommandHelp($cmd);
                } else {
                    $this->message("Help not available\n");
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

        $commands = $this->commandManager->getCommands($command, $packages, !$all, $allowPrefixMatch);

        if (in_array($mode, ['list', 'test-exists'])) {
            if ($mode === 'list') {
                foreach ($commands as $commandObj) {
                    echo $commandObj->getPackageName() . "\n";
                }
            }
            return empty($commands) ? 1 : 0;
        }

        $invokePlugin = $this->commandManager->getSingleCommand(".invoke");

        // In fact the reversed order is the primary order in the data structures
        // and therefore we need to reverse when one requests the non-reversed
        // order
        if (!($options['reverse'] ?? false)) {
            $commands = array_reverse($commands);
        }

        if (empty($commands) && !$all) {
            $this->message("Error: Unknown command: %s\n", $command);
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
            if ($mode === 'abort-on-failure' && $finalRet !== 0) {
                break;
            }
            if ($mode === 'exit-on-success' && $finalRet === 0) {
                return 0;
            }
        }

        if ($mode === 'exit-on-success') {
            return 1;
        }

        return $finalRet;
    }

    private function invokeCommand(Command $command, array $args, ?Command $invokePlugin)
    {
        foreach ($command->getMetadataErrors() as $error) {
            $this->message("Warning: invalid metadata: %s\n", $error);
        }

        $getopt = $this->createCommandGetOpt($command, true);
        try {
            $options = $getopt->parseArgs($args);
        } catch (Exception $e) {
            $this->message("Error: Cannot parse options: %s\n", $e->getMessage());
            $this->message("For help use: %s %s --help\n", $this->config['argv0'], $command->getName());
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

    private function putHelpPlugin(string $helpPlugin): void
    {
        try {
            $this->run(["-q", sprintf(".help-%s", $helpPlugin)]);
        } catch (Exception $e) {
        }
    }

    private function printGlobalHelp()
    {
        $this->putHelpPlugin("prefix");
        $getopt = $this->createGlobalGetOpt();
        $this->message($getopt->getHelpFormatted($this->formatter)."\n");
        $commandRows = [];
        foreach ($this->commandManager->getAllCommands() as $commandName => $command) {
            $commandRows[] = [
                $commandName,
                $command->getDescription(),
            ];
        }
        if (empty($commandRows)) {
            $commands = null;
            $this->message($this->wrapText("No commands are currently registered in the app console."));
        } else {
            $table = (new AsciiTable())->column([0, 2])->column()->width($this->formatter->getWidth(true));
            $commands = $table->render($commandRows);
            $this->message($this->formatter->formatBlock("Available commands:", $commands));
        }
        $this->putHelpPlugin("suffix");
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
        $this->message($getopt->getHelpFormatted($this->formatter));

        $help = $command->getHelp();
        if ($help !== null) {
            $this->message("\n".$this->wrapText($help, "Description:", true));
        }
    }

    private function printVersionInfo(): void
    {
        $this->message("Error: version info not yet available!\n");
    }

    private function getControlOpts(bool $forSubCommand): array
    {
        $options = [
            'h|help[__help__]        Show help',
            'v|version[__version__]  Show version',
        ];
        if (!$forSubCommand) {
            $options = array_merge($options, [
                'a|all             Run all commands of the given name',
                'r|reverse         Run the commands in a reverse order',
                'p|package*        [=pkg]Run only the command from a specific package',
                'abort-on-failure|exit-on-success|l|list|test-exists{0,1}[mode=@] ' .
                    '[l|list]print packages containing the command to be invoked instead of invoking them' .
                    '[test-exists]test if the command exists' .
                    '[abort-on-failure]abort multiple command execution in case one fails' .
                    '[exit-on-success]exit when the first command succeeds',
                'q|quiet           Suppress internal error messages',
                '$command?         Command to be called',
                '$args*            Command arguments',
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
            $options->registerOptions($this->getControlOpts(true), false);
        }
        return $options;
    }
}
