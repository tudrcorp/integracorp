<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DetailCorporateQuotesController extends Controller
{
    static function coverage_discount($total, $discount) {
        $ammount_discount = ($total * $discount) / 100;
        return $total - $ammount_discount;
    }

    static function coverage_increase($total, $increase)
    {
        $ammount_increase = ($total * $increase) / 100;
        return $total + $ammount_increase;
    }
}