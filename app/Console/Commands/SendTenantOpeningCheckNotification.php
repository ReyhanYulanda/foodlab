<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\FcmToken;
use App\Services\Firebases;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SendTenantOpeningCheckNotification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notification:check-tenant-opening';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send notification to tenants to check if they are open via Firebase.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(Firebases $firebases)
    {
        // Get all FCM tokens for users with role 'tenant'
        $tenantTokens = FcmToken::whereHas('user', function ($query) {
            $query->role('tenant');
        })
            ->pluck('fcm_token')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        if (empty($tenantTokens)) {
            $this->info('No tenant tokens found.');
            return 0;
        }

        // Send notification
        $firebases
            ->withNotification(
                'Status Toko',
                'Apakah tenant hari ini buka?'
            )
            ->withData([
                'title' => 'Status Toko',
                'body' => 'Apakah tenant hari ini buka?',
                'type' => 'tenant_opening_check',
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            ])
            ->sendToTenant($tenantTokens);

        $this->info('Tenant opening check notification sent successfully to ' . count($tenantTokens) . ' tokens.');
        Log::info('Tenant opening check notification sent at ' . Carbon::now('Asia/Jakarta')->toDateTimeString() . ' to ' . count($tenantTokens) . ' tokens.');

        return 0;
    }
}
