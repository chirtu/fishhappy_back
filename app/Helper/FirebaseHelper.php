<?php

namespace App\Helper;

use Google\Auth\ApplicationDefaultCredentials;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

trait FirebaseHelper
{
    protected function pushNotification(array $devices, array $data): void
    {
        $project = config('services.firebase.project_id');
        $devices = array_unique(array_filter($devices));
        if (! $project || $devices === []) {
            return;
        }

        defer(static function () use ($project, $devices, $data) {
            try {
                $credentials = ApplicationDefaultCredentials::getCredentials('https://www.googleapis.com/auth/firebase.messaging');
                $token = $credentials->fetchAuthToken()['access_token'];
                $url = 'https://fcm.googleapis.com/v1/projects/'.rawurlencode($project).'/messages:send';
                foreach ($devices as $device) {
                    $response = Http::withToken($token)->timeout(8)->post($url, [
                        'message' => ['token' => $device, 'data' => array_map('strval', $data)],
                    ]);
                    if ($response->failed()) {
                        Log::warning('Firebase rejected a Fish Happy notification.', ['status' => $response->status()]);
                    }
                }
            } catch (Throwable $exception) {
                Log::warning('Fish Happy push delivery failed.', ['type' => $exception::class]);
            }
        });
    }
}
