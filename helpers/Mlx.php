<?php
namespace Helpers;

use Exception;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverTimeouts;
use WpOrg\Requests\Requests;

class Mlx {
    public $headers;
    public $url;
    public $profileSettings;
    public $launcherUrl;

    public function __construct($profileSettings)
    {
        $this->headers = [
            "Content-Type" => "application/json",
            "Accept" => "application/json"
        ];

        $this->url = 'https://api.multilogin.com';
        $this->launcherUrl = 'https://launcher.mlx.yt:45001/api';

        $this->profileSettings = $profileSettings;
    }
    
    public function signIn(array $creds): array {
        $url = $this->url . "/user/signin";

        $request = Requests::post($url, $this->headers, json_encode($creds));

        if($request->status_code !== 200) {
            $message = json_decode($request->body)->status->message;

            throw new Exception($message);
        } else {
            $data = json_decode($request->body)->data;

            $token = $data->token;
            $refreshToken = $data->refresh_token;

            return [$token, $refreshToken];
        }
    }
    
    public function getWorkspaceId($token, $workspaceName) {
        $url = $this->url . "/user/workspaces";

        $this->headers["Authorization"] = "Bearer $token";

        $request = Requests::get($url, $this->headers);

        if($request->status_code !== 200) {
            $message = json_decode($request->body)->status->message;

            throw new Exception($message);
        } else {
            $workspaces = json_decode($request->body)->data->workspaces;
      
            $workspaceId = array_filter($workspaces, function($workspace) use ($workspaceName) {
                return $workspace->name === $workspaceName;
            });
            $workspaceId = $workspaceId[0]->workspace_id;

            return $workspaceId;
        }
    }

    public function refreshToken($token, $refreshToken, $email, $workspaceId) {
        $url = $this->url . "/user/refresh_token";

        $this->headers["Authorization"] = "Bearer $token";

        $body = [
            'email' => $email,
            'workspace_id' => $workspaceId,
            'refresh_token' => $refreshToken
        ];

        $request = Requests::post($url, $this->headers, json_encode($body));

        if($request->status_code !== 200) {
            $message = json_decode($request->body)->status->message;

            throw new Exception($message);
        } else {
            $token = json_decode($request->body)->data->token;
  
            return $token;
        }
    }

    public function getAutomationToken($token) {
        $url = $this->url . "/workspace/automation_token?expiration_period=no_exp";

        $this->headers["Authorization"] = "Bearer $token";

        $request = Requests::get($url, $this->headers);

        if($request->status_code !== 200) {
            $message = json_decode($request->body)->status->message;

            throw new Exception($message);
        } else {
            $newToken = json_decode($request->body)->data->token;

            return $newToken;
        }
    }

    public function getFolderId($token) {
        $url = $this->url . "/workspace/folders";

        $this->headers["Authorization"] = "Bearer $token";

        $request = Requests::get($url, $this->headers);

        if($request->status_code !== 200) {
            $message = json_decode($request->body)->status->message;

            throw new Exception($message);
        } else {
            $folderId = json_decode($request->body)->data->folders[0]->folder_id;
        
            return $folderId;
        }
    }

    public function createProfile(string $token, array $proxy, int $id, array $extensions, string $folderId): string {
        $profileSettings = $this->profileSettings;

        $profileSettings['folder_id'] = $folderId;
        $profileSettings['name'] = "Profilee number $id";
         
        if($proxy['host']) {
            $profileSettings['parameters']['proxy'] = [
                "host" => $proxy["host"],
                "type" => $proxy["type"],
                "port" => (int) $proxy["port"],
                "username" => $proxy["username"],
                "password" => $proxy["password"]
            ];
        }
        $profileSettings['parameters']['fingerprint'] = [
            "cmd_params" => [
                "params" => [
                    [
                        "flag" => "load-extension",
                        "value" => implode(",", $extensions)
                    ]
                ]
            ]
        ];

        $this->headers["Authorization"] = "Bearer $token";

        $url = $this->url . "/profile/create";

        $response = Requests::post($url, $this->headers, json_encode($profileSettings));
        
        if($response->status_code !== 201) {
            $message = json_decode($response->body)->status->message;

            throw new Exception($message);
        } else {
            $profileId = json_decode($response->body)->data->ids[0];
        
            return $profileId;
        }
    }

    public function updateProfile(string $token, string $profileId, array|null $extensions, array|null $proxy = null) {
        $profileSettings['profile_id'] = $profileId;

        if($proxy) {
            $profileSettings['parameters']['flags']['proxy_masking'] = 'custom';
            $profileSettings['proxy'] = [
                "host" => $proxy["host"],
                "type" => $proxy["type"],
                "port" => (int) $proxy["port"],
                "username" => $proxy["username"],
                "password" => $proxy["password"]
            ];
        }

        if($extensions) {
            $profileSettings['parameters']['fingerprint'] = [
                "cmd_params" => [
                    "params" => [
                        [
                            "flag" => "load-extension",
                            "value" => implode(",", $extensions)
                        ]
                    ]
                ]
            ];
        }

        $this->headers["Authorization"] = "Bearer $token";

        $url = $this->url . "/profile/partial_update";

        $response = Requests::post($url, $this->headers, json_encode($profileSettings));
        
        if($response->status_code !== 200) {
            $message = json_decode($response->body)->status->message;

            throw new Exception($message);
        } else {
            return $profileId;
        }
    }

    public function deleteProfile(string $token, string $profileId, bool $permanently = true) {
        $body = [
            "ids" => [
                $profileId
            ],
            "permanently" => $permanently
        ];
       
        $this->headers["Authorization"] = "Bearer $token";

        $url = $this->url . "/profile/remove";

        $response = Requests::post($url, $this->headers, json_encode($body));
        
        if($response->status_code !== 200) {
            $message = json_decode($response->body)->status->message;

            throw new Exception($message);
        } else {
            return $profileId;
        }
    }

    public function searchProfile(string $token, string $profileName, string $storage, bool $all = false) {
        $body = [
            'is_removed' => false,
            'limit' => 1,
            'offset' => 0,
            'search_text' => $profileName,
            'storage_type' => $storage
        ];
       
        $this->headers["Authorization"] = "Bearer $token";

        $url = $this->url . "/profile/search";

        $response = Requests::post($url, $this->headers, json_encode($body));

        if($response->status_code !== 200) {
            $message = json_decode($response->body)->status->message;

            throw new Exception($message);
        } else {
            if($all) {
                $profiles = json_decode($response->body)->data->profiles;

                return $profiles;
            } else {
                $profileId = json_decode($response->body)->data->profiles[0]->id;
           
                return $profileId;
            }
        }
    }

    public function startProfile(string $token, string $profileId, string $folderId, string $headlessMode) {
        $this->headers["Authorization"] = "Bearer $token";

        $url = $this->launcherUrl . "/v2/profile/f/$folderId/p/$profileId/start?automation_type=selenium&headless_mode=$headlessMode";

        $response = Requests::get($url, $this->headers);

        if($response->status_code !== 200) {
            $message = json_decode($response->body)->status->message;

            throw new Exception($message);
        } else {
            $profilePort = json_decode($response->body)->data->port;
      
            return $profilePort;
        }
    }

    public function startQuickProfile(string $token, bool $isHeadless, array|null $extensions, array|null $proxy = null) {
        $profileSettings = $this->profileSettings;
    
        $profileSettings['automation'] = 'selenium';
        $profileSettings['is_headless'] = $isHeadless;
    
        if ($proxy) {
            $profileSettings['proxy'] = [
                "host" => $proxy["host"],
                "type" => $proxy["type"],
                "port" => (int) $proxy["port"],
                "username" => $proxy["username"],
                "password" => $proxy["password"]
            ];
        }
    
        if(count($extensions)) {
            $profileSettings['parameters']['fingerprint'] = [
                "cmd_params" => [
                    "params" => [
                        [
                            "flag" => "load-extension",
                            "value" => implode(",", $extensions)
                        ]
                    ]
                ]
            ];
        }
    
        $this->headers["Authorization"] = "Bearer $token";
    
        $url = $this->launcherUrl . "/v1/profile/quick";
    
        $jsonBody = json_encode($profileSettings);
    
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('JSON encode error: ' . json_last_error_msg());
        }
    
        $response = Requests::post($url, $this->headers, $jsonBody);

        if($response->status_code !== 200) {
            $message = json_decode($response->body)->status->message;
    
            throw new Exception($message);
        } else {
            $profilePort = json_decode($response->body)->status->message;
    
            return $profilePort;
        }
    }

    public function stopProfile(string $token, string $profileId) {
        $this->headers["Authorization"] = "Bearer $token";

        $url = $this->launcherUrl . "/v1/profile/stop/p/$profileId";

        $response = Requests::get($url, $this->headers);

        if($response->status_code !== 200) {
            $message = json_decode($response->body)->status->message;
          
            throw new Exception($message);
        } else {
            return $profileId;
        }
    }

    public function unlockProfiles($token) {
        $this->headers["Authorization"] = "Bearer $token";

        $url = $this->url . "/bpds/profile/unlock_profiles";

        $response = Requests::get($url, $this->headers);

        if($response->status_code !== 200) {
            $message = json_decode($response->body)->status->message;
          
            throw new Exception($message);
        } else {
            return 200;
        }
    }

    public function getProfileDriver(string $profilePort, string $browserType = 'mimic'): RemoteWebDriver {     
        $url = "http://127.0.0.1:$profilePort";

        $driver = null;

        if($browserType === 'mimic') {
            $capabilities = DesiredCapabilities::chrome();
            $capabilities->setCapability('pageLoadStrategy', 'none');

            $driver = RemoteWebDriver::create($url, $capabilities);
        } else {
            $capabilities = DesiredCapabilities::firefox();
            $capabilities->setCapability('pageLoadStrategy', 'none');

            $driver = RemoteWebDriver::create($url, $capabilities);
        }

        return $driver;
    }
} 
?>
    