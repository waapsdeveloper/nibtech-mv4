<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class GradeTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('grade')->delete();
        
        \DB::table('grade')->insert(array (
            0 => 
            array (
                'id' => 1,
                'name' => 'Like New',
            ),
            1 => 
            array (
                'id' => 2,
                'name' => 'Very Good',
            ),
            2 => 
            array (
                'id' => 3,
                'name' => 'Good',
            ),
            3 => 
            array (
                'id' => 4,
                'name' => 'Fair',
            ),
            4 => 
            array (
                'id' => 5,
                'name' => 'Stallone',
            ),
            5 => 
            array (
                'id' => 7,
                'name' => 'Tested Ungraded',
            ),
            6 => 
            array (
                'id' => 8,
                'name' => 'Faulty',
            ),
            7 => 
            array (
                'id' => 9,
                'name' => 'Untested Ungraded',
            ),
            8 => 
            array (
                'id' => 10,
                'name' => 'RMA',
            ),
            9 => 
            array (
                'id' => 11,
                'name' => 'Wholesale',
            ),
            10 => 
            array (
                'id' => 12,
                'name' => 'Camera Lens',
            ),
            11 => 
            array (
                'id' => 13,
                'name' => 'Back Glass',
            ),
        ));
        
        
    }
}