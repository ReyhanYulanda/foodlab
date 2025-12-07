<?php


namespace App\Services\Tenant\Actions;


use App\Repositories\TenantRepository;


class InteruptBusyAction
{
    protected $tenantRepo;


    public function __construct(TenantRepository $tenantRepo)
    {
        $this->tenantRepo = $tenantRepo;
    }


    public function execute($user)
    {
        $tenant = $this->tenantRepo->getByUser($user->id);
        if (!$tenant) {
            abort(404, 'Tenant tidak ditemukan untuk user ini');
        }


        // Business logic: flip isBusy flag or set to false
        $tenant->isBusy = false;
        $tenant->busy_until = now()->copy()->addMinutes(3);
        $tenant->save();


        return $tenant;
    }
}
