<?php
// JSON URL se data fetch karna
$jsonUrl = 'https://sliv-fc.pfy.workers.dev';

// Set headers for proper content type
header('Content-Type: audio/x-mpegurl');
header('Content-Disposition: attachment; filename="flvali_playlist.m3u"');

// JSON data fetch karna using file_get_contents
$jsonData = file_get_contents($jsonUrl);

// Check if data fetch hua ya nahi
if ($jsonData === false) {
    die("Error: Unable to fetch data from URL");
}

// JSON ko PHP array mein decode karna
$channels = json_decode($jsonData, true);

// Check if JSON decode properly hua
if ($channels === null) {
    die("Error: Invalid JSON data");
}

// M3U header print karna
echo "#EXTM3U\n";

// Har channel ke liye M3U format mein convert karna
foreach ($channels as $channel) {
    // EXTINF line with logo and name
    echo "#EXTINF:-1 tvg-logo=\"" . $channel['logo'] . "\"," . $channel['name'] . "\n";
    // Stream URL
    echo $channel['link'] . "\n";
}
?>
