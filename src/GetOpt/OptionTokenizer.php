<?php

namespace SPSOstrov\AppConsole\GetOpt;

class OptionTokenizer
{
    const STANDALONE_CHARS = "[]|:?*={},$~";
    public function tokenize(string $optionDescriptor): iterable
    {
        $n = strlen($optionDescriptor);
        $acc = "";
        $accType = null;
        $accIndex = null;
        $quoteChar = null;
        $i = 0;
        $read = true;
        while ($i < $n) {
            $char = substr($optionDescriptor, $i, 1);
            if ($accType === null) {
                if (ctype_space($char)) {
                    $acc = $char;
                    $accType = "space";
                    $accIndex = $i;
                } elseif (preg_match('/[a-zA-Z0-9_.-]/', $char)) {
                    $acc = $char;
                    $accType = "identifier";
                    $accIndex = $i;
                } elseif ($char === "'" || $char === '"') {
                    $acc = $char;
                    $accType = "quote";
                    $quoteChar = $char;
                    $accIndex = $i;
                } elseif ($char === "@") {
                    $accType = '@';
                    $acc = $char;
                    $accIndex = $i;
                } elseif (strpos(self::STANDALONE_CHARS, $char) !== false) {
                    yield [$char, $char, $i];
                } else {
                    yield ["error", $char, $i];
                }
            } elseif ($accType === "quote") {
                $acc .= $char;
                if ($char === $quoteChar) {
                    yield ["quote", $acc, $accIndex];
                    $acc = "";
                    $accType = null;
                    $accIndex = null;
                } elseif ($char === '\\') {
                    $accType = 'quote-escape';
                }
            } elseif ($accType === "quote-escape") {
                $acc .= $char;
                $accType = 'quote';
            } elseif ($accType === "identifier") {
                if (preg_match('/[a-zA-Z0-9_.-]/', $char)) {
                    $acc .= $char;
                } else {
                    yield [$accType, $acc, $accIndex];
                    $accType = null;
                    $acc = "";
                    $accIndex = null;
                    $read = false;
                }
            } elseif ($accType === "@") {
                if ($char === '@') {
                    $acc .= $char;
                    if (strlen($acc) >= 3) {
                        yield ['@', $acc, $accIndex];
                        $acc = "";
                        $accType = null;
                        $accIndex = null;
                    }
                } else {
                    yield ['@', $acc, $accIndex];
                    $acc = "";
                    $accType = null;
                    $accIndex = null;
                    $read = false;
                }
            } elseif ($accType === "space") {
                if (ctype_space($char)) {
                    $acc .= $char;
                } else {
                    yield [$accType, $acc, $accIndex];
                    $accIndex = $i;
                    $acc = $char;
                    $accType = "help";
                }
            } elseif ($accType === "help") {
                $acc .= $char;
            }
            if ($read) {
                $i++;
            } else {
                $read = true;
            }
        }
        if ($accType !== null) {
            if ($accType === "quote" || $accType === "quote-escape") {
                yield ["error", $acc, $accIndex];
            } else {
                yield [$accType, $acc, $accIndex];
            }
        }
    }
}
