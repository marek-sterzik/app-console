<?php

namespace SPSOstrov\AppConsole;

class MetaDataChecker
{
    const CHECKS = [
        "description" => "checkString",
        "operands" => "checkOperands",
        "options" => "checkOptions",
        "help" => "checkString",
        "argumentResolver" => "checkArgumentResolver",
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

    private function checkOperands(&$val)
    {
        if (!is_array($val)) {
            return false;
        }
        return true;
    }

    private function checkOptions(&$val)
    {
        if (!is_array($val)) {
            return false;
        }
        return true;
    }

    private function checkArgumentResolver(&$val)
    {
        if (!is_callable($val)) {
            return false;
        }
        return true;
    }
}
