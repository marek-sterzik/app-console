<?php

namespace SPSOstrov\AppConsole\GetOpt;

class Option
{
    const ARG_NONE = "none";
    const ARG_REQUIRED = "required";
    const ARG_OPTIONAL = "optional";
    const ARG_ARRAY = "array";

    /** @var int */
    private static $idCounter = 0;

    /** @var int */
    private $id;

    /** @var bool */
    private $isArgument;

    /** @var string[] */
    private $short;

    /** @var string[] */
    private $long;

    /** @var string */
    private $argType;

    /** @var int|null */
    private $min;

    /** @var int|null */
    private $max;

    /** @var string|null */
    private $checker;

    /** @var array */
    private $rules;

    /** @var string|null */
    private $description;


    public function __construct(string $optionDescription)
    {
        $this->id = ++static::$idCounter;
        $decoded = (new OptionParser())->parse($optionDescription);
        $this->isArgument = $decoded['isArgument'];
        $this->short = $decoded['short'];
        $this->long = $decoded['long'];
        $this->argType = $decoded['argType'] ?? null;
        $this->min = $decoded['min'] ?? null;
        $this->max = $decoded['max'] ?? null;
        $this->checker = $decoded['checker'] ?? null;
        $this->rules = $decoded['rules'] ?? [['from' => '$', 'to' => ['@@@'], 'type' => '$']];
        $this->description = $decoded['description'] ?? null;

        if ($this->argType === null) {
            if ($this->min === null && $this->max === null) {
                if ($this->isArgument()) {
                    $this->argType = self::ARG_REQUIRED;
                } else {
                    $this->argType = self::ARG_NONE;
                }
            } elseif ($this->max === null || $this->max > 1) {
                $this->argType = self::ARG_ARRAY;
            } else {
                $this->argType = self::ARG_NONE;
            }
        }

        if ($this->min === null) {
            if ($this->isArgument()) {
                if ($this->argType === self::ARG_ARRAY) {
                    $this->min = 0;
                    $this->max = null;
                } elseif ($this->argType === self::ARG_OPTIONAL) {
                    $this->min = 0;
                    $this->max = 1;
                } elseif ($this->argType === self::ARG_REQUIRED) {
                    $this->min = 1;
                    $this->max = 1;
                } else {
                    $this->min = 0;
                    $this->max = 0;
                }
            } else {
                $this->min = 0;
                if ($this->argType === self::ARG_ARRAY) {
                    $this->max = null;
                } else {
                    $this->max = 1;
                }
            } 
        }

        if ($this->description !== null) {
            $this->description = trim($this->description);
            if ($this->description === "") {
                $this->description = null;
            }
        }
    }

    public function __clone()
    {
        $this->id = ++static::$idCounter;
    }


    public function id(): int
    {
        return $this->id;
    }

    public function hasArgument(): bool
    {
        return in_array($this->argType, [self::ARG_ARRAY, self::ARG_REQUIRED, self::ARG_OPTIONAL]);
    }

    public function isArgument(): bool
    {
        return $this->isArgument;
    }

    public function getShort(): array
    {
        return $this->short;
    }

    public function getLong(): array
    {
        return $this->long;
    }

    public function getAll(): array
    {
        return array_merge($this->short, $this->long);
    }

    public function getMin(): int
    {
        return $this->min;
    }

    public function getMax(): ?int
    {
        return $this->max;
    }

    public function getArgType(): string
    {
        return $this->argType;
    }

    public function removeOption(string $option): void
    {
        $remover = function($item) use ($option) {
            return $item !== $option;
        };
        $this->short = array_filter($this->short, $remover);
        $this->long = array_filter($this->long, $remover);
    }

    public function getChecker(): ?string
    {
        return $this->checker;
    }

    public function getRules(): array
    {
        return $this->rules;
    }

    public function useArrayWrite(): bool
    {
        return in_array($this->argType, [self::ARG_ARRAY]);
    }

    public function getRepresentative(bool $decorate = false): ?string
    {
        if (!empty($this->long)) {
            $representative = $this->long[0];
        } elseif (!empty($this->short)) {
            $representative = $this->short[0];
        } else {
            return null;
        }
        if ($decorate) {
            $representative = $this->decorateOption($representative);
        }
        return $representative;
    }

    public function getRepresentatives(bool $decorate = false): ?string
    {
        if (!empty($this->long)) {
            $representatives = $this->long;
        } elseif (!empty($this->short)) {
            $representatives = $this->short;
        } else {
            return null;
        }
        if ($decorate) {
            $representatives = array_map(function ($name) {
                return $this->decorateOption($name);
            }, $representatives);
        }
        return implode(", ", $representatives);
    }

    private function decorateOption(string $name): string
    {
        if ($this->isArgument()) {
            return '<' . $name . '>';
        } else {
            if (strlen($name) <= 1) {
                return '-' . $name;
            } else {
                return '--' . $name;
            }
        }
    }
}
