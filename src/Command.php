<?php

namespace SPSOstrov\AppConsole;

final class Command
{
    private $bin;
    private $name;
    private $envVars;
    private $metadata;

    private function __construct($bin, $name, $envVars)
    {
        $this->bin = $bin;
        $this->name = $name;
        $this->envVars = $envVars;
        $this->metadata = null;
    }

    public function getBin()
    {
        return $this->bin;
    }

    public function getName()
    {
        return $this->name;
    }

    public function invoke($args)
    {
        foreach ($this->envVars as $var => $value) {
            putenv(sprintf("%s=%s", $var, $value));
        }

        $cmd = escapeshellcmd($this->bin);
        foreach ($args as $arg) {
            $cmd .= " " . escapeshellarg($arg);
        }
        $ret = 1;
        system($cmd, $ret);
        return $ret;
    }

    public function getHelp()
    {
        return $this->metadataTyped("help", "is_string");
    }

    public function getOptions()
    {
        return $this->metadataTyped("options", "is_array") ?? [];
    }

    public function getOperands()
    {
        return $this->metadataTyped("operands", "is_array") ?? [];
    }

    public function getDescription()
    {
        return $this->metadataTyped("description", "is_string");
    }

    public function getPassArgsAsJson()
    {
        return $this->metadata('passArgsAsJson') ? true : false;
    }

    private function metadataTyped($key, $type)
    {
        $data = $this->metadata($key);
        if ($type($data)) {
            return $data;
        }
        return null;
    }

    private function metadata($key)
    {
        if ($this->metadata === null) {
            $this->metadata = $this->loadMetadata();
        }
        return $this->metadata[$key] ?? null;
    }

    private function loadMetadata()
    {
        $data = @file_get_contents($this->bin . ".json");
        if (!is_string($data)) {
            return [];
        }
        $data = @json_decode($data, true);
        if (!is_array($data)) {
            return [];
        }
        return $data;
    }
}
