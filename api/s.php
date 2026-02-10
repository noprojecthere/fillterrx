<?php
// ============================================
// METHOD 3: Extract KEY/IV directly from .cs3
// Download and extract actual bytes from classes.dex
// ============================================

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

// Save .cs3
file_put_contents('SKTechProvider.cs3', $cs3Data);

// .cs3 is a ZIP file - extract classes.dex
$zip = new ZipArchive();
if ($zip->open('SKTechProvider.cs3') === TRUE) {
    echo "ZIP opened successfully\n";
    
    // List all files
    for ($i = 0; $i < $zip->numFiles; $i++) {
        echo "  File: " . $zip->getNameIndex($i) . "\n";
    }
    
    // Extract classes.dex
    $dexData = $zip->getFromName('classes.dex');
    if ($dexData === false) {
        // Try other common names
        $dexData = $zip->getFromIndex(0);
    }
    $zip->close();
    
    if ($dexData) {
        echo "classes.dex size: " . strlen($dexData) . " bytes\n\n";
        
        // ============================================
        // SEARCH FOR HEX STRINGS IN DEX
        // ============================================
        echo "=== SEARCHING FOR KEY STRINGS IN DEX ===\n\n";
        
        // Search for known patterns
        $patterns = [
            'hexStringToByteArray',
            'SKLIVE',
            'AES_KEY',
            'AES_IV',
            'AES/CBC',
            'PKCS5Padding',
            'SecretKeySpec',
        ];
        
        foreach ($patterns as $pattern) {
            $pos = strpos($dexData, $pattern);
            if ($pos !== false) {
                echo "Found '$pattern' at position: $pos\n";
                // Show surrounding bytes
                $start = max(0, $pos - 20);
                $end = min(strlen($dexData), $pos + strlen($pattern) + 100);
                $surrounding = substr($dexData, $start, $end - $start);
                
                // Extract printable strings around it
                $printable = '';
                for ($j = 0; $j < strlen($surrounding); $j++) {
                    $c = ord($surrounding[$j]);
                    if ($c >= 32 && $c <= 126) {
                        $printable .= $surrounding[$j];
                    } else {
                        if (strlen($printable) > 3) {
                            // Keep it
                        }
                        $printable .= '.';
                    }
                }
                echo "Context: $printable\n\n";
            }
        }
        
        // ============================================
        // FIND ALL HEX-LIKE STRINGS (32-64 chars)
        // ============================================
        echo "=== ALL POTENTIAL KEY STRINGS ===\n\n";
        
        // Extract all printable strings from DEX
        $allStrings = [];
        $currentString = '';
        
        for ($i = 0; $i < strlen($dexData); $i++) {
            $c = ord($dexData[$i]);
            if ($c >= 32 && $c <= 126) {
                $currentString .= chr($c);
            } else {
                if (strlen($currentString) >= 16 && strlen($currentString) <= 128) {
                    $allStrings[] = $currentString;
                }
                $currentString = '';
            }
        }
        
        // Filter potential keys
        echo "--- Strings that look like hex (32+ chars, hex chars) ---\n";
        $hexCandidates = [];
        foreach ($allStrings as $str) {
            $len = strlen($str);
            if ($len >= 32 && $len <= 64) {
                // Check if mostly hex characters
                $hexChars = 0;
                for ($j = 0; $j < $len; $j++) {
                    if (ctype_xdigit($str[$j])) $hexChars++;
                }
                $hexPercent = ($hexChars / $len) * 100;
                
                if ($hexPercent > 70) {
                    echo "[$len chars, {$hexPercent}% hex] $str\n";
                    $hexCandidates[] = $str;
                }
            }
        }
        
        echo "\n--- Strings containing 'key', 'iv', 'aes', 'crypt' ---\n";
        foreach ($allStrings as $str) {
            $lower = strtolower($str);
            if (strpos($lower, 'key') !== false || 
                strpos($lower, 'aes') !== false ||
                strpos($lower, 'crypt') !== false ||
                strpos($lower, 'cipher') !== false ||
                strpos($lower, 'sklive') !== false) {
                echo "[$str]\n";
            }
        }
        
        echo "\n--- All 32-char strings ---\n";
        foreach ($allStrings as $str) {
            if (strlen($str) == 32) {
                echo "$str\n";
            }
        }
        
        echo "\n--- All 48-char strings ---\n";
        foreach ($allStrings as $str) {
            if (strlen($str) == 48) {
                echo "$str\n";
            }
        }

        echo "\n--- All 64-char strings ---\n";
        foreach ($allStrings as $str) {
            if (strlen($str) == 64) {
                echo "$str\n";
            }
        }
        
        // ============================================
        // NOW TRY ALL CANDIDATES AS KEYS
        // ============================================
        if (!empty($hexCandidates)) {
            echo "\n=== TRYING HEX CANDIDATES AS KEYS ===\n\n";
            
            // Fetch encrypted data
            $ch2 = curl_init();
            curl_setopt($ch2, CURLOPT_URL, "https://sufyanpromax.space/events.txt");
            curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch2, CURLOPT_USERAGENT, 
                'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
            curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
            $encData = trim(curl_exec($ch2));
            curl_close($ch2);
            
            // Prepare ciphertext
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
            
            echo "Ciphertext: " . strlen($ciphertext) . " bytes\n\n";
            
            foreach ($hexCandidates as $candidate) {
                echo "Trying: $candidate\n";
                
                // Try as hex-decoded key
                if (strlen($candidate) == 32 && ctype_xdigit($candidate)) {
                    $tryKey = hex2bin($candidate);
                    $result = @openssl_decrypt($ciphertext, 'aes-128-cbc', 
                        $tryKey, OPENSSL_RAW_DATA, $AES_IV);
                    if ($result && substr(trim($result), 0, 1) === '[') {
                        echo "🎉 SUCCESS with hex-decoded 128!\n";
                        echo substr($result, 0, 200) . "\n";
                        file_put_contents('decrypted.json', $result);
                        break;
                    }
                }
                
                // Try as direct string key (various sizes)
                foreach ([16, 24, 32] as $keyLen) {
                    $tryKey = substr(str_pad($candidate, $keyLen, "\0"), 0, $keyLen);
                    $cipher = match($keyLen) {
                        16 => 'aes-128-cbc',
                        24 => 'aes-192-cbc',
                        32 => 'aes-256-cbc',
                    };
                    
                    // Try with hex-decoded IV
                    $result = @openssl_decrypt($ciphertext, $cipher, 
                        $tryKey, OPENSSL_RAW_DATA, hex2bin($IV_HEX_STRING));
                    if ($result && substr(trim($result), 0, 1) === '[') {
                        echo "🎉 SUCCESS! cipher=$cipher\n";
                        echo substr($result, 0, 200) . "\n";
                        file_put_contents('decrypted.json', $result);
                        break 2;
                    }
                    
                    // Try with raw IV string
                    $ivRaw = substr(str_pad($IV_HEX_STRING, 16, "\0"), 0, 16);
                    $result = @openssl_decrypt($ciphertext, $cipher, 
                        $tryKey, OPENSSL_RAW_DATA, $ivRaw);
                    if ($result && substr(trim($result), 0, 1) === '[') {
                        echo "🎉 SUCCESS with raw IV! cipher=$cipher\n";
                        echo substr($result, 0, 200) . "\n";
                        file_put_contents('decrypted.json', $result);
                        break 2;
                    }
                }
            }
        }
        
    } else {
        echo "Could not find classes.dex in ZIP!\n";
    }
} else {
    echo "Failed to open ZIP!\n";
}

echo "\n=== DONE ===\n";
?>
