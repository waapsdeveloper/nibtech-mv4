<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class CategoryTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('category')->delete();
        
        \DB::table('category')->insert(array (
            0 => 
            array (
                'id' => 1,
                'parent_id' => NULL,
                'name' => 'Earbuds',
            ),
            1 => 
            array (
                'id' => 2,
                'parent_id' => NULL,
                'name' => 'TV Device',
            ),
            2 => 
            array (
                'id' => 3,
                'parent_id' => NULL,
                'name' => 'Smart Watch',
            ),
            3 => 
            array (
                'id' => 4,
                'parent_id' => NULL,
                'name' => 'VR Headset',
            ),
            4 => 
            array (
                'id' => 5,
                'parent_id' => NULL,
                'name' => 'Gaming Console',
            ),
            5 => 
            array (
                'id' => 6,
                'parent_id' => NULL,
                'name' => 'Tablet',
            ),
            6 => 
            array (
                'id' => 7,
                'parent_id' => NULL,
                'name' => 'Mini PC',
            ),
            7 => 
            array (
                'id' => 8,
                'parent_id' => NULL,
                'name' => 'Laptops',
            ),
            8 => 
            array (
                'id' => 9,
                'parent_id' => NULL,
                'name' => 'Smartphones',
            ),
        ));
        
        
    }
}