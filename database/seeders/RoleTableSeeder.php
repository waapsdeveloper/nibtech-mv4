<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class RoleTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('role')->delete();
        
        \DB::table('role')->insert(array (
            0 => 
            array (
                'id' => 1,
                'name' => 'Super Admin',
            ),
            1 => 
            array (
                'id' => 2,
                'name' => 'Admin',
            ),
            2 => 
            array (
                'id' => 3,
                'name' => 'Manager',
            ),
            3 => 
            array (
                'id' => 4,
                'name' => 'AfterSale',
            ),
            4 => 
            array (
                'id' => 5,
                'name' => 'Invoice',
            ),
            5 => 
            array (
                'id' => 6,
                'name' => 'Grading',
            ),
            6 => 
            array (
                'id' => 7,
                'name' => 'Testing',
            ),
        ));
        
        
    }
}