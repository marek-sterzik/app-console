<?php

namespace SPSOstrov\AppConsole;

class ParamConverter
{
    const TYPE_ARRAY = '@';
    const TYPE_SCALAR = '$';
    const TYPE_SCALAR_OPT = '?';
    const TYPE_COUNT = '#';
    const TYPE_INVALID = '';

    /** @var string|null */
    private $identifier = null;

    /** @var string */
    private $type;

    /** @var string|null */
    private $default = null;

    
    public function __construct(string $descriptor)
    {
        $this->type = substr($descriptor, 0, 1);
        if (!in_array($this->type, [self::TYPE_ARRAY, self::TYPE_SCALAR, self::TYPE_SCALAR_OPT, self::TYPE_COUNT])) {
            $this->type = self::TYPE_INVALID;
            return;
        }

        $descriptor = substr($descriptor, 1);

        if ($descriptor !== "") {
            if (!preg_match('/^([a-zA-Z0-9_.-]+)(.*)$/', $descriptor, $matches)) {
                $this->type = self::TYPE_INVALID;
                return;
            }
            $this->identifier = $matches[1];
            $descriptor = $matches[2];
            if ($descriptor !== '') {
                if (substr($descriptor, 0, 1) !== '?') {
                    $this->type = self::TYPE_INVALID;
                    return;
                }
                $descriptor = substr($descriptor, 1);
                $default = @json_decode($descriptor, true);
                if (!is_string($default)) {
                    $this->type = self::TYPE_INVALID;
                    return;
                }
                $this->default = $default;
            }
        }
    }

    public function getArgs(array $options, array $args): array
    {
        if ($this->type === self::TYPE_INVALID) {
            return [];
        }
        if ($this->identifier !== null) {
            $data = $options[$this->identifier] ?? [];
        } else {
            $data = $args;
        }
        if (!is_array($data)) {
            $data = [$data];
        }
        if ($this->type === self::TYPE_COUNT) {
            return [count($data)];
        }
        if (empty($data) && $this->default !== null) {
            $data[] = $this->default;
        }
        if (in_array($this->type, [self::TYPE_SCALAR, self::TYPE_SCALAR_OPT]) && count($data) > 1) {
            $data = [$data[0]];
        }

        if ($this->type === self::TYPE_SCALAR && empty($data)) {
            $data[] = '';
        }
        
        foreach ($data as &$item) {
            if (is_bool($item)) {
                $item = $item ? 1 : 0;
            }
        }

        return $data;
    }
}

