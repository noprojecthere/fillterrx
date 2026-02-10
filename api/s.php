<?php
// ============================================
// DEBUG 5 - PROPER DEX STRING TABLE PARSER
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
curl_close($ch);

echo "Size: " . strlen($cs3Data) . " bytes\n";

// ============================================
// EXTRACT classes.dex FROM ZIP IN MEMORY
// ============================================

$tmpFile = tempnam(sys_get_temp_dir(), 'cs3_');
file_put_contents($tmpFile, $cs3Data);

$zip = new ZipArchive();
$dexData = null;

if ($zip->open($tmpFile) === TRUE) {
    $dexData = $zip->getFromName('classes.dex');
    $zip->close();
    echo "DEX size: " . strlen($dexData) . " bytes\n\n";
} 

@unlink($tmpFile);

if (empty($dexData)) {
    die("Failed to get DEX!\n");
}

// ============================================
// PROPER DEX STRING TABLE PARSER
// ============================================

echo "=== PARSING DEX STRING TABLE ===\n\n";

// DEX Header
$magic = substr($dexData, 0, 8);
echo "Magic: " . bin2hex(substr($dexData, 0, 4)) . "\n";

// String IDs
$stringIdsSize = unpack('V', substr($dexData, 0x38, 4))[1];
$stringIdsOff  = unpack('V', substr($dexData, 0x3C, 4))[1];

echo "String count: $stringIdsSize\n";
echo "String IDs offset: $stringIdsOff\n\n";

// Read ULEB128
function readULEB128($data, &$pos) {
    $result = 0;
    $shift = 0;
    do {
        $b = ord($data[$pos]);
        $result |= ($b & 0x7F) << $shift;
        $shift += 7;
        $pos++;
    } while ($b & 0x80);
    return $result;
}

// Extract ALL strings from DEX string table
$allStrings = [];

for ($i = 0; $i < $stringIdsSize; $i++) {
    // Read string data offset
    $dataOff = unpack('V', substr($dexData, $stringIdsOff + ($i * 4), 4))[1];
    
    // Read ULEB128 size
    $pos = $dataOff;
    $strSize = readULEB128($dexData, $pos);
    
    // Read null-terminated MUTF-8 string
    $str = '';
    $maxRead = min($strSize * 3, 1000); // safety limit
    $count = 0;
    while ($pos < strlen($dexData) && ord($dexData[$pos]) != 0 && $count < $maxRead) {
        $str .= $dexData[$pos];
        $pos++;
        $count++;
    }
    
    $allStrings[] = $str;
}

echo "Total strings extracted: " . count($allStrings) . "\n\n";

// ============================================
// SEARCH FOR KEY/IV RELATED STRINGS
// ============================================

echo "=== ALL STRINGS (filtered) ===\n\n";

echo "--- Strings containing 'key', 'iv', 'aes', 'crypt', 'hex', 'sklive' ---\n";
foreach ($allStrings as $idx => $str) {
    $lower = strtolower($str);
    if (strpos($lower, 'key') !== false || 
        strpos($lower, 'aes') !== false ||
        strpos($lower, 'crypt') !== false ||
        strpos($lower, 'sklive') !== false ||
        strpos($lower, 'hex') !== false ||
        strpos($lower, 'cipher') !== false ||
        strpos($lower, 'secret') !== false ||
        strpos($lower, 'iv') !== false) {
        echo "  [$idx] ($str)\n";
    }
}

echo "\n--- Strings 30-70 chars long ---\n";
foreach ($allStrings as $idx => $str) {
    $len = strlen($str);
    if ($len >= 30 && $len <= 70) {
        $hexChars = 0;
        for ($j = 0; $j < $len; $j++) {
            if (ctype_xdigit($str[$j])) $hexChars++;
        }
        $pct = round(($hexChars / $len) * 100);
        echo "  [$idx] ($len chars, $pct% hex) $str\n";
    }
}

echo "\n--- Strings exactly 32 chars ---\n";
foreach ($allStrings as $idx => $str) {
    if (strlen($str) == 32) {
        echo "  [$idx] $str\n";
    }
}

echo "\n--- Strings exactly 36 chars ---\n";
foreach ($allStrings as $idx => $str) {
    if (strlen($str) == 36) {
        echo "  [$idx] $str\n";
    }
}

echo "\n--- Strings exactly 48 chars ---\n";
foreach ($allStrings as $idx => $str) {
    if (strlen($str) == 48) {
        echo "  [$idx] $str\n";
    }
}

echo "\n--- Strings exactly 64 chars ---\n";
foreach ($allStrings as $idx => $str) {
    if (strlen($str) == 64) {
        echo "  [$idx] $str\n";
    }
}

echo "\n--- ALL strings 16+ chars (potential keys) ---\n";
foreach ($allStrings as $idx => $str) {
    $len = strlen($str);
    if ($len >= 16 && $len <= 100) {
        // Skip obvious non-key strings
        if (strpos($str, ' ') !== false && strpos($str, '.') !== false) continue;
        if (strpos($str, 'http') === 0) continue;
        if (strpos($str, 'com.') === 0) continue;
        if (strpos($str, 'java') === 0) continue;
        if (strpos($str, 'kotlin') === 0) continue;
        if (strpos($str, 'android') === 0) continue;
        if (strpos($str, 'org.') === 0) continue;
        if (strpos($str, 'javax') === 0) continue;
        
        echo "  [$idx] ($len) $str\n";
    }
}

echo "\n--- FULL STRING DUMP (all " . count($allStrings) . " strings) ---\n";
foreach ($allStrings as $idx => $str) {
    if (strlen($str) > 0 && strlen($str) < 200) {
        echo "  [$idx] $str\n";
    }
}

// ============================================
// NOW TRY EVERY 32+ CHAR STRING AS KEY
// ============================================

echo "\n\n=== BRUTE FORCE DECRYPTION ===\n\n";

// Fetch encrypted data
$ch2 = curl_init();
curl_setopt($ch2, CURLOPT_URL, "https://sufyanpromax.space/events.txt");
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_USERAGENT, 
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch2, CURLOPT_TIMEOUT, 30);
$encData = trim(curl_exec($ch2));
curl_close($ch2);

// Decrypt steps
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

echo "Ciphertext: " . strlen($ciphertext) . " bytes, aligned: " . 
     (strlen($ciphertext) % 16 == 0 ? "YES" : "NO") . "\n\n";

// Collect all potential keys from strings
$potentialKeys = [];

foreach ($allStrings as $str) {
    $len = strlen($str);
    if ($len >= 16 && $len <= 64) {
        $potentialKeys["raw_$str"] = $str;
        
        // If valid hex, also try decoded
        if (ctype_xdigit($str) && $len % 2 == 0) {
            $potentialKeys["hexdec_$str"] = hex2bin($str);
        }
    }
}

// Also try the Java-style hex decode for every string
foreach ($allStrings as $str) {
    $len = strlen($str);
    if ($len >= 30 && $len <= 64 && $len % 2 == 0) {
        $javaDecoded = '';
        for ($i = 0; $i < $len - 1; $i += 2) {
            $h = ctype_xdigit($str[$i]) ? hexdec($str[$i]) : -1;
            $l = ctype_xdigit($str[$i+1]) ? hexdec($str[$i+1]) : -1;
            $javaDecoded .= chr((($h << 4) + $l) & 0xFF);
        }
        $potentialKeys["javahex_$str"] = $javaDecoded;
    }
}

echo "Potential keys to try: " . count($potentialKeys) . "\n\n";

// Try ALL potential IVs too
$potentialIVs = [];
foreach ($allStrings as $str) {
    if (strlen($str) == 32 && ctype_xdigit($str)) {
        $potentialIVs["hexdec_$str"] = hex2bin($str);
    }
    if (strlen($str) == 16) {
        $potentialIVs["raw16_$str"] = $str;
    }
    if (strlen($str) >= 32) {
        // Java-style decode to 16 bytes
        $len = strlen($str);
        if ($len >= 32) {
            $sub = substr($str, 0, 32);
            if (ctype_xdigit($sub)) {
                $potentialIVs["sub32hex_$str"] = hex2bin($sub);
            }
        }
    }
}

// Add known IV variations
$potentialIVs["known_hex"] = hex2bin("7031udZrQVKAfFo4jAhXoaAJNM6Trsrpxso9");
$potentialIVs["known_raw16"] = substr("7031udZrQVKAfFo4jAhXoaAJNM6Trsrpxso9", 0, 16);

echo "Potential IVs to try: " . count($potentialIVs) . "\n\n";

$found = false;
$tried = 0;

foreach ($potentialKeys as $keyName => $key) {
    foreach (['aes-128-cbc', 'aes-256-cbc'] as $cipher) {
        $reqLen = ($cipher === 'aes-128-cbc') ? 16 : 32;
        $adjKey = substr(str_pad($key, $reqLen, "\0"), 0, $reqLen);
        
        foreach ($potentialIVs as $ivName => $iv) {
            if (strlen($iv) !== 16) continue;
            
            $tried++;
            $result = @openssl_decrypt(
                $ciphertext, $cipher, $adjKey,
                OPENSSL_RAW_DATA, $iv
            );
            
            if ($result !== false && strlen($result) > 10) {
                $t = trim($result);
                $fc = substr($t, 0, 1);
                if ($fc === '[' || $fc === '{') {
                    echo "???????????????????????????????????????\n";
                    echo "?????? SUCCESS! ??????\n";
                    echo "Key name: $keyName\n";
                    echo "Key hex: " . bin2hex($adjKey) . "\n";
                    echo "Key len: " . strlen($adjKey) . "\n";
                    echo "IV name: $ivName\n";
                    echo "IV hex: " . bin2hex($iv) . "\n";
                    echo "Cipher: $cipher\n";
                    echo "Tried: $tried combinations\n";
                    echo "\nFirst 500 chars:\n";
                    echo substr($t, 0, 500) . "\n";
                    echo "???????????????????????????????????????\n";
                    $found = true;
                    break 3;
                }
            }
        }
    }
}

if (!$found) {
    echo "? Tried $tried combinations - none worked!\n";
}

echo "\n</pre>";
?>
