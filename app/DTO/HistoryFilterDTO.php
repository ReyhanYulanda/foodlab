<?php


namespace App\DTO;


class HistoryFilterDTO
{
    public $tenant_id;
    public $filter_date;
    public $start_date;
    public $end_date;


    public function __construct(array $data = [])
    {
        $this->tenant_id = $data['tenant_id'] ?? null;
        $this->filter_date = $data['filter_date'] ?? null;
        $this->start_date = $data['start_date'] ?? null;
        $this->end_date = $data['end_date'] ?? null;
    }
}
