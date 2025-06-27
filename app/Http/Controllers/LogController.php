<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class LogController extends Controller
{
    static function log($user_id, $action, $route, $response = null)
    {
        $log = new \App\Models\Log();
        $log->user_id = $user_id;
        $log->action = $action;
        $log->route = $route;
        $log->response = $response;
        $log->method = request()->method();
        $log->ip = request()->ip();
        $log->user_agent = request()->header('User-Agent');
        $log->save();
    }
    
    static function getLogs($user_id)
    {
        return \App\Models\Log::where('user_id', $user_id)->orderBy('created_at', 'desc')->get();
    }
    
    static function getLog($id)
    {
        return \App\Models\Log::find($id);
    }
    
    static function deleteLog($id)
    {
        $log = \App\Models\Log::find($id);
        if ($log) {
            $log->delete();
            return true;
        }
        return false;
    }
    
    static function deleteLogs($user_id)
    {
        $logs = \App\Models\Log::where('user_id', $user_id)->get();
        foreach ($logs as $log) {
            $log->delete();
        }
        return true;
    }
    
    static function deleteAllLogs()
    {
        $logs = \App\Models\Log::all();
        foreach ($logs as $log) {
            $log->delete();
        }
        return true;
    }
    
    static function getLogsByAction($action)
    {
        return \App\Models\Log::where('action', $action)->orderBy('created_at', 'desc')->get();
    }
    
    static function getLogsByUser($user_id)
    {
        return \App\Models\Log::where('user_id', $user_id)->orderBy('created_at', 'desc')->get();
    }
}