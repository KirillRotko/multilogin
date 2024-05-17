<?php
    require __DIR__ . '/vendor/autoload.php';
    require __DIR__ . '/config.php';

    use Helpers\Mlx;

    $mlx = new Mlx();

    try {
        $token = $mlx->signIn($creds);

        foreach ($proxies as $index => $proxie) {
            $profileId = $mlx->createProfile($token, $proxie, $index + 1, $extensions, $_ENV['FOLDER_ID']);
        }
       
    } catch(Exception $e) {
        echo $e->getMessage();
    }
?>