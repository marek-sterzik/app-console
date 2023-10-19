<?php

namespace SPSOstrov\AppConsole\GetOpt;

use Exception;

class ParserException extends Exception
{
    /** @var string */
    private $parserErrorMessage;
    
    /** @var int|null */
    private $parserErrorPosition;

    public function __construct(string $parserErrorMessage, ?int $parserErrorPosition = null)
    {
        if ($parserErrorPosition !== null) {
            $message = sprintf("Parser error at char %d: %s", $parserErrorPosition, $parserErrorMessage);
        } else {
            $message = sprintf("Parser error: %s", $parserErrorMessage);
        }
        parent::__construct($message);
        $this->parserErrorMessage = $parserErrorMessage;
        $this->parserErrorPosition = $parserErrorPosition;
    }

    public function getParserErrorMessage(): string
    {
        return $this->parserErrorMessage;
    }

    public function getParserErrorPosition(): int
    {
        return $this->parserErrorPosition;
    }
}
