<?php

namespace DASHIF;

class ModuleDolby extends ModuleInterface
{
    public function __construct()
    {
        parent::__construct();
        $this->name = "Dolby";
    }

    protected function addCLIArguments()
    {
        global $argumentParser;
        $argumentParser->addOption("dolby", "o", "dolby", "Enable Dolby checking");
    }

    public function handleArguments()
    {
        global $argumentParser;
        if ($argumentParser->getOption("dolby")) {
            $this->enabled = true;
        }
    }

    public function hookRepresentation()
    {
        parent::hookRepresentation();
        $this->validateDolby();
    }

    private function validateDolby()
    {
        require_once __DIR__ . '/impl/validateDolby.php';
    }

    private function compareTocWithDac4($atomInfo)
    {
        require_once __DIR__ . '/impl/compareTocWithDac4.php';
    }

    private function getDac4($atomInfo)
    {
        return require __DIR__ . '/impl/getDac4.php';
    }

    private function getToc($atomInfo)
    {
        return require __DIR__ . '/impl/getToc.php';
    }
}

$modules[] = new ModuleDolby();
