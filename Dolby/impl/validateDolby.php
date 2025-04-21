<?php


global $session, $logger, $mpdHandler;

// Temporary debug code
file_put_contents('/tmp/dolby_debug.log', "validateDolby called\n", FILE_APPEND);
try {
    if (!isset($mpdHandler) || !method_exists($mpdHandler, 'getFeatures')) {
        file_put_contents('/tmp/dolby_debug.log', "mpdHandler or getFeatures() missing\n", FILE_APPEND);
    }
    if (method_exists($mpdHandler, 'getFeatures')) {
        $features = $mpdHandler->getFeatures();
        file_put_contents('/tmp/dolby_debug.log', "Features: " . json_encode($features) . "\n", FILE_APPEND);
    }
} catch (Exception $e) {
    file_put_contents('/tmp/dolby_debug.log', "Error: " . $e->getMessage() . "\n", FILE_APPEND);
}


$period = $mpdHandler->getFeatures()['Period'][$mpdHandler->getSelectedPeriod()];
$adaptationSet = $period['AdaptationSet'][$mpdHandler->getSelectedAdaptationSet()];
$representation = $adaptationSet['Representation'][$mpdHandler->getSelectedRepresentation()];

$codecs = ($adaptationSet['codecs'] == null) ? $representation['codecs'] : $adaptationSet['codecs'];
$isDolby = ($codecs != null) && (
  (substr($codecs, 0, 4) == "ac-3") ||
  (substr($codecs, 0, 4) == "ec-3") ||
  (substr($codecs, 0, 4) == "ac-4")
);

$mimeType = $representation['mimeType'];
if (!$mimeType) {
    $mimeType = $adaptationSet['mimeType'];
}

if ($isDolby && $mimeType == 'audio/mp4') {
    $atomXml = $session->getSelectedRepresentationDir();
    $xml = DASHIF\Utility\parseDOM("$atomXml/atomInfo.xml", 'atomlist');
    if ($xml) {
        $this->compareTocWithDac4($xml);
    }
}
