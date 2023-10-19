<?php

namespace SPSOstrov\AppConsole\GetOpt;

use Exception;

class OptionParser
{
    private $data;
    private $tokens;
    private $optionsLength;
    private $errorPosition;

    public function parse(string $options): array
    {
        $this->initialize($options);

        $this->merge($this->loadOptionList());
        $this->loadByTypeMultiple([
            '{' => 'loadQuantity',
            ':' => 'loadArgType',
            '?' => 'loadArgType',
            '*' => 'loadArgType',
            '~' => 'loadArgType',
            'help' => 'loadDescription',
            'space' => 'loadDescription',
            '=' => 'loadChecker',
            '[' => 'loadWriteRules',
        ], true);

        $token = $this->readToken(false);
        if ($token !== null) {
            $this->unexpectedToken($token);
        }


        return $this->data;

    }

    private function initialize(string $options)
    {
        $this->data = [];
        $this->tokens = (new OptionTokenizer())->tokenize($options);
        $this->tokens->rewind();
        $this->optionsLength = strlen($options);
        $this->errorPosition = null;
    }

    private function merge(array $data): void
    {
        $this->data = array_merge($this->data, $data);
    }

    private function loadWriteRule(): array
    {
        $optionList = $this->loadOptionList(true);
        $identifiers = [];
        foreach (['short', 'long', 'identifier'] as $type) {
            foreach ($optionList[$type] as $short) {
                $identifiers[] = $short;
            }
        }
        $token = $this->readToken(false);
        $from = '$';
        $type = '$';
        if ($token !== null && $token[0] === '=') {
            $token = $this->readNextToken();
            if ($token === null) {
                $this->unexpectedToken($token);
            }
            if ($token[0] === 'identifier') {
                $from = $token[1];
                $type = 'var';
            } elseif ($token[0] === '@') {
                $from = $token[1];
                $type = '@';
            } elseif ($token[0] === '$') {
                $from = '$';
                $type = '$';
            } elseif ($token[0] === 'quote') {
                $from = $this->unquote($token);
                if ($from === null) {
                    $this->unexpectedToken($token);
                }
                $type = 'const';
            } else {
                $this->unexpectedToken($token);
            }
            $this->readToken();
        }
        return ['from' => $from, 'to' => $identifiers, 'type' => $type];
    }

    private function unquote(?array $token): ?string
    {
        if ($token === null || $token[0] !== 'quote') {
            return null;
        }
        $decoded = @json_decode($token[1], true);
        if (!is_string($decoded)) {
            return null;
        }
        return $decoded;
    }

    private function loadWriteRules(): void
    {
        $rules = [];
        $token = $this->readToken(false);
        if ($token === null || $token[0] !== '[') {
            $this->unexpectedToken($token);
        }
        $token = $this->readNextToken();
        $first = true;
        while ($token === null || $token[0] !== ']') {
            if ($token === null) {
                $this->unexpectedToken($token);
            }
            if ($first) {
                $first = false;
            } else {
                if ($token[0] !== ',') {
                    $this->unexpectedToken($token);
                }
                $token = $this->readNextToken();
            }
            $rules[] = $this->loadWriteRule();
            $token = $this->readToken(false);
            if ($token === null || ($token[0] !== ']' && $token[0] !== ',')) {
                $this->unexpectedToken($token);
            }
        }
        $this->readToken();
        $this->merge(['rules' => $rules]);
    }

    private function loadChecker(): void
    {
        $token = $this->readToken(false);
        if ($token === null || $token[1] !== "=") {
            $this->unexpectedToken($token);
        }
        $token = $this->readNextToken();
        if ($token === null || $token[0] !== 'identifier' || !$this->isCheckerIdentifier($token[1])) {
            $this->unexpectedToken($token);
        }
        $this->readToken();
        $this->merge(["checker" => $token[1]]);
    }

    private function isCheckerIdentifier(string $identifier): bool
    {
        return preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)*$/', $identifier);
    }

    private function loadDescription(): void
    {
        $token = $this->readToken(false);
        while ($token !== null && $token[0] === 'space') {
            $token = $this->readNextToken();
        }
        $description = null;
        if ($token !== null) {
            if ($token[0] !== 'help') {
                $this->unexpectedToken($token);
            }
            $description = $token[1];
            $this->readToken();
        }
        $this->merge(["description" => $description]);
    }

    private function putQuantity(int $min, ?int $max): void
    {
        if (array_key_exists('min', $this->data) || array_key_exists('max', $this->data)) {
            $this->error("Quantity can be specified only once");
        }
        if ($max !== null && $max < $min) {
            $this->error("Minimum quantity must be greater than maximum quantity");
        }
        $this->merge(["min" => $min, "max" => $max]);
    }

    private function loadArgType()
    {
        $argTypes = [
            "~" => Option::ARG_NONE,
            ":" => Option::ARG_REQUIRED,
            "?" => Option::ARG_OPTIONAL,
            "*" => Option::ARG_ARRAY,
        ];
        $this->setErrorPosition();
        $token = $this->readToken(false);
        if ($token === null || !isset($argTypes[$token[0]])) {
            $this->error("Invalid quantity specification");
        }
        $this->readToken();
        $argType = $argTypes[$token[0]];
        if (array_key_exists('argType', $this->data) || array_key_exists('argType', $this->data)) {
            $this->error("Option argument type can be specified only once");
        }
        $this->merge(["argType" => $argType]);
    }

    private function loadQuantity()
    {
        $this->setErrorPosition();
        $token = $this->readToken(false);
        if ($token === null || $token[0] !== '{') {
            $this->unexpectedToken($token);
        }
        $token = $this->readNextToken();
        $min = $this->tokenToNum($token);
        if ($min === null && $token[0] !== ',') {
            $this->unexpectedToken($token);
        }
        if ($min !== null || $token[0] !== ',') {
            $token = $this->readNextToken();
        }
        if ($min === null) {
            $min = 0;
        }
        if ($token === null || ($token[0] !== '}' && $token[0] !== ',')) {
            $this->unexpectedToken($token);
        }

        $max = null;

        if ($token[0] === ',') {
            $token = $this->readNextToken();
            $max = $this->tokenToNum($token);
            if ($max === null) {
                if ($token === null || $token[0] !== '}') {
                    $this->unexpectedToken($token);
                }
            } else {
                $token = $this->readNextToken();
            }
            if ($token === null || $token[0] !== '}') {
                $this->unexpectedToken($token);
            }
        }

        $this->readToken();
        $this->putQuantity($min, $max);
    }

    private function tokenToNum(?array $token): ?int
    {
        if ($token === null) {
            return null;
        }
        if ($token[0] !== 'identifier') {
            return null;
        }
        if (!preg_match('/^[0-9]+$/', $token[1])) {
            return null;
        }
        return (int)$token[1];
    }

    private function readToken(bool $moveToNext = true): ?array
    {
        $val = $this->tokens->current();
        if ($moveToNext) {
            $this->tokens->next();
        }
        if ($val && $val[0] === 'error') {
            $this->unexpectedToken($val);
        }
        return $val ? $val: null;
    }

    private function readNextToken(): ?array
    {
        $this->readToken();
        return $this->readToken(false);
    }

    private function loadOptionList(bool $allowIdentifiers = false): array
    {
        $this->setErrorPosition();
        $options = [
            'short' => [],
            'long' => [],
            'identifier' => [],
            'special' => [],
            'argument' => [],
        ];
        $token = $this->readToken(false);
        while ($token !== null) {
            $type = $this->determineOptionType($token);
            if ($type !== null) {
                $options[$type][] = $token[1];
                $token = $this->readNextToken();
                if ($token !== null && $token[0] === '|') {
                    $token = $this->readNextToken();
                    $expectingNext = true;
                } else {
                    break;
                }
            } else {
                $this->unexpectedToken($token);
            }
        }
        if (!$allowIdentifiers) {
            if (!empty($options['identifier'])) {
                $this->error(sprintf("Invalid option name: %s", $options['identifier'][0]));
            }
            unset($options['identifier']);
            
        }

        foreach ($options['special'] as $opt) {
            if ($opt === '@' || $opt === '@@@') {
                $options['short'][] = '@';
            }
            if ($opt === '@@' || $opt === '@@@') {
                $options['long'][] = '@@';
            }
        }
        unset($options['special']);
        foreach (["long", "short"] as $type) {
            $arr = $options[$type];
            $options[$type] = [];
            foreach ($arr as $val) {
                if (!in_array($val, $options[$type])) {
                    $options[$type][] = $val;
                }
            }
        }
        if (count($options['argument']) > 1) {
            $this->error("Invalid option list");
        }
        if (!empty($options['argument'])) {
            if (!empty($options['long']) || !empty($options['short'])) {
                $this->error("Invalid option list");
            }
            $options['long'] = null;
            $options['short'] = null;
        }
        unset($options['argument']);
        return $options;
    }

    private function loadByTypeMultiple(array $types, bool $eachOnlyOnce): void
    {
        $load = true;
        while ($load) {
            $type = $this->loadByTypeSingle($types);
            if ($type !== null) {
                if ($eachOnlyOnce) {
                    unset($types[$type]);
                }
            } else {
                $load = false;
            }
        }
    }

    private function loadByTypeSingle(array $types): ?string
    {
        $token = $this->readToken(false);
        if ($token === null || !isset($types[$token[0]])) {
            return null;
        }
        $method = $types[$token[0]];
        if (is_string($method) && is_callable([$this, $method])) {
            $this->$method();
        } elseif (is_callable($method)) {
            $method();
        } else {
            throw Exception("Parser bug: invalid callback for loadByTypeSingle()");
        }
        return $token[0];
    }

    private function determineOptionType(array $token): ?string
    {
        if ($token[0] === 'identifier') {
            $option = $this->identifierToOption($token[1]);
            if ($option !== null) {
                if (strlen($option) === 1) {
                    return "short";
                } else {
                    return "long";
                }
            }
            return 'identifier';
        } elseif ($token[0] === '@') {
            return 'special';
        } elseif ($token[0] === '$') {
            return 'argument';
        } else {
            return null;
        }
    }

    private function identifierToOption(string $identifier): ?string
    {
        if (preg_match('/^' . ArgsTokenizer::LONGOPT_REGEXP . '$/', $identifier)) {
            return $identifier;
        }
        return null;
    }


    private function setErrorPosition(): void
    {
        $token = $this->readToken(false);
        if ($token === null) {
            $this->errorPosition = $this->optionsLength;
        } else {
            $this->errorPosition = $token[2];
        }
    }

    private function error(string $message, ?array $token = null, bool $nullTokenIsEof = false)
    {
        if ($token !== null) {
            throw new ParserException($message, $token[2]);
        } else {
            throw new ParserException($message, $nullTokenIsEof ? $this->optionsLength : $this->errorPosition);
        }
    }

    private function unexpectedToken(?array $token)
    {
        if ($token !== null) {
            $message = sprintf("Unexpected token: %s", json_encode($token[1]));
        } else {
            $message = "Unexpected end of statement";
        }
        $this->error($message, $token, true);
    }
}
