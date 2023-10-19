<?php

namespace SPSOstrov\AppConsole\GetOpt;

class ArgsParser
{
    /** @var ArgsTokenizer */
    private $tokenizer;
    
    /** @var Options */
    private $options;

    /** @var array */
    private $data = [];

    /** @var array */
    private $statistics = [];

    public function __construct(Options $options)
    {
        $this->options = $options;
        $this->tokenizer = new ArgsTokenizer($options);
    }

    public function parse(array $args): array
    {
        $this->data = [];
        $this->statistics = [];
        $nArg = 0;
        foreach ($this->tokenizer->tokenize($args) as $token) {
            if ($token[0] === 'error') {
                throw new ArgsException($token[1], $token[3]);
            } elseif ($token[0] === 'option') {
                $option = $this->options->getOptionFor($token[1]);
                if ($option === null) {
                    throw new ArgsException("ArgsParser Bug!");
                }
                $this->processOption($option, $token[1], $token[2]);
            } elseif ($token[0] === 'arg') {
                $option = $this->options->getOptionForArg($nArg++);
                if ($option === null) {
                    throw new ArgsException("Unexpected argument");
                }
                $this->processOption($option, null, $token[1]);
            } else {
                throw new ArgsException("ArgsParser Bug!", $token[3]);
            }
        }
        $this->checkLimits();
        return $this->data;
    }

    private function checkLimits(): void
    {
        foreach ($this->options->getAll() as $option) {
            $count = $this->statistics[$option->id()] ?? 0;
            $optionName = $option->getRepresentatives(true) ?? '<unknown>';
            $type = $option->isArgument() ? 'argument' : 'option';
            $message = null;
            if ($count < $option->getMin()) {
                if ($option->getMin() > 1) {
                    $note = sprintf(" (at least %d required)", $option->getMin());
                } else {
                    $note = "";
                }
                if ($count === 0) {
                    $message = sprintf("Missing %s %s%s", $type, $optionName, $note);
                } else {
                    $message = sprintf("Too few %ss %s%s", $type, $optionName, $note);
                }
            } elseif ($option->getMax() !== null && $option->getMax() < $count) {
                if ($option->getMax() > 1) {
                    $message = sprintf("Too many %ss %s (only %d options allowed)", $type, $optionName, $option->getMax());
                } elseif ($option->getMax() < 1) {
                    $message = sprintf("Using %s %s is forbidden", $type, $optionName);
                } else {
                    $message = sprintf("Using %s %s is allowed only once", $type, $optionName);
                }
            }
            if ($message !== null) {
                throw new ArgsException($message);
            }
        }
    }

    private function processOption(Option $option, ?string $optionNameUsed, ?string $optArg): void
    {
        if ($option->isArgument() || $option->hasArgument()) {
            $value = $optArg;
        } else {
            $value = true;
        }
        $this->checkValue($value, $option->getChecker());
        $arrayWrite = $option->useArrayWrite();
        foreach ($option->getRules() as $rule) {
            $valueToWrite = $this->createValue($option, $optionNameUsed, $rule, $value);
            foreach ($rule['to'] as $key) {
                $this->writeValue($option, $key, $valueToWrite, $arrayWrite);
            }
        }
        $id = $option->id();
        $this->statistics[$id] = ($this->statistics[$id] ?? 0) + 1;
    }

    private function createValue(Option $option, ?string $optionNameUsed, array $rule, $value)
    {
        $type = $rule['type'];
        $from = $rule['from'];
        switch ($type) {
            case 'var':
                $from = $this->data[$from] ?? null;
                if (is_array($from)) {
                    if (empty($from)) {
                        $from = null;
                    } else {
                        $from = array_pop($from);
                    }
                }
                return $from;
            case 'const':
                return $from;
            case '@':
                if ($from === '@' || $from === '@@') {
                    if ($optionNameUsed !== null) {
                        return $optionNameUsed;
                    } else {
                        return $option->getRepresentative();
                    }
                } else {
                    return implode("|", $option->getAll());
                }
            case '$':
                return $value;
            default:
                return null;
        }
    }

    private function writeValue(Option $option, string $key, $value, bool $arrayWrite): void
    {
        if ($key === "@") {
            $keys = $option->getShort();
        } elseif ($key === "@@") {
            $keys = $option->getLong();
        } elseif ($key === "@@@") {
            $keys = $option->getAll();
        } else {
            if ($arrayWrite) {
                $current = $this->data[$key] ?? [];
                if (!is_array($current)) {
                    if ($current === null) {
                        $current = [];
                    } else {
                        $current = [$current];
                    }
                }
                if (is_array($value)) {
                    $current = array_merge($current, $value);
                } else {
                    $current[] = $value;
                }
                $this->data[$key] = $current;
            } else {
                $this->data[$key] = $value;
            }
            return;
        }

        $keys = array_filter($keys, function($item) {
            if (!preg_match('/^@{1,3}$/', $item)) {
                return true;
            }
            return false;
        });

        foreach ($keys as $key) {
            $this->writeValue($option, $key, $value, $arrayWrite);
        }
    }

    private function checkValue(&$value, ?string $checker): void
    {
    }
}
