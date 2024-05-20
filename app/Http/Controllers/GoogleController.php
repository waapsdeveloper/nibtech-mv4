<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Google_Client;
use Google_Service_Gmail;

class GoogleController extends Controller
{
    public function redirectToGoogle()
    {
        $client = new Google_Client();
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setRedirectUri(config('services.google.redirect_uri'));
        $client->addScope(Google_Service_Gmail::GMAIL_SEND);

        return redirect($client->createAuthUrl());
    }

    public function handleGoogleCallback(Request $request)
    {
        $client = new Google_Client();
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setRedirectUri(config('services.google.redirect_uri'));
        $client->authenticate($request->input('code'));

        $token = $client->getAccessToken();
        // Store token securely, possibly in the database or encrypted session

        // Use the token to send emails via Gmail API
        $client->setAccessToken($token);
        $service = new Google_Service_Gmail($client);

        $message = new \Google_Service_Gmail_Message();
        $rawMessageString = "From: your-email@gmail.com\r\n";
        $rawMessageString .= "To: recipient@example.com\r\n";
        $rawMessageString .= "Subject: Test Email\r\n\r\n";
        $rawMessageString .= "This is a test email using Gmail API with OAuth 2.0.";

        $rawMessage = strtr(base64_encode($rawMessageString), array('+' => '-', '/' => '_'));
        $message->setRaw($rawMessage);

        $service->users_messages->send('me', $message);

        return 'Email sent!';
    }
}
