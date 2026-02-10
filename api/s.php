<?php
// ============================================
// SKTECH LIVE - M3U PLAYLIST GENERATOR
// Auto-extracts key from .cs3 file
// ============================================

// ============================================
// STEP 1: EXTRACT KEY & IV FROM .cs3
// ============================================

function extractKeysFromCS3() {
    $cs3Url = "https://raw.githubusercontent.com/NivinCNC/CNCVerse-Cloud-Stream-Extension/builds/SKTechProvider.cs3";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $cs3Url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    $cs3Data = curl_exec($ch);
    curl_close($ch);
    
    if (empty($cs3Data)) return null;
    
    // Extract DEX from ZIP
    $tmpFile = tempnam(sys_get_temp_dir(), 'cs3_');
    file_put_contents($tmpFile, $cs3Data);
    
    $zip = new ZipArchive();
    $dexData = null;
    if ($zip->open($tmpFile) === TRUE) {
        $dexData = $zip->getFromName('classes.dex');
        $zip->close();
    }
    @unlink($tmpFile);
    
    if (empty($dexData)) return null;
    
    // Parse DEX string table
    $stringIdsSize = unpack('V', substr($dexData, 0x38, 4))[1];
    $stringIdsOff = unpack('V', substr($dexData, 0x3C, 4))[1];
    
    $allStrings = [];
    for ($i = 0; $i < $stringIdsSize; $i++) {
        $dataOff = unpack('V', substr($dexData, $stringIdsOff + ($i * 4), 4))[1];
        $pos = $dataOff;
        
        // Read ULEB128
        $strSize = 0;
        $shift = 0;
        do {
            $b = ord($dexData[$pos]);
            $strSize |= ($b & 0x7F) << $shift;
            $shift += 7;
            $pos++;
        } while ($b & 0x80);
        
        // Read string
        $str = '';
        $count = 0;
        while ($pos < strlen($dexData) && ord($dexData[$pos]) != 0 && $count < 1000) {
            $str .= $dexData[$pos];
            $pos++;
            $count++;
        }
        $allStrings[] = $str;
    }
    
    // Find KEY and IV: 32-char hex strings
    $hexStrings32 = [];
    foreach ($allStrings as $idx => $str) {
        if (strlen($str) == 32 && ctype_xdigit($str)) {
            $hexStrings32[] = ['idx' => $idx, 'str' => $str];
        }
    }
    
    // KEY and IV are usually adjacent 32-char hex strings
    // Find them near AES_KEY/AES_IV labels
    $keyStr = null;
    $ivStr = null;
    
    if (count($hexStrings32) >= 2) {
        // First two 32-char hex strings are KEY and IV
        $keyStr = $hexStrings32[0]['str'];
        $ivStr = $hexStrings32[1]['str'];
    }
    
    if (!$keyStr || !$ivStr) return null;
    
    return [
        'key' => hex2bin($keyStr),
        'iv' => hex2bin($ivStr)
    ];
}

// ============================================
// STEP 2: LOOKUP TABLE & DECRYPTION
// ============================================

$LOOKUP_TABLE_D = "\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0a\x0b\x0c\x0d\x0e\x0f" .
                  "\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1a\x1b\x1c\x1d\x1e\x1f" .
                  " !\"#\$%&'()*+,-./" .
                  "0123456789:;<=>?" .
                  "@EGMNKABUVCDYHLI" .
                  "FPOZQSRWTXJ[\\]^_" .
                  "`egmnkabuvcdyhli" .
                  "fpozqsrwtxj{|}~\x7f";

function customToStandardBase64($customB64) {
    global $LOOKUP_TABLE_D;
    $result = '';
    $len = strlen($customB64);
    for ($i = 0; $i < $len; $i++) {
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
    // Step 1: Custom Base64 → Standard Base64
    $standardB64 = customToStandardBase64($encryptedData);
    
    // Step 2: Base64 decode
    $decoded = base64_decode($standardB64);
    if ($decoded === false) return null;
    
    // Step 3: REVERSE
    $reversed = strrev($decoded);
    
    // Step 4: Base64 decode again
    $ciphertext = base64_decode($reversed);
    if ($ciphertext === false) return null;
    
    // Step 5: Check alignment
    if (strlen($ciphertext) % 16 !== 0) return null;
    
    // Step 6: AES-128-CBC decrypt
    $decrypted = openssl_decrypt(
        $ciphertext,
        'aes-128-cbc',
        $key,
        OPENSSL_RAW_DATA,
        $iv
    );
    
    return $decrypted ?: null;
}

function fetchAndDecrypt($url, $key, $iv) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT,
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    curl_close($ch);
    
    if (empty($response)) return null;
    return decryptSKLive(trim($response), $key, $iv);
}

// ============================================
// STEP 3: MAIN - GENERATE M3U
// ============================================

// Extract keys
$keys = extractKeysFromCS3();
if (!$keys) {
    die("Failed to extract keys from .cs3 file!");
}

$AES_KEY = $keys['key'];
$AES_IV = $keys['iv'];

// Check what to generate
$mode = $_GET['mode'] ?? 'events'; // events, channels, all

if ($mode === 'events' || $mode === 'all') {
    // Fetch and decrypt events
    $eventsUrl = "https://sufyanpromax.space/events.txt";
    $decryptedEvents = fetchAndDecrypt($eventsUrl, $AES_KEY, $AES_IV);
    
    if (!$decryptedEvents) {
        die("Failed to decrypt events!");
    }
    
    $eventWrappers = json_decode($decryptedEvents, true);
    if (!$eventWrappers) {
        die("Failed to parse events JSON!");
    }
    
    // Output M3U
    header('Content-Type: audio/mpegurl; charset=utf-8');
    header('Content-Disposition: inline; filename="sktech_live.m3u"');
    header('Access-Control-Allow-Origin: *');
    
    echo "#EXTM3U\n\n";
    
    foreach ($eventWrappers as $wrapper) {
        $eventJson = $wrapper['event'] ?? null;
        if (!$eventJson) continue;
        
        $event = json_decode($eventJson, true);
        if (!$event) continue;
        
        // Skip invisible
        if (isset($event['visible']) && !$event['visible']) continue;
        
        $eventName = $event['eventName'] ?? 'Unknown';
        $teamA = $event['teamAName'] ?? '';
        $teamB = $event['teamBName'] ?? '';
        $category = $event['category'] ?? 'Other';
        $logo = $event['eventLogo'] ?? '';
        $links = $event['links'] ?? '';
        $date = $event['date'] ?? '';
        $time = $event['time'] ?? '';
        
        // Display name
        if (!empty($teamA) && !empty($teamB) && $teamA !== $teamB) {
            $displayName = "$teamA vs $teamB";
        } else if (!empty($teamA)) {
            $displayName = $teamA;
        } else {
            $displayName = $eventName;
        }
        
        // Get slug
        $slug = pathinfo($links, PATHINFO_FILENAME);
        if (empty($slug)) continue;
        
        // Fetch streams
        $streamUrl = "https://sufyanpromax.space/{$slug}.txt";
        $decryptedStreams = fetchAndDecrypt($streamUrl, $AES_KEY, $AES_IV);
        
        if (!$decryptedStreams) continue;
        
        $streams = json_decode($decryptedStreams, true);
        if (!$streams || !is_array($streams)) continue;
        
        $serverNum = 1;
        foreach ($streams as $stream) {
            $serverName = $stream['name'] ?? "Server $serverNum";
            $link = $stream['link'] ?? '';
            
            // Try tokenApi if no direct link
            if (empty($link) && !empty($stream['tokenApi'])) {
                $tokenConfig = json_decode($stream['tokenApi'], true);
                if ($tokenConfig && !empty($tokenConfig['api'])) {
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $tokenConfig['api']);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                    curl_setopt($ch, CURLOPT_USERAGENT,
                        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
                    $tokenResponse = curl_exec($ch);
                    curl_close($ch);
                    
                    if ($tokenResponse && !empty($tokenConfig['link_key'])) {
                        $tokenJson = json_decode($tokenResponse, true);
                        if ($tokenJson) {
                            $link = $tokenJson[$tokenConfig['link_key']] ?? '';
                        }
                    }
                    if (empty($link) && $tokenResponse) {
                        $link = trim($tokenResponse);
                    }
                }
            }
            
            if (empty($link)) {
                $serverNum++;
                continue;
            }
            
            // Parse headers from link (url|header1=val1&header2=val2)
            $parts = explode('|', $link, 2);
            $url = $parts[0];
            
            $category = trim($category);
            
            echo "#EXTINF:-1 tvg-logo=\"{$logo}\" group-title=\"{$category}\",{$displayName} - {$serverName}\n";
            
            // Add VLC options for headers
            if (isset($parts[1])) {
                $headerPairs = explode('&', $parts[1]);
                foreach ($headerPairs as $pair) {
                    $kv = explode('=', $pair, 2);
                    if (count($kv) == 2) {
                        $hName = strtolower(trim($kv[0]));
                        $hVal = trim($kv[1]);
                        if ($hName === 'user-agent') {
                            echo "#EXTVLCOPT:http-user-agent={$hVal}\n";
                        } elseif ($hName === 'referer' || $hName === 'referrer') {
                            echo "#EXTVLCOPT:http-referrer={$hVal}\n";
                        } elseif ($hName === 'origin') {
                            echo "#EXTVLCOPT:http-origin={$hVal}\n";
                        }
                    }
                }
            }
            
            // DRM info comment
            if (!empty($stream['api']) && strpos($url, '.mpd') !== false) {
                echo "#KODIPROP:inputstream.adaptive.license_key={$stream['api']}\n";
            }
            
            echo "{$url}\n\n";
            $serverNum++;
        }
    }
    
    echo "# Generated: " . date('Y-m-d H:i:s') . "\n";
    echo "# Events: " . count($eventWrappers) . "\n";
}

// ============================================
// CHANNELS MODE (IPTV Categories)
// ============================================
if ($mode === 'channels' || $mode === 'all') {
    $catUrl = "https://sufyanpromax.space/categories.txt";
    $decryptedCats = fetchAndDecrypt($catUrl, $AES_KEY, $AES_IV);
    
    if ($decryptedCats) {
        $catWrappers = json_decode($decryptedCats, true);
        if ($catWrappers) {
            if ($mode === 'channels') {
                header('Content-Type: audio/mpegurl; charset=utf-8');
                echo "#EXTM3U\n\n";
            }
            
            echo "\n# === IPTV CATEGORIES ===\n\n";
            
            foreach ($catWrappers as $wrapper) {
                $catJson = $wrapper['cat'] ?? null;
                if (!$catJson) continue;
                
                $cat = json_decode($catJson, true);
                if (!$cat) continue;
                if (isset($cat['visible']) && !$cat['visible']) continue;
                
                $catName = $cat['name'] ?? 'Unknown';
                $catApi = $cat['api'] ?? '';
                $catLogo = $cat['logo'] ?? '';
                
                if (empty($catApi)) continue;
                
                echo "# Category: {$catName}\n";
                echo "#EXTINF:-1 tvg-logo=\"{$catLogo}\" group-title=\"IPTV\",{$catName}\n";
                echo "{$catApi}\n\n";
            }
        }
    }
}
?>
