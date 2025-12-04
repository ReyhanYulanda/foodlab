<?php


namespace App\Services\Tenant\Actions;


use App\Repositories\MenuRepository;
use App\Repositories\TenantRepository;
use App\Services\Tenant\Helpers\ImageUploader;
use Illuminate\Support\Facades\Validator;
use App\DTO\MenuDTO;
use Illuminate\Http\UploadedFile;


class CreateMenuAction
{
    protected $menuRepo;
    protected $tenantRepo;


    public function __construct(MenuRepository $menuRepo, TenantRepository $tenantRepo)
    {
        $this->menuRepo = $menuRepo;
        $this->tenantRepo = $tenantRepo;
    }


    public function execute($request)
    {
        $validator = Validator::make($request->all(), [
            'harga' => 'required|numeric',
            'nama_menu' => 'required|string',
            'kategori_id' => 'required|integer',
            'gambar' => 'nullable|mimes:png,jpg,jpeg|max:2048',
            'deskripsi_menu' => 'nullable|string'
        ]);


        $validator->validate();


        $tenant = $this->tenantRepo->getByUser($request->user()->id);


        $gambarUrl = null;
        if ($request->hasFile('gambar') && $request->file('gambar') instanceof UploadedFile) {
            $gambarUrl = ImageUploader::upload($request->file('gambar'));
        }


        $dto = new MenuDTO([
            'tenant_id' => $tenant->id,
            'kategori_id' => $request->kategori_id,
            'harga' => $request->harga,
            'nama' => $request->nama_menu,
            'deskripsi' => $request->deskripsi_menu,
            'gambar' => $gambarUrl,
        ]);


        return $this->menuRepo->create($dto->toArray());
    }
}
