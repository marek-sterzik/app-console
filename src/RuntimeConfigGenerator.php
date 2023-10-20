<?php

namespace SPSOstrov\AppConsole;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\InstalledVersions;
use Composer\EventDispatcher\EventSubscriberInterface;

class RuntimeConfigGenerator
{
    const SELF_PACKAGE = "spsostrov/app-console";

    public function generateConfig()
    {
        $runtimeConfig = ['scripts-dirs' => []];
        $rootDir = Path::canonize($this->getPackagePath("__root__"));
        $packages = array_merge(
            ['__root__'],
            InstalledVersions::getInstalledPackagesByType('spsostrov-app-console'),
            [self::SELF_PACKAGE]
        );
        foreach ($packages as $package) {
            $installPath = $this->getPackagePath($package);
            $path = Path::canonize($installPath);
            $path = $this->stripPathPrefix($path, $rootDir);
            if ($path !== null) {
                $packageConfig = $this->loadDirsFromPackage($rootDir, $path, $package);
                if ($packageConfig['scripts-dir'] !== null) {
                    $runtimeConfig['scripts-dirs'][$packageConfig['scripts-dir']] = $package;
                }
            } else {
                $this->io->writeError([
                        sprintf(
                            "<warning>Cannot determine relative path for spsstrov-runtime plugin %s</warning>",
                            $package
                        )
                ]);
            }
        }
        return $runtimeConfig;
    }

    private function getRootDir()
    {
        $package = InstalledVersions::getRootPackage();
        return $package['install_path'];
    }

    private function getPackagePath($package)
    {
        if ($package === '__root__') {
            return $this->getRootDir();
        } else {
            return InstalledVersions::getInstallPath($package);
        }
    }

    private function loadDirsFromPackage($rootDir, $packageDir, $package)
    {
        $config = $this->loadExtraFromComposerJson($rootDir, $packageDir, $package);
        if (!array_key_exists('scripts-dir', $config)) {
            $config['scripts-dir'] = 'scripts';
        }
        if (is_string($config['scripts-dir'])) {
            $config['scripts-dir'] = Path::canonize(
                $packageDir . "/" . Path::canonizeRelative($config['scripts-dir'])
            );
            if ($config['scripts-dir'] === null) {
                $this->io->writeError([
                        sprintf(
                            "<warning>Package %s contains invalid scripts-dir</warning>",
                            $package
                        )
                ]);
            } else {
                if ($package !== '__root__' && !is_dir($rootDir . "/" . $config['scripts-dir'])) {
                    $config['scripts-dir'] = null;
                }
            }
        }
        return $config;
    }

    private function loadExtraFromComposerJson($rootDir, $packageDir, $package)
    {
        $composerFile = sprintf("%s/%s/composer.json", $rootDir, $packageDir);
        $content = @file_get_contents($composerFile);
        if (!is_string($content)) {
            return [];
        }
        $content = @json_decode($content, true);
        if (!is_array($content)) {
                $this->io->writeError([
                        sprintf(
                            "<warning>Package %s has broken composer.json</warning>",
                            $package
                        )
                ]);
                return [];
        }
        if (!isset($content['extra'])) {
            return [];
        }
        if (!is_array($content['extra'])) {
            $this->io->writeError([
                    sprintf(
                        "<warning>Package %s has broken extra field in composer.json</warning>",
                        $package
                    )
            ]);
            return [];
        }
        if (!isset($content['extra']['spsostrov-app-console'])) {
            return [];
        }
        $config = $content['extra']['spsostrov-app-console'];
        if (!$this->validateExtraConfig($config)) {
            $this->io->writeError([
                    sprintf(
                        "<warning>Package %s has broken runtime configuration in composer.json</warning>",
                        $package
                    )
            ]);
            return [];
        }

        return $config;
    }

    private function validateExtraConfig(&$config)
    {
        if (!is_array($config)) {
            return false;
        }
        if (isset($config['scripts-dir']) && !is_string($config['scripts-dir'])) {
            return false;
        }
        return true;
    }

    private function stripPathPrefix($path, $prefix)
    {
        if ($path === $prefix) {
            return '.';
        }
        if (substr($prefix, -1, 1) !== "/") {
            $prefix = $prefix . "/";
        }
        $len = strlen($prefix);
        if (substr($path, 0, $len) === $prefix) {
            return substr($path, $len);
        } else {
            return null;
        }
    }
}
