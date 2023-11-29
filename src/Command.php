<?php

namespace SPSOstrov\AppConsole;

final class Command
{
    /** @var string */
    private $bin;

    /** @var string|null */
    private $metadataFile;

    /** @var string */
    private $type;

    /** @var string */
    private $name;

    /** @var string */
    private $packageName;

    /** @var array */
    private $envVars;

    /** @var array|null */
    private $metadata;

    /** @var bool */
    private $isSymlink;

    public function __construct(
        string $bin,
        ?string $metadataFile,
        string $name,
        string $packageName,
        array $envVars,
        bool $isSymlink
    ) {
        $this->bin = $bin;
        $this->metadataFile = $metadataFile;
        $this->type = "none";
        $this->name = $name;
        $this->packageName = $packageName;
        $this->envVars = $envVars;
        $this->isSymlink = $isSymlink;
        $this->metadata = null;
        $this->detectBin();
    }

    private function detectBin(): void
    {
        if (file_exists($this->bin) && is_executable($this->bin)) {
            $this->type = "normal";
        }
    }

    public function getPackageName(): string
    {
        return $this->packageName;
    }

    public function isInvokable(): bool
    {
        return in_array($this->type, ['normal']);
    }

    public function isHidden(): bool
    {
        if (!$this->isInvokable()) {
            return true;
        }
        $defaultHidden = $this->isSymlink || ((substr($this->name, 0, 1) === '.') ? true : false);
        if ($this->metadata("hidden") ?? $defaultHidden) {
            return true;
        }
        return false;
    }

    public function getInvokerParam(): array
    {
        return $this->metadata('invoker-params') ?? [];
    }

    public function getInvokerBinary(Command $commandToBeInvoked): ?array
    {
        if (!$this->isInvokable()) {
            return null;
        }
        if (!$this->metadata('is-invoker')) {
            return null;
        }
        
        $binary = [$this->bin];
        
        $invokerParams = $commandToBeInvoked->getInvokerParam();

        foreach ($this->metadata('invoker-accepted-params') ?? [] as $param => $defaultValue) {
            $value = $invokerParams[$param] ?? $defaultValue;
            if ($value === null) {
                return null;
            }
            $binary[] = $value;
        }

        return $binary;
    }

    public function getBin(): string
    {
        return $this->bin;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function invoke(array $args, ?array $invoker): int
    {
        if ($this->isInvokable()) {
            foreach ($this->envVars as $var => $value) {
                putenv(sprintf("%s=%s", $var, $value));
            }
            if ($invoker === null) {
                $invoker = [];
            }
            $invoker[] = $this->bin;
            return Run::run(array_merge($invoker, $args));
        } else {
            fprintf(STDERR, "Error: this command is not invokable");
            return 1;
        }
    }

    public function getHelp(): ?string
    {
        return $this->metadata("help");
    }

    public function getOptions(): array
    {
        return $this->metadata("options") ?? [];
    }

    public function getDescription(): ?string
    {
        return $this->metadata("description");
    }

    public function getMetadataErrors(): array
    {
        return $this->metadata('errors') ?? [];
    }

    public function transformArguments(array $options, array $arguments): array
    {
        $argsDescriptor = $this->metadata("args");
        if ($argsDescriptor === null) {
            return $arguments;
        } else {
            $paramsConverter = new ParamsConverter($argsDescriptor);
            return $paramsConverter->getArgs($options, $arguments);
        }
    }

    private function metadata(string $key)
    {
        if ($this->metadata === null) {
            $this->metadata = $this->loadMetadata();
        }
        return $this->metadata[$key] ?? null;
    }

    private function loadMetadata(): array
    {
        if ($this->metadataFile === null) {
            return [];
        }

        $metaData = @file_get_contents($this->metadataFile);

        if (!is_string($metaData)) {
            return [];
        }

        $metaData = @json_decode($metaData, true);

        if (is_array($metaData) && isset($metaData['plugins'])) {
            $plugins = $metaData['plugins'];
            unset($metaData['plugins']);
            if (!is_array($plugins)) {
                if ($plugins === null) {
                    $plugins = [];
                } else {
                    $plugins = [$plugins];
                }
            }
            $this->postProcessMetadata($plugins, $metaData);
        }

        if (!MetaDataChecker::instance()->check($metaData)) {
            $metaData = [];
        }

        $metaData['errors'] = MetaDataChecker::instance()->getLastErrors();
        
        return $metaData;
    }

    private function postProcessMetadata(array $plugins, array &$metaData): void
    {
        $envInitialized = false;
        foreach ($plugins as $plugin) {
            $plugin = $this->instantiatePlugin($plugin);
            if ($plugin !== null) {
                if (!$envInitialized) {
                    foreach ($this->envVars as $var => $value) {
                        putenv(sprintf("%s=%s", $var, $value));
                    }
                    $envInitialized = true;
                }
                $plugin->processMetadata($metaData);
            }
        }
    }

    private function instantiatePlugin($plugin): ?Plugin
    {
        if (!is_array($plugin)) {
            $plugin = ["plugin" => $plugin];
        }

        if (!isset($plugin["args"])) {
            $plugin["args"] = [];
        }

        if (!is_array($plugin["args"])) {
            $plugin["args"] = [$plugin["args"]];
        }

        $plugin["args"] = array_values($plugin["args"]);

        if (!is_string($plugin["plugin"])) {
            return null;
        }

        if (!is_a($plugin["plugin"], Plugin::class, true)) {
            return null;
        }

        $pluginClass = $plugin["plugin"];
        return new $pluginClass(...$plugin["args"]);
    }
}
