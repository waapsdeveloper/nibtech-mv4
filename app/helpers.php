<?php

if(!function_exists('amount_formatter')) {
    function amount_formatter($amount, $decimals = 2) {
        if(session('amount_view') == '1') {
            return number_format($amount, $decimals);
        }else{
            return '**.**';
        }
    }
}
