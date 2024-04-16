<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class RolePermissionTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('role_permission')->delete();
        
        \DB::table('role_permission')->insert(array (
            0 => 
            array (
                'id' => 1,
                'role_id' => 1,
                'permission_id' => 1,
            ),
            1 => 
            array (
                'id' => 2,
                'role_id' => 1,
                'permission_id' => 2,
            ),
            2 => 
            array (
                'id' => 3,
                'role_id' => 1,
                'permission_id' => 3,
            ),
            3 => 
            array (
                'id' => 4,
                'role_id' => 1,
                'permission_id' => 4,
            ),
            4 => 
            array (
                'id' => 5,
                'role_id' => 1,
                'permission_id' => 5,
            ),
            5 => 
            array (
                'id' => 6,
                'role_id' => 1,
                'permission_id' => 6,
            ),
            6 => 
            array (
                'id' => 7,
                'role_id' => 1,
                'permission_id' => 7,
            ),
            7 => 
            array (
                'id' => 8,
                'role_id' => 1,
                'permission_id' => 8,
            ),
            8 => 
            array (
                'id' => 9,
                'role_id' => 1,
                'permission_id' => 9,
            ),
            9 => 
            array (
                'id' => 10,
                'role_id' => 1,
                'permission_id' => 10,
            ),
            10 => 
            array (
                'id' => 11,
                'role_id' => 1,
                'permission_id' => 11,
            ),
            11 => 
            array (
                'id' => 12,
                'role_id' => 1,
                'permission_id' => 12,
            ),
            12 => 
            array (
                'id' => 13,
                'role_id' => 1,
                'permission_id' => 13,
            ),
            13 => 
            array (
                'id' => 14,
                'role_id' => 1,
                'permission_id' => 14,
            ),
            14 => 
            array (
                'id' => 15,
                'role_id' => 1,
                'permission_id' => 15,
            ),
            15 => 
            array (
                'id' => 16,
                'role_id' => 1,
                'permission_id' => 16,
            ),
            16 => 
            array (
                'id' => 17,
                'role_id' => 1,
                'permission_id' => 17,
            ),
            17 => 
            array (
                'id' => 18,
                'role_id' => 1,
                'permission_id' => 18,
            ),
            18 => 
            array (
                'id' => 19,
                'role_id' => 5,
                'permission_id' => 7,
            ),
            19 => 
            array (
                'id' => 20,
                'role_id' => 5,
                'permission_id' => 8,
            ),
            20 => 
            array (
                'id' => 21,
                'role_id' => 3,
                'permission_id' => 9,
            ),
            21 => 
            array (
                'id' => 22,
                'role_id' => 3,
                'permission_id' => 7,
            ),
            22 => 
            array (
                'id' => 23,
                'role_id' => 3,
                'permission_id' => 10,
            ),
            23 => 
            array (
                'id' => 24,
                'role_id' => 3,
                'permission_id' => 14,
            ),
            24 => 
            array (
                'id' => 25,
                'role_id' => 1,
                'permission_id' => 19,
            ),
            25 => 
            array (
                'id' => 26,
                'role_id' => 1,
                'permission_id' => 20,
            ),
            26 => 
            array (
                'id' => 27,
                'role_id' => 4,
                'permission_id' => 7,
            ),
            27 => 
            array (
                'id' => 28,
                'role_id' => 4,
                'permission_id' => 8,
            ),
            28 => 
            array (
                'id' => 29,
                'role_id' => 4,
                'permission_id' => 9,
            ),
            29 => 
            array (
                'id' => 30,
                'role_id' => 4,
                'permission_id' => 9,
            ),
        ));
        
        
    }
}