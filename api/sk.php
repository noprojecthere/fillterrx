<?php

// ============================================
// SKLIVE DECRYPTOR - PHP VERSION
// ============================================

// Lookup Table (from decompiled code)
$LOOKUP_TABLE_D = "\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0a\x0b\x0c\x0d\x0e\x0f" .
                  "\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1a\x1b\x1c\x1d\x1e\x1f" .
                  " !\"#\$%&'()*+,-./" .
                  "0123456789:;<=>?" .
                  "@EGMNKABUVCDYHLI" .
                  "FPOZQSRWTXJ[\\]^_" .
                  "`egmnkabuvcdyhli" .
                  "fpozqsrwtxj{|}~\x7f";

// KEY and IV - exact same as decompiled
$KEY_HEX_STRING = "6c326S2GUzu2eRTTGAXmFcfGis1RK3YsU6K1";
$IV_HEX_STRING  = "70314b356e50377542386848316c3139";

// Same hexStringToByteArray logic as Kotlin
function hexStringToByteArray($hex) {
    $len = strlen($hex);
    $data = [];
    for ($i = 0; $i < $len; $i += 2) {
        $high = hexCharToDigit($hex[$i]);
        $low  = hexCharToDigit($hex[$i + 1]);
        $data[] = chr((($high << 4) + $low) & 0xFF);
    }
    return implode('', $data);
}

function hexCharToDigit($char) {
    // Exactly like Java's Character.digit(char, 16)
    $code = ord($char);
    if ($code >= ord('0') && $code <= ord('9')) return $code - ord('0');
    if ($code >= ord('a') && $code <= ord('f')) return $code - ord('a') + 10;
    if ($code >= ord('A') && $code <= ord('F')) return $code - ord('A') + 10;
    return -1; // Invalid hex char - same as Java
}

// Generate AES KEY and IV
$AES_KEY = hexStringToByteArray($KEY_HEX_STRING);
$AES_IV  = hexStringToByteArray($IV_HEX_STRING);

// Custom Base64 to Standard Base64
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

// Main Decrypt Function
function decryptSKLive($encryptedData) {
    global $AES_KEY, $AES_IV;
    
    try {
        // Step 1: Custom Base64 ? Standard Base64
        $standardB64 = customToStandardBase64($encryptedData);
        
        // Step 2: Base64 decode
        $decoded = base64_decode($standardB64);
        
        // Step 3: REVERSE the string
        $reversed = strrev($decoded);
        
        // Step 4: Base64 decode again
        $ciphertext = base64_decode($reversed);
        
        // Step 5: Check block alignment
        if (strlen($ciphertext) % 16 !== 0) {
            echo "ERROR: Not block-aligned for AES!\n";
            return $decoded;
        }
        
        // Step 6: AES-CBC decrypt
        $decrypted = openssl_decrypt(
            $ciphertext,
            'aes-256-cbc',  // ya aes-128-cbc (key length pe depend)
            $AES_KEY,
            OPENSSL_RAW_DATA,
            $AES_IV
        );
        
        // Agar 256 kaam na kare to 128 try karo
        if ($decrypted === false) {
            $decrypted = openssl_decrypt(
                $ciphertext,
                'aes-128-cbc',
                $AES_KEY,
                OPENSSL_RAW_DATA,
                $AES_IV
            );
        }
        
        if ($decrypted === false) {
            echo "Decryption failed!\n";
            return null;
        }
        
        return $decrypted;
        
    } catch (Exception $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
        return null;
    }
}

// ============================================
// EVENTS FETCH & M3U GENERATE
// ============================================

function fetchAndDecrypt($url) {
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
    
    return decryptSKLive(trim($response));
}

// ============================================
// FETCH EVENTS & GENERATE M3U
// ============================================

$eventsUrl = "https://sufyanpromax.space/events.txt";
$decryptedEvents = fetchAndDecrypt($eventsUrl);

if (!$decryptedEvents) {
    die("Failed to decrypt events!");
}

$eventWrappers = json_decode($decryptedEvents, true);
if (!$eventWrappers) {
    die("Failed to parse events JSON!");
}

// Generate M3U
header('Content-Type: audio/mpegurl');
header('Content-Disposition: inline; filename="sktech_live.m3u"');

echo "#EXTM3U\n\n";

foreach ($eventWrappers as $wrapper) {
    $eventJson = $wrapper['event'] ?? null;
    if (!$eventJson) continue;
    
    $event = json_decode($eventJson, true);
    if (!$event) continue;
    
    // Skip invisible events
    if (isset($event['visible']) && !$event['visible']) continue;
    
    $eventName = $event['eventName'] ?? 'Unknown';
    $teamA = $event['teamAName'] ?? '';
    $teamB = $event['teamBName'] ?? '';
    $category = $event['category'] ?? 'Other';
    $logo = $event['eventLogo'] ?? '';
    $links = $event['links'] ?? '';
    
    // Create display name
    if (!empty($teamA) && !empty($teamB) && $teamA !== $teamB) {
        $displayName = "$teamA vs $teamB";
    } else {
        $displayName = $eventName;
    }
    
    // Get slug from links
    $slug = pathinfo($links, PATHINFO_FILENAME);
    if (empty($slug)) continue;
    
    // Fetch stream URLs for this event
    $streamUrl = "https://sufyanpromax.space/{$slug}.txt";
    $decryptedStreams = fetchAndDecrypt($streamUrl);
    
    if (!$decryptedStreams) continue;
    
    $streams = json_decode($decryptedStreams, true);
    if (!$streams || !is_array($streams)) continue;
    
    // Add each stream server
    $serverNum = 1;
    foreach ($streams as $stream) {
        $serverName = $stream['name'] ?? "Server $serverNum";
        $link = $stream['link'] ?? '';
        
        if (empty($link)) {
            // Check tokenApi
            if (!empty($stream['tokenApi'])) {
                $tokenConfig = json_decode($stream['tokenApi'], true);
                if ($tokenConfig && !empty($tokenConfig['api'])) {
                    // Fetch from token API
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $tokenConfig['api']);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    $tokenResponse = curl_exec($ch);
                    curl_close($ch);
                    
                    if ($tokenResponse && !empty($tokenConfig['link_key'])) {
                        $tokenJson = json_decode($tokenResponse, true);
                        $link = $tokenJson[$tokenConfig['link_key']] ?? '';
                    }
                }
            }
            if (empty($link)) continue;
        }
        
        // Parse link (may have headers after |)
        $parts = explode('|', $link, 2);
        $url = $parts[0];
        
        // DRM info
        $drmInfo = $stream['api'] ?? '';
        
        echo "#EXTINF:-1 tvg-logo=\"{$logo}\" group-title=\"{$category}\",{$displayName} - {$serverName}\n";
        
        // Add headers as VLC options if present
        if (isset($parts[1])) {
            $headerPairs = explode('&', $parts[1]);
            foreach ($headerPairs as $pair) {
                $kv = explode('=', $pair, 2);
                if (count($kv) == 2) {
                    $headerName = strtolower(trim($kv[0]));
                    $headerValue = trim($kv[1]);
                    if ($headerName === 'user-agent') {
                        echo "#EXTVLCOPT:http-user-agent={$headerValue}\n";
                    } elseif ($headerName === 'referer') {
                        echo "#EXTVLCOPT:http-referrer={$headerValue}\n";
                    }
                }
            }
        }
        
        echo "{$url}\n\n";
        $serverNum++;
    }
}

echo "# Generated: " . date('Y-m-d H:i:s') . "\n";
?>
