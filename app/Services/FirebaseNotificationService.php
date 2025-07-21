<?php

namespace App\Services;

use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Messaging as FirebaseMessaging;

class FirebaseNotificationService
{
    protected $messaging;
    protected $notification = null;
    protected $message = [];

    public function __construct(FirebaseMessaging $messaging)
    {
        $this->messaging = $messaging;
    }

    /**
     * Set notification (title & body)
     */
    public function withNotification(string $title, string $body)
    {
        $this->notification = Notification::create($title, $body);
        return $this;
    }

    /**
     * Set data payload
     */
    public function withData(array $message)
    {
        $this->message = $message;
        return $this;
    }

    /**
     * Send notification & data to one or multiple tokens
     */
    public function sendMessages($tokens)
    {
        try {
            if (is_string($tokens)) {
                $tokens = [$tokens];
            }

            foreach ($tokens as $token) {
                if (empty($token)) {
                    continue;
                }

                $cloudMessage = CloudMessage::withTarget('token', $token);

                if (!empty($this->notification)) {
                    $cloudMessage = $cloudMessage->withNotification($this->notification);
                }

                if (!empty($this->message)) {
                    $cloudMessage = $cloudMessage->withData($this->message);
                }

                $this->messaging->send($cloudMessage);
            }

            return true;
        } catch (\Throwable $th) {
            // Log::error($th); // optional: log error
            return false;
        }
    }
}
