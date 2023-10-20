<?php

namespace SPSOstrov\AppConsole;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\InstalledVersions;
use Composer\EventDispatcher\EventSubscriberInterface;

class ComposerPlugin implements PluginInterface, EventSubscriberInterface
{
    const SELF_PACKAGE = "spsostrov/app-console";

    private $composer;
    private $io;
    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    public function deactivate(Composer $composer, IOInterface $io)
    {
    }

    public function uninstall(Composer $composer, IOInterface $io)
    {
    }

    public static function getSubscribedEvents()
    {
        return [
            'post-autoload-dump' => 'postAutoloadDump',
        ];
    }

    public function postAutoloadDump($object = null)
    {
        $runtimeConfigGenerator = new RuntimeConfigGenerator();
        $runtimeConfig = $runtimeConfigGenerator->generateConfig();
        (new RuntimeConfig($rootDir))->set($runtimeConfig);
    }
}
