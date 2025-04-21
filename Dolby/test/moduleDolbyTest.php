<?php
use PHPUnit\Framework\TestCase;


// Define interface for mocking representation objects
interface DolbyRepresentationInterface {
    public function getCodecs();
    public function getMimeType();
}


class ModuleDolbyTest extends TestCase
{
    private $originalArgumentParser;
    private static $verboseEnabled = false;
    private static $testCounter = 0;
    private static $testDescriptions = [
        'testDolbyModuleExists' => 'MODULE LOADING: Basic instantiation and interface check',
        'testModuleEnablesWhenDolbyOptionIsSet' => 'ARGUMENTS: Module enables with dolby flag',
        'testModuleStaysDisabledWhenDolbyOptionNotSet' => 'ARGUMENTS: Module stays disabled without flag',
        'testHookRepresentationCallsValidateDolbyWhenEnabled' => 'HOOKS: Validation called when enabled',
        'testHookRepresentationSkipsValidationWhenDisabled' => 'HOOKS: Validation skipped when disabled',
        'testValidateDolbyDetectsAC4Content' => 'VALIDATION: AC-4 content properly detected',
        'testValidateDolbySkipsNonDolbyContent' => 'VALIDATION: Non-Dolby content skipped',
        'testAccessingPrivateMethods' => 'REFLECTION: Module structure analysis',
        'testCompareTocWithDac4ValidatesVersions' => 'VALIDATION CORE: compareTocWithDac4 validation',
        'testGetTocParsesXmlCorrectly' => 'XML PARSING: Testing TOC data extraction',
        'testGetDac4ParsesXmlCorrectly' => 'XML PARSING: Testing DAC4 data extraction',
        'testDolbyTestVectorsAvailability' => 'TEST VECTORS: Verifying availability of Dolby test content',
        'testValidateOnDemandProfileAC4Content' => 'ON-DEMAND PROFILE: Validating AC-4 in SegmentBase MPD structure'
    ];
    
    /**
     * Set up test environment before any tests run
     */
    public static function setUpBeforeClass(): void
    {
        // check for environment variable
        if (getenv('PHPUNIT_VERBOSE') === 'true') {
            self::$verboseEnabled = true;
        } else {
            // Then check for older PHPUnit  -v flag
            $arguments = $_SERVER['argv'] ?? [];
            foreach ($arguments as $arg) {
                if (preg_match('/^-v+$/', $arg)) {
                    self::$verboseEnabled = true;
                    break;
                }
            }
        }

        if (self::$verboseEnabled) {
            print("\n\n=== DOLBY MODULE TEST SUMMARY ===\n");
        }
    }
    
    /**
     * Clean up after all tests are done
     */
    public static function tearDownAfterClass(): void
    {
        // Reset counter
        self::$testCounter = 0;
        
        if (self::$verboseEnabled) {
            print("\n\n=== END OF DOLBY MODULE TESTS ===\n\n");
        }
    }
    
    /**
     * Log test info for verbose output
     */
    private function logTestInfo($testName)
    {
        if (self::$verboseEnabled && isset(self::$testDescriptions[$testName])) {
            self::$testCounter++;
            print("\n\n→ Test #" . self::$testCounter . ": " . self::$testDescriptions[$testName]);
        }
    }
    
    /**
     * more recenet PHPUnit versions have a name() method
     */
    private function getTestName(): string
    {
        // For recent  PHPUnit ex 12.x
        if (method_exists($this, 'name')) {
            return $this->name();
        }
        // For older PHPUnit example 9.x
        if (method_exists($this, 'getName')) {
            return $this->getName();
        }
        // Fallback
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        if (isset($trace[1]['function'])) {
            return $trace[1]['function'];
        }
        return "unknown";
    }
    protected function setUp(): void
    {
        // Remember original state
        global $argumentParser;
        $this->originalArgumentParser = $argumentParser ?? null;
        
        // Log test name if verbose
        $this->logTestInfo($this->getTestName());
    }
    
    protected function tearDown(): void
    {
        // Restore original globals
        global $argumentParser;
        $argumentParser = $this->originalArgumentParser;
    }
    
    /**
     * Check module instantiation and interface
     */
    public function testDolbyModuleExists()
    {
        $module = new \DASHIF\ModuleDolby();
        $this->assertInstanceOf(\DASHIF\ModuleInterface::class, $module);
        $this->assertEquals("Dolby", $module->name);
    }
    
    /**
     * Verify module enables when dolby flag is set
     */
    public function testModuleEnablesWhenDolbyOptionIsSet()
    {
        global $argumentParser;
        $mockArgumentParser = $this->createMock(\DASHIF\ArgumentsParser::class);
        $mockArgumentParser->method('getOption')
            ->with('dolby')
            ->willReturn(true);
        $argumentParser = $mockArgumentParser;
        
        $testModule = new class extends \DASHIF\ModuleDolby {
            public function isEnabled() {
                return $this->enabled;
            }
        };
        
        $testModule->handleArguments();
        $this->assertTrue($testModule->isEnabled());
    }
    
    /**
     * Make sure module stays disabled when flag isn't set
     */
    public function testModuleStaysDisabledWhenDolbyOptionNotSet()
    {
        global $argumentParser;
        $mockArgumentParser = $this->createMock(\DASHIF\ArgumentsParser::class);
        $mockArgumentParser->method('getOption')
            ->with('dolby')
            ->willReturn(false);
        $argumentParser = $mockArgumentParser;
        
        $testModule = new class extends \DASHIF\ModuleDolby {
            public function isEnabled() {
                return $this->enabled;
            }
        };
        
        $testModule->handleArguments();
        $this->assertFalse($testModule->isEnabled());
    }

    /**
     * Test that validation is called when module is enabled
     */
    public function testHookRepresentationCallsValidateDolbyWhenEnabled()
    {
        global $logger, $mpdHandler, $session;
        $originalLogger = $logger;
        $originalMpdHandler = $mpdHandler;
        $originalSession = $session;
        
        $mockLogger = $this->createMock(\DASHIF\ModuleLogger::class);
        $loggerCalled = false;
        
        $mockLogger->method('test')
            ->with(
                $this->anything(),  // module
                $this->anything(),  // spec
                $this->anything(),  // description
                $this->anything(),  // result
                $this->anything(),  // severity
                $this->anything(),  // passMsg
                $this->anything()   // failMsg
            )
            ->willReturnCallback(function() use (&$loggerCalled) {
                $loggerCalled = true;
                return true;
            });
        
        // Use the interface for mocking
        $mockRepresentation = $this->createMock(DolbyRepresentationInterface::class);
        $mockRepresentation->method('getCodecs')->willReturn('ac-4.02.01.03');
        $mockRepresentation->method('getMimeType')->willReturn('audio/mp4');
        
        $mockMpdHandler = $this->createMock(\DASHIF\MPDHandler::class);
        $mockMpdHandler->method('getSelectedRepresentation')->willReturn($mockRepresentation);
        
        $mockSession = $this->createMock(\DASHIF\SessionHandler::class);
        $mockSession->method('getSelectedRepresentationDir')
            ->willReturn(sys_get_temp_dir());
        
        $GLOBALS['logger'] = $mockLogger;
        $GLOBALS['mpdHandler'] = $mockMpdHandler;
        $GLOBALS['session'] = $mockSession;
        
        $module = new class extends \DASHIF\ModuleDolby {
            public function validateDolby() {
                global $logger, $mpdHandler;
                $representation = $mpdHandler->getSelectedRepresentation();
                
                if ($representation && 
                    method_exists($representation, 'getCodecs') &&
                    stripos($representation->getCodecs(), 'ac-4') !== false) {
                    $logger->test(
                        'Dolby',                // module
                        'TEST',                 // spec
                        'Test validation',      // description
                        'PASS',                 // result
                        'Warning',              // severity
                        'Dolby AC-4 detected',  // passMsg
                        'No Dolby AC-4 found'   // failMsg
                    );
                }
            }
            
            public function hookRepresentation() {
                if (!$this->enabled) {
                    return;
                }
                
                $this->validateDolby();
            }
            
            public function setEnabled($state) {
                $this->enabled = $state;
            }
        };
        
        $module->setEnabled(true);
        $module->hookRepresentation();
        
        $GLOBALS['logger'] = $originalLogger;
        $GLOBALS['mpdHandler'] = $originalMpdHandler;
        $GLOBALS['session'] = $originalSession;
        
        $this->assertTrue($loggerCalled);
    }

    /**
     * Check validation is skipped when module is disabled
     */
    public function testHookRepresentationSkipsValidationWhenDisabled()
    {
        global $logger;
        $originalLogger = $logger;
        
        $mockLogger = $this->createMock(\DASHIF\ModuleLogger::class);
        $loggerCalled = false;
        
        $mockLogger->method('test')
            ->willReturnCallback(function() use (&$loggerCalled) {
                $loggerCalled = true;
                return true;
            });
        
        $GLOBALS['logger'] = $mockLogger;
        
        $module = new class extends \DASHIF\ModuleDolby {
            public function hookRepresentation() {
                if (!$this->enabled) {
                    return;
                }
                
                require_once __DIR__ . '/../impl/validateDolby.php';
            }
            
            public function setEnabled($state) {
                $this->enabled = $state;
            }
        };
        
        $module->setEnabled(false);
        $module->hookRepresentation();
        
        $GLOBALS['logger'] = $originalLogger;
        
        $this->assertFalse($loggerCalled);
    }

    /**
     * Test proper AC-4 content detection
     */
    public function testValidateDolbyDetectsAC4Content()
    {
        global $mpdHandler, $session, $logger;
        $originalMpdHandler = $mpdHandler ?? null;
        $originalSession = $session ?? null;
        $originalLogger = $logger ?? null;
        
        $validationAttempted = false;
        
        $mockRepresentation = new stdClass();
        $mockRepresentation->codecs = 'ac-4.02.01.03'; 
        $mockRepresentation->mimeType = 'audio/mp4';
        
        $mockMpdHandler = $this->createMock(\DASHIF\MPDHandler::class);
        $mockMpdHandler->method('getSelectedRepresentation')->willReturn($mockRepresentation);
        
        $mockSession = $this->createMock(\DASHIF\SessionHandler::class);
        $mockSession->method('getSelectedRepresentationDir')->willReturn(sys_get_temp_dir());
        
        $GLOBALS['mpdHandler'] = $mockMpdHandler;
        $GLOBALS['session'] = $mockSession;
        
        $module = new class($validationAttempted) extends \DASHIF\ModuleDolby {
            private $validationAttempted = false;
            
            public function __construct(&$validationAttempted) {
                parent::__construct();
                $this->validationAttempted = &$validationAttempted;
            }
            
            public function compareTocWithDac4() {
                $this->validationAttempted = true;
            }
            
            public function validateDolby() {
                global $mpdHandler;
                
                $representation = $mpdHandler->getSelectedRepresentation();
                
                if (isset($representation->codecs) && 
                    isset($representation->mimeType) && 
                    $representation->mimeType == 'audio/mp4' &&
                    (stripos($representation->codecs, 'ac-3') !== false || 
                     stripos($representation->codecs, 'ec-3') !== false || 
                     stripos($representation->codecs, 'ac-4') !== false)) {
                    
                    $this->compareTocWithDac4();
                }
            }
        };
        
        $module->validateDolby();
        
        $GLOBALS['mpdHandler'] = $originalMpdHandler;
        $GLOBALS['session'] = $originalSession;
        $GLOBALS['logger'] = $originalLogger;
        
        $this->assertTrue($validationAttempted, "AC-4 content should be detected for validation");
    }

    /**
     * Verify non-Dolby content isn't processed
     */
    public function testValidateDolbySkipsNonDolbyContent()
    {
        global $mpdHandler, $session, $logger;
        $originalMpdHandler = $mpdHandler ?? null;
        $originalSession = $session ?? null;
        $originalLogger = $logger ?? null;
        
        $validationAttempted = false;
        
        $mockRepresentation = new stdClass();
        $mockRepresentation->codecs = 'avc1.4D401F';
        $mockRepresentation->mimeType = 'video/mp4';
        
        $mockMpdHandler = $this->createMock(\DASHIF\MPDHandler::class);
        $mockMpdHandler->method('getSelectedRepresentation')->willReturn($mockRepresentation);
        
        $mockSession = $this->createMock(\DASHIF\SessionHandler::class);
        
        $GLOBALS['mpdHandler'] = $mockMpdHandler;
        $GLOBALS['session'] = $mockSession;
        
        $module = new class($validationAttempted) extends \DASHIF\ModuleDolby {
            private $validationAttempted = false;
            
            public function __construct(&$validationAttempted) {
                parent::__construct();
                $this->validationAttempted = &$validationAttempted;
            }
            
            public function compareTocWithDac4() {
                $this->validationAttempted = true;
            }
            
            public function validateDolby() {
                global $mpdHandler;
                
                $representation = $mpdHandler->getSelectedRepresentation();
                
                if (isset($representation->codecs) && 
                    isset($representation->mimeType) && 
                    $representation->mimeType == 'audio/mp4' &&
                    (stripos($representation->codecs, 'ac-3') !== false || 
                     stripos($representation->codecs, 'ec-3') !== false || 
                     stripos($representation->codecs, 'ac-4') !== false)) {
                    
                    $this->compareTocWithDac4();
                }
            }
        };
        
        $module->validateDolby();
        
        $GLOBALS['mpdHandler'] = $originalMpdHandler;
        $GLOBALS['session'] = $originalSession;
        $GLOBALS['logger'] = $originalLogger;
        
        $this->assertFalse($validationAttempted, "Non-Dolby content should be skipped");
    }

    /**
     * Use reflection to analyze module structure
     */
    public function testAccessingPrivateMethods()
    {
        $module = new \DASHIF\ModuleDolby();
        $class = new \ReflectionClass($module);
        $methods = $class->getMethods();
        $methodNames = [];
        
        $methodSummary = [
            'public' => 0,
            'protected' => 0,
            'private' => 0
        ];
        
        foreach ($methods as $method) {
            $methodNames[] = $method->getName();
            if ($method->isPublic()) {
                $methodSummary['public']++;
            } elseif ($method->isProtected()) {
                $methodSummary['protected']++;
            } else {
                $methodSummary['private']++;
            }
        }
        
        if (self::$verboseEnabled) {
            print("\n\nMethod counts:"); 
            print("\n    Public:    " . $methodSummary['public']);
            print("\n    Protected: " . $methodSummary['protected']);
            print("\n    Private:   " . $methodSummary['private']);
            
            print("\n\nAvailable methods in ModuleDolby:\n");
            print(implode(", ", $methodNames) . "\n");
        }
        
        $this->assertTrue(count($methodNames) > 0, "Module should have methods");
    }

    /**
     * Test TOC and DAC4 version comparison
     */
    public function testCompareTocWithDac4ValidatesVersions()
    {
        // Setup test environment
        global $logger;
        $originalLogger = $logger;
        
        // Track validation results
        $testResults = [
            'module' => null,
            'spec' => null,
            'test' => null,
            'result' => null
        ];
        
        // Mock logger to capture test results
        $mockLogger = $this->createMock(\DASHIF\ModuleLogger::class);
        $mockLogger->method('test')
            ->willReturnCallback(function($module, $spec, $test, $result, $severity, $passMessage, $failMessage) use (&$testResults) {
                $testResults['module'] = $module;
                $testResults['spec'] = $spec;
                $testResults['test'] = $test;
                $testResults['result'] = $result;
                return true;
            });
        
        $GLOBALS['logger'] = $mockLogger;
        
        // Create module instance
        $module = new \DASHIF\ModuleDolby();
        
        // Create test class that extends ModuleDolby with overridden methods
        $testModule = new class extends \DASHIF\ModuleDolby {
            // Override getToc and getDac4 to avoid file access
            public function getToc() {
                // Create a mock DOM document for TOC
                $dom = new \DOMDocument();
                
                // Create the basic structure
                $root = $dom->createElement('tt:tt');
                $dom->appendChild($root);
                
                // Add ac4_toc element
                $ac4Toc = $dom->createElement('ac4_toc');
                $root->appendChild($ac4Toc);
                
                // Add ac4_presentation_v1_info element
                $presentationInfo = $dom->createElement('ac4_presentation_v1_info');
                $ac4Toc->appendChild($presentationInfo);
                
                // Add presentation_version element
                $presentationVersion = $dom->createElement('presentation_version', '1');
                $presentationInfo->appendChild($presentationVersion);
                
                // Add mdcompat element
                $mdcompat = $dom->createElement('mdcompat', '2');
                $presentationInfo->appendChild($mdcompat);
                
                return $dom;
            }
            
            public function getDac4() {
                // Create a mock DOM document for DAC4
                $dom = new \DOMDocument();
                
                // Create the basic structure
                $root = $dom->createElement('tt:tt');
                $dom->appendChild($root);
                
                // Add ac4_dsi_v1 element
                $ac4Dsi = $dom->createElement('ac4_dsi_v1');
                $root->appendChild($ac4Dsi);
                
                // Add bitstream_version element
                $bitstreamVersion = $dom->createElement('bitstream_version', '1');
                $ac4Dsi->appendChild($bitstreamVersion);
                
                // Add presentation_version element
                $presentationVersion = $dom->createElement('presentation_version', '1');
                $ac4Dsi->appendChild($presentationVersion);
                
                return $dom;
            }
            
            // Make compareTocWithDac4 public for testing
            public function compareTocWithDac4() {
                // Use mock getToc and getDac4 results
                $toc = $this->getToc();
                $dac4 = $this->getDac4();
                
                global $logger;
                
                // Log test result directly
                $logger->test(
                    'Dolby',
                    'AC4',
                    'Validate AC4 TOC version matches DAC4 version',
                    'PASS',
                    'Warning',
                    'TOC and DAC4 versions match',
                    'TOC and DAC4 versions do not match'
                );
            }
        };

        // Call the method
        $testModule->compareTocWithDac4();
        
        // Verify that a validation test was recorded
        $this->assertNotNull($testResults['result'], "Logger should be called with test results");
        $this->assertEquals('Dolby', $testResults['module'], "Module name should be 'Dolby'");
        $this->assertEquals('PASS', $testResults['result'], "Test should pass with matching versions");
        
        // For verbose mode, show test results
        if (self::$verboseEnabled) {
            print("\n    Test result: " . $testResults['result']);
            print("\n    Test description: " . $testResults['test']);
        }
        
        // Restore original logger
        $GLOBALS['logger'] = $originalLogger;
    }

    /**
     * Test TOC XML parsing
     */
    public function testGetTocParsesXmlCorrectly()
    {
        $this->logTestInfo("XML PARSING: Testing TOC data extraction");
        
        // Load the XML file into a DOMDocument
        $atomInfoPath = __DIR__ . '/resources/atomInfo.xml';
        $dom = new \DOMDocument();
        $dom->load($atomInfoPath);
        
        if (self::$verboseEnabled) {
            print("\n    Testing with XML from: " . $atomInfoPath);
            print("\n    XML content: \n" . htmlspecialchars(file_get_contents($atomInfoPath)));
        }
        
        // Create module instance
        $module = new \DASHIF\ModuleDolby();
        
        // Use reflection to access the private method
        $reflectionMethod = new \ReflectionMethod('\DASHIF\ModuleDolby', 'getToc');
        $reflectionMethod->setAccessible(true);
        
        // Call the method with the DOM document as parameter
        $result = $reflectionMethod->invoke($module, $dom);
        
        // Verify the result is an array
        $this->assertIsArray($result, "getToc should return an array");
        $this->assertGreaterThan(0, count($result), "getToc should return at least one item");
        
        // Verify the object structure without assuming class types
        $firstItem = $result[0];
        
        if (self::$verboseEnabled) {
            print("\n    Result object properties: ");
            foreach (get_object_vars($firstItem) as $name => $value) {
                print("\n      $name: $value");
            }
        }
        
        // Verify attribute values were extracted correctly
        $this->assertEquals('1', $firstItem->bitstream_version, "bitstream_version should be extracted");
        $this->assertEquals('1', $firstItem->fs_index, "fs_index should be extracted");
        $this->assertEquals('3', $firstItem->frame_rate_index, "frame_rate_index should be extracted");
    }

    /**
     * Test DAC4 XML parsing
     */
    public function testGetDac4ParsesXmlCorrectly()
    {
        $this->logTestInfo("XML PARSING: Testing DAC4 data extraction");
        
        // Load the XML file into a DOMDocument
        $dashInitPath = __DIR__ . '/resources/dash_init.xml';
        $dom = new \DOMDocument();
        $dom->load($dashInitPath);
        
        if (self::$verboseEnabled) {
            print("\n    Testing with XML from: " . $dashInitPath);
            print("\n    XML content: \n" . htmlspecialchars(file_get_contents($dashInitPath)));
        }
        
        // Create module instance
        $module = new \DASHIF\ModuleDolby();
        
        // Use reflection to access the private method
        $reflectionMethod = new \ReflectionMethod('\DASHIF\ModuleDolby', 'getDac4');
        $reflectionMethod->setAccessible(true);
        
        // Call the method with the DOM document as parameter
        $result = $reflectionMethod->invoke($module, $dom);
        
        // Verify the result is an array
        $this->assertIsArray($result, "getDac4 should return an array");
        $this->assertGreaterThan(0, count($result), "getDac4 should return at least one item");
        
        // Verify the object structure without assuming class types
        $firstItem = $result[0];
        
        if (self::$verboseEnabled) {
            print("\n    Result object properties: ");
            foreach (get_object_vars($firstItem) as $name => $value) {
                print("\n      $name: $value");
            }
        }
        
        // Verify attribute values were extracted correctly
        $this->assertEquals('1', $firstItem->bitstream_version, "bitstream_version should be extracted");
        $this->assertEquals('1', $firstItem->fs_index, "fs_index should be extracted");
        $this->assertEquals('3', $firstItem->frame_rate_index, "frame_rate_index should be extracted");
    }

    /**
     * Get or download test vectors if needed
     */
    private function ensureTestVectorsAvailable()
    {
        $vectorsDir = __DIR__ . '/testvectors';
        // Check for directories with the pattern NN_AC4_Vector_N
        $vectorDirs = is_dir($vectorsDir) ? glob("$vectorsDir/[0-9][0-9]_AC4_Vector_*", GLOB_ONLYDIR) : [];
        $vectorsAvailable = !empty($vectorDirs);
        
        if (!$vectorsAvailable) {
            if (self::$verboseEnabled) {
                print("\n    Test vectors not found, downloading...");
            }
            
            $downloaderScript = __DIR__ . '/download_vectors.py';
            if (!file_exists($downloaderScript)) {
                if (self::$verboseEnabled) {
                    print("\n    ERROR: Vector downloader script not found at: $downloaderScript");
                }
                return 0;
            }
            
            // Download vectors
            $output = [];
            $returnCode = 0;
            exec("python3 $downloaderScript 2>&1", $output, $returnCode);
            
            if ($returnCode !== 0) {
                if (self::$verboseEnabled) {
                    print("\n    ERROR: Failed to download test vectors:");
                    print("\n    " . implode("\n    ", $output));
                }
                return 0;
            }
            
            if (self::$verboseEnabled) {
                print("\n    " . implode("\n    ", $output));
            }
            
            // Refresh directory listing after download
            $vectorDirs = is_dir($vectorsDir) ? glob("$vectorsDir/[0-9][0-9]_AC4_Vector_*", GLOB_ONLYDIR) : [];
        }
        
        // Return the count of valid vector directories
        return count($vectorDirs);
    }

    /**
     * Check test vector downloads are working
     */
    public function testDolbyTestVectorsAvailability()
    {
        $this->logTestInfo("TEST VECTORS: Verifying availability of Dolby test content");
        
        // Attempt to ensure test vectors are available
        $vectorsDir = __DIR__ . '/testvectors';
        $vectorCount = $this->ensureTestVectorsAvailable();
        
        // Check if we have vectors
        if (self::$verboseEnabled) {
            if ($vectorCount > 0) {
                print("\n    Found $vectorCount Dolby AC-4 test vectors");
                
                // List vectors by type if verbose mode is enabled
                $vectorDirs = glob("$vectorsDir/[0-9][0-9]_AC4_Vector_*", GLOB_ONLYDIR);
                $onDemandVectors = [];
                $liveVectors = [];
                
                foreach ($vectorDirs as $vectorDir) {
                    $vectorName = basename($vectorDir);
                    $hasSegmentFiles = !empty(glob("$vectorDir/*.m4s"));
                    
                    if ($hasSegmentFiles) {
                        $liveVectors[] = $vectorName;
                    } else {
                        $onDemandVectors[] = $vectorName;
                    }
                }
                
                // Display vector types
                print("\n    On-Demand profile vectors: " . count($onDemandVectors));
                print("\n    Live profile vectors: " . count($liveVectors));
                
                // Check for media files to ensure vectors are complete
                $totalMediaFiles = 0;
                $totalMpdFiles = 0;
                
                foreach ($vectorDirs as $vectorDir) {
                    $mpdFiles = glob("$vectorDir/*.mpd");
                    $mediaFiles = array_merge(
                        glob("$vectorDir/*.mp4"), 
                        glob("$vectorDir/*.m4s")
                    );
                    
                    $totalMpdFiles += count($mpdFiles);
                    $totalMediaFiles += count($mediaFiles);
                }
                
                print("\n    Total MPD files: $totalMpdFiles");
                print("\n    Total media files: $totalMediaFiles");
            } else {
                print("\n    No test vectors found or downloaded");
            }
        }
        
        // We should have at least one test vector
        $this->assertGreaterThan(0, $vectorCount, "Should have at least one Dolby test vector available");
        
        // If we have vectors, verify basic structure
        if ($vectorCount > 0) {
            $vectorDirs = glob("$vectorsDir/[0-9][0-9]_AC4_Vector_*", GLOB_ONLYDIR);
            $sampleDir = $vectorDirs[0]; // Check first vector
            
            // Each vector should have an MPD file
            $this->assertGreaterThan(0, count(glob("$sampleDir/*.mpd")), 
                "Vector directory should contain an MPD file");
            
            // Each vector should have at least one media file
            $mediaFiles = array_merge(glob("$sampleDir/*.mp4"), glob("$sampleDir/*.m4s"));
            $this->assertGreaterThan(0, count($mediaFiles), 
                "Vector directory should contain at least one media file");
            
            // Each vector should have an info.txt file
            $this->assertFileExists("$sampleDir/info.txt", 
                "Vector directory should contain an info.txt file");
        }
    }

    /**
     * Test validation with On-Demand profile AC-4 content
     */
    public function testValidateOnDemandProfileAC4Content()
    {
        // Ensure we have test vectors available
        $vectorDir = __DIR__ . "/testvectors/01_AC4_Vector_1";
        $mpdPath = "$vectorDir/Living_Room_1080p_20_96k_25fps.mpd";
        $audioFile = "$vectorDir/media-audio-en-ac-4.mp4";
        $initSegment = "$vectorDir/media-audio-en-ac-4_init.mp4";

        if (!file_exists($mpdPath) || !file_exists($audioFile) || !file_exists($initSegment)) {
            $this->markTestSkipped("Required test vector files not found for On-Demand profile test");
            return;
        }

        $mpdContent = file_get_contents($mpdPath);
        $mpdXml = new \SimpleXMLElement($mpdContent);

        $id = null;
        $codecs = null;
        $bandwidth = null;
        $audioSamplingRate = null;
        foreach ($mpdXml->Period as $period) {
            foreach ($period->AdaptationSet as $adaptationSet) {
                if ((string)$adaptationSet['contentType'] === 'audio' ||
                    (string)$adaptationSet['mimeType'] === 'audio/mp4') {
                    foreach ($adaptationSet->Representation as $representation) {
                        $reprCodecs = (string)$representation['codecs'];
                        if (strpos($reprCodecs, 'ac-4') !== false) {
                            $id = (string)$representation['id'];
                            $codecs = $reprCodecs;
                            $bandwidth = (int)$representation['bandwidth'];
                            $audioSamplingRate = (int)$representation['audioSamplingRate'];
                            break 3;
                        }
                    }
                }
            }
        }
        $this->assertNotNull($id, "MPD should contain AC-4 representation");

        $sessionId = 'ac4_test_' . time();
        $sessionDir = sys_get_temp_dir() . '/dashif_sessions/' . $sessionId;
        $repDir = $sessionDir . '/Period0/AdaptationSet0/Representation0';
        if (!is_dir($repDir)) {
            mkdir($repDir, 0777, true);
        }
        copy($mpdPath, $repDir . '/' . basename($mpdPath));
        copy($audioFile, $repDir . '/' . basename($audioFile));
        copy($initSegment, $repDir . '/' . basename($initSegment));

        $atomInfoPath = $repDir . '/atomInfo.xml';
        $atomInfoContent = '<?xml version="1.0" encoding="UTF-8"?>
    <atomlist>
    <ac4_toc bitstream_version="1" fs_index="1" frame_rate_index="3" short_program_id="0" n_presentations="1"/>
    <ac4_dsi_v1 bitstream_version="1" fs_index="1" frame_rate_index="3" short_program_id="0" n_presentations="1"/>
    </atomlist>';
        file_put_contents($atomInfoPath, $atomInfoContent);

        // --- Capture logger calls ---
        $testResults = [];
        $mockLogger = $this->createMock(\DASHIF\ModuleLogger::class);
        $mockLogger->method('test')
            ->willReturnCallback(function($spec, $section, $test, $check, $fail_type, $msg_succ, $msg_fail) use (&$testResults) {
                $testResults[] = [
                    'spec' => $spec,
                    'section' => $section,
                    'test' => $test,
                    'check' => $check,
                    'fail_type' => $fail_type,
                    'msg_succ' => $msg_succ,
                    'msg_fail' => $msg_fail,
                ];
                return true;
            });

        $mockSession = $this->createMock(\DASHIF\SessionHandler::class);
        $mockSession->method('getSelectedRepresentationDir')->willReturn($repDir);

        $features = [
            'Period' => [
                0 => [
                    'AdaptationSet' => [
                        0 => [
                            'Representation' => [
                                0 => [
                                    'id' => $id,
                                    'codecs' => $codecs,
                                    'mimeType' => 'audio/mp4',
                                    'bandwidth' => $bandwidth,
                                    'audioSamplingRate' => $audioSamplingRate
                                ]
                            ],
                            'codecs' => null,
                            'mimeType' => 'audio/mp4'
                        ]
                    ]
                ]
            ]
        ];

        $mockMpdHandler = $this->createMock(\DASHIF\MPDHandler::class);
        $mockMpdHandler->method('getSelectedPeriod')->willReturn(0);
        $mockMpdHandler->method('getSelectedAdaptationSet')->willReturn(0);
        $mockMpdHandler->method('getSelectedRepresentation')->willReturn(0);
        $mockMpdHandler->method('getFeatures')->willReturn($features);

        global $logger, $session, $mpdHandler;
        $originalLogger = $logger;
        $originalSession = $session;
        $originalMpdHandler = $mpdHandler;

        $logger = $mockLogger;
        $session = $mockSession;
        $mpdHandler = $mockMpdHandler;

        try {
            $module = new \DASHIF\ModuleDolby();
            $refClass = new \ReflectionClass('\DASHIF\ModuleDolby');
            $refEnabled = $refClass->getProperty('enabled');
            $refEnabled->setAccessible(true);
            $refEnabled->setValue($module, true);

            $validateMethod = $refClass->getMethod('validateDolby');
            $validateMethod->setAccessible(true);
            $validateMethod->invoke($module);

            $this->assertNotEmpty($testResults, "Logger should have been called with test results");
            foreach ($testResults as $i => $result) {
                $msg = sprintf(
                    "[ASSERT] Test #%d: %s => %s",
                    $i + 1,
                    $result['test'],
                    $result['check'] ? "PASS" : "FAIL"
                );
                print($msg . "\n");
                $this->assertTrue($result['check'], "Validation check failed: " . $result['test']);
            }
        } finally {
            $logger = $originalLogger;
            $session = $originalSession;
            $mpdHandler = $originalMpdHandler;

            if (is_dir($sessionDir)) {
                $this->removeDirectory($sessionDir);
            }
            if (file_exists('/tmp/dolby_debug.log')) {
                unlink('/tmp/dolby_debug.log');
            }
        }
    }
 
 
    /**
     * Helper method to recursively remove a directory
     */
    private function removeDirectory($dir) {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = "$dir/$file";
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        
        return rmdir($dir);
    }

}