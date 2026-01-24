<?php
// Set headers for M3U playlist
header('Content-Type: audio/x-mpegurl');
header('Content-Disposition: inline; filename="jpvali_playlist.m3u"');

// M3U file URL
$m3u_url = 'https://raw.githubusercontent.com/alex8875/m3u/refs/heads/main/jtv.m3u';
//
// Fetch the M3U content
$content = @file_get_contents($m3u_url);

if ($content === false) {
    die("#EXTM3U\n#EXTINF:-1,Error: Unable to fetch M3U file\n");
}

// Split content into lines
$lines = explode("\n", $content);
$modified_lines = [];

foreach ($lines as $line) {
    $line = trim($line);

    // REMOVE: #EXTHTTP lines completely
    if (strpos($line, '#EXTHTTP:') === 0) {
        continue; // Skip this line
    }

    // Modify license key format
    if (strpos($line, '#KODIPROP:inputstream.adaptive.license_key=') === 0) {
        $key_part = substr($line, strlen('#KODIPROP:inputstream.adaptive.license_key='));
        $keys = explode(':', $key_part);
        if (count($keys) == 2) {
            $keyid = trim($keys[0]);
            $key = trim($keys[1]);
            $line = '#KODIPROP:inputstream.adaptive.license_key=https://vercel-php-clearkey-hex-base64-json.vercel.app/api/results.php?keyid=' . $keyid . '&key=' . $key;
        }
    }

    // Modify URL: Extract cookie and rebuild URL
    if ((strpos($line, 'https://jiotvmblive.cdn.jio.com') === 0 || 
         strpos($line, 'https://jiotvpllive.cdn.jio.com') === 0) && 
        strpos($line, 'index.mpd?') !== false) {

        // Extract the cookie value from __hdnea__ parameter
        if (preg_match('/__hdnea__=([^&]+)/', $line, $matches)) {
            $cookie_value = $matches[1];
            
            // Get base URL (everything before the ?)
            $url_parts = explode('?', $line);
            $base_url = $url_parts[0];
            
            // Rebuild URL with ||cookie= format
            $line = $base_url . '?||cookie=__hdnea__=' . $cookie_value;
        }
    }

    $modified_lines[] = $line;
}

// Output the modified content
echo implode("\n", $modified_lines);
?>
