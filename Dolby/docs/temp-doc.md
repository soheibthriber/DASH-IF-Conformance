# Dolby Module Documentation

## Overview
The Dolby module (`ModuleDolby`) validates Dolby AC-4 audio content within DASH streams.

## Module Architecture

### Class Structure
- `ModuleDolby` extends `ModuleInterface`
- Location: `Dolby/module.php`
- Implementation files in `/Dolby/impl/` directory:
  - `validateDolby.php` - Main validation logic
  - `getToc.php` - Extracts Dolby TOC (Table of Contents) data
  - `getDac4.php` - Extracts DAC4 (Dolby AC-4 Descriptor) data
  - `compareTocWithDac4.php` - Compares data structures for consistency

### Helper Classes
- `AC4TOC` - Stores Dolby AC-4 TOC (Table of Contents) metadata
- `DAC4` - Stores DAC4 (Dolby AC-4 Descriptor) metadata
- Both classes share similar properties:
  - `bitstream_version`
  - `fs_index`
  - `frame_rate_index`
  - `short_program_id`
  - `n_presentations`

### Methods and Functionality
The class contains the following methods (discovered via reflection):

#### Core Methods
- `__construct` - Initializes the module
- `addCLIArguments` - Registers command-line arguments
- `handleArguments` - Processes command-line arguments
- `isEnabled` - Checks if module is enabled
- `setEnabled` - Sets the enabled state
- `message` - Handles message output

#### Hook Methods
- `hookBeforeMPD` - Pre-MPD processing
- `hookMPD` - MPD processing
- `hookPeriod` - Period processing
- `hookBeforeAdaptationSet` - Pre-AdaptationSet processing
- `hookAdaptationSet` - AdaptationSet processing
- `hookBeforeRepresentation` - Pre-Representation processing
- `hookRepresentation` - Main entry point for validation
- `hookLiveMpd` - Live MPD processing

#### Validation Methods
- `validateDolby` - Main validation logic
- `compareTocWithDac4` - Compares TOC and DAC4 data
- `getDac4` - Extracts DAC4 data
- `getToc` - Extracts TOC data
- `detectFromManifest` - Detects Dolby content from manifest

### Dependencies
The module uses these global objects:
- `$logger` (ModuleLogger) - Reports validation results
- `$mpdHandler` (MPDHandler) - Accesses stream representation data
- `$session` (SessionHandler) - Accesses file paths and directories
- `$argumentParser` (ArgumentsParser) - Handles CLI configuration

## Key Functionality

### Initialization & Configuration
- The module is enabled via CLI with the `dolby` flag
- `handleArguments()` method checks for this flag
- Default state: disabled unless explicitly enabled

### Validation Process
1. `hookRepresentation()` method is called during validation
2. When enabled, checks if current representation is AC-4 audio
3. For AC-4 content:
   - Extracts TOC data from atomInfo.xml
   - Extracts DAC4 data from dash_init.xml
   - Compares the two for consistency
   - Reports results via logger

### Detection Logic
- Looks for codec strings containing "ac-3", "ec-3", or "ac-4"
- Examines MimeType to ensure it's audio/mp4
- Only validates mp4 content with Dolby audio codecs

## XML Parsing Details

### XML Structure
- Dolby metadata is stored in XML format
- The module parses these files to extract validation data
- XML parsing focuses on element attributes rather than child elements

### Key XML Elements
1. TOC (Table of Contents) data in atomInfo.xml:
   ```xml
   <ac4_toc bitstream_version="1" fs_index="1" frame_rate_index="3" 
           short_program_id="0" n_presentations="1"/>