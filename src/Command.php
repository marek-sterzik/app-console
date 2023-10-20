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

    /** @var array */
    private $envVars;

    /** @var array|null */
    private $metadata;

    public function __construct(string $bin, ?string $metadataFile, string $name, array $envVars)
    {
        $this->bin = $bin;
        $this->metadataFile = $metadataFile;
        $this->type = "none";
        $this->name = $name;
        $this->envVars = $envVars;
        $this->metadata = null;
        $this->detectBin();
    }

    private function detectBin(): void
    {
        if (file_exists($this->bin) && is_executable($this->bin)) {
            $this->type = "normal";
        }
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
        if (substr($this->name, 0, 1) === '.') {
            return true;
        }
        if ($this->metadata("hidden")) {
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
            $cmd = escapeshellcmd(array_shift($invoker));
            foreach (array_merge($invoker, $args) as $arg) {
                $cmd .= " " . escapeshellarg($arg);
            }
            $ret = 1;
            system($cmd, $ret);
            return $ret;
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

        if (!MetaDataChecker::instance()->check($metaData)) {
            $metaData = [];
        }

        $metaData['errors'] = MetaDataChecker::instance()->getLastErrors();
        
        return $metaData;
    }
}
