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

    /** @var imt */
    private $id;

    /** @var string[]|null */
    private $short;

    /** @var string[]|null */
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
        $this->short = $decoded['short'];
        $this->long = $decoded['long'];
        $this->argType = $decoded['argType'] ?? null;
        $this->min = $decoded['min'] ?? null;
        $this->max = $decoded['max'] ?? null;
        $this->checker = $decoded['checker'] ?? null;
        $this->rules = $decoded['rules'] ?? [['from' => '$', 'to' => ['@@', '@'], 'type' => '$']];
        $this->description = $decoded['description'] ?? null;

        if ($this->short !== null) {
            sort($this->short);
        }
        if ($this->long !== null) {
            sort($this->long);
        }

        if ($this->argType === null) {
            if ($this->min === null && $this->max === null) {
                $this->argType = self::ARG_NONE;
            } elseif ($this->max === null || $this->max > 1) {
                $this->argType = self::ARG_ARRAY;
            } else {
                $this->argType = self::ARG_REQUIRED;
            }
        }

        if ($this->min === null) {
            $this->min = 0;
            if ($this->argType === self::ARG_ARRAY) {
                $this->max = null;
            } else {
                $this->max = 1;
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
        return $this->short === null || $this->long === null;
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

    public function getArgType(): string
    {
        return $this->argType;
    }

    public function removeOption(string $option): void
    {
        $remover = function($item) use ($option) {
            return $item !== $option;
        };
        if ($this->short !== null) {
            $this->short = array_filter($this->short, $remover);
        }
        if ($this->long !== null) {
            $this->long = array_filter($this->long, $remover);
        }
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
}
