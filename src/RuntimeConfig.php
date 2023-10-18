<?php

namespace SPSOstrov\AppConsole;

class RuntimeConfig
{
    const CONFIG = "vendor/composer/spsostrov-app-console.json";

    private $rootDir;

    public function __construct($rootDir)
    {
        $this->rootDir = $rootDir;
    }

    public function set($config)
    {
        $config = json_encode($config);
        file_put_contents($this->rootDir . "/" . self::CONFIG, $config."\n");
    }

    public function get()
    {
        $config = $this->loadFile();
        if (!isset($config['scripts-dirs'])) {
            $config['scripts-dirs'] = ['scripts'];
        }
        return $config;
    }

    private function loadFile()
    {
        $config = @file_get_contents($this->rootDir . "/" . self::CONFIG);
        if (!is_string($config)) {
            return [];
        }
        $config = @json_decode($config, true);
        if (!is_array($config)) {
            return [];
        }
        return $config;
    }
}
