<?php

namespace SPSOstrov\AppConsole\GetOpt;

class ArgsTokenizer
{
    const LONGOPT_REGEXP = '[a-zA-Z0-9]+(-[a-zA-Z0-9]+)*';
    const SHORTOPT_REGEXP = '[a-zA-Z0-9]';

    /** @var Options */
    private $options;

    /** @var bool */
    private $errorOnBadOption;

    public function __construct(Options $options)
    {
        $this->options = $options;
        $this->errorOnBadOption = $options->getStrictMode();
    }

    public function tokenize(array $args): iterable
    {
        $n = count($args);
        $index = 0;
        $optIndex = 0;
        $state = "init";
        $optionsFinished = false;
        $stateOption = null;
        while ($index < $n && !$optionsFinished) {
            $currentArg = substr($args[$index], $optIndex);
            if ($state === "init") {
                if ($currentArg == '--') {
                    $optionsFinished = true;
                    $index++;
                } elseif (substr($currentArg, 0, 2) == '--') {
                    $optIndex += 2;
                    $state = 'long';
                } elseif (substr($currentArg, 0, 1) == '-') {
                    $optIndex += 1;
                    $state = "short";
                } else {
                    $optionsFinished = true;
                }
            } elseif ($state === 'short') {
                $noArgOptions = [];
                $error = false;
                $option = null;
                $argType = null;
                for ($i = 0; $i < strlen($currentArg); $i++) {
                    $option = substr($currentArg, $i, 1);
                    if (!preg_match('/^' . self::SHORTOPT_REGEXP .'$/', $option)) {
                        $error = true;
                        break;
                    }
                    $argType = $this->options->getArgTypeFor($option);
                    if ($argType === null) {
                        $error = true;
                        break;
                    }
                    if ($argType === Option::ARG_NONE) {
                        $noArgOptions[] = $option;
                    } else {
                        break;
                    }
                }
                if ($option === null || $argType === null) {
                    $error = true;
                }
                if ($i >= strlen($currentArg)) {
                    $option = null;
                    $argType = null;
                }
                if ($error) {
                    if ($this->errorOnBadOption) {
                        yield ["error", "Invalid option", null, $index];
                        return;
                    }
                    $optIndex = 0;
                    $optionsFinished = true;
                    $state = "init";
                } else {
                    foreach ($noArgOptions as $opt) {
                        yield ["option", $opt, null, $index];
                    }
                    if ($option !== null) {
                        $rest = substr($currentArg, $i+1);
                        if ($rest !== '') {
                            yield ["option", $option, $rest, $index];
                            $index++;
                            $optIndex = 0;
                            $state = "init";
                        } else {
                            if ($argType === Option::ARG_OPTIONAL) {
                                $state = 'optional-arg';
                            } else {
                                $state = 'required-arg';
                            }
                            $index++;
                            $optIndex = 0;
                            $stateOption = $option;
                        }
                    } else {
                        $index++;
                        $optIndex = 0;
                        $state = "init";
                    }
                }
            } elseif ($state === 'long') {
                $error = false;
                if (preg_match('/^'. self::LONGOPT_REGEXP . '$/', $currentArg)) {
                    $option = $currentArg;
                    $argument = null;
                } elseif (preg_match('/^('. self::LONGOPT_REGEXP . ')=(.*)$/', $currentArg, $matches)) {
                    $option = $matches[1];
                    $argument = array_pop($matches);
                } else {
                    $error = true;
                }
                if (!$error) {
                    $argType = $this->options->getArgTypeFor($option);
                }
                if ($error || $argType === null || ($argType === Option::ARG_NONE && $argument !== null)) {
                    if ($this->errorOnBadOption) {
                        yield ["error", "Invalid option", null, $index];
                        return;
                    }
                    $optIndex = 0;
                    $optionsFinished = true;
                    $state = "init";
                } elseif ($argType === Option::ARG_NONE || $argument !== null) {
                    yield ["option", $option, $argument, $index];
                    $index++;
                    $optIndex = 0;
                    $state = "init";
                } else {
                    $index++;
                    $optIndex = 0;
                    $state = ($argType === Option::ARG_OPTIONAL) ? 'optional-arg' : 'required-arg';
                    $stateOption = $option;
                }
            } elseif ($state === 'optional-arg') {
                if (substr($currentArg, 0, 1) === '-') {
                    yield ["option", $stateOption, null, $index - 1];
                    $stateOption = null;
                    $state = "init";
                } else {
                    $state = "required-arg";
                }
            } elseif ($state === 'required-arg') {
                yield ["option", $stateOption, $currentArg, $index];
                $state = "init";
                $index++;
                $optIndex = 0;
                $stateOption = null;
            } else {
                yield ["error", "Bug in argument tokenizer", null, $index];
            }
        }
        if ($state === 'optional-arg') {
            yield ["option", $stateOption, null, $index - 1];
            $stateOption = null;
            $state = "init";
        }
        if ($state !== "init") {
            yield ["error", "Argument expected for option", null, $index];
            return;
        }
        while ($index < $n) {
            yield ["arg", $args[$index], null, $index];
            $index++;
        }
        
    }
}
