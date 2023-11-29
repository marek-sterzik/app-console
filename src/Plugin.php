<?php

namespace SPSOstrov\AppConsole;

interface Plugin
{
    public function processMetadata(array &$metadata): void;
}
