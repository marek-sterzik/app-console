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
        foreach ($this->tokenizer->tokenize($args) as $token) {
            if ($token[0] === 'error') {
                throw new ArgsException($token[1], $token[3]);
            } elseif ($token[0] === 'option') {
                $this->processOption($token[1], $token[2], $token[3]);
            } elseif ($token[0] === 'arg') {
                $this->processArg($token[1], $token[3]);
            } else {
                throw new ArgsException("ArgsParser Bug!", $token[3]);
            }
        }
        return $this->data;
    }

    private function processOption(string $option, ?string $optArg, int $argNumber): void
    {
        $option = $this->options->getOptionFor($option);
        if ($option === null) {
            throw new ArgsException("ArgsParser Bug!", $argNumber);
        }
        if ($option->hasArgument()) {
            $value = $optArg;
        } else {
            $value = true;
        }
        $this->checkValue($value, $option->getChecker());
        $arrayWrite = $option->useArrayWrite();
        foreach ($option->getRules() as $rule) {
            $valueToWrite = $this->createValue($option, $rule, $value);
            foreach ($rule['to'] as $key) {
                $this->writeValue($option, $key, $value, $arrayWrite);
            }
        }
        $id = $option->id();
        $this->statistics[$id] = ($this->statistics[$id] ?? 0) + 1;
    }

    private function createValue(Option $option, array $rule, $value)
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
                if ($from === '@') {
                    $data = $option->getShort();
                } elseif ($from === '@@') {
                    $data = $option->getLong();
                } elseif ($from === '@@@') {
                    $data = $option->getAll();
                } else {
                    $data = [];
                }
                if (empty($data)) {
                    return null;
                }
                return implode("|", $data);
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
