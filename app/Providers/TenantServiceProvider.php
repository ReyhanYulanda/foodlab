<?php


namespace App\Providers;


use Illuminate\Support\ServiceProvider;
use App\Repositories\MenuRepository;
use App\Repositories\TenantRepository;
use App\Repositories\TransaksiRepository;


class TenantServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Bind repositories as singletons or concrete types
        $this->app->bind(MenuRepository::class, function ($app) {
            return new MenuRepository();
        });


        $this->app->bind(TenantRepository::class, function ($app) {
            return new TenantRepository();
        });


        $this->app->bind(TransaksiRepository::class, function ($app) {
            return new TransaksiRepository();
        });
    }


    public function boot()
    {
        // nothing for now
    }
}
