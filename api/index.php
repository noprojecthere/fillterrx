<?php
// Remote playlist URL
$remoteUrl = 'https://m3u-fetcher.vercel.app/api/airtel';

// BLOCK rules: jis block ke text ya URL me ye patterns mil jaayen, us block ko hide kar do.
$blockPatterns = [
    '/join@Billa_tv/i',                         // sample entry hide
    '#https?://cdn\.videas\.fr/.*#i',          // demo HLS URL hide
    // Aap aur patterns add kar sakte ho, e.g. group-title, tvg-logo domain, channel names:
    // '/group-title="Join"/i',
    // '/\bZee\s*TV\b/i',
];

// OPTIONAL allow-list: agar yahan patterns add karoge, to sirf yehi pass honge; empty rakho to sab allowed except blocked.
$allowOnlyPatterns = [
    // keep empty for now
];

// Fetch remote M3U
function fetch_m3u($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Vercel-PHP M3U Filter)',
        CURLOPT_HTTPHEADER => ['Accept: text/plain; charset=UTF-8'],
    ]);
    $body = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false || $code < 200 || $code >= 300) {
        http_response_code(502);
        header('Content-Type: text/plain; charset=UTF-8');
        echo "#EXTM3U\n# Error fetching origin: CODE=$code ERR=$err\n";
        exit;
    }
    return $body;
}

// Decide if a block should be dropped
function should_drop_block(array $blockLines, array $blockPatterns, array $allowOnlyPatterns): bool {
    $text = implode("\n", $blockLines);

    if (!empty($allowOnlyPatterns)) {
        $allowed = false;
        foreach ($allowOnlyPatterns as $pat) {
            if (preg_match($pat, $text)) { $allowed = true; break; }
        }
        if (!$allowed) return true;
    }

    foreach ($blockPatterns as $pat) {
        if (preg_match($pat, $text)) return true;
    }
    return false;
}

// Parse playlist into channel blocks and filter
function filter_playlist(string $raw, array $blockPatterns, array $allowOnlyPatterns): string {
    $lines = preg_split("/\r\n|\r|\n/", $raw);
    $out = [];
    $out[] = '#EXTM3U';

    $current = [];
    $seenHeader = false;

    foreach ($lines as $line) {
        $trim = trim($line);
        if ($trim === '') continue;

        // Normalize: skip origin header (we already added our own)
        if (!$seenHeader && stripos($trim, '#EXTM3U') === 0) {
            $seenHeader = true;
            continue;
        }

        // Collect lines in current block
        $current[] = $trim;

        // Non-comment line (URL) ends a block
        if ($trim[0] !== '#') {
            if (!should_drop_block($current, $blockPatterns, $allowOnlyPatterns)) {
                foreach ($current as $cl) { $out[] = $cl; }
            }
            $current = [];
        }
    }

    // Drop incomplete trailing block (metadata without URL)
    return implode("\n", $out) . "\n";
}

// Main
$raw = fetch_m3u($remoteUrl);

// Output as M3U8
header('Content-Type: application/vnd.apple.mpegurl; charset=UTF-8');
header('Cache-Control: no-store, must-revalidate');
header('Pragma: no-cache');

echo filter_playlist($raw, $blockPatterns, $allowOnlyPatterns);
