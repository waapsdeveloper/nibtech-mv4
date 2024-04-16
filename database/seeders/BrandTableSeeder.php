<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class BrandTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('brand')->delete();
        
        \DB::table('brand')->insert(array (
            0 => 
            array (
                'id' => 1,
                'name' => 'Apple',
            ),
            1 => 
            array (
                'id' => 2,
                'name' => 'Samsung',
            ),
            2 => 
            array (
                'id' => 3,
                'name' => 'Google',
            ),
            3 => 
            array (
                'id' => 4,
                'name' => 'Xiaomi',
            ),
            4 => 
            array (
                'id' => 5,
                'name' => 'OnePlus',
            ),
            5 => 
            array (
                'id' => 6,
                'name' => 'Caterpillar',
            ),
            6 => 
            array (
                'id' => 7,
                'name' => 'MIcrosoft',
            ),
            7 => 
            array (
                'id' => 8,
                'name' => 'Sony',
            ),
            8 => 
            array (
                'id' => 9,
                'name' => 'Huawei',
            ),
            9 => 
            array (
                'id' => 10,
                'name' => 'Motorola',
            ),
            10 => 
            array (
                'id' => 11,
                'name' => 'Meta',
            ),
        ));
        
        
    }
}