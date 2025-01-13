<?php

namespace App\Response;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;

class ResponseApi implements Responsable
{
    protected $data;
    protected $message;
    protected $statusCode;
    protected $status;

    public function __construct($data = null, $message = null, $statusCode = 200, $status = null)
    {
        $this->data = $data;
        $this->message = $message;
        $this->statusCode = $statusCode;
        $this->status = $status;
    }

    public static function success($data = null, $message = null, $statusCode = 200, $status = 'success')
    {
        return new static($data, $message, $statusCode, $status);
    }

    public static function error($message = null, $statusCode = 400, $status = 'failed')
    {
        return new static(null, $message, $statusCode, $status);
    }

    public static function serverError($status = 'failed')
    {
        $message = 'Kesalahan Pada Server';
        $statusCode = 500;
        return new static(null, $message, $statusCode, $status);
    }

    public static function forbidden($message = null, $statusCode = 403, $status = 'failed')
    {
        return new static(null, $message, $statusCode, $status);
    }

    public function toResponse($request)
    {
        if($this->status == 'success'){
            $response = [
                'status' => $this->status,
                'message' => $this->message,
                'data' => $this->data,
            ];
        }else{
            $response = [
                'status' => $this->status,
                'message' => $this->message,
            ];
        }

        return new JsonResponse($response, $this->statusCode);
    }
}
