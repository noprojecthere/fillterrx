<?php
// Set headers to output M3U file
header('Content-Type: audio/x-mpegurl');
header('Content-Disposition: inline; filename="playlist.m3u"');

// Fetch JSON data from URL
$jsonUrl = "https://ztv.pfy.workers.dev";
$jsonData = file_get_contents($jsonUrl);

// Check if data was fetched successfully
if ($jsonData === false) {
    die("Error: Unable to fetch JSON data from URL");
}

// Decode JSON data
$channels = json_decode($jsonData, true);

// Check if JSON is valid
if ($channels === null) {
    die("Error: Invalid JSON data");
}

// Start M3U playlist with header
echo "#EXTM3U\n\n";

// Add join message entry
echo '#EXTINF:-1 movie-type="web" group-title="Join Now" tvg-logo="https://blogger.googleusercontent.com/img/b/R29vZ2xl/AVvXsEiamoffBdXQP0r6SHT9kM1ravyBjCVbUrncneORa9h4STgb_d8iEmMyKWn5hbzNnShrdNQYmCMDmbr3xFittRirO_zNiW4ic1FpEwxoVKwxSleDLlTgx9tHmKmKWRwqIyHYWgaUohCyIYKF6TMAutBebcryI8jVyoU4YmeKLPj4dU1gvxmenQ9Lg7MpyOfK/s1280/20250321_130159.png" , join:@streamstartv' . "\n";
echo "https://t.me/streamstartv\n\n";

// Loop through each channel and create M3U entries
foreach ($channels as $channel) {
    // Extract fields from JSON
    $name = $channel['name'] ?? 'Unknown';
    $logo = $channel['logo'] ?? '';
    $link = $channel['link'] ?? '';
    $drmLicense = $channel['drmLicense'] ?? '';
    $cookie = $channel['cookie'] ?? '';

    // Split drmLicense into keyid and key
    if (!empty($drmLicense)) {
        $licenseParts = explode(':', $drmLicense);
        $keyid = $licenseParts[0] ?? '';
        $key = $licenseParts[1] ?? '';
    } else {
        $keyid = '';
        $key = '';
    }

    // Create EXTINF line
    echo '#EXTINF:-1 group-title="Streamstar" tvg-logo="' . $logo . '" , ' . $name . "\n";

    // Create stream URL with license and cookie parameters
    if (!empty($keyid) && !empty($key)) {
        echo $link . '?|LicenseURL=https://vercel-php-clearkey-hex-base64-json.vercel.app/api/results.php?keyid=' . $keyid . '&key=' . $key . '||cookie=' . $cookie . "\n\n";
    } else {
        echo $link . "\n\n";
    }
}
?>
