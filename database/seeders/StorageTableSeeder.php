<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class StorageTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('storage')->delete();
        
        \DB::table('storage')->insert(array (
            0 => 
            array (
                'id' => 1,
                'name' => '16GB',
            ),
            1 => 
            array (
                'id' => 2,
                'name' => '32GB',
            ),
            2 => 
            array (
                'id' => 3,
                'name' => '64GB',
            ),
            3 => 
            array (
                'id' => 4,
                'name' => '128GB',
            ),
            4 => 
            array (
                'id' => 5,
                'name' => '256GB',
            ),
            5 => 
            array (
                'id' => 6,
                'name' => '512GB',
            ),
            6 => 
            array (
                'id' => 7,
                'name' => '825GB',
            ),
            7 => 
            array (
                'id' => 8,
                'name' => '1TB',
            ),
        ));
        
        
    }
}