<?php


namespace App\Services\Tenant\Actions;


use App\Repositories\MenuRepository;
use App\Repositories\TenantRepository;
use App\Services\Tenant\Helpers\ImageUploader;
use Illuminate\Support\Facades\Validator;
use App\DTO\MenuDTO;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use App\Models\Menus;


class UpdateMenuAction
{
    protected $menuRepo;
    protected $tenantRepo;


    public function __construct(MenuRepository $menuRepo, TenantRepository $tenantRepo)
    {
        $this->menuRepo = $menuRepo;
        $this->tenantRepo = $tenantRepo;
    }


    public function execute($id, $request)
    {
        $menu = $this->menuRepo->find($id);
        if (!$menu) {
            abort(404, 'Menu tidak ditemukan');
        }


        $tenant = $this->tenantRepo->getByUser($request->user()->id);


        // Authorization: ensure owner (kept using Gate policy)
        if (!Gate::allows('update-tenant-menu', [$menu, $tenant])) {
            abort(403, 'Anda Bukan Pemilik Tenant Ini');
        }


        $validator = Validator::make($request->all(), [
            'harga' => 'nullable|numeric|gt:0',
            'gambar' => 'nullable|mimes:png,jpg,jpeg|max:2048',
            'nama_menu' => 'nullable|string',
            'deskripsi_menu' => 'nullable|string',
            'kategori_id' => 'nullable|integer',
            'isReady' => 'nullable|boolean'
        ]);
        $validator->validate();


        $gambarUrl = $menu->gambar;
        if ($request->hasFile('gambar') && $request->file('gambar') instanceof UploadedFile) {
            $gambarUrl = ImageUploader::upload($request->file('gambar'));
        }


        $dto = new MenuDTO([
            'tenant_id' => $tenant->id ?? $menu->tenant_id,
            'kategori_id' => $request->kategori_id ?? $menu->kategori_id,
            'harga' => $request->harga ?? $menu->harga,
            'gambar' => $gambarUrl ?? $menu->gambar,
            'nama' => $request->nama_menu ?? $menu->nama,
            'deskripsi' => $request->deskripsi_menu ?? $menu->deskripsi,
            'isReady' => $request->has('isReady') ? $request->isReady : $menu->isReady,
        ]);


        return $this->menuRepo->update($menu->id, $dto->toArray());
    }
}
