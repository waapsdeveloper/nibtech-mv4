<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class CurrencyTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('currency')->delete();
        
        \DB::table('currency')->insert(array (
            0 => 
            array (
                'id' => 1,
                'country_id' => 13,
                'name' => 'Australian Dollars',
                'code' => 'AUD',
                'sign' => 'A$',
            ),
            1 => 
            array (
                'id' => 2,
                'country_id' => 37,
                'name' => 'Canadian Dollars',
                'code' => 'CAD',
                'sign' => 'C$',
            ),
            2 => 
            array (
                'id' => 3,
                'country_id' => 43,
                'name' => 'Chinese Yuan',
                'code' => 'CNY',
                'sign' => '元',
            ),
            3 => 
            array (
                'id' => 4,
                'country_id' => 76,
                'name' => 'Euro',
                'code' => 'EUR',
                'sign' => '€',
            ),
            4 => 
            array (
                'id' => 5,
                'country_id' => 214,
                'name' => 'Pound Sterling',
                'code' => 'GBP',
                'sign' => '£',
            ),
            5 => 
            array (
                'id' => 6,
                'country_id' => 89,
                'name' => 'HongKong Dollar',
                'code' => 'HKD',
                'sign' => 'HK$',
            ),
            6 => 
            array (
                'id' => 7,
                'country_id' => 93,
                'name' => 'Indonesia Rupee',
                'code' => 'IDR',
                'sign' => 'Rp',
            ),
            7 => 
            array (
                'id' => 8,
                'country_id' => 100,
                'name' => 'Japnese Yen',
                'code' => 'JPY',
                'sign' => '¥',
            ),
            8 => 
            array (
                'id' => 9,
                'country_id' => 188,
                'name' => 'Korean won',
                'code' => 'KRW',
                'sign' => '₩',
            ),
            9 => 
            array (
                'id' => 10,
                'country_id' => 120,
                'name' => 'Malaysian Ringgit',
                'code' => 'MYR',
                'sign' => 'RM',
            ),
            10 => 
            array (
                'id' => 11,
                'country_id' => 143,
                'name' => 'NZD Dollar',
                'code' => 'NZD',
                'sign' => 'NZ$',
            ),
            11 => 
            array (
                'id' => 12,
                'country_id' => 182,
                'name' => 'SG dollar',
                'code' => 'SGD',
                'sign' => 'S$',
            ),
            12 => 
            array (
                'id' => 13,
                'country_id' => 201,
                'name' => 'Thai Baht',
                'code' => 'THB',
                'sign' => '฿',
            ),
            13 => 
            array (
                'id' => 14,
                'country_id' => 215,
                'name' => 'United States Dollar',
                'code' => 'USD',
                'sign' => 'US$',
            ),
            14 => 
            array (
                'id' => 15,
                'country_id' => 221,
                'name' => 'Vietnam Dong',
                'code' => 'VND',
                'sign' => '₫',
            ),
            15 => 
            array (
                'id' => 16,
                'country_id' => 167,
                'name' => 'Ruble',
                'code' => 'RUB',
                'sign' => '₽',
            ),
            16 => 
            array (
                'id' => 17,
                'country_id' => 187,
                'name' => 'RAND',
                'code' => 'ZAR',
                'sign' => 'R',
            ),
            17 => 
            array (
                'id' => 18,
                'country_id' => 215,
                'name' => 'USDT',
                'code' => 'USDT',
                'sign' => ' ₮',
            ),
            18 => 
            array (
                'id' => 19,
                'country_id' => 18,
                'name' => 'Takka',
                'code' => 'BDT',
                'sign' => '৳',
            ),
            19 => 
            array (
                'id' => 20,
                'country_id' => 136,
                'name' => 'KYAT',
                'code' => 'KYAT',
                'sign' => 'K',
            ),
            20 => 
            array (
                'id' => 21,
                'country_id' => 35,
                'name' => 'Riels',
                'code' => 'RIELS',
                'sign' => 'RIELS',
            ),
            21 => 
            array (
                'id' => 22,
                'country_id' => 92,
                'name' => 'INDIAN RUPEES',
                'code' => 'INR',
                'sign' => '₹',
            ),
            22 => 
            array (
                'id' => 23,
                'country_id' => 159,
                'name' => 'PESO',
                'code' => 'PESO',
                'sign' => '₱',
            ),
            23 => 
            array (
                'id' => 25,
                'country_id' => 198,
                'name' => 'TAIWAN DOLLAR',
                'code' => 'TWD',
                'sign' => 'NT$',
            ),
            24 => 
            array (
                'id' => 26,
                'country_id' => 107,
                'name' => 'LAO KIP',
                'code' => 'KIP',
                'sign' => ' ₭',
            ),
            25 => 
            array (
                'id' => 27,
                'country_id' => 152,
                'name' => 'Pakistani Rupee',
                'code' => 'PKR',
                'sign' => '₨',
            ),
        ));
        
        
    }
}