<?php

namespace SPSOstrov\AppConsole;

use Composer\InstalledVersions;
use Composer\EventDispatcher\EventSubscriberInterface;

class RuntimeConfigGenerator
{
    const SELF_PACKAGE = "spsostrov/app-console";

    public function generateConfig()
    {
        $runtimeConfig = ['scripts-dirs' => [], "argv0" => null, "argv0-resolve-path" => true];
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
                $packageConfig = $this->loadConfigFromPackage($rootDir, $path, $package);
                if ($packageConfig['scripts-dir'] !== null) {
                    $runtimeConfig['scripts-dirs'][$packageConfig['scripts-dir']] = [
                        "package" => $package,
                        "packageRelDir" => $path,
                    ];
                }
                if ($package === '__root__') {
                    $argv0 = $packageConfig['argv0'] ?? null;
                    if (isset($packageConfig['argv0-env'])) {
                        $argv0FromEnv = @getenv($packageConfig['argv0-env']);
                        if (is_string($argv0FromEnv)) {
                            $argv0 = $argv0FromEnv;
                        }
                    }
                    $runtimeConfig['argv0'] = $argv0;
                    $runtimeConfig['argv0-resolve-path'] = $packageConfig['argv0-resolve-path'] ?? true;
                }
            } else {
                fprintf(
                    STDERR,
                    "Warning: Cannot determine relative path for spsstrov-runtime plugin %s\n",
                    $package
                );
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

    private function loadConfigFromPackage($rootDir, $packageDir, $package)
    {
        $config = $this->loadExtraFromComposerJson($rootDir, $packageDir, $package);
        $config['scripts-dir'] = $this->extractScriptsDir($config);
        if (is_string($config['scripts-dir'])) {
            $config['scripts-dir'] = Path::canonize(
                $packageDir . "/" . Path::canonizeRelative($config['scripts-dir'])
            );
            if ($config['scripts-dir'] === null) {
                fprintf(
                    STDERR,
                    "Warning: Package %s contains invalid scripts-dir\n",
                    $package
                );
            } else {
                if ($package !== '__root__' && !is_dir($rootDir . "/" . $config['scripts-dir'])) {
                    $config['scripts-dir'] = null;
                }
            }
        }
        return $config;
    }

    private function extractScriptsDir(array $config): string
    {
        $scriptsDir = $config['scripts-dir'] ?? 'scripts';
        $scriptsDirEnv = $config['scripts-dir-env'] ?? null;
        if ($scriptsDirEnv !== null) {
            $scriptsDirFromEnv = @getenv($scriptsDirEnv);
            $scriptsDirEnvVariants = $config['scripts-dir-env-variants'] ?? null;
            if (is_string($scriptsDirFromEnv) &&
                ($scriptsDirEnvVariants === null || in_array($scriptsDirFromEnv, $scriptsDirEnvVariants))
            ) {
                $scriptsDir = $scriptsDirFromEnv;
            }
        }
        return $scriptsDir;
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
                fprintf(
                    STDERR,
                    "Warning: Package %s has broken composer.json\n",
                    $package
                );
                return [];
        }
        if (!isset($content['extra'])) {
            return [];
        }
        if (!is_array($content['extra'])) {
            fprintf(
                STDERR,
                "Warning: Package %s has broken extra field in composer.json\n",
                $package
            );
            return [];
        }
        if (!isset($content['extra']['spsostrov-app-console'])) {
            return [];
        }
        $config = $content['extra']['spsostrov-app-console'];
        if (!$this->validateExtraConfig($config)) {
            fprintf(
                STDERR,
                "Warning: Package %s has broken runtime configuration in composer.json\n",
                $package
            );
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
        if (isset($config['scripts-dir-env']) && !is_string($config['scripts-dir-env'])) {
            return false;
        }
        if (isset($config['scripts-dir-env-variants'])) {
            if (!is_array($config['scripts-dir-env-variants'])) {
                return false;
            }
            foreach ($config['scripts-dir-env-variants'] as $variant) {
                if (!is_string($variant)) {
                    return false;
                }
            }
        }
        if (isset($config['argv0']) && !is_string($config['argv0'])) {
            return false;
        }
        if (isset($config['argv0-env']) && !is_string($config['argv0-env'])) {
            return false;
        }
        if (isset($config['argv0-resolve-path']) && !is_bool($config['argv0-resolve-path'])) {
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
