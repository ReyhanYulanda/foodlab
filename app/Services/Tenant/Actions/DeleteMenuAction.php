<?php


namespace App\Services\Tenant\Actions;


use App\Repositories\MenuRepository;
use Illuminate\Support\Facades\Gate;
use App\Repositories\TenantRepository;


class DeleteMenuAction
{
    protected $menuRepo;
    protected $tenantRepo;


    public function __construct(MenuRepository $menuRepo, TenantRepository $tenantRepo)
    {
        $this->menuRepo = $menuRepo;
        $this->tenantRepo = $tenantRepo;
    }


    public function execute($id)
    {
        $menu = $this->menuRepo->find($id);
        if (!$menu) {
            abort(404, 'Menu tidak ditemukan');
        }


        $tenant = $this->tenantRepo->getById($menu->tenant_id);


        if (!Gate::allows('delete-tenant-menu', [$menu, $tenant])) {
            abort(403, 'Anda Bukan Pemilik Tenant Ini');
        }


        $this->menuRepo->delete($id);


        return true;
    }
}
