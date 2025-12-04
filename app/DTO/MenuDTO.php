<?php


namespace App\DTO;


class MenuDTO
{
    protected $data = [];


    public function __construct(array $data = [])
    {
        $this->data = $data;
    }


    public function toArray()
    {
        return $this->data;
    }
}
