<?php
// ============================================
// DEBUG - Dekho kahan problem hai
// ============================================

echo "<pre>\n";

// ============ LOOKUP TABLE ============
$LOOKUP_TABLE_D = "\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0a\x0b\x0c\x0d\x0e\x0f" .
                  "\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1a\x1b\x1c\x1d\x1e\x1f" .
                  " !\"#\$%&'()*+,-./" .
                  "0123456789:;<=>?" .
                  "@EGMNKABUVCDYHLI" .
                  "FPOZQSRWTXJ[\\]^_" .
                  "`egmnkabuvcdyhli" .
                  "fpozqsrwtxj{|}~\x7f";

// ============ FUNCTIONS ============
function customToStandardBase64($customB64) {
    global $LOOKUP_TABLE_D;
    $result = '';
    for ($i = 0; $i < strlen($customB64); $i++) {
        $ascii = ord($customB64[$i]);
        if ($ascii < strlen($LOOKUP_TABLE_D)) {
            $result .= $LOOKUP_TABLE_D[$ascii];
        } else {
            $result .= $customB64[$i];
        }
    }
    return $result;
}

function decryptSKLive($encryptedData, $key, $iv) {
    $standardB64 = customToStandardBase64($encryptedData);
    $decoded = base64_decode($standardB64);
    if ($decoded === false) return null;
    $reversed = strrev($decoded);
    $ciphertext = base64_decode($reversed);
    if ($ciphertext === false) return null;
    if (strlen($ciphertext) % 16 !== 0) return null;
    $decrypted = openssl_decrypt($ciphertext, 'aes-128-cbc', $key, OPENSSL_RAW_DATA, $iv);
    return $decrypted ?: null;
}

function fetchUrl($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT,
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    return ['body' => $response, 'code' => $httpCode, 'error' => $error];
}

// ============ EXTRACT KEYS ============
echo "=== EXTRACTING KEYS ===\n";

$cs3Url = "https://raw.githubusercontent.com/NivinCNC/CNCVerse-Cloud-Stream-Extension/builds/SKTechProvider.cs3";
$cs3Result = fetchUrl($cs3Url);
echo "CS3 download: HTTP {$cs3Result['code']}, Size: " . strlen($cs3Result['body']) . "\n";

$tmpFile = tempnam(sys_get_temp_dir(), 'cs3_');
file_put_contents($tmpFile, $cs3Result['body']);
$zip = new ZipArchive();
$dexData = null;
if ($zip->open($tmpFile) === TRUE) {
    $dexData = $zip->getFromName('classes.dex');
    $zip->close();
}
@unlink($tmpFile);

$stringIdsSize = unpack('V', substr($dexData, 0x38, 4))[1];
$stringIdsOff = unpack('V', substr($dexData, 0x3C, 4))[1];

$allStrings = [];
for ($i = 0; $i < $stringIdsSize; $i++) {
    $dataOff = unpack('V', substr($dexData, $stringIdsOff + ($i * 4), 4))[1];
    $pos = $dataOff;
    $strSize = 0; $shift = 0;
    do { $b = ord($dexData[$pos]); $strSize |= ($b & 0x7F) << $shift; $shift += 7; $pos++; } while ($b & 0x80);
    $str = '';  $count = 0;
    while ($pos < strlen($dexData) && ord($dexData[$pos]) != 0 && $count < 1000) { $str .= $dexData[$pos]; $pos++; $count++; }
    $allStrings[] = $str;
}

$hexStrings32 = [];
foreach ($allStrings as $str) {
    if (strlen($str) == 32 && ctype_xdigit($str)) {
        $hexStrings32[] = $str;
    }
}

$AES_KEY = hex2bin($hexStrings32[0]);
$AES_IV = hex2bin($hexStrings32[1]);

echo "KEY: {$hexStrings32[0]} (" . strlen($AES_KEY) . " bytes)\n";
echo "IV:  {$hexStrings32[1]} (" . strlen($AES_IV) . " bytes)\n\n";

// ============ FETCH EVENTS ============
echo "=== FETCHING EVENTS ===\n";

$eventsResult = fetchUrl("https://sufyanpromax.space/events.txt");
echo "Events fetch: HTTP {$eventsResult['code']}, Size: " . strlen($eventsResult['body']) . "\n";

if (empty($eventsResult['body'])) {
    die("Failed to fetch events!\n");
}

$decryptedEvents = decryptSKLive(trim($eventsResult['body']), $AES_KEY, $AES_IV);
echo "Decrypted: " . (empty($decryptedEvents) ? "FAILED" : strlen($decryptedEvents) . " bytes") . "\n\n";

if (empty($decryptedEvents)) {
    die("Decryption failed!\n");
}

$eventWrappers = json_decode($decryptedEvents, true);
echo "Total events: " . count($eventWrappers) . "\n\n";

// ============ ANALYZE EACH EVENT ============
echo "=== EVENT ANALYSIS ===\n\n";

$visibleCount = 0;
$invisibleCount = 0;
$streamSuccessCount = 0;
$streamFailCount = 0;

foreach ($eventWrappers as $idx => $wrapper) {
    $eventJson = $wrapper['event'] ?? null;
    if (!$eventJson) {
        echo "[$idx] NO EVENT JSON!\n";
        continue;
    }
    
    $event = json_decode($eventJson, true);
    if (!$event) {
        echo "[$idx] FAILED TO PARSE EVENT JSON!\n";
        continue;
    }
    
    $visible = $event['visible'] ?? 'not set';
    $eventName = $event['eventName'] ?? 'Unknown';
    $teamA = $event['teamAName'] ?? '';
    $teamB = $event['teamBName'] ?? '';
    $links = $event['links'] ?? '';
    $category = $event['category'] ?? '';
    
    $displayName = (!empty($teamA) && !empty($teamB) && $teamA !== $teamB) 
        ? "$teamA vs $teamB" 
        : $eventName;
    
    $isVisible = !isset($event['visible']) || $event['visible'] === true;
    
    if ($isVisible) {
        $visibleCount++;
    } else {
        $invisibleCount++;
    }
    
    echo "[$idx] $displayName\n";
    echo "     Category: $category\n";
    echo "     Visible: " . var_export($visible, true) . " → " . ($isVisible ? "SHOW" : "SKIP") . "\n";
    echo "     Links: $links\n";
    
    $slug = pathinfo($links, PATHINFO_FILENAME);
    echo "     Slug: " . (empty($slug) ? "EMPTY!" : $slug) . "\n";
    
    // Only fetch streams for first 3 visible events (to avoid timeout)
    if ($isVisible && !empty($slug) && $streamSuccessCount + $streamFailCount < 5) {
        $streamUrl = "https://sufyanpromax.space/{$slug}.txt";
        echo "     Fetching: $streamUrl\n";
        
        $streamResult = fetchUrl($streamUrl);
        echo "     Stream HTTP: {$streamResult['code']}, Size: " . strlen($streamResult['body']) . "\n";
        
        if (!empty($streamResult['body']) && $streamResult['code'] == 200) {
            $decryptedStream = decryptSKLive(trim($streamResult['body']), $AES_KEY, $AES_IV);
            
            if ($decryptedStream) {
                $streams = json_decode($decryptedStream, true);
                if ($streams && is_array($streams)) {
                    echo "     Streams found: " . count($streams) . "\n";
                    foreach ($streams as $sIdx => $stream) {
                        $sName = $stream['name'] ?? "Server $sIdx";
                        $sLink = $stream['link'] ?? 'EMPTY';
                        $sApi = $stream['api'] ?? '';
                        $sToken = !empty($stream['tokenApi']) ? 'YES' : 'NO';
                        echo "       [$sIdx] $sName → link: " . substr($sLink, 0, 80) . "\n";
                        echo "              api: $sApi, tokenApi: $sToken\n";
                    }
                    $streamSuccessCount++;
                } else {
                    echo "     Failed to parse streams JSON!\n";
                    echo "     Raw: " . substr($decryptedStream, 0, 200) . "\n";
                    $streamFailCount++;
                }
            } else {
                echo "     Stream DECRYPT FAILED!\n";
                $streamFailCount++;
            }
        } else {
            echo "     Stream fetch failed! Error: {$streamResult['error']}\n";
            $streamFailCount++;
        }
    }
    
    echo "\n";
}

echo "=== SUMMARY ===\n";
echo "Total events: " . count($eventWrappers) . "\n";
echo "Visible: $visibleCount\n";
echo "Invisible: $invisibleCount\n";
echo "Stream success: $streamSuccessCount\n";
echo "Stream fail: $streamFailCount\n";

echo "\n</pre>";
?>
