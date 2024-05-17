<?php
use Symfony\Component\Dotenv\Dotenv;

$dotenv = new Dotenv();
$dotenv->load(__DIR__.'/.env');

$creds = [
    "email" => $_ENV['MLX_EMAIL'],
    "password" => $_ENV['MLX_PASSWORD']
];

$extensions = [
    "/extensions/adblock",
    "/extensions/colorzilla"
];

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
?>