<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Google_Client;
use App\Mail\GmailTransport;

class MailServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Register the custom Gmail transport
        $this->app->singleton(TransportInterface::class, function ($app) {
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

        // Register the mailer
        $this->app->singleton('mailer', function ($app) {
            $transport = $app->make(TransportInterface::class);
            return new Mailer($transport);
        });
    }

    public function boot()
    {
        //
    }
}
