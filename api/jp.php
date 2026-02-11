<?php
header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: s-maxage=3600, stale-while-revalidate=900');

// Source M3U URL
$sourceUrl = 'https://raw.githubusercontent.com/amit-654584/jtv/refs/heads/main/jtv.m3u';

// Fetch the M3U content
$context = stream_context_create([
    'http' => [
        'timeout' => 30,
        'user_agent' => 'Mozilla/5.0'
    ],
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false
    ]
]);

$m3uContent = file_get_contents($sourceUrl, false, $context);

if ($m3uContent === false) {
    die("Error: Could not fetch M3U from source URL.");
}

// New user agent
$newUserAgent = 'plaYtv/7.1.3 (Linux;Android 13) ygx/824.1 ExoPlayerLib/824.0';

// License key URL template
$licenseKeyUrlTemplate = 'https://aqfadtv.xyz/clearkey/results.php?keyid={KEYID}&key={KEY}';

// Split content into lines
$lines = explode("\n", $m3uContent);

// We'll parse the file block by block
// Each channel block starts with #EXTINF and ends with the stream URL

$output = "#EXTM3U\n\n";

$i = 0;
$totalLines = count($lines);

while ($i < $totalLines) {
    $line = trim($lines[$i]);

    // Skip empty lines
    if ($line === '') {
        $i++;
        continue;
    }

    // Skip standalone #EXTM3U lines (including ones with x-tvg-url)
    if (strpos($line, '#EXTM3U') === 0) {
        $i++;
        continue;
    }

    // Skip comment lines (lines starting with # but not valid M3U tags we care about)
    // Comments like "# ======= CATEGORY: BUSINESS NEWS =======" should be skipped
    if ($line === '#' || (strpos($line, '#') === 0 
        && strpos($line, '#EXTINF') !== 0 
        && strpos($line, '#KODIPROP') !== 0 
        && strpos($line, '#EXTVLCOPT') !== 0 
        && strpos($line, '#EXTHTTP') !== 0)) {
        $i++;
        continue;
    }

    // Look for #EXTINF line - start of a channel block
    if (strpos($line, '#EXTINF') === 0) {
        
        // Check if this is a telegram link entry - we need to look ahead to find the URL
        // Collect the entire block first
        $block = [];
        $block[] = $line;
        $i++;
        
        // Collect all lines until we hit the stream URL (non-# line that's not empty)
        while ($i < $totalLines) {
            $nextLine = trim($lines[$i]);
            if ($nextLine === '') {
                $i++;
                continue;
            }
            $block[] = $nextLine;
            $i++;
            // If it's not a # line, it's the stream URL - end of block
            if (strpos($nextLine, '#') !== 0) {
                break;
            }
        }
        
        // Now process the block
        $extinfLine = '';
        $kodiprops = [];
        $vlcopt = '';
        $exthttp = '';
        $streamUrl = '';
        
        foreach ($block as $bline) {
            if (strpos($bline, '#EXTINF') === 0) {
                $extinfLine = $bline;
            } elseif (strpos($bline, '#KODIPROP') === 0) {
                $kodiprops[] = $bline;
            } elseif (strpos($bline, '#EXTVLCOPT') === 0) {
                $vlcopt = $bline;
            } elseif (strpos($bline, '#EXTHTTP') === 0) {
                $exthttp = $bline;
            } elseif (strpos($bline, '#') !== 0) {
                $streamUrl = $bline;
            }
        }
        
        // Skip entries with telegram links (t.me)
        if (strpos($streamUrl, 't.me') !== false || strpos($extinfLine, 't.me') !== false) {
            continue;
        }
        
        // Skip entries that are just promotional/non-channel entries
        // Check if the channel name contains t.me or telegram references
        if (preg_match('/,\s*t\.me\//i', $extinfLine)) {
            continue;
        }
        
        // Skip if stream URL contains wistia or other non-jio URLs that are promo
        if (strpos($streamUrl, 'wistia.com') !== false) {
            continue;
        }
        
        // Skip entries without proper stream URL
        if (empty($streamUrl)) {
            continue;
        }
        
        // --- TRANSFORM THE BLOCK ---
        
        // 1. Transform license_key
        $newKodiprops = [];
        foreach ($kodiprops as $kp) {
            if (strpos($kp, 'license_key=') !== false) {
                // Extract the original key value: keyid:key format
                if (preg_match('/license_key=([a-f0-9]+):([a-f0-9]+)/i', $kp, $keyMatches)) {
                    $keyid = $keyMatches[1];
                    $key = $keyMatches[2];
                    $newLicenseUrl = str_replace(['{KEYID}', '{KEY}'], [$keyid, $key], $licenseKeyUrlTemplate);
                    $kp = '#KODIPROP:inputstream.adaptive.license_key=' . $newLicenseUrl;
                }
            }
            $newKodiprops[] = $kp;
        }
        
        // 2. Transform user-agent
        $newVlcopt = '#EXTVLCOPT:http-user-agent=' . $newUserAgent;
        
        // 3. Extract cookie value from #EXTHTTP
        $cookieValue = '';
        if (!empty($exthttp)) {
            // Extract cookie value from JSON format: {"cookie":"VALUE"}
            if (preg_match('/"cookie"\s*:\s*"([^"]+)"/', $exthttp, $cookieMatch)) {
                $cookieValue = $cookieMatch[1];
            }
        }
        
        // 4. Transform stream URL
        // Remove everything after .mpd (query string) and add ?||cookie=COOKIE_VALUE
        if (!empty($cookieValue)) {
            // Find .mpd and remove everything after it, then append new cookie format
            if (preg_match('/^(.*?\.mpd)/', $streamUrl, $urlMatch)) {
                $streamUrl = $urlMatch[1] . '?||cookie=' . $cookieValue;
            }
            // Also handle .m3u8 URLs
            elseif (preg_match('/^(.*?\.m3u8)/', $streamUrl, $urlMatch)) {
                $streamUrl = $urlMatch[1] . '?||cookie=' . $cookieValue;
            }
        } else {
            // If no cookie, still clean the URL
            if (preg_match('/^(.*?\.mpd)/', $streamUrl, $urlMatch)) {
                $streamUrl = $urlMatch[1];
            }
        }
        
        // 5. Build the output block
        $output .= $extinfLine . "\n";
        foreach ($newKodiprops as $kp) {
            $output .= $kp . "\n";
        }
        $output .= $newVlcopt . "\n";
        if (!empty($exthttp)) {
            $output .= $exthttp . "\n";
        }
        $output .= $streamUrl . "\n";
        $output .= "\n";
        
    } else {
        // Skip any other lines
        $i++;
    }
}

echo $output;
?>
