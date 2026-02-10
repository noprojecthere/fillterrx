<?php
// ============================================
// DEBUG SCRIPT - Pehle yeh run karo
// ============================================

$KEY_HEX_STRING = "6c326S2GUzu2eRTTGAXmFcfGis1RK3YsU6K1";
$IV_HEX_STRING  = "70314b356e50377542386848316c3139";

function hexCharToDigit($char) {
    $code = ord($char);
    if ($code >= ord('0') && $code <= ord('9')) return $code - ord('0');
    if ($code >= ord('a') && $code <= ord('f')) return $code - ord('a') + 10;
    if ($code >= ord('A') && $code <= ord('F')) return $code - ord('A') + 10;
    return -1;
}

function hexStringToByteArray($hex) {
    $len = strlen($hex);
    $data = '';
    for ($i = 0; $i < $len - 1; $i += 2) {
        $high = hexCharToDigit($hex[$i]);
        $low  = hexCharToDigit($hex[$i + 1]);
        $byte = (($high << 4) + $low) & 0xFF;
        $data .= chr($byte);
    }
    return $data;
}

$AES_KEY = hexStringToByteArray($KEY_HEX_STRING);
$AES_IV  = hexStringToByteArray($IV_HEX_STRING);

echo "=== KEY DEBUG ===\n";
echo "KEY string: $KEY_HEX_STRING\n";
echo "KEY string length: " . strlen($KEY_HEX_STRING) . " chars\n";
echo "KEY bytes length: " . strlen($AES_KEY) . " bytes\n";
echo "KEY hex dump: " . bin2hex($AES_KEY) . "\n\n";

echo "IV string: $IV_HEX_STRING\n";  
echo "IV string length: " . strlen($IV_HEX_STRING) . " chars\n";
echo "IV bytes length: " . strlen($AES_IV) . " bytes\n";
echo "IV hex dump: " . bin2hex($AES_IV) . "\n\n";

// Check each char of KEY
echo "=== KEY CHAR ANALYSIS ===\n";
for ($i = 0; $i < strlen($KEY_HEX_STRING); $i++) {
    $char = $KEY_HEX_STRING[$i];
    $digit = hexCharToDigit($char);
    $valid = ($digit >= 0) ? "?" : "? INVALID";
    echo "Position $i: '$char' ? digit: $digit $valid\n";
}

?>
