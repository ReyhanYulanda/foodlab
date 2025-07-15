<?php

namespace App\Http\Controllers;

use App\Services\FirebaseNotificationService;
use Google\Cloud\Storage\Notification;
use Illuminate\Http\Request;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Laravel\Firebase\Facades\Firebase;
use App\Services\Firebases;

class SendNotificationController extends Controller
{
    protected $firebases;

    public function __construct(Firebases $firebases)
    {
        $this->firebases = $firebases;
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, FirebaseNotificationService $firebases)
    {
        $tenantUser = (object) [
            'fcm_token' => 'token_di_sini'
        ];

        $firebases
            ->withNotification('Pesanan Masuk', 'Ada pesanan baru masuk di tenant kamu. Yuk, segera proses!')
            ->withData([
                'title' => 'Pesanan Masuk',
                'body' => 'Ada pesanan baru masuk di tenant kamu. Yuk, segera proses!'
            ])
            ->sendMessages($tenantUser->fcm_token);

        return response()->json(['status' => 'success']);
    }

    public function sendToUser(Request $request)
    {
        try {
            $request->validate([
                'fcm_token' => 'required|string',
            ]);

            $fcmToken = $request->fcm_token;

            $this->firebases
                ->withNotification('Pesanan Masuk', 'Ada Pesanan Masuk!')
                ->withData([
                    'title' => 'Pesanan Masuk',
                    'body' => 'Ada Pesanan Masuk!'
                ])->sendMessages([
                    $fcmToken
                ]);

            return response()->json(['message' => 'Notification sent successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }


    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
