<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // \App\Models\User::factory(10)->create();
        $this->call(AdminTableSeeder::class);
        $this->call(BrandTableSeeder::class);
        $this->call(CategoryTableSeeder::class);
        $this->call(ColorTableSeeder::class);
        $this->call(CountryTableSeeder::class);
        $this->call(CurrencyTableSeeder::class);
        $this->call(CustomerTableSeeder::class);
        $this->call(GradeTableSeeder::class);
        $this->call(MultiTypeTableSeeder::class);
        $this->call(OrdersTableSeeder::class);
        $this->call(OrderItemsTableSeeder::class);
        $this->call(OrderStatusTableSeeder::class);
        $this->call(PermissionTableSeeder::class);
        $this->call(ProcessTableSeeder::class);
        $this->call(ProductsTableSeeder::class);
        $this->call(RoleTableSeeder::class);
        $this->call(RolePermissionTableSeeder::class);
        $this->call(StockTableSeeder::class);
        $this->call(StorageTableSeeder::class);
        $this->call(VariationTableSeeder::class);
    }
}
