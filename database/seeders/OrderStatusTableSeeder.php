<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class OrderStatusTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('order_status')->delete();
        
        \DB::table('order_status')->insert(array (
            0 => 
            array (
                'id' => 1,
                'name' => 'To Be Treated',
            ),
            1 => 
            array (
                'id' => 2,
                'name' => 'Awaiting Shipment',
            ),
            2 => 
            array (
                'id' => 3,
                'name' => 'Shipped',
            ),
            3 => 
            array (
                'id' => 4,
                'name' => 'Cancelled',
            ),
            4 => 
            array (
                'id' => 5,
                'name' => 'Refunded Before Delivery',
            ),
            5 => 
            array (
                'id' => 6,
                'name' => 'Reimbursed After Delivery',
            ),
            6 => 
            array (
                'id' => 7,
                'name' => 'Correction',
            ),
        ));
        
        
    }
}