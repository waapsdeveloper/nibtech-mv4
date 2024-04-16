<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class PermissionTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('permission')->delete();
        
        \DB::table('permission')->insert(array (
            0 => 
            array (
                'id' => 1,
                'name' => 'view_purchase',
            ),
            1 => 
            array (
                'id' => 2,
                'name' => 'add_purchase',
            ),
            2 => 
            array (
                'id' => 3,
                'name' => 'add_purchase_item',
            ),
            3 => 
            array (
                'id' => 4,
                'name' => 'delete_purchase',
            ),
            4 => 
            array (
                'id' => 5,
                'name' => 'delete_purchase_item',
            ),
            5 => 
            array (
                'id' => 6,
                'name' => 'purchase_detail',
            ),
            6 => 
            array (
                'id' => 7,
                'name' => 'view_order',
            ),
            7 => 
            array (
                'id' => 8,
                'name' => 'dispatch_order',
            ),
            8 => 
            array (
                'id' => 9,
                'name' => 'view_inventory',
            ),
            9 => 
            array (
                'id' => 10,
                'name' => 'view_product',
            ),
            10 => 
            array (
                'id' => 11,
                'name' => 'add_product',
            ),
            11 => 
            array (
                'id' => 12,
                'name' => 'update_product',
            ),
            12 => 
            array (
                'id' => 13,
                'name' => 'update_variation',
            ),
            13 => 
            array (
                'id' => 14,
                'name' => 'view_team',
            ),
            14 => 
            array (
                'id' => 15,
                'name' => 'add_member',
            ),
            15 => 
            array (
                'id' => 16,
                'name' => 'edit_member',
            ),
            16 => 
            array (
                'id' => 17,
                'name' => 'view_permissions',
            ),
            17 => 
            array (
                'id' => 18,
                'name' => 'change_permission',
            ),
            18 => 
            array (
                'id' => 19,
                'name' => 'view_cost',
            ),
            19 => 
            array (
                'id' => 20,
                'name' => 'view_price',
            ),
        ));
        
        
    }
}