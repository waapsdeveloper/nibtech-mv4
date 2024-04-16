<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class MultiTypeTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('multi_type')->delete();
        
        \DB::table('multi_type')->insert(array (
            0 => 
            array (
                'id' => 1,
                'table_name' => 'orders',
                'sort' => 1,
                'name' => 'Purchase',
                'description' => 'Purchased From Vendor',
            ),
            1 => 
            array (
                'id' => 2,
                'table_name' => 'orders',
                'sort' => 2,
                'name' => 'Purchase Return',
                'description' => 'Return of Purchased item to vendor',
            ),
            2 => 
            array (
                'id' => 3,
                'table_name' => 'orders',
                'sort' => 3,
                'name' => 'Sales',
                'description' => 'Item sold to customer',
            ),
            3 => 
            array (
                'id' => 4,
                'table_name' => 'orders',
                'sort' => 4,
                'name' => 'Sales Return',
                'description' => 'Return of sold items from customer',
            ),
            4 => 
            array (
                'id' => 5,
                'table_name' => 'orders',
                'sort' => 5,
                'name' => 'Wholesale',
                'description' => 'Wholesale Bulk Stock',
            ),
            5 => 
            array (
                'id' => 6,
                'table_name' => 'process',
                'sort' => 1,
                'name' => 'Purchase',
                'description' => NULL,
            ),
            6 => 
            array (
                'id' => 7,
                'table_name' => 'process',
                'sort' => 2,
                'name' => 'Sent to Testing',
                'description' => NULL,
            ),
            7 => 
            array (
                'id' => 8,
                'table_name' => 'process',
                'sort' => 3,
                'name' => 'Tested',
                'description' => NULL,
            ),
            8 => 
            array (
                'id' => 9,
                'table_name' => 'process',
                'sort' => 4,
                'name' => 'Sent to InHouse Repair',
                'description' => NULL,
            ),
            9 => 
            array (
                'id' => 10,
                'table_name' => 'process',
                'sort' => 5,
                'name' => 'Sent for Repair',
                'description' => NULL,
            ),
            10 => 
            array (
                'id' => 11,
                'table_name' => 'process',
                'sort' => 6,
                'name' => 'RMA',
                'description' => NULL,
            ),
            11 => 
            array (
                'id' => 12,
                'table_name' => 'process',
                'sort' => 7,
                'name' => 'WholeSale',
                'description' => NULL,
            ),
            12 => 
            array (
                'id' => 13,
                'table_name' => 'process',
                'sort' => 8,
                'name' => 'Sent for Grading',
                'description' => NULL,
            ),
            13 => 
            array (
                'id' => 14,
                'table_name' => 'process',
                'sort' => 9,
                'name' => 'Graded Very Good',
                'description' => NULL,
            ),
            14 => 
            array (
                'id' => 15,
                'table_name' => 'process',
                'sort' => 10,
                'name' => 'Graded Good',
                'description' => NULL,
            ),
            15 => 
            array (
                'id' => 16,
                'table_name' => 'process',
                'sort' => 11,
                'name' => 'Graded Stallone',
                'description' => NULL,
            ),
            16 => 
            array (
                'id' => 17,
                'table_name' => 'process',
                'sort' => 12,
                'name' => 'Graded 2x Stallone',
                'description' => NULL,
            ),
            17 => 
            array (
                'id' => 18,
                'table_name' => 'process',
                'sort' => 13,
                'name' => 'Sold to Customer',
                'description' => NULL,
            ),
            18 => 
            array (
                'id' => 19,
                'table_name' => 'process',
                'sort' => 14,
                'name' => 'Returned to Supplier',
                'description' => NULL,
            ),
        ));
        
        
    }
}