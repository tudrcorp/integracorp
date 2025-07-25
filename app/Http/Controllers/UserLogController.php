<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserLogController extends Controller
{
    static function actionLogUser($action, $module, $code)
    {
        $log = new \App\Models\UserLog();
        $log->user_id = Auth::user()->id;
        $log->action = $action;
        $log->module = $module;
        $log->code = $code;
        $log->ip_address = request()->ip();
        $log->user_agent = request()->header('User-Agent');
        $log->save();
    }
}