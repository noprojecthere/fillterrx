<?php
header('Content-Type: application/x-mpegURL');
header('Cache-Control: s-maxage=1800, stale-while-revalidate=300');


function fetchExternalM3U($url) {
    $options = [
        'http' => [
            'method' => 'GET',
            'timeout' => 10,
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ]
    ];
    
    $context = stream_context_create($options);
    $data = @file_get_contents($url, false, $context);
    
    return $data !== false ? $data : '';
}


$liveEvents = [
    [
        'title' => '@streamstartv',
        'tvg_id' => '1998',
        'logo' => 'https://blogger.googleusercontent.com/img/b/R29vZ2xl/AVvXsEiamoffBdXQP0r6SHT9kM1ravyBjCVbUrncneORa9h4STgb_d8iEmMyKWn5hbzNnShrdNQYmCMDmbr3xFittRirO_zNiW4ic1FpEwxoVKwxSleDLlTgx9tHmKmKWRwqIyHYWgaUohCyIYKF6TMAutBebcryI8jVyoU4YmeKLPj4dU1gvxmenQ9Lg7MpyOfK/s1280/20250321_130159.png',
        'url' => 'https://fansspot.fun/promo.mp4',
        'props' => []
    ],
    [
        'title' => 'HINDI',
        'logo' => 'g',
        'url' => 'https://jcevents.hotstar.com/bpk-tv/f0e3e64ae415771d8e460317ce97aa5e/Fallback/index.m3u8?||cookie=hdnea=exp=1771226699~acl=%2f*~id=42423f41220471e1d21c1e8b565c4e9c~data=hdntl~hmac=c1c47eb6dc2d91d81b7ffd2dfaaec0a9a6175341dc3fd307c94098f655c003b4|||http-origin=https://www.hotstar.com|||http-referer=https://www.hotstar.com/||http-user-agent=Hotstar;in.startv.hotstar/25.02.24.8.11169%20(Android/15)',
        'props' => [
            '#KODIPROP:inputstream.adaptive.license_type=clearkey',
            '#KODIPROP:inputstream.adaptive.license_key=https://aqfadtv.xyz/clearkey/results.php?keyid=fe7718fbb3fb4ba78c07cc0f578744e6&key=624e24b1843b459fab0a949609416f0d'
        ]
    ],
    [
        'title' => 'PRIME HINDI',
        'logo' => 'g',
        'url' => 'https://ta.bia-cf.live.pv-cdn.net/syd-nitro/live/clients/dash/enc/utvv87801b/out/v1/d9a29796c1c3468785dbee1776ffd354/cenc.mpd?',
        'props' => [
            '#KODIPROP:inputstream.adaptive.license_type=clearkey',
            '#KODIPROP:inputstream.adaptive.license_key=https://vercel-php-clearkey-hex-base64-json.vercel.app/api/results.php?keyid=531zGng3g8GY8382QcW8kmWNZtLyFH1WHb7&key=f5a77d391847790f46059cbb092d575b'
        ]
    ],
    [
        'title' => 'PRIME ENGLISH',
        'logo' => 'g',
        'url' => 'https://ta.bia-cf.live.pv-cdn.net/bom-nitro/live/clients/dash/enc/1pasle3psa/out/v1/d25c047441ed467595c8085109e0d73f/cenc.mpd?',
        'props' => [
            '#KODIPROP:inputstream.adaptive.license_type=clearkey',
            '#KODIPROP:inputstream.adaptive.license_key=https://vercel-php-clearkey-hex-base64-json.vercel.app/api/results.php?keyid=e2135pbYhaAC4uzcTSn9C16bqeZDG6X3xuG3&key=2671d5rMERSxaN5uLj1onK6S5LqbHGzihEA6'
        ]
    ],
    [
        'title' => 'WILLOW',
        'logo' => 'g',
        'url' => 'https://otte.live.fly.ww.aiv-cdn.net/syd-nitro/live/dash/enc/utvv87801b/out/v1/d9a29796c1c3468785dbee1776ffd354/cenc.mpd?',
        'props' => [
            '#KODIPROP:inputstream.adaptive.license_type=clearkey',
            '#KODIPROP:inputstream.adaptive.license_key=https://vercel-php-clearkey-hex-base64-json.vercel.app/api/results.php?keyid=531zGng3g8GY8382QcW8kmWNZtLyFH1WHb7&key=f5a77d391847790f46059cbb092d575b'
        ]
    ],
    [
        'title' => 'English',
        'logo' => 'g',
        'url' => 'https://livetv-push.hotstar.com/dash/live/2002465/sshd2livetvwv/master.mpd?||cookie=hdntl=exp=1768658307~acl=*sshd1livetv*~id=be0003805b17e9ab1b346bc65ce52c50~data=hdntl~hmac=e5ab2cce5158f895db33cf31fdf85b2f9eba6e3e1e55931b104759277dacf50|||http-origin=https://www.hotstar.com|||http-referer=https://www.hotstar.com/||http-user-agent=Hotstar;in.startv.hotstar/25.02.24.8.11169%20(Android/15)',
        'props' => [
            '#KODIPROP:inputstream.adaptive.license_type=clearkey',
            '#KODIPROP:inputstream.adaptive.license_key=https://vercel-php-clearkey-hex-base64-json.vercel.app/api/results.php?keyid=fe7718fbb3fb4ba78c07cc0f578744e6&key=624e24b1843b459fab0a949609416f0d'
        ]
    ]
];

 



PRIME HINDI 
|drmScheme=clearkey&drmLicense=:


echo "#EXTM3U\n\n";


foreach ($liveEvents as $event) {
    // Output KODIPROP lines if present
    if (!empty($event['props'])) {
        foreach ($event['props'] as $prop) {
            echo $prop . "\n";
        }
    }
    
    
    $extinf = "#EXTINF:-1";
    if (!empty($event['tvg_id'])) $extinf .= ' tvg-id="' . $event['tvg_id'] . '"';
    if (!empty($event['logo'])) $extinf .= ' tvg-logo="' . $event['logo'] . '"';
    if (!empty($event['group'])) $extinf .= ' group-title="' . $event['group'] . '"';
    $extinf .= ', ' . $event['title'];
    
    echo $extinf . "\n";
    echo $event['url'] . "\n\n";
}


$externalData = fetchExternalM3U('https://modsdone.com/Billatv/Crichd.php');

if (!empty($externalData)) {
    // Remove duplicate #EXTM3U header from external data
    $externalData = preg_replace('/^#EXTM3U\s*/i', '', trim($externalData));
    echo $externalData;
}
?>
    
