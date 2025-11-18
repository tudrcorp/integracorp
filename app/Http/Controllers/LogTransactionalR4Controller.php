<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LogTransactionalR4;
use Illuminate\Support\Facades\Log;

class LogTransactionalR4Controller extends Controller
{
    public static function response($code, $message, $uuid) {

        try {
            
            $log = new LogTransactionalR4();
            $log->code      = $code;
            $log->message   = $message;
            $log->uuid      = $uuid;
            $log->save();
            
        } catch (\Throwable $th) {
            Log::error($th->getMessage());
        }
    }
}