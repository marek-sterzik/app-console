<?php

namespace SPSOstrov\AppConsole;

use SPSOstrov\GetOpt\Options;
use Exception;

class MetaDataChecker
{
    const CHECKS = [
        "description" => "checkString",
        "options" => "checkOptions",
        "help" => "checkString",
        "args" => "checkArgs",
        "hidden" => "checkBool",
        "is-invoker" => "checkBool",
        "invoker-accepted-params" => "checkInvokerParams",
        "invoker-params" => "checkInvokerParams",
        "extra" => "checkArray",
    ];
    private static $instance = null;

    public static function instance()
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    private $errors = [];

    public function check(&$data)
    {
        $this->errors = [];
        if (!is_array($data)) {
            $this->error("Command has malformed metadata");
            $data = [];
        }
        foreach (array_keys($data) as $key) {
            if ($data[$key] === null) {
                unset($data[$key]);
            } elseif (isset(self::CHECKS[$key])) {
                $fn = self::CHECKS[$key];
                if (!$this->$fn($data[$key])) {
                    $this->error(sprintf("Command metadata field %s contains invalid value", $key));
                    unset($data[$key]);
                }
            } else {
                unset($data[$key]);
            }
        }
        return true;
    }

    public function getLastErrors()
    {
        return $this->errors;
    }

    private function error($message)
    {
        $this->errors[] = $message;
    }

    private function checkString(&$val)
    {
        if (is_numeric($val)) {
            $val = (string)$val;
        }
        if (!is_string($val)) {
            return false;
        }
        return true;
    }

    private function checkArray(&$val)
    {
        if (!is_array($val)) {
            if ($val !== null) {
                return false;
            }
            $val = [];
        }
        return true;
    }

    private function checkOptions(&$val)
    {
        if (!is_array($val)) {
            return false;
        }
        foreach ($val as $opt) {
            if (!is_string($opt)) {
                return false;
            }
        }
        try {
            new Options($val);
        } catch (Exception $e) {
            return false;
        }
        return true;
    }

    private function checkArgs(&$val)
    {
        if (!is_array($val)) {
            return false;
        }
        foreach ($val as $opt) {
            if (!is_string($opt)) {
                return false;
            }
        }
        return true;
    }

    private function checkBool(&$val)
    {
        $val = $val ? true : false;
        return true;
    }

    private function checkInvokerParams(&$val)
    {
        if (!is_array($val)) {
            return false;
        }
        foreach ($val as $value) {
            if ($value !== null && !is_string($value)) {
                return false;
            }
        }
        return true;
    }
}
