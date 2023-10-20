<?php

namespace SPSOstrov\AppConsole;

class ParamsConverter
{
    /** @var ParamConverter */
    private $paramConverters = [];

    public function __construct(array $descriptor)
    {
        foreach ($descriptor as $desc) {
            $this->paramConverters[] = new ParamConverter($desc);
        }
    }

    public function getArgs(array $options, array $args): array
    {
        $finalArgs = [];
        foreach ($this->paramConverters as $pc) {
            $finalArgs = array_merge($finalArgs, $pc->getArgs($options, $args));
        }
        return $finalArgs;
    }
}
