<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Validator;
use App\Response\ResponseApi;

class ValidationHelper
{
    public static function validate($data, $rules)
    {
        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return ResponseApi::error($validator->errors()->all(), 403);
        }

        return null;
    }
}
