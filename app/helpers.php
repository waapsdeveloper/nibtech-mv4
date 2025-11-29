<?php

if(!function_exists('amount_formatter')) {
    function amount_formatter($amount, $decimals = 2) {
        if(session('amount_view') == '1') {
            return number_format($amount, $decimals);
        }else{
            return '*****';
        }
    }
}

if (! function_exists('conversation_url_for_order_item')) {
    function conversation_url_for_order_item($orderItem)
    {
        if (! $orderItem || ! $orderItem->care_id) {
            return null;
        }

        $marketplaceId = (int) data_get($orderItem, 'order.marketplace_id');

        if ($marketplaceId === 4) {
            return 'https://refurbed-merchant.zendesk.com/agent/tickets/' . $orderItem->care_id;
        }

        return 'https://backmarket.fr/bo-seller/customer-care/help-requests/' . $orderItem->care_id;
    }
}
