<?php
    // Load env variables
    require __DIR__ . '/vendor/autoload.php';

    use Facebook\WebDriver\Exception\MoveTargetOutOfBoundsException;
    use Facebook\WebDriver\Exception\StaleElementReferenceException;
    use Facebook\WebDriver\Exception\WebDriverException;
    use Facebook\WebDriver\Interactions\WebDriverActions;
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
    error_reporting(E_ERROR | E_PARSE);
    
    $profileSettings = $config['profileSettings'];
        
    $mlx = new Mlx($profileSettings);

    fireApp($config, $mlx);

    // Main function
    function fireApp($config, $mlx) {
        $creds = $config['creds'];
        $proxies = $config['proxies'];
        $extensions = $config['extensions'];
        $workspaceName = $config['workspaceName'];
        $visitTimeout = $config['visitTimeout'];
        $creds['password'] = md5($creds['password']);
        $quick = $config['quick'];

        try {
            // Sign in
            [$token, $refreshToken] = $mlx->signIn($creds);
        
            // Change workspace
            $workspaceId = $mlx->getWorkspaceId($token, $workspaceName);
      
            $newWorkspaceToken = $mlx->refreshToken($token, $refreshToken, $creds['email'], $workspaceId);
            $automationToken = $mlx->getAutomationToken($newWorkspaceToken);
          
            // Get folder id
            $folderId = $mlx->getFolderId($automationToken);
            
            if($_SERVER['argv'][1] === '--delete') {
                deleteProfiles($mlx, $automationToken, $config);

                return;
            }

            if($_SERVER['argv'][1] === '--update') {
                updateProfiles($mlx, $automationToken, $config);

                return;
            }

            if($_SERVER['argv'][1] !== '--run') {
                createProfiles($mlx, $automationToken, $folderId, $proxies, $extensions);
            }

            if($_SERVER['argv'][1] === '--run' || !isset($_SERVER['argv'][1])) {
                while(true) {
                    runProfiles($mlx, $automationToken, $folderId, $config, $quick);

                    echo "Next launch of profiles will be in $visitTimeout minutes\n";

                    $visitTimeoutInSeconds = $visitTimeout * 60;

                    sleep($visitTimeoutInSeconds);
                }
            }
        } catch(Exception $e) {
            echo $e->getMessage() . "\n";
        }
    }

    function automation(RemoteWebDriver $driver, Mlx $mlx, string $token, string $profileId, array $websites, int $profileNumber, bool $moveMouse = true, int $visitDuration = 5) {
        try {
            $tabs = $driver->getWindowHandles();

            foreach($websites as $website) {
                $driver->switchTo()->window($tabs[0]);

                echo "Navigating to: $website\n";

                $driver->get($website);

                $driver->switchTo()->window($tabs[0]);

                if($moveMouse) {
                    echo "Moving mouse randomly\n";

                    $actions = new WebDriverActions($driver);
                
                    $maxRetry = 3;
                    $retryCount = 0;
                    $isSuccess = false;
                
                    while (!$isSuccess && $retryCount < $maxRetry) {
                        try {
                            $divs = $driver->findElements(WebDriverBy::tagName('div'));
                
                            $visibleDivs = array_filter($divs, function ($div) {
                                return $div->isDisplayed();
                            });
                
                            if (!empty($visibleDivs)) {
                                $randomIndices = array_rand($visibleDivs, rand(1, min(5, count($visibleDivs))));
                
                                foreach ((array)$randomIndices as $index) {
                                    $randomDiv = $visibleDivs[$index];
                                    $actions->moveToElement($randomDiv)->perform();
                                    usleep(500000);
                                }
                
                                $isSuccess = true;
                            }
                        } catch (StaleElementReferenceException $e) {
                            $retryCount++;

                            continue;
                        } catch (MoveTargetOutOfBoundsException $e) {
                            echo "Error with profile $profileNumber: move target out of bounds\n";
                            break; 
                        }
                    }
                
                    if (!$isSuccess) {
                        echo "Failed to perform mouse movements after multiple attempts.\n";
                    }
                }

                echo "Sleeping for " . ($visitDuration) . " seconds...\n";
                sleep($visitDuration);
    
                echo "Visited: $website\n";
            }
        } catch(Exception $e) {
            echo "Exception in automation with profile $profileNumber: " . $e->getMessage() . "\n";
        } finally {
            try {
                $tabs = $driver->getWindowHandles();

                foreach ($tabs as $tab) {
                    $driver->switchTo()->window($tab);
                    $driver->close();
                }
            } catch (WebDriverException $e) {
                echo "Exception caught while closing tabs in finally block: " . $e->getMessage() . "\n";
            }
            
            $driver->quit();
            $mlx->stopProfile($token, $profileId);

            echo "Profile $profileNumber stopped\n";
        }
    }
    
    function createProfiles(Mlx $mlx, string $token, string $folderId, array $proxies, array $extensions) {
        foreach ($proxies as $index => $proxy) {
            try {
                $mlx->createProfile($token, $proxy, $index + 1, $extensions, $folderId);
                $profileNumber = $index + 1;
    
                echo "Profile $profileNumber created\n";
            } catch(Exception $e) {
                throw new Exception($e->getMessage());
            }
        }
    }

    function updateProfiles(Mlx $mlx, string $token, array $config) {
        $proxies = $config['proxies'];
        $extensions = $config['extensions'];
        $storage = $config['profileSettings']['parameters']['storage']['is_local'] ? 'local' : 'cloud';
        $profileName = "Profile number";

        foreach ($proxies as $index => $proxy) {
            $profileNumber = ++$index;
            $profileId = null;

            try {
                $profileId = $mlx->searchProfile($token, $profileName . " $profileNumber", $storage);

                if($profileId) {
                    $mlx->updateProfile($token, $profileId, $extensions, $proxy);

                    echo "Profile $profileNumber updated\n";
                } else {
                    throw new Exception("Cant update a profile: a profile with name - " . $profileName . " $profileNumber" . " not found");
                }
            } catch(Exception $e) {
                throw new Exception($e->getMessage());
            }
        } 
    }

    function deleteProfiles(Mlx $mlx, string $token, array $config) {
        $storage = $config['profileSettings']['parameters']['storage']['is_local'] ? 'local' : 'cloud';
        $proxies = $config['proxies'];

        foreach ($proxies as $index => $proxy) {
            $profileNumber = ++$index;

            echo "Deleting profile number $profileNumber\n";

            $id = $mlx->searchProfile($token, "Profile number $profileNumber", $storage);

            if(!$id) {
                echo "Profile number $profileNumber not found\n";

                break;
            }

            $mlx->deleteProfile($token, $id);

            echo "Profile number $profileNumber deleted\n";
        }
    }

    function runProfiles(Mlx $mlx, string $token, string $folderId, array $config, bool $quick) {
        $proxies = $config['proxies'];
        $storage = $config['profileSettings']['parameters']['storage']['is_local'] ? 'local' : 'cloud';
        $browserType = $config['profileSettings']['browser_type'];
        $profileName = "Profile number";
        $websites = $config['websites'];
        $visitDuration = $config['visitDuration'];
        $moveMouse = $config['moveMouseRandomly'];
        $maxProcesses = $config['maxProcesses'];
        $extensions = $config['extensions'];

        $currentProcesses = 0;
        $pids = [];

        foreach ($proxies as $index => $proxy) {
            $profileNumber = ++$index;
            $profileId = $mlx->searchProfile($token, $profileName . " $profileNumber", $storage);

            if ($profileId) {
                while ($currentProcesses >= $maxProcesses) {
                    $pid = pcntl_wait($status);
                    if ($pid > 0) {
                        $currentProcesses--;
                    }
                }

                $pid = pcntl_fork();

                if ($pid == -1) {
                    throw new Exception("Could not fork process");
                } elseif ($pid) {
                    $currentProcesses++;
                    $pids[] = $pid;
                } else {
                    try {
                        echo "Starting profile $profileNumber\n";

                        $profilePort = null;

                        if($quick) {
                            $profilePort = $mlx->startQuickProfile($token, false, $extensions, $proxy);
                        } else {
                            $profilePort = $mlx->startProfile($token, $profileId, $folderId);
                        }

                        echo "Profile $profileNumber started\n";

                        $driver = $mlx->getProfileDriver($profilePort, $browserType);

                        automation($driver, $mlx, $token, $profileId, $websites, $profileNumber, $moveMouse, $visitDuration);
                    } catch(Exception $e) {
                        echo "Error with profile $profileNumber: " . $e->getMessage() . "\n";
                    }
                    exit(0); 
                }
            } else {
                echo "Cant start a profile: a profile with name - " . $profileName . " $profileNumber" . " not found\n";
            }
        }

        while ($currentProcesses > 0) {
            $pid = pcntl_wait($status);
            if ($pid > 0) {
                $currentProcesses--;
            }
        }
    }
?> 