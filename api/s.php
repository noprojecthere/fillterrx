<?php
// ============================================
// DEBUG 4 - No file writing needed
// Works directly in memory
// ============================================

echo "<pre>\n";
echo "=== DOWNLOADING .cs3 FILE ===\n";

$cs3Url = "https://raw.githubusercontent.com/NivinCNC/CNCVerse-Cloud-Stream-Extension/builds/SKTechProvider.cs3";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $cs3Url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);
$cs3Data = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP: $httpCode, Size: " . strlen($cs3Data) . " bytes\n";

if (empty($cs3Data)) {
    die("Failed to download .cs3!\n");
}

// ============================================
// OPEN ZIP FROM MEMORY (no file needed)
// ============================================

// Create temp file in system temp directory
$tmpFile = tempnam(sys_get_temp_dir(), 'cs3_');
file_put_contents($tmpFile, $cs3Data);

$zip = new ZipArchive();
$opened = $zip->open($tmpFile);

if ($opened !== TRUE) {
    // If temp dir also fails, try memory stream
    echo "Temp file method: trying alternative...\n";
    
    // Parse ZIP manually from memory
    // ZIP files start with PK (0x504B)
    if (substr($cs3Data, 0, 2) === "PK") {
        echo "Valid ZIP detected!\n";
    }
    
    // Find classes.dex in ZIP manually
    $dexStart = strpos($cs3Data, "dex\n");
    if ($dexStart === false) {
        $dexStart = strpos($cs3Data, "dex\x0a");
    }
    
    if ($dexStart !== false) {
        echo "Found DEX at position: $dexStart\n";
        $dexData = substr($cs3Data, $dexStart);
        echo "DEX data size: " . strlen($dexData) . " bytes\n";
    } else {
        echo "DEX not found via header search, trying full data...\n";
        $dexData = $cs3Data;
    }
} else {
    echo "ZIP opened successfully!\n";
    
    for ($i = 0; $i < $zip->numFiles; $i++) {
        echo "  File: " . $zip->getNameIndex($i) . "\n";
    }
    
    $dexData = $zip->getFromName('classes.dex');
    if ($dexData === false) {
        $dexData = $zip->getFromIndex(0);
    }
    $zip->close();
    echo "DEX size: " . strlen($dexData) . " bytes\n";
}

// Clean up temp file
if (isset($tmpFile) && file_exists($tmpFile)) {
    unlink($tmpFile);
}

if (empty($dexData)) {
    die("Could not extract DEX data!\n");
}

// ============================================
// EXTRACT ALL STRINGS FROM DEX/ZIP DATA
// ============================================

echo "\n=== EXTRACTING STRINGS ===\n\n";

$allStrings = [];
$currentString = '';

// Search in ENTIRE cs3Data (includes all ZIP content)
$searchData = $cs3Data;

for ($i = 0; $i < strlen($searchData); $i++) {
    $c = ord($searchData[$i]);
    if ($c >= 32 && $c <= 126) {
        $currentString .= chr($c);
    } else {
        if (strlen($currentString) >= 10) {
            $allStrings[] = $currentString;
        }
        $currentString = '';
    }
}

echo "Total strings found: " . count($allStrings) . "\n\n";

// ============================================
// FIND POTENTIAL KEYS
// ============================================

echo "--- Strings with 'key', 'iv', 'aes', 'crypt', 'sklive' ---\n";
foreach ($allStrings as $str) {
    $lower = strtolower($str);
    if (strpos($lower, 'key') !== false || 
        strpos($lower, 'aes') !== false ||
        strpos($lower, 'crypt') !== false ||
        strpos($lower, 'cipher') !== false ||
        strpos($lower, 'sklive') !== false ||
        strpos($lower, 'secret') !== false) {
        echo "  [$str]\n";
    }
}

echo "\n--- All 32-char strings ---\n";
foreach ($allStrings as $str) {
    if (strlen($str) == 32) {
        echo "  $str\n";
    }
}

echo "\n--- All 36-char strings ---\n";
foreach ($allStrings as $str) {
    if (strlen($str) == 36) {
        echo "  $str\n";
    }
}

echo "\n--- All 48-char strings ---\n";
foreach ($allStrings as $str) {
    if (strlen($str) == 48) {
        echo "  $str\n";
    }
}

echo "\n--- All 64-char strings ---\n";
foreach ($allStrings as $str) {
    if (strlen($str) == 64) {
        echo "  $str\n";
    }
}

echo "\n--- Hex-like strings (30-70 chars, >70% hex) ---\n";
$hexCandidates = [];
foreach ($allStrings as $str) {
    $len = strlen($str);
    if ($len >= 30 && $len <= 70) {
        $hexChars = 0;
        for ($j = 0; $j < $len; $j++) {
            if (ctype_xdigit($str[$j])) $hexChars++;
        }
        $hexPercent = ($hexChars / $len) * 100;
        if ($hexPercent > 70) {
            echo "  [$len chars, " . round($hexPercent) . "% hex] $str\n";
            $hexCandidates[] = $str;
        }
    }
}

// ============================================
// ALSO SEARCH hexStringToByteArray ARGUMENTS
// ============================================

echo "\n--- Strings near 'hexStringToByteArray' ---\n";
$searchFor = 'hexStringToByteArray';
$pos = 0;
while (($pos = strpos($searchData, $searchFor, $pos)) !== false) {
    echo "  Found at position: $pos\n";
    
    // Get 200 bytes after
    $after = substr($searchData, $pos, 300);
    $printable = '';
    $strings = [];
    $current = '';
    for ($j = 0; $j < strlen($after); $j++) {
        $c = ord($after[$j]);
        if ($c >= 32 && $c <= 126) {
            $current .= chr($c);
        } else {
            if (strlen($current) > 3) {
                $strings[] = $current;
            }
            $current = '';
        }
    }
    if (strlen($current) > 3) $strings[] = $current;
    
    echo "  Nearby strings:\n";
    foreach ($strings as $s) {
        echo "    ? $s\n";
    }
    echo "\n";
    $pos++;
}

// ============================================
// SEARCH FOR SPECIFIC KNOWN PATTERNS
// ============================================

echo "\n--- Searching for known KEY pattern ---\n";
$knownKey = "6c326S2GUzu2eRTTGAXmFcfGis1RK3YsU6K1";
$knownIV = "70314b356e50377542386848316c3139";

$keyPos = strpos($searchData, $knownKey);
$ivPos = strpos($searchData, $knownIV);

echo "Known KEY found at: " . ($keyPos !== false ? $keyPos : "NOT FOUND") . "\n";
echo "Known IV found at: " . ($ivPos !== false ? $ivPos : "NOT FOUND") . "\n";

if ($keyPos !== false) {
    // Show raw bytes around key
    $start = max(0, $keyPos - 50);
    $rawBytes = substr($searchData, $start, 200);
    echo "Raw hex around KEY: " . bin2hex($rawBytes) . "\n";
}

if ($ivPos !== false) {
    $start = max(0, $ivPos - 50);
    $rawBytes = substr($searchData, $start, 200);
    echo "Raw hex around IV: " . bin2hex($rawBytes) . "\n";
}

// ============================================
// NOW TRY ALL CANDIDATES
// ============================================

echo "\n=== TRYING DECRYPTION ===\n\n";

// Fetch encrypted events
$ch2 = curl_init();
curl_setopt($ch2, CURLOPT_URL, "https://sufyanpromax.space/events.txt");
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_USERAGENT, 
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch2, CURLOPT_TIMEOUT, 30);
$encData = trim(curl_exec($ch2));
curl_close($ch2);

echo "Encrypted data: " . strlen($encData) . " bytes\n";

// Custom Base64 decode
$LOOKUP = "\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0a\x0b\x0c\x0d\x0e\x0f" .
          "\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1a\x1b\x1c\x1d\x1e\x1f" .
          " !\"#\$%&'()*+,-./" .
          "0123456789:;<=>?" .
          "@EGMNKABUVCDYHLI" .
          "FPOZQSRWTXJ[\\]^_" .
          "`egmnkabuvcdyhli" .
          "fpozqsrwtxj{|}~\x7f";

$stdB64 = '';
for ($i = 0; $i < strlen($encData); $i++) {
    $a = ord($encData[$i]);
    $stdB64 .= ($a < strlen($LOOKUP)) ? $LOOKUP[$a] : $encData[$i];
}

$decoded = base64_decode($stdB64);
$reversed = strrev($decoded);
$ciphertext = base64_decode($reversed);

echo "Ciphertext: " . strlen($ciphertext) . " bytes\n";
echo "Block aligned: " . (strlen($ciphertext) % 16 == 0 ? "YES" : "NO") . "\n\n";

// IV - try both hex-decoded and raw
$ivHex = "70314b356e50377542386848316c3139";
$iv1 = hex2bin($ivHex);  // 16 bytes
$iv2 = substr($ivHex, 0, 16);  // first 16 chars as raw

// Collect ALL potential keys to try
$keysToTry = [];

// Add hex candidates
foreach ($hexCandidates as $hc) {
    $keysToTry["hexcandidate_raw_$hc"] = $hc;
    if (strlen($hc) == 32 && ctype_xdigit($hc)) {
        $keysToTry["hexcandidate_decoded_$hc"] = hex2bin($hc);
    }
}

// Add all 32-char strings
foreach ($allStrings as $str) {
    if (strlen($str) == 32) {
        $keysToTry["str32_raw_$str"] = $str;
        if (ctype_xdigit($str)) {
            $keysToTry["str32_hex_$str"] = hex2bin($str);
        }
    }
}

// Add java-style key
$javaKey = '';
$keyStr = "6c326S2GUzu2eRTTGAXmFcfGis1RK3YsU6K1";
for ($i = 0; $i < strlen($keyStr) - 1; $i += 2) {
    $h = ctype_xdigit($keyStr[$i]) ? hexdec($keyStr[$i]) : -1;
    $l = ctype_xdigit($keyStr[$i+1]) ? hexdec($keyStr[$i+1]) : -1;
    $javaKey .= chr((($h << 4) + $l) & 0xFF);
}
$keysToTry["java_style_18"] = $javaKey;
$keysToTry["java_trim16"] = substr($javaKey, 0, 16);
$keysToTry["java_pad32"] = str_pad($javaKey, 32, "\0");

// Direct key string variations
$keysToTry["direct_16"] = substr($keyStr, 0, 16);
$keysToTry["direct_32"] = substr(str_pad($keyStr, 32, "\0"), 0, 32);

echo "Total keys to try: " . count($keysToTry) . "\n\n";

$ciphers = ['aes-128-cbc', 'aes-192-cbc', 'aes-256-cbc'];
$ivs = ['hex_decoded' => $iv1, 'raw_16' => $iv2];
$found = false;

foreach ($keysToTry as $keyName => $key) {
    foreach ($ciphers as $cipher) {
        $reqLen = match($cipher) {
            'aes-128-cbc' => 16,
            'aes-192-cbc' => 24,
            'aes-256-cbc' => 32,
        };
        
        $adjKey = substr(str_pad($key, $reqLen, "\0"), 0, $reqLen);
        
        foreach ($ivs as $ivName => $iv) {
            $result = @openssl_decrypt(
                $ciphertext, $cipher, $adjKey, 
                OPENSSL_RAW_DATA, $iv
            );
            
            if ($result !== false && strlen($result) > 10) {
                $t = trim($result);
                $fc = substr($t, 0, 1);
                if ($fc === '[' || $fc === '{') {
                    echo "?????????????????????????????\n";
                    echo "?? SUCCESS!\n";
                    echo "Key: $keyName\n";
                    echo "Cipher: $cipher\n";
                    echo "IV: $ivName\n";
                    echo "Key hex: " . bin2hex($adjKey) . "\n";
                    echo "IV hex: " . bin2hex($iv) . "\n";
                    echo "Result (300 chars):\n";
                    echo substr($t, 0, 300) . "\n";
                    echo "?????????????????????????????\n";
                    $found = true;
                    break 3;
                }
            }
        }
    }
}

if (!$found) {
    echo "? No working combination found!\n";
    echo "\nOpenSSL errors:\n";
    while ($msg = openssl_error_string()) {
        echo "  $msg\n";
    }
}

echo "\n</pre>";
?>
