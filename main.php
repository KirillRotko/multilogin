<?php
    require __DIR__ . '/vendor/autoload.php';
    require __DIR__ . '/config.php';

    use Symfony\Component\Dotenv\Dotenv;
    use Helpers\Mlx;

    $dotenv = new Dotenv();
    $dotenv->load(__DIR__.'/.env');

    $creds = [
        "email" => $_ENV['MLX_EMAIL'],
        "password" => $_ENV['MLX_PASSWORD']
    ];

    $mlx = new Mlx();

    try {
        [$token, $refreshToken] = $mlx->signIn($creds);

        $workspaceName = $_ENV['WORKSPACE_NAME'];
        $workspaceId = $mlx->getWorkspaceId($token, $workspaceName);
  
        $newWorkspaceToken = $mlx->refreshToken($token, $refreshToken, $creds['email'], $workspaceId);
      
        $folderId = $mlx->getFolderId($newWorkspaceToken);

        foreach ($proxies as $index => $proxy) {
            $profileId = $mlx->createProfile($newWorkspaceToken, $proxy, $index + 1, $extensions, $folderId);
        }
       
    } catch(Exception $e) {
        echo $e->getMessage();
    }
?>