<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class AdminTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {


        \DB::table('admin')->delete();

        \DB::table('admin')->insert(array (
            0 =>
            array (
                'id' => 1,
                'username' => 'admin',
                'email' => 'haleem.shahhs@gmail.com',
                'password' => '$2y$10$Io4kEkUbz94t2mEJh4IMVuRjc1DsRlj9i4mFSfU28ZAtKgwVi98cu',
                'first_name' => 'Haleem',
                'last_name' => 'Admin',
                'photo' => NULL,
                'role_id' => 1,
                'parent_id' => 0,
                'two_factor_secret' => NULL,
                'two_factor_recovery_codes' => NULL,
                'two_factor_confirmed_at' => NULL,
            ),
            1 =>
            array (
                'id' => 2,
                'username' => 'owais',
                'email' => 'owais@gmail.com',
                'password' => '$2y$10$pOO7rUl9Skp.vBJpJNqy7.zVmCl5/6EDXE6eiPHtwOfG.aFZK/.xa',
                'first_name' => 'Syed Owais',
                'last_name' => 'Iqbal',
                'photo' => NULL,
                'role_id' => 1,
                'parent_id' => 1,
                'two_factor_secret' => NULL,
                'two_factor_recovery_codes' => NULL,
                'two_factor_confirmed_at' => NULL,
            ),
            2 =>
            array (
                'id' => 4,
                'username' => 'ahmed',
                'email' => 'ahmed@gmail.com',
                'password' => '$2y$10$sC9bsTe5vOiiResQJ7oTY.Qpga0DSElL.r0rFwa9Yxwrx0ZrBfPPy',
                'first_name' => 'Ahmed',
                'last_name' => 'Siddique',
                'photo' => NULL,
                'role_id' => 1,
                'parent_id' => 1,
                'two_factor_secret' => NULL,
                'two_factor_recovery_codes' => NULL,
                'two_factor_confirmed_at' => NULL,
            ),
            3 =>
            array (
                'id' => 5,
                'username' => 'rohit',
                'email' => 'rohit@gmail.com',
                'password' => '$2y$10$cn0BAxd04/HKf9OzZrgIMu9ecC2tHNp1aC3mXYHARqGkB17.hjv3K',
                'first_name' => 'Rohit',
                'last_name' => 'Sharma',
                'photo' => NULL,
                'role_id' => 3,
                'parent_id' => 4,
                'two_factor_secret' => NULL,
                'two_factor_recovery_codes' => NULL,
                'two_factor_confirmed_at' => NULL,
            ),
            4 =>
            array (
                'id' => 6,
                'username' => 'paras',
                'email' => 'paras@gmail.com',
                'password' => '$2y$10$IKVeJEVJW7vO7Ox7UPXIB.87EBnwtn7bMBKS4DvmviQXDHulLztbW',
                'first_name' => 'Paras',
                'last_name' => 'Khan',
                'photo' => NULL,
                'role_id' => 5,
                'parent_id' => 5,
                'two_factor_secret' => NULL,
                'two_factor_recovery_codes' => NULL,
                'two_factor_confirmed_at' => NULL,
            ),
            5 =>
            array (
                'id' => 7,
                'username' => 'anshu',
                'email' => 'anshu@gmail.com',
                'password' => '$2y$10$nrFCznoj8HE5ICRqu41XX.HZKS1unSHR4cxljYQg3vYbpY7lYSi3.',
                'first_name' => 'Anshu',
                'last_name' => 'Chohan',
                'photo' => NULL,
                'role_id' => 5,
                'parent_id' => 6,
                'two_factor_secret' => NULL,
                'two_factor_recovery_codes' => NULL,
                'two_factor_confirmed_at' => NULL,
            ),
            6 =>
            array (
                'id' => 8,
                'username' => 'sangeeta',
                'email' => 'sangeeta@gmail.com',
                'password' => '$2y$10$304e4lMFPfggJ0gb9cgx7OVtTJXBTBzHxH0plj6WkCW1kQNNKDvx6',
                'first_name' => 'Sangeeta',
                'last_name' => 'Punia',
                'photo' => NULL,
                'role_id' => 5,
                'parent_id' => 6,
                'two_factor_secret' => NULL,
                'two_factor_recovery_codes' => NULL,
                'two_factor_confirmed_at' => NULL,
            ),
            7 =>
            array (
                'id' => 10,
                'username' => 'umer',
                'email' => 'umer@gmail.com',
                'password' => '$2y$10$aHf4gh.24nSi58dJ2U6OYe0E/gTX/nqR7KgH97ayipY4SWbRhIEPG',
                'first_name' => 'Khwaja',
                'last_name' => 'Umer',
                'photo' => NULL,
                'role_id' => 4,
                'parent_id' => 5,
                'two_factor_secret' => NULL,
                'two_factor_recovery_codes' => NULL,
                'two_factor_confirmed_at' => NULL,
            ),
        ));


    }
}
