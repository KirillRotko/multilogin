<?php
    // Load env variables
    require __DIR__ . '/vendor/autoload.php';

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Symfony\Component\Dotenv\Dotenv;
    use Helpers\Mlx;

    $dotenv = new Dotenv();
    $dotenv->load(__DIR__.'/.env');

    // Load config
    require __DIR__ . '/config.php';

    // Run app
    $profileSettings = $config['profileSettings'];
        
    $mlx = new Mlx($profileSettings);

    fireApp($config, $mlx);

    // Main function
    function fireApp($config, $mlx) {
        $creds = $config['creds'];
        $proxies = $config['proxies'];
        $extensions = $config['extensions'];
        $workspaceName = $config['workspaceName'];

        try {
            // Sign in
            [$token, $refreshToken] = $mlx->signIn($creds);
        
            // Change workspace
            $workspaceId = $mlx->getWorkspaceId($token, $workspaceName);
      
            $newWorkspaceToken = $mlx->refreshToken($token, $refreshToken, $creds['email'], $workspaceId);
          
            // Get folder id
            $folderId = $mlx->getFolderId($newWorkspaceToken);
            ini_set('display_errors', 1);
            ini_set('display_startup_errors', 1);
            error_reporting(E_ALL);

            if($_SERVER['argv'][1] === '--update') {
                updateProfiles($mlx, $newWorkspaceToken, $config);

                return;
            }

            if($_SERVER['argv'][1] !== '--run') {
                createProfiles($mlx, $newWorkspaceToken, $folderId, $proxies, $extensions);
            }

            if($_SERVER['argv'][1] === '--run' || !isset($_SERVER['argv'][1])) {
                runProfiles($mlx, $newWorkspaceToken, $folderId, $config);

                return;
            }
        } catch(Exception $e) {
            echo $e->getMessage();
        }
    }

    function automation(RemoteWebDriver $driver, Mlx $mlx, string $token, string $profileId, array $websites, int $visitDuration = 5) {
        try {
            foreach($websites as $website) {
                echo "Navigating to: $website\n";

                $driver = $driver->get($website);
                
                echo "Sleeping for " . ($visitDuration) . " seconds...\n";
                sleep($visitDuration);
    
                echo "Visited: $website\n";
            }
        } catch(Exception $e) {
            throw new Exception($e->getMessage());
        } finally {
            $driver->quit();
            $mlx->stopProfile($token, $profileId);

            echo "Profile stopped";
        }
    }
    
    function createProfiles(Mlx $mlx, string $token, string $folderId, array $proxies, array $extensions) {
        foreach ($proxies as $index => $proxy) {
            $profileId = $mlx->createProfile($token, $proxy, $index + 1, $extensions, $folderId);
        
            break;
        }
    }

    function updateProfiles(Mlx $mlx, string $token, array $config) {
        $proxies = $config['proxies'];
        $extensions = $config['extensions'];
        $storage = $config['profileSettings']['parameters']['storage']['is_local'] ? 'local' : 'cloud';
        $profileName = "Profile number";

        foreach ($proxies as $index => $proxy) {
            $profileNumber = ++$index;

            $profileId = $mlx->searchProfile($token, $profileName . " $profileNumber", $storage);

            if($profileId) {
                try {
                    $mlx->updateProfile($token, $profileId, $extensions, $proxy);
                } catch(Exception $e) {
                    throw new Exception($e->getMessage());
                }
            } else {
                throw new Exception("Cant update a profile: a profile with name - " . $profileName . " $profileNumber" . " not found");
            }
      
            break;
        }
    }

    function runProfiles(Mlx $mlx, string $token, string $folderId, array $config) {
        $proxies = $config['proxies'];
        $storage = $config['profileSettings']['parameters']['storage']['is_local'] ? 'local' : 'cloud';
        $browserType = $config['profileSettings']['browser_type'];
        $profileName = "Profile number";
        $websites = $config['websites'];
        $visitDuration = $config['visitDuration'];

        foreach ($proxies as $index => $proxy) {
            $profileNumber = ++$index;

            $profileId = $mlx->searchProfile($token, $profileName . " $profileNumber", $storage);

            if($profileId) {
                try {
                    $profilePort = $mlx->startProfile($token, $profileId, $folderId);
                    $driver = $mlx->getProfileDriver($profilePort, $browserType);

                    automation($driver, $mlx, $token, $profileId, $websites, $visitDuration);
                } catch(Exception $e) {
                    throw new Exception($e->getMessage());
                }
            } else {
                throw new Exception("Cant start a profile: a profile with name - " . $profileName . " $profileNumber" . " not found");
            }
        }
    }
?> 