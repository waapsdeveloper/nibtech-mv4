<?php

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Broadcast::routes();

        Broadcast::channel('group-chat.{id}', function ($user, $id) {
            return true; // Add permission checks if needed
        });

        Broadcast::channel('private-chat.{sender_id}.{receiver_id}', function ($user, $sender_id, $receiver_id) {
            return $user->admin_id == $sender_id || $user->admin_id == $receiver_id;
        });


        require base_path('routes/channels.php');
    }
}
