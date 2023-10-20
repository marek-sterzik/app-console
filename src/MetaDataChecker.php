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
            $data = [];
        }
        foreach (array_keys($data) as $key) {
            if (isset(self::CHECKS[$key])) {
                $fn = self::CHECKS[$key];
                if (!$this->$fn($data[$key])) {
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
    }
}
