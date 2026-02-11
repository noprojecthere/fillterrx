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
        'url' => 'https://livetv-push.hotstar.com/dash/live/2002466/sshindiwv/master.mpd?||cookie=hdntl=exp=1768658389~acl=*sshindi*~id=bcf094aea9a3582d2656ffa3136e916f~data=hdntl~hmac=07b3719JZy4p5AaUyqbeXAc86Avu6oQJx4xc81a2eb289ced52026761b1b616c4|||http-origin=https://www.hotstar.com|||http-referer=https://www.hotstar.com/||http-user-agent=Hotstar;in.startv.hotstar/25.02.24.8.11169%20(Android/15)',
        'props' => [
            '#KODIPROP:inputstream.adaptive.license_type=clearkey',
            '#KODIPROP:inputstream.adaptive.license_key=https://vercel-php-clearkey-hex-base64-json.vercel.app/api/results.php?keyid=fe7718fbb3fb4ba78c07cc0f578744e6&key=624e24b1843b459fab0a949609416f0d'
        ]
    ],
    [
        'title' => 'English',
        'logo' => 'g',
        'url' => 'https://otte.live.fly.ww.aiv-cdn.net/iad-nitro/live/clients/dash/enc/jufvexhaqf/out/v1/b218966eb1c84d6fba6dc68f47154e3c/cenc.mpd?',
        'props' => [
            '#KODIPROP:inputstream.adaptive.license_type=clearkey',
            '#KODIPROP:inputstream.adaptive.license_key=https://vercel-php-clearkey-hex-base64-json.vercel.app/api/results.php?keyid=1d95f034e5b8b6794a447ef73f83e15d&key=af682ffc1e5e749a5fecbbc7d69ad942'
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
    
