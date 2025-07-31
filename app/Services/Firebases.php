<?php

namespace App\Services;

use App\Models\User;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Laravel\Firebase\Facades\Firebase;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\Notification;
use Throwable;

class Firebases
{
    protected $messaging;
    protected $factory;
    protected $message;
    protected $notification;
    // protected
    public function __construct()
    {
        $this->factory = (new Factory)->withServiceAccount(base_path('masbro.json'));
        $this->messaging = $this->factory->createMessaging();
        $this->defaultValue();
    }

    public function withNotification(String $judul, String $body){
        $this->notification = Notification::create($judul, $body);
        return $this;
    }
    public function withData(Array $data){
        $this->message = $data;
        return $this;
    }
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

                $cloudMessage = CloudMessage::withTarget('token', $token)
                    // ->withNotification($this->notification)
                    ->withData($this->message);

                $this->messaging->send($cloudMessage);
            }

            return true;
        } catch (Throwable $th) {
            return false;
        }
    }
    public function defaultValue(){
        $this->notification = Notification::create("Selamat Datang Di Masbro Canteen", "Aplikasi Pemesanan Makanan di Kantin PENS Dengan Menerapkan Payment Gateway");
        $this->message = ["DATA" => "NO DATA"];
    }

    public function updateFcmToken(User $user, $token = ""){
        $updated = $user->update(['fcm_token' => $token]);
        return $updated;
    }
}
