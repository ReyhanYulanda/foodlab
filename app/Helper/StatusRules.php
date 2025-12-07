<?php

namespace App\Helpers;

class StatusRules
{
    /**
     * Global rules: status yang tidak boleh diapa-apakan lagi.
     */
    public static function checkGlobalStatus(?string $currentStatus): ?array
    {
        if ($currentStatus === 'refund_selesai') {
            return [
                'message' => 'Pesanan telah selesai refund system karena melebihi 10 menit.',
                'code'    => 403,
            ];
        }

        if ($currentStatus === 'pesanan_ditolak') {
            return [
                'message' => 'Pesanan sudah ditolak sebelumnya.',
                'code'    => 403,
            ];
        }

        if ($currentStatus === 'selesai') {
            return [
                'message' => 'Pesanan sudah selesai.',
                'code'    => 403,
            ];
        }

        return null;
    }

    /**
     * Rules untuk kombinasi current_status → requested_status.
     */
    public static function checkTransition(?string $currentStatus, ?string $requestedStatus): ?array
    {
        $rules = [
            // Sudah dalam proses
            'pesanan_diproses|pesanan_diproses' => [
                'message' => 'Pesanan sudah dalam proses sebelumnya.',
                'code'    => 403,
            ],

            'pesanan_diproses|pesanan_ditolak' => [
                'message' => 'Pesanan sedang diproses, tidak bisa ditolak.',
                'code'    => 403,
            ],

            // Siap diantar
            'siap_diantar|siap_diantar' => [
                'message' => 'Pesanan sudah siap diantar sebelumnya.',
                'code'    => 403,
            ],

            // Siap diambil
            'siap_diambil|siap_diambil' => [
                'message' => 'Pesanan sudah siap diambil sebelumnya.',
                'code'    => 403,
            ],

            // Diantar
            'diantar|diantar' => [
                'message' => 'Pesanan sudah dalam proses pengantaran sebelumnya.',
                'code'    => 403,
            ],

            'diantar|siap_diantar' => [
                'message' => 'Pesanan sudah dalam proses pengantaran sebelumnya.',
                'code'    => 403,
            ],

            // Selesai
            'selesai|siap_diambil' => [
                'message' => 'Pesanan sudah siap diambil sebelumnya.',
                'code'    => 403,
            ],

            'selesai|siap_diantar' => [
                'message' => 'Pesanan sudah selesai sebelumnya.',
                'code'    => 403,
            ],

            'selesai|selesai' => [
                'message' => 'Pesanan sudah selesai sebelumnya.',
                'code'    => 403,
            ],

            // Ditolak
            'pesanan_ditolak|pesanan_ditolak' => [
                'message' => 'Pesanan sudah ditolak sebelumnya.',
                'code'    => 403,
            ],
        ];

        $key = "{$currentStatus}|{$requestedStatus}";

        return $rules[$key] ?? null;
    }
}
