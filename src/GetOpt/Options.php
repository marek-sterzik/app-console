<?php

namespace SPSOstrov\AppConsole\GetOpt;

class Options
{
    /** @var array<string,Option> */
    private $map = [];

    /** @var Option[] */
    private $argMap = [];

    /** @var Option[] */
    private $options = [];

    /** @var bool */
    private $strictMode = true;

    public function __construct(array $options, bool $strictMode = true)
    {
        $this->strictMode = $strictMode;
        foreach ($options as $option) {
            $this->registerOption($option);
        }
    }

    public function registerOption($option, bool $strict = true)
    {
        $cloned = false;
        if (!($option instanceof Option)) {
            if (!is_string($option)) {
                throw new ParserException("Options needs to be specified as strings");
            }
            $option = new Option($option);
            $cloned = true;
        }
        if ($option->isArgument()) {
            $this->argMap[] = $option;
        } else {
            foreach ($option->getAll() as $name) {
                if (isset($this->map[$name])) {
                    if ($strict) {
                        if ($name === '@@') {
                            $name = "<default-long>";
                        } elseif ($name === '@') {
                            $name = "<default-short>";
                        } else {
                            if (strlen($name) == 1) {
                                $name = "-" . $name;
                            } else {
                                $name = "--" . $name;
                            }
                        }
                        $message = sprintf("Option %s must be defined only once in Options", $name);
                        throw new ParserException($message);
                    } else {
                        if (!$cloned) {
                            $option = clone $option;
                            $cloned = true;
                        }
                        $option->removeOption($name);
                    }
                }
                $this->map[$name] = $option;
            }
        }
        $this->options[] = $option;
    }

    public function getStrictMode(): bool
    {
        return $this->strictMode;
    }

    public function setStrictMode(bool $strictMode = true): self
    {
        $this->strictMode = $strictMode;
        return $this;
    }

    public function getArgTypeFor(string $option): ?string
    {
        $option = $this->getOptionFor($option);
        if ($option === null) {
            return null;
        }
        return $option->getArgType();
    }

    public function getOptionFor(string $option): ?Option
    {
        if ($option === '') {
            return null;
        }
        if (isset($this->map[$option])) {
            return $this->map[$option];
        }
        $option = (strlen($option) == 1)? '@' : '@@';
        return $this->map[$option] ?? null;
    }

    public function parseArgs(?array $args = null): array
    {
        if ($args === null) {
            $args = $_SERVER['argv'] ?? ["x"];
            array_shift($args);
        }
        $parser = new ArgsParser($this);
        return $parser->parse($args);
    }
}
