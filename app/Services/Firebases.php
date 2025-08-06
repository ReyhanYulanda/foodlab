<?php

namespace App\Services;

use App\Models\FcmToken;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

class Firebases
{
    protected $messaging;
    protected $factory;
    protected $message;
    protected $notification;

    public function __construct()
    {
        $this->factory = (new Factory)->withServiceAccount(base_path('masbro.json'));
        $this->messaging = $this->factory->createMessaging();
        // $this->defaultValue();
    }

    public function withNotification(string $title, string $body)
    {
        $this->notification = Notification::create($title, $body);
        return $this;
    }

    public function withData(array $data)
    {
        $this->message = $data;
        return $this;
    }

    protected function defaultValue()
    {
        $this->notification = Notification::create(
            "Selamat Datang Di Masbro Canteen",
            "Aplikasi Pemesanan Makanan di Kantin PENS Dengan Menerapkan Payment Gateway"
        );

        $this->message = [
            "title" => "Default Title",
            "body" => "Default Body",
            "click_action" => "FLUTTER_NOTIFICATION_CLICK"
        ];
    }

    public function sendMessages($tokens, string $channelId = 'fcm_fallback_notification_channel')
    {
        try {
            if (is_string($tokens)) {
                $tokens = [$tokens];
            }

            foreach ($tokens as $token) {
                if (empty($token)) {
                    continue;
                }

                try {
                    $messageArray = [
                        'token' => $token,
                        'notification' => [
                            'title' => $this->notification->title(),
                            'body' => $this->notification->body(),
                        ],
                        'android' => [
                            'notification' => [
                                'sound' => 'default',
                                'channel_id' => $channelId,
                            ],
                            'priority' => 'high',
                        ],
                        'data' => $this->message,
                    ];

                    $cloudMessage = CloudMessage::fromArray($messageArray);
                    $this->messaging->send($cloudMessage);
                } catch (\Kreait\Firebase\Exception\Messaging\NotFound $e) {
                    // Token tidak valid → hapus dari database
                    FcmToken::where('fcm_token', $token)->delete();
                    Log::warning("FCM Token invalid and deleted: " . $token);
                } catch (Throwable $th) {
                    Log::error('FCM Send Error', [
                        'message' => $th->getMessage(),
                        'trace' => $th->getTraceAsString(),
                    ]);
                }
            }

            return true;
        } catch (Throwable $th) {
            Log::error('FCM Send Error', [
                'message' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);
            return false;
        }
    }

    public function updateFcmToken(User $user, string $token = '', string $device_id = null)
    {
        return \App\Models\FcmToken::updateOrCreate(
            ['user_id' => $user->id, 'fcm_token' => $token],
            ['device_id' => $device_id]
        );
    }

    public function sendToTenant($tokens)
    {
        return $this->sendMessages($tokens, 'tenant_channel');
    }

    public function sendToDriver($tokens)
    {
        return $this->sendMessages($tokens, 'driver_fdlb_channel');
    }

    public function sendToFallback($tokens)
    {
        return $this->sendMessages($tokens, 'fcm_fallback_notification_channel');
    }
    
}
