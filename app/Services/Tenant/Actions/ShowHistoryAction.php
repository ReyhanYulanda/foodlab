<?php


namespace App\Services\Tenant\Actions;


use App\Repositories\TransaksiRepository;
use App\Repositories\TenantRepository;
use App\DTO\HistoryFilterDTO;
use Illuminate\Http\Request;


class ShowHistoryAction
{
    protected $transaksiRepo;
    protected $tenantRepo;


    public function __construct(TransaksiRepository $transaksiRepo, TenantRepository $tenantRepo)
    {
        $this->transaksiRepo = $transaksiRepo;
        $this->tenantRepo = $tenantRepo;
    }


    public function execute(Request $request)
    {
        $user = $request->user();
        $tenant = $this->tenantRepo->getByUser($user->id);
        if (!$tenant) {
            abort(403, 'User tidak memiliki tenant terkait.');
        }


        $dto = new HistoryFilterDTO([
            'tenant_id' => $tenant->id,
            'filter_date' => $request->input('filter_date'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
        ]);


        return $this->transaksiRepo->getTenantHistory($dto);
    }
}
