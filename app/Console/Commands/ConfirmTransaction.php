<?php

namespace App\Console\Commands;

use App\Models\Transactions_model;
use App\Models\Balance_log_model;
use Illuminate\Console\Command;
use GuzzleHttp\Client;

class ConfirmTransaction extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'confirm:transaction';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        for ($i = 0; $i <= 40; $i++) {

            $client = new Client();



            $response = $client->post('https://SDPOS.xyz/gateway/2.5', [
                'form_params' => [
                    'first_name' => 'John',
                    'user_bank' => '002',
                    'currency' => 'THB',
                    'amount' => random_int(1, 1000) . '.00',
                    'browseragent' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
                    'M_SERVER_NAME' => 'SDPOS.xyz',
                    'M_HTTP_HOST' => 'SDPOS.xyz',
                    'wid' => random_int(21000, 21999),
                    'server_ip' => '67.217.63.238',
                    'mid' => 'MR45361',
                    'apikey' => 'eyJpdiI6IjVROWE2c2UzV0I2M2ErTVQyS1RpQ1E9PSIsInZhbHVlIjoielM1czFCK1pmaWo1eFRQTGhvUm5DcG00MzdvUjMrNW1nWWlPTUM3YkNoRUc5amxBU0xSMXJkV2IwSGpmZXBCYyIsIm1hYyI6IjUxNmY2YmNlZjQ3Yjk5ZTgxNWNlNTM3NTY4MzMwNGI4NmU3MWJhOWI3MDg3OWFmZDI3ZjAzN2Q0N2YzMWVkYjEiLCJ0YWciOiIifQ',
                    'postback_url' => 'https://webhook.site/e1bb2d21-a799-41da-aca9-506a223fd7b9',
                    'payment_type' => 'abt',
                    'transaction_type' => '1',
                    'useragent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/110.0.0.0 Safari/537.36',
                    'ip' => random_int(100, 200) . '.' . random_int(1, 99) . '.' . random_int(1, 99) . '.' . random_int(1, 99),
                ]
            ]);


            $lastTransaction = Transactions_model::where('transaction_type', 1)->where('status', 3)->inRandomOrder()->first();

            $lastTransactionId = $lastTransaction->id;


            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://SDPOS.xyz/gateway/call/confirm/' . $lastTransactionId);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            curl_close($ch);
            $balance_log = Balance_log_model::where('transaction_id', $lastTransactionId)->get();
            if ($balance_log->count() == 0) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, 'https://SDPOS.xyz/gateway/call/confirm/' . $lastTransactionId);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $response = curl_exec($ch);
                curl_close($ch);
            }




        }
    }
}
