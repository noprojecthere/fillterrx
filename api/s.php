// <?php
// // ============================================
// // SKTECH LIVE - M3U PLAYLIST GENERATOR (FIXED)
// // ============================================

// $LOOKUP_TABLE_D = "\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0a\x0b\x0c\x0d\x0e\x0f" .
//                   "\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1a\x1b\x1c\x1d\x1e\x1f" .
//                   " !\"#\$%&'()*+,-./" .
//                   "0123456789:;<=>?" .
//                   "@EGMNKABUVCDYHLI" .
//                   "FPOZQSRWTXJ[\\]^_" .
//                   "`egmnkabuvcdyhli" .
//                   "fpozqsrwtxj{|}~\x7f";

// function customToStandardBase64($customB64) {
//     global $LOOKUP_TABLE_D;
//     $result = '';
//     for ($i = 0; $i < strlen($customB64); $i++) {
//         $ascii = ord($customB64[$i]);
//         if ($ascii < strlen($LOOKUP_TABLE_D)) {
//             $result .= $LOOKUP_TABLE_D[$ascii];
//         } else {
//             $result .= $customB64[$i];
//         }
//     }
//     return $result;
// }

// function decryptSKLive($encryptedData, $key, $iv) {
//     $standardB64 = customToStandardBase64($encryptedData);
//     $decoded = base64_decode($standardB64);
//     if ($decoded === false) return null;
//     $reversed = strrev($decoded);
//     $ciphertext = base64_decode($reversed);
//     if ($ciphertext === false || strlen($ciphertext) % 16 !== 0) return null;
//     $decrypted = openssl_decrypt($ciphertext, 'aes-128-cbc', $key, OPENSSL_RAW_DATA, $iv);
//     return $decrypted ?: null;
// }

// function fetchUrl($url) {
//     // URL encode spaces and special chars in path
//     $parts = parse_url($url);
//     $path = $parts['path'] ?? '';
//     // Encode each path segment
//     $segments = explode('/', $path);
//     $encodedSegments = array_map(function($seg) {
//         return rawurlencode($seg);
//     }, $segments);
//     $encodedPath = implode('/', $encodedSegments);
//     $encodedUrl = $parts['scheme'] . '://' . $parts['host'] . $encodedPath;
    
//     $ch = curl_init();
//     curl_setopt($ch, CURLOPT_URL, $encodedUrl);
//     curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//     curl_setopt($ch, CURLOPT_USERAGENT,
//         'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
//     curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
//     curl_setopt($ch, CURLOPT_TIMEOUT, 15);
//     curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
//     $response = curl_exec($ch);
//     $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
//     curl_close($ch);
//     return ['body' => $response, 'code' => $httpCode];
// }

// function extractKeysFromCS3() {
//     $cs3Url = "https://raw.githubusercontent.com/NivinCNC/CNCVerse-Cloud-Stream-Extension/builds/SKTechProvider.cs3";
//     $ch = curl_init();
//     curl_setopt($ch, CURLOPT_URL, $cs3Url);
//     curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//     curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
//     curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
//     curl_setopt($ch, CURLOPT_TIMEOUT, 60);
//     $cs3Data = curl_exec($ch);
//     curl_close($ch);
//     if (empty($cs3Data)) return null;

//     $tmpFile = tempnam(sys_get_temp_dir(), 'cs3_');
//     file_put_contents($tmpFile, $cs3Data);
//     $zip = new ZipArchive();
//     $dexData = null;
//     if ($zip->open($tmpFile) === TRUE) {
//         $dexData = $zip->getFromName('classes.dex');
//         $zip->close();
//     }
//     @unlink($tmpFile);
//     if (empty($dexData)) return null;

//     $stringIdsSize = unpack('V', substr($dexData, 0x38, 4))[1];
//     $stringIdsOff = unpack('V', substr($dexData, 0x3C, 4))[1];
//     $hexStrings32 = [];
    
//     for ($i = 0; $i < $stringIdsSize; $i++) {
//         $dataOff = unpack('V', substr($dexData, $stringIdsOff + ($i * 4), 4))[1];
//         $pos = $dataOff;
//         $shift = 0; $strSize = 0;
//         do { $b = ord($dexData[$pos]); $strSize |= ($b & 0x7F) << $shift; $shift += 7; $pos++; } while ($b & 0x80);
//         $str = ''; $count = 0;
//         while ($pos < strlen($dexData) && ord($dexData[$pos]) != 0 && $count < 1000) { $str .= $dexData[$pos]; $pos++; $count++; }
        
//         if (strlen($str) == 32 && ctype_xdigit($str)) {
//             $hexStrings32[] = $str;
//         }
//     }
    
//     if (count($hexStrings32) < 2) return null;
//     return ['key' => hex2bin($hexStrings32[0]), 'iv' => hex2bin($hexStrings32[1])];
// }

// // ============================================
// // Check if debug mode
// // ============================================
// $debug = isset($_GET['debug']);

// // ============================================
// // EXTRACT KEYS
// // ============================================
// $keys = extractKeysFromCS3();
// if (!$keys) die("Failed to extract keys!");
// $AES_KEY = $keys['key'];
// $AES_IV = $keys['iv'];

// // ============================================
// // FETCH EVENTS
// // ============================================
// $eventsResult = fetchUrl("https://sufyanpromax.space/events.txt");
// if (empty($eventsResult['body'])) die("Failed to fetch events!");

// $decryptedEvents = decryptSKLive(trim($eventsResult['body']), $AES_KEY, $AES_IV);
// if (!$decryptedEvents) die("Failed to decrypt events!");

// $eventWrappers = json_decode($decryptedEvents, true);
// if (!$eventWrappers) die("Failed to parse events!");

// $baseUrl = "https://sufyanpromax.space";

// // ============================================
// // DEBUG MODE
// // ============================================
// if ($debug) {
//     header('Content-Type: text/plain; charset=utf-8');
//     echo "=== DEBUG MODE ===\n\n";
//     echo "Total events: " . count($eventWrappers) . "\n\n";
    
//     $testCount = 0;
//     foreach ($eventWrappers as $idx => $wrapper) {
//         $event = json_decode($wrapper['event'] ?? '{}', true);
//         if (!$event || (isset($event['visible']) && !$event['visible'])) continue;
        
//         $links = $event['links'] ?? '';
//         $eventName = $event['eventName'] ?? 'Unknown';
        
//         echo "[$idx] $eventName\n";
//         echo "  Links raw: $links\n";
        
//         // FIX: Use links directly (remove .txt, add back)
//         $linksPath = rtrim($links, '.txt');
//         $linksPath = rtrim($linksPath, '/');
        
//         // Build full URL
//         $fullUrl = "$baseUrl/$links";
//         echo "  Full URL: $fullUrl\n";
        
//         if ($testCount < 3) {
//             $result = fetchUrl($fullUrl);
//             echo "  HTTP: {$result['code']}, Size: " . strlen($result['body']) . "\n";
            
//             if ($result['code'] == 200 && !empty($result['body'])) {
//                 $decStream = decryptSKLive(trim($result['body']), $AES_KEY, $AES_IV);
//                 if ($decStream) {
//                     $streams = json_decode($decStream, true);
//                     if ($streams) {
//                         echo "  Streams: " . count($streams) . "\n";
//                         foreach ($streams as $s) {
//                             echo "    - " . ($s['name'] ?? '?') . " → " . substr($s['link'] ?? 'EMPTY', 0, 80) . "\n";
//                             if (!empty($s['tokenApi'])) echo "    tokenApi: YES\n";
//                         }
//                     }
//                 } else {
//                     echo "  Decrypt FAILED\n";
//                 }
//             }
//             $testCount++;
//         }
//         echo "\n";
//     }
//     exit;
// }

// // ============================================
// // GENERATE M3U
// // ============================================
// header('Content-Type: audio/mpegurl; charset=utf-8');
// header('Content-Disposition: inline; filename="sktech_live.m3u"');
// header('Access-Control-Allow-Origin: *');

// echo "#EXTM3U\n\n";

// $totalStreams = 0;

// foreach ($eventWrappers as $wrapper) {
//     $eventJson = $wrapper['event'] ?? null;
//     if (!$eventJson) continue;
    
//     $event = json_decode($eventJson, true);
//     if (!$event) continue;
//     if (isset($event['visible']) && !$event['visible']) continue;
    
//     $eventName = $event['eventName'] ?? 'Unknown';
//     $teamA = $event['teamAName'] ?? '';
//     $teamB = $event['teamBName'] ?? '';
//     $category = trim($event['category'] ?? 'Other');
//     $logo = $event['eventLogo'] ?? '';
//     $links = $event['links'] ?? '';
    
//     if (empty($links)) continue;
    
//     // Display name
//     if (!empty($teamA) && !empty($teamB) && $teamA !== $teamB) {
//         $displayName = "$teamA vs $teamB";
//     } else if (!empty($teamA)) {
//         $displayName = $teamA;
//     } else {
//         $displayName = $eventName;
//     }
    
//     // FIX: Use full links path (includes "pro/" folder)
//     $fullStreamUrl = "$baseUrl/$links";
    
//     $streamResult = fetchUrl($fullStreamUrl);
    
//     if ($streamResult['code'] != 200 || empty($streamResult['body'])) continue;
    
//     $decryptedStreams = decryptSKLive(trim($streamResult['body']), $AES_KEY, $AES_IV);
//     if (!$decryptedStreams) continue;
    
//     $streams = json_decode($decryptedStreams, true);
//     if (!$streams || !is_array($streams)) continue;
    
//     $serverNum = 1;
//     foreach ($streams as $stream) {
//         $serverName = $stream['name'] ?? "Server $serverNum";
//         $link = $stream['link'] ?? '';
        
//         // Try tokenApi
//         if (empty($link) && !empty($stream['tokenApi'])) {
//             $tokenConfig = json_decode($stream['tokenApi'], true);
//             if ($tokenConfig && !empty($tokenConfig['api'])) {
//                 $tokenResult = fetchUrl($tokenConfig['api']);
//                 if ($tokenResult['code'] == 200 && !empty($tokenResult['body'])) {
//                     if (!empty($tokenConfig['link_key'])) {
//                         $tokenJson = json_decode($tokenResult['body'], true);
//                         if ($tokenJson) {
//                             $link = $tokenJson[$tokenConfig['link_key']] ?? '';
//                         }
//                     }
//                     if (empty($link)) {
//                         $link = trim($tokenResult['body']);
//                     }
//                 }
//             }
//         }
        
//         if (empty($link)) { $serverNum++; continue; }
        
//         // Parse headers
//         $parts = explode('|', $link, 2);
//         $url = $parts[0];
        
//         echo "#EXTINF:-1 tvg-logo=\"{$logo}\" group-title=\"{$category}\",{$displayName} - {$serverName}\n";
        
//         if (isset($parts[1])) {
//             $headerPairs = explode('&', $parts[1]);
//             foreach ($headerPairs as $pair) {
//                 $kv = explode('=', $pair, 2);
//                 if (count($kv) == 2) {
//                     $hName = strtolower(trim($kv[0]));
//                     $hVal = trim($kv[1]);
//                     if ($hName === 'user-agent') echo "#EXTVLCOPT:http-user-agent={$hVal}\n";
//                     elseif ($hName === 'referer' || $hName === 'referrer') echo "#EXTVLCOPT:http-referrer={$hVal}\n";
//                 }
//             }
//         }
        
//         if (!empty($stream['api']) && strpos($url, '.mpd') !== false) {
//             echo "#KODIPROP:inputstream.adaptive.license_key={$stream['api']}\n";
//         }
        
//         echo "{$url}\n\n";
//         $totalStreams++;
//         $serverNum++;
//     }
// }

// echo "# Generated: " . date('Y-m-d H:i:s') . "\n";
// echo "# Total streams: {$totalStreams}\n";
// ?>
