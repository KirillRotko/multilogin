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
    
    public function signIn(array $creds): string {
        $url = "https://api.multilogin.com/user/signin";

        $request = Requests::post($url, $this->headers, json_encode($creds));

        if($request->status_code !== 200) {
            $message = json_decode($request->body)->status->message;

            throw new Exception($message);
        } else {
            $token = json_decode($request->body)->data->token;

            return $token;
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
                    "is_local" => false,
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
            $profileId= json_decode($response->body)->data->ids[0];

            return $profileId;
        }
    }
} 
?>
    