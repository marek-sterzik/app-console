<?php

namespace SPSOstrov\AppConsole;

final class Command
{
    private $bin;
    private $name;
    private $envVars;
    private $metadata;

    public function __construct($bin, $name, $envVars)
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
        return $this->metadata("help");
    }

    public function getOptions()
    {
        return $this->metadata("options") ?? [];
    }

    public function getOperands()
    {
        return $this->metadata("operands") ?? [];
    }

    public function getDescription()
    {
        return $this->metadata("description");
    }

    public function getMetadataErrors()
    {
        return $this->metadata('errors') ?? [];
    }

    public function transformArguments($options, $arguments)
    {
        $argsDescriptor = $this->metadata("args");
        if ($argsDescriptor === null) {
            return $arguments;
        } else {
            $paramsConverter = new ParamsConverter($argsDescriptor);
            return $paramsConverter->getArgs($options, $arguments);
        }
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
        $metaFile = $this->bin . ".meta.php";
        if (!file_exists($metaFile)) {
            return [];
        }
        $metaData = @include $metaFile;
        if (!MetaDataChecker::instance()->check($metaData)) {
            $metaData = [];
        }
        $metaData['errors'] = MetaDataChecker::instance()->getLastErrors();
        return $metaData;
    }
}
