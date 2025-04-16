<?php

// Include essential files
require_once __DIR__ . '/../../Utils/Argument.php';
require_once __DIR__ . '/../../Utils/ArgumentsParser.php';
require_once __DIR__ . '/../../Utils/moduleInterface.php';
require_once __DIR__ . '/../../Utils/moduleLogger.php';
require_once __DIR__ . '/../../Utils/sessionHandler.php';
require_once __DIR__ . '/../../Utils/MPDHandler.php';

// Set up minimal global objects
global $argumentParser;
$argumentParser = new \DASHIF\ArgumentsParser();

require_once __DIR__ . '/../module.php';
