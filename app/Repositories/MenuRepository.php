<?php


namespace App\Repositories;


use App\Models\Menus;


class MenuRepository
{
    public function create(array $data)
    {
        return Menus::create($data);
    }


    public function find($id)
    {
        return Menus::find($id);
    }


    public function update($id, array $data)
    {
        $menu = Menus::find($id);
        if (!$menu) return null;
        $menu->update($data);
        return $menu->fresh();
    }


    public function delete($id)
    {
        $menu = Menus::find($id);
        if ($menu) return $menu->delete();
        return false;
    }
}
