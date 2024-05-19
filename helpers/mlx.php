<?php
namespace Helpers;

use Exception;
use WpOrg\Requests\Requests;

class Mlx {
    public $headers;

    public function __construct()
    {
        $this->headers = [
            "Content-Type" => "application/json",
            "Accept" => "application/json"
        ];
    }
    
    public function signIn(array $creds): array {
        $url = "https://api.multilogin.com/user/signin";

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
        $url = "https://api.multilogin.com/user/workspaces";

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
        $url = "https://api.multilogin.com/user/refresh_token";

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

    public function getFolderId($token) {
        $url = "https://api.multilogin.com/workspace/folders";

        $this->headers["Authorization"] = "Bearer $token";

        $request = Requests::get($url, $this->headers);

        if($request->status_code !== 200) {
            $message = json_decode($request->body)->status->message;

            throw new Exception($message);
        } else {
            $folderId = json_decode($request->body)->data->folders[0]->folder_id;
            var_dump(json_decode($request->body)->data->folders);
            return $folderId;
        }
    }

    public function createProfile(string $token, array $proxy, int $id, array $extensions, string $folderId, string $browserType = "mimic"): int {
        $profileSettings =  [
            "browser_type" => $browserType,
            "folder_id" => $folderId,
            "name" => "Profile number $id",
            "os_type" => "windows",
            "proxy" => [
                "host" => $proxy["host"],
                "type" => $proxy["type"],
                "port" => $proxy["port"],
                "username" => $proxy["username"],
                "password" => $proxy["password"]
            ],
            "parameters" => [
                "fingerprint" => [
                    "cmd_params" => [
                        "params" => [
                            [
                                "flag" => "load-extension",
                                "value" => implode(",", $extensions)
                            ]
                        ]
                    ]
                ],
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
        ];

        $this->headers["Authorization"] = "Bearer $token";

        $url = "https://api.multilogin.com/profile/create";

        $response = Requests::post($url, $this->headers, json_encode($profileSettings));
        
        if($response->status_code !== 201) {
            $message = json_decode($response->body)->status->message;

            throw new Exception($message);
        } else {
            $profileId = json_decode($response->body)->data->ids[0];
        
            return $profileId;
        }
    }
} 
?>
    