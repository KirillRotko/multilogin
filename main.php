<?php
    // Load env variables
    require __DIR__ . '/vendor/autoload.php';

use Facebook\WebDriver\Exception\ElementClickInterceptedException;
use Facebook\WebDriver\Exception\MoveTargetOutOfBoundsException;
    use Facebook\WebDriver\Exception\StaleElementReferenceException;
    use Facebook\WebDriver\Exception\WebDriverException;
    use Facebook\WebDriver\Interactions\WebDriverActions;
    use Facebook\WebDriver\Remote\RemoteWebDriver;
    use Facebook\WebDriver\WebDriverBy;
    use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\WebDriverKeys;
use Symfony\Component\Dotenv\Dotenv;
    use Helpers\Mlx;

    $dotenv = new Dotenv();
    $dotenv->load(__DIR__.'/.env');

    // Load config
    require __DIR__ . '/config.php';
    ini_set('max_execution_time', -1);
    ini_set('memory_limit', -1);

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
        $inAFewMinutes = $config['inAFewMinutes'];

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
                updateProfiles($mlx, $automationToken, $config, $refreshToken, $workspaceId);

                return;
            }

            if($_SERVER['argv'][1] !== '--run') {
                createProfiles($mlx, $automationToken, $folderId, $proxies, $extensions);
            }

            if($_SERVER['argv'][1] === '--run' || !isset($_SERVER['argv'][1])) {
                $startTime = time();
                $targetTime = $startTime + ($inAFewMinutes['minutes'] * 60);
                $finished = false;

                while(!$finished) {
                    $newConfig = $config;

                    if($inAFewMinutes) {
                        $currentTime = time();
                
                        if ($currentTime >= $targetTime) {
                            echo "Special event \n";

                            $newConfig['visitDuration'] = $inAFewMinutes['visitDuration'];
                            $newConfig['websites'] = $inAFewMinutes['websites'];
                            $newConfig['moveMouseRandomly'] = $inAFewMinutes['moveMouseRandomly'];
                            $newConfig['clickLinks'] = $inAFewMinutes['clickLinks'];
                
                            $targetTime = $currentTime + ($visitTimeout * 60);

                            $finished = true;
                        }
                    }

                    runProfiles($mlx, $automationToken, $folderId, $newConfig, $quick, $refreshToken, $workspaceId);

                    if($finished) {
                        echo "Finished special event \n";

                        return;
                    } else {
                        echo "Next launch of profiles will be in $visitTimeout minutes\n";

                        $visitTimeoutInSeconds = $visitTimeout * 60;
    
                        sleep($visitTimeoutInSeconds);
                    }
                }
            }
        } catch(Exception $e) {
            echo $e->getMessage() . "\n";
        }
    }

    function automation(RemoteWebDriver $driver, Mlx $mlx, string $token, string $profileId, array $websites, int $profileNumber, bool $moveMouse = true, bool $googleLogin = false, $googleProfile = null, int|string $visitDuration = 5, bool $randomClicks = false) {
        try {
            if($googleLogin) {
              loginGoogle($driver, $googleProfile);
            }

            $tabs = $driver->getWindowHandles();

            foreach($websites as $website) {
                $driver->switchTo()->window($tabs[0]);

                echo "Navigating to: $website\n";

                $driver->get($website);

                $driver->switchTo()->window($tabs[0]);

                if($moveMouse) {
                  moveMouse($driver, $profileNumber);
                }

                if ($randomClicks) {
                    echo "Clicking on links randomly\n";
                
                    $maxRetry = rand(2, 5);
                    $retryCount = 0;
                    $isSuccess = rand(2, 5);
                    $start = 0;
                
                    while ($start < $isSuccess && $retryCount < $maxRetry) {
                        try {
                            $links = $driver->findElements(WebDriverBy::tagName('a'));
                     
                            $clickableLinks = array_filter($links, function ($link) {
                                return $link->isDisplayed() && $link->isEnabled();
                            });
                
                            if (!empty($clickableLinks)) {
                                $randomLink = $clickableLinks[array_rand($clickableLinks)];

                                $randomLink->click();

                                moveMouse($driver, $profileNumber);

                                $start += 1;

                                sleep(rand(5, 10));
                            }
                        } catch (StaleElementReferenceException $e) {
                            $retryCount++;
                            continue;
                        } catch (MoveTargetOutOfBoundsException $e) {
                            echo "Error with profile $profileNumber: move target out of bounds\n";
                            break; 
                        } catch (ElementClickInterceptedException $e) {
                            echo "Element click intercepted: Trying another link...\n";
                            $start += 1;
                            continue;
                        }
                    }
                
                    if (!$isSuccess) {
                        echo "Failed to perform mouse click after multiple attempts.\n";
                    }
                }

                $sleepTime = $visitDuration;

                if($visitDuration === 'random') {
                    $sleepTime = rand(120, 180);
                }

                echo "Sleeping for " . ($sleepTime) . " seconds...\n";
                sleep($sleepTime);
    
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

    function moveMouse($driver, $profileNumber) {
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

    function updateProfiles(Mlx $mlx, string $token, array $config, $refreshToken, $workspaceId) {
        $proxies = $config['proxies'];
        $extensions = $config['extensions'];
        $storage = $config['profileSettings']['parameters']['storage']['is_local'] ? 'local' : 'cloud';
        $profileName = "Profilee number";
        $creds = $config['creds'];
    
        foreach ($proxies as $index => $proxy) {
            $profileNumber = ++$index;
            $profileId = null;
    
            $retry = true;

            while ($retry) {
                try {
                    $profileId = $mlx->searchProfile($token, $profileName . " $profileNumber", $storage);
    
                    if ($profileId) {
                        $mlx->updateProfile($token, $profileId, $extensions, $proxy);
                        echo "Profile $profileNumber updated\n";

                        $retry = false; 
                    } else {
                        throw new Exception("Cant update a profile: a profile with name - " . $profileName . " $profileNumber not found");
                    }
                } catch (Exception $e) {
                    if ($e->getMessage() === 'unauthorized' || $e->getMessage() === 'Wrong JWT token') {
                        try {
                            $newWorkspaceToken = $mlx->refreshToken($token, $refreshToken, $creds['email'], $workspaceId);
                            $token = $mlx->getAutomationToken($newWorkspaceToken);

                            echo "Token updated\n";
                        } catch (Exception $tokenException) {
                            echo "Failed to get new token: " . $tokenException->getMessage() . "\n";
                            break; 
                        }
                    } else {
                        echo "Error updating profile $profileNumber: " . $e->getMessage() . "\n";
                        break; 
                    }
                }
            }
        }
    }

    function deleteProfiles(Mlx $mlx, string $token, array $config) {
        $storage = $config['profileSettings']['parameters']['storage']['is_local'] ? 'local' : 'cloud';
        $proxies = $config['proxies'];

        foreach ($proxies as $index => $proxy) {
            $profileNumber = ++$index;

            echo "Deleting profile number $profileNumber\n";

            $id = $mlx->searchProfile($token, "Profilee number $profileNumber", $storage);

            if(!$id) {
                echo "Profile number $profileNumber not found\n";

                break;
            }

            $mlx->deleteProfile($token, $id);

            echo "Profile number $profileNumber deleted\n";
        }
    }

    function runProfiles(Mlx $mlx, string $token, string $folderId, array $config, bool $quick, $refreshToken, $workspaceId) {
        $proxies = $config['proxies'];
        $storage = $config['profileSettings']['parameters']['storage']['is_local'] ? 'local' : 'cloud';
        $browserType = $config['profileSettings']['browser_type'];
        $profileName = "Profilee number";
        $websites = $config['websites'];
        $visitDuration = $config['visitDuration'];
        $moveMouse = $config['moveMouseRandomly'];
        $maxProcesses = $config['maxProcesses'];
        $extensions = $config['extensions'];
        $creds = $config['email'];
        $googleLogin = $config['googleLogin'];
        $clickLinks = $config['clickLinks'];
        $randomWebsitesCount = $config['randomWebsitesCount'];

        $googleProfiles = $googleLogin ? getGoogleProfilesFromFile('gmail_accounts.txt') : null;
    
        $mlx->unlockProfiles($token);
    
        $currentProcesses = 0;
        $pids = [];
    
        foreach ($proxies as $index => $proxy) {
            $profileNumber = ++$index;
            $profileId = !$quick ? $mlx->searchProfile($token, $profileName . " $profileNumber", $storage) : 'yes';
    
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
    
                        $headlessMode = 'false';
    
                        if ((int)$profileNumber % (int)$maxProcesses === 0) {
                            $headlessMode = $quick ? false : 'false';
                        } else {
                            $headlessMode = $quick ? true : 'true';
                        }
    
                        $profilePort = null;
    
                        if ($quick) {
                            $profilePort = $mlx->startQuickProfile($token, $headlessMode, $extensions, $proxy);
                        } else {
                            $profilePort = $mlx->startProfile($token, $profileId, $folderId, $headlessMode);
                        }
    
                        // sleep(20);
                        echo "Profile $profileNumber started\n";
    
                        if ($proxy['host']) {
                            $driver = $mlx->getProfileDriver($profilePort, $browserType);

                            $selectedWebsites = $websites;

                            if($randomWebsitesCount) {
                                $numberOfAvailableWebsites = count($websites);

                                if ($numberOfAvailableWebsites < 3) {
                                    $numberOfWebsites = $numberOfAvailableWebsites;
                                } else {
                                    $numberOfWebsites = rand(3, min(10, $numberOfAvailableWebsites));
                                }
                                $numberOfWebsites = 1;
                                $selectedWebsitesKeys = array_rand($websites, $numberOfWebsites);

                                if (!is_array($selectedWebsitesKeys)) {
                                    $selectedWebsitesKeys = [$selectedWebsitesKeys];
                                }
                
                                $selectedWebsites = [];

                                foreach ($selectedWebsitesKeys as $websiteKey) {
                                    $websiteUrl = $websites[$websiteKey];

                                    $selectedWebsites[] = $websiteUrl;
                                }
                            }

                            automation($driver, $mlx, $token, $profileId, $selectedWebsites, $profileNumber, $moveMouse, $googleLogin, $googleProfiles[(int) $profileNumber - 1], $visitDuration, $clickLinks);
                        } else {
                            $mlx->stopProfile($token, $profileId);
    
                            echo "Profile $profileNumber stopped\n";
                        }
                    } catch (Exception $e) {
                        if ($e->getMessage() === 'unauthorized' || $e->getMessage() === 'Wrong JWT token') {
                            try {
                                $newWorkspaceToken = $mlx->refreshToken($token, $refreshToken, $creds['email'], $workspaceId);
                                $token = $mlx->getAutomationToken($newWorkspaceToken);
    
                                echo "Token refreshed, retrying...\n";
                            } catch (Exception $tokenException) {
                                echo "Failed to get new token: " . $tokenException->getMessage() . "\n";
                                exit(1); 
                            }
                        } else {
                            echo "Error with profile $profileNumber: " . $e->getMessage() . "\n";
                            exit(1); 
                        }
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

    function loadProxiesFromFile($filePath) {
        $proxies = [];
        
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            list($host, $port, $username, $password) = explode(':', $line);
            
            $proxies[] = [
                'username' => $username,
                'password' => $password,
                'host' => $host,
                'port' => $port,
                'type' => 'http'
            ];
        }
        
        return $proxies;
    }

    function getGoogleProfilesFromFile($filePath) {
        $profiles = [];
        
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            list($email, $password, $verificationEmail) = explode(':', $line);
            
            $profiles[] = [
                'email' => $email,
                'password' => $password,
                'verificationEmail' => $verificationEmail,
            ];
        }
        
        return $profiles;
    }

    function generateRandomStringWithRandomLength($minLength = 5, $maxLength = 20) {
        $length = rand($minLength, $maxLength);
    
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
    
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
    
        return $randomString;
    }

    function loginGoogle($driver, $profile) {
        try {
            echo "Log in google... \n";

            $search = generateRandomStringWithRandomLength();
            $driver->get("https://www.google.com/search?q=$search");

            try {
                $driver->manage()->timeouts()->implicitlyWait(10); 
                $element = $driver->findElement(WebDriverBy::cssSelector("a[href*='https://accounts.google.com/SignOutOptions']"));

                if($element) {
                    echo "Logged in \n";
    
                    sleep(5);
    
                    return;
                }
            } catch(Exception $e) {
                echo 'Exception: ',  $e->getMessage(), "\n";
            }

            echo "Visiting log in page... \n";
            
            $signIn = $driver->findElement(WebDriverBy::cssSelector("a[href*='https://accounts.google.com/ServiceLogin']"));
            $signIn->click();

            $driver->wait()->until(
                WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector('input[type="email"]'))
            );

            echo "Filling a form... \n";
            echo "Filling an email... \n";

            // Input email
            $emailField = $driver->findElement(WebDriverBy::cssSelector('input[type="email"]'));
            $emailField->sendKeys($profile['email']);
            $emailField->sendKeys(WebDriverKeys::ENTER);
          
            // Wait for the password field to be present
            $driver->wait()->until(
                WebDriverExpectedCondition::elementToBeClickable(WebDriverBy::cssSelector('input[type="password"]'))
            );

            sleep(2);

            echo "Filling a password... \n";
    
            // Input password
            $passwordField = $driver->findElement(WebDriverBy::cssSelector('input[type="password"]'));
            $passwordField->sendKeys($profile['password']);
            $passwordField->sendKeys(WebDriverKeys::ENTER);

            sleep(2);

            $currentUrl = $driver->getCurrentURL();

            if ($currentUrl !== "https://www.google.com/search?q=$search") {  
                // Wait for the verification options to be present
                $driver->wait()->until(
                    WebDriverExpectedCondition::elementToBeClickable(WebDriverBy::cssSelector('ul li:nth-child(3)'))
                );
                
                echo "Selecting verification method... \n";
        
                // Select verification method
                $verificationOption = $driver->findElement(WebDriverBy::cssSelector('ul li:nth-child(3)'));
                $verificationOption->click();
        
                // Wait for the verification email field to be present
                $driver->wait()->until(
                    WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector('input[type="email"]'))
                );
        
                echo "Filling a verification email... \n";

                // Input verification email
                $verificationEmailField = $driver->findElement(WebDriverBy::cssSelector('input[type="email"]'));
                $verificationEmailField->sendKeys($profile['verificationEmail']);
                $verificationEmailField->sendKeys(WebDriverKeys::ENTER);
            }
   
            echo "Logged in \n";
    
            sleep(2);
        } catch (Exception $e) {
            echo 'Exception: ',  $e->getMessage(), "\n";
        }
    }
?> 