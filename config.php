<?php
// Set 1000 different proxies
$proxies = [];

for ($i = 1; $i <= 100; $i++) {
    $proxies[] = [
        'username' => "rtixxerh-$i",
        'password' => '72szql5eb4bh',
        'host' => 'p.webshare.io',
        'port' => '80',
        'type' => 'http'
    ];
}

$config = [
    'extensions' => [
        // '/extensions/ad',
        // '/extensions/ad+',
        // '/extensions/adyoutube',
        // '/extensions/similarweb',
    ],
    'quick' => false,
    'proxies' => $proxies,
    'websites' => [
        "https://wikipedia.org/",
        "https://naver.com/",
        "https://globo.com/",
        "https://qq.com/",
        "https://cnn.com/",
        "https://bbc.com/",
        "https://news.google.com/",
        "https://theguardian.com/",
        "https://infobae.com/",
        "https://indiatimes.com/",
        "https://foxnews.com/",
        "https://douyin.com/",
        "https://hindustantimes.com/",
        "https://sohu.com/",
        "https://news18.com/",
        "https://news.naver.com/",
        "https://kompas.com/",
        "https://people.com/",
        "https://ndtv.com/",
        "https://usatoday.com/",
        "https://forbes.com/",
        "https://indianexpress.com/",
        "https://tribunnews.com/",
        "https://detik.com/",
        "https://washingtonpost.com/",
        "https://cnbc.com/",
        "https://bbc.co.uk/",
        "https://dailymail.co.uk/",
        "https://vnexpress.net/",  
    ],
    'visitDuration' => 650,
    'visitTimeout' => 'everyday',
    'moveMouseRandomly' => false,
    'clickLinks' => false,
    'randomWebsitesCount' => false,
    'googleLogin' => true,
    'inAFewMinutes' => [
        'visitDuration' => 900, 
        'websites' => ['https://dmn-anal4.site/page.php?d=2&p=5'], 
        'minutes' => 0, 
        'moveMouseRandomly' => false,
        'clickLinks' => false,
    ],
    'maxProcesses' => 10,
    'profileSettings' => [
        "browser_type" => 'mimic',
        "os_type" => "windows",
        "parameters" => [
            "flags" => [
                "audio_masking" => "mask",
                "fonts_masking" => "mask",
                "geolocation_masking" => "mask",
                "geolocation_popup" => "prompt",
                "graphics_masking" => "mask",
                "graphics_noise" => "mask",
                "localization_masking" => "mask",
                "media_devices_masking" => "mask",
                "navigator_masking" => "mask",
                "ports_masking" => "mask",
                "proxy_masking" => "custom",
                "screen_masking" => "mask",
                "timezone_masking" => "mask",
                "webrtc_masking" => "mask"
            ],
            "storage" => [
                "is_local" => false,
                "save_service_worker" => false
            ]
        ]
    ],
    'creds' => [
        "email" => $_ENV['MLX_EMAIL'],
        "password" => $_ENV['MLX_PASSWORD']
    ],
    'workspaceName' => $_ENV['WORKSPACE_NAME'],
];

// Set correct paths for extensions
$config['extensions'] = array_map(function($extension) {
    return __DIR__ . $extension;
}, $config['extensions']);
?>