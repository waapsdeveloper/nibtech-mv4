<?php

namespace Tests\Feature;

use App\Events\PrivateMessageSent;
use App\Http\Livewire\PrivateChat;
use App\Models\Admin_model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

class PrivateChatTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpDatabase();
    }

    private function setUpDatabase(): void
    {
        Schema::dropAllTables();

        Schema::create('admin', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->timestamps();
        });

        Schema::create('marketplace', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('api_key')->nullable();
            $table->timestamps();
        });

        Schema::create('private_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sender_id');
            $table->unsignedBigInteger('receiver_id');
            $table->text('message')->nullable();
            $table->string('image')->nullable();
            $table->string('gif_url')->nullable();
            $table->timestamps();
        });

        Schema::create('chat_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_id');
            $table->string('context_type');
            $table->unsignedBigInteger('context_id')->nullable();
            $table->unsignedBigInteger('message_id');
            $table->string('snippet')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function test_user_can_send_gif_message(): void
    {
        $sender = Admin_model::create([
            'first_name' => 'Sender',
            'last_name' => 'Test',
            'email' => 'sender@example.com',
        ]);

        $receiver = Admin_model::create([
            'first_name' => 'Receiver',
            'last_name' => 'User',
            'email' => 'receiver@example.com',
        ]);

        config(['services.refurbed.api_key' => 'test-refurbed-key']);
        session(['user_id' => $sender->id]);

        Event::fake();

        Livewire::test(PrivateChat::class, ['receiverId' => $receiver->id])
            ->set('gifUrl', 'https://media.tenor.com/example.gif')
            ->call('sendMessage')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('private_messages', [
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'gif_url' => 'https://media.tenor.com/example.gif',
        ]);

        Event::assertDispatched(PrivateMessageSent::class);
    }
}
