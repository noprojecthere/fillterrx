<?php
header('Content-Type: text/plain; charset=utf-8');

// API URL
$apiUrl = 'https://cloudplay-app.cloudplay-help.workers.dev/hotstar?password=all';

// Data fetch karo
$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => "User-Agent: Mozilla/5.0\r\n",
        'timeout' => 30
    ]
]);

$jsonData = @file_get_contents($apiUrl, false, $context);
$channels = json_decode($jsonData, true);

// Clean M3U start
echo "#EXTM3U\n\n";

// Har channel process karo
foreach ($channels as $channel) {
    $id = $channel['id'] ?? '';
    $name = $channel['name'] ?? 'Unknown';
    $group = $channel['group'] ?? 'General';
    $logo = $channel['logo'] ?? '';
    $userAgent = $channel['user_agent'] ?? '';
    $m3u8Url = $channel['m3u8_url'] ?? '';
    
    if (empty($m3u8Url)) continue;
    
    // Headers extract karo
    $cookie = $channel['headers']['Cookie'] ?? '';
    $origin = $channel['headers']['Origin'] ?? 'https://www.hotstar.com';
    $referer = $channel['headers']['Referer'] ?? 'https://www.hotstar.com';
    
    // 1. EXTINF line
    echo "#EXTINF:-1";
    if ($id) echo " tvg-id=\"$id\"";
    if ($group) echo " group-title=\"$group\"";
    if ($logo) echo " tvg-logo=\"$logo\"";
    echo ",$name\n";
    
    // 2. User Agent (EXTVLCOPT)
    if ($userAgent) {
        echo "#EXTVLCOPT:http-user-agent=" . $userAgent . "\n";
    }
    
    // 3. Origin header
    echo "#EXTVLCOPT:http-origin=" . $origin . "\n";
    
    // 4. Referrer header
    echo "#EXTVLCOPT:http-referrer=" . $referer . "\n";
    
    // 5. Final stream URL - **NEW QUERY FORMAT**
    if ($cookie) {
        // Extract hdntl value from cookie (hdntl=... tak)
        if (preg_match('/hdntl=(.*)/', $cookie, $matches)) {
            $hdntl = $matches[1];
            echo "$m3u8Url?hdntl=$hdntl\n\n";
        } else {
            echo "$m3u8Url\n\n";
        }
    } else {
        echo "$m3u8Url\n\n";
    }
}
?>
