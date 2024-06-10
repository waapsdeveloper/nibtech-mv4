<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Mail\MailManager;
use Google_Client;
use App\Mail\GmailTransport;

class MailServiceProvider extends ServiceProvider
{
    public function register()
    {
        //
    }

    public function boot()
    {
        $this->app->make(MailManager::class)->extend('gmail', function () {
            $client = new Google_Client();
            $client->setClientId(config('services.google.client_id'));
            $client->setClientSecret(config('services.google.client_secret'));
            $client->setRedirectUri(config('services.google.redirect_uri'));

            // Fetch the saved token
            $token = \App\Models\GoogleToken::first();

            if ($token) {
                $client->setAccessToken($token->access_token);

                if ($client->isAccessTokenExpired()) {
                    $client->fetchAccessTokenWithRefreshToken($token->refresh_token);
                    $token->update(['access_token' => $client->getAccessToken()['access_token']]);
                }
            }

            return new GmailTransport($client);
        });
    }
}
