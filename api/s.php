<?php
// ============================================
// DEBUG SCRIPT 2 - Sab combinations try karo
// ============================================

$KEY_HEX_STRING = "6c326S2GUzu2eRTTGAXmFcfGis1RK3YsU6K1";
$IV_HEX_STRING  = "70314b356e50377542386848316c3139";

$AES_IV = hex2bin($IV_HEX_STRING);

echo "IV: " . bin2hex($AES_IV) . " (" . strlen($AES_IV) . " bytes)\n\n";

// ============================================
// FETCH ENCRYPTED DATA
// ============================================
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://sufyanpromax.space/events.txt");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, 
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
$encrypted = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Encrypted length: " . strlen($encrypted) . "\n";

if (empty($encrypted)) {
    die("Failed to fetch events.txt!\n");
}

$encrypted = trim($encrypted);
echo "First 100 chars: " . substr($encrypted, 0, 100) . "\n\n";

// ============================================
// CUSTOM BASE64 TRANSLATION
// ============================================
$LOOKUP_TABLE_D = "\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0a\x0b\x0c\x0d\x0e\x0f" .
                  "\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1a\x1b\x1c\x1d\x1e\x1f" .
                  " !\"#\$%&'()*+,-./" .
                  "0123456789:;<=>?" .
                  "@EGMNKABUVCDYHLI" .
                  "FPOZQSRWTXJ[\\]^_" .
                  "`egmnkabuvcdyhli" .
                  "fpozqsrwtxj{|}~\x7f";

$standardB64 = '';
$len = strlen($encrypted);
for ($i = 0; $i < $len; $i++) {
    $ascii = ord($encrypted[$i]);
    if ($ascii < strlen($LOOKUP_TABLE_D)) {
        $standardB64 .= $LOOKUP_TABLE_D[$ascii];
    } else {
        $standardB64 .= $encrypted[$i];
    }
}

echo "Standard B64 (first 100): " . substr($standardB64, 0, 100) . "\n\n";

// ============================================
// DECODE STEPS
// ============================================
$decoded = base64_decode($standardB64);
echo "After B64 decode: " . strlen($decoded) . " bytes\n";

$reversed = strrev($decoded);
echo "After reverse: " . strlen($reversed) . " bytes\n";

$ciphertext = base64_decode($reversed);
echo "Ciphertext: " . strlen($ciphertext) . " bytes\n";
echo "Block aligned (mod 16): " . (strlen($ciphertext) % 16) . "\n\n";

if (strlen($ciphertext) % 16 !== 0) {
    echo "? NOT BLOCK ALIGNED - Something wrong in decode steps!\n\n";
    
    // Try without reverse
    echo "=== Trying WITHOUT reverse ===\n";
    $ciphertext2 = base64_decode($decoded);
    if ($ciphertext2 !== false) {
        echo "Without reverse ciphertext: " . strlen($ciphertext2) . " bytes\n";
        echo "Block aligned: " . (strlen($ciphertext2) % 16) . "\n\n";
    }
}

// ============================================
// JAVA STYLE KEY GENERATION (exact copy)
// ============================================
function javaHexStringToByteArray($hex) {
    $len = strlen($hex);
    $data = '';
    for ($i = 0; $i < $len - 1; $i += 2) {
        $c1 = $hex[$i];
        $c2 = $hex[$i + 1];
        $high = javaCharDigit($c1);
        $low = javaCharDigit($c2);
        // Java: (byte) ((high << 4) + low)
        // In Java, byte is signed -128 to 127
        $val = ($high << 4) + $low;
        $data .= chr($val & 0xFF);
    }
    return $data;
}

function javaCharDigit($ch) {
    $c = ord($ch);
    if ($c >= 48 && $c <= 57) return $c - 48;     // 0-9
    if ($c >= 65 && $c <= 70) return $c - 65 + 10; // A-F
    if ($c >= 97 && $c <= 102) return $c - 97 + 10; // a-f
    return -1;
}

$javaKey = javaHexStringToByteArray($KEY_HEX_STRING);
echo "=== JAVA-STYLE KEY ===\n";
echo "Key hex: " . bin2hex($javaKey) . "\n";
echo "Key length: " . strlen($javaKey) . " bytes\n\n";

// ============================================
// TRY ALL POSSIBLE KEY SIZES & METHODS
// ============================================

$allKeys = [];

// 1. Java-style (18 bytes) - pad to 16, 24, 32
$allKeys["java-pad16"] = substr(str_pad($javaKey, 16, "\0"), 0, 16);
$allKeys["java-pad24"] = str_pad($javaKey, 24, "\0");
$allKeys["java-pad32"] = str_pad($javaKey, 32, "\0");
$allKeys["java-trim16"] = substr($javaKey, 0, 16);

// 2. Direct string as key
$allKeys["direct-16"] = substr($KEY_HEX_STRING, 0, 16);
$allKeys["direct-24"] = substr(str_pad($KEY_HEX_STRING, 24, "\0"), 0, 24);
$allKeys["direct-32"] = substr(str_pad($KEY_HEX_STRING, 32, "\0"), 0, 32);

// 3. MD5/SHA of key string
$allKeys["md5-raw"] = md5($KEY_HEX_STRING, true);  // 16 bytes
$allKeys["sha256-16"] = substr(hash('sha256', $KEY_HEX_STRING, true), 0, 16);
$allKeys["sha256-32"] = hash('sha256', $KEY_HEX_STRING, true);

// 4. Key string as UTF-8 bytes, different lengths
$allKeys["utf8-16"] = substr($KEY_HEX_STRING, 0, 16);
$allKeys["utf8-32"] = str_pad($KEY_HEX_STRING, 32, "\0");

// 5. Maybe KEY is actually the ASCII representation
// "6c326S2GUzu2eRTTGAXmFcfGis1RK3Ys" = 32 chars = could be 32-byte key
$allKeys["ascii-32-full"] = substr($KEY_HEX_STRING, 0, 32);
$allKeys["ascii-36-pad"] = str_pad($KEY_HEX_STRING, 48, "\0");

// 6. IV as key (testing)
$allKeys["iv-as-key"] = $AES_IV;

// 7. Maybe the key IS the hex but needs different interpretation
// Some hex pairs are valid, invalid ones should be handled differently
$validOnly = '';
for ($i = 0; $i < strlen($KEY_HEX_STRING) - 1; $i += 2) {
    $h = javaCharDigit($KEY_HEX_STRING[$i]);
    $l = javaCharDigit($KEY_HEX_STRING[$i + 1]);
    if ($h >= 0 && $l >= 0) {
        $validOnly .= chr(($h << 4) + $l);
    }
}
$allKeys["valid-hex-only-pad16"] = str_pad($validOnly, 16, "\0");

echo "=== TRYING ALL KEY + CIPHER COMBINATIONS ===\n\n";

$ciphers = ['aes-128-cbc', 'aes-192-cbc', 'aes-256-cbc'];
$found = false;

foreach ($allKeys as $keyName => $key) {
    foreach ($ciphers as $cipher) {
        $requiredLen = match($cipher) {
            'aes-128-cbc' => 16,
            'aes-192-cbc' => 24,
            'aes-256-cbc' => 32,
        };
        
        // Only try if key length matches
        if (strlen($key) !== $requiredLen) continue;
        
        $result = @openssl_decrypt(
            $ciphertext,
            $cipher,
            $key,
            OPENSSL_RAW_DATA,
            $AES_IV
        );
        
        if ($result !== false && strlen($result) > 10) {
            $trimmed = trim($result);
            $firstChar = substr($trimmed, 0, 1);
            $isJson = ($firstChar === '[' || $firstChar === '{');
            $isPrintable = ctype_print(substr($trimmed, 0, 50)) || $isJson;
            
            if ($isPrintable || $isJson) {
                echo "???????????????????????????????\n";
                echo "KEY NAME: $keyName\n";
                echo "CIPHER: $cipher\n";
                echo "KEY HEX: " . bin2hex($key) . "\n";
                echo "KEY LEN: " . strlen($key) . " bytes\n";
                
                if ($isJson) {
                    echo "STATUS: ??? JSON FOUND! ???\n";
                    echo "FIRST 300 CHARS:\n";
                    echo substr($trimmed, 0, 300) . "\n";
                    
                    file_put_contents('decrypted_events.json', $result);
                    echo "\nSaved to decrypted_events.json!\n";
                    $found = true;
                    break 2;
                } else {
                    echo "STATUS: ?? Readable but not JSON\n";
                    echo "FIRST 200 CHARS:\n";
                    echo substr($trimmed, 0, 200) . "\n";
                }
                echo "???????????????????????????????\n\n";
            }
        }
    }
}

if (!$found) {
    echo "\n? No working key found with standard methods!\n\n";
    
    echo "=== LAST RESORT: Try openssl errors ===\n";
    while ($msg = openssl_error_string()) {
        echo "OpenSSL Error: $msg\n";
    }
    
    echo "\n=== RAW CIPHERTEXT HEX (first 64 bytes) ===\n";
    echo bin2hex(substr($ciphertext, 0, 64)) . "\n";
    
    echo "\n=== DECODED STRING (first 200 chars) ===\n";
    echo substr($decoded, 0, 200) . "\n";
    
    echo "\n=== REVERSED STRING (first 200 chars) ===\n";  
    echo substr($reversed, 0, 200) . "\n";
}

?>
