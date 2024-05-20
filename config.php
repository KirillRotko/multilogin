<?php
// Set 1000 different proxies
$proxies = [];

for ($i = 1; $i <= 1000; $i++) {
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
        "/extensions/adblock",
        "/extensions/colorzilla",
        "/extensions/pixel-perfect"
    ],
    'proxies' => $proxies,
    'websites' => [
        "https://wikipedia.org/",
        "https://multilogin.com/",
        "https://dell.com/",
        "https://reddit.com/",
        "https://youtube.com/",
        "https://www.twitch.tv",
        "https://discord.com",
        "https://www.amazon.com",
        "https://hyperx.com",
        "https://secretlab.co",
        "https://store.steampowered.com"
    ],
    'visitDuration' => 3,
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
                "is_local" => true,
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