<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Google_Client;
use Google_Service_Gmail;
use Google_Service_Gmail_Message;
use App\Models\GoogleToken;
use Exception;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;

class GoogleController extends Controller
{
    public function redirectToGoogle()
    {
        $client = new Google_Client();
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setRedirectUri(config('services.google.redirect_uri'));
        $client->setAccessType('offline'); // Request offline access to get a refresh token
        $client->setIncludeGrantedScopes(true); // Ensure granted scopes are included
        $client->addScope(Google_Service_Gmail::MAIL_GOOGLE_COM);
        $client->setPrompt('consent'); // Force consent screen to get refresh token

        return redirect($client->createAuthUrl());
    }

    public function handleGoogleCallback(Request $request)
    {
        $client = new Google_Client();
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setRedirectUri(config('services.google.redirect_uri'));

        $code = $request->input('code');
        if ($code) {
            $token = $client->fetchAccessTokenWithAuthCode($code);

            if (isset($token['error'])) {
                Log::error('Google OAuth error: ' . $token['error']);
                return redirect()->route('index')->with('error', 'Failed to authenticate with Google.');
            }

            $accessToken = $token['access_token'];
            $refreshToken = $token['refresh_token'] ?? null;

            if (!$refreshToken) {
                Log::error('Refresh token not received');
                return redirect()->route('index')->with('error', 'Failed to receive refresh token from Google.');
            }

            GoogleToken::updateOrCreate(
                ['user_id' => session('user_id')],
                [
                    'access_token' => $accessToken,
                    'refresh_token' => $refreshToken,
                ]
            );

            return redirect()->route('index')->with('success', 'Google OAuth Token stored successfully!');
        }

        return redirect()->route('index')->with('error', 'Authorization code not received.');
    }

    public function sendEmail($recipientEmail, $subject, $body, $attachments = [])
    {
        $googleToken = GoogleToken::first();

        if (!$googleToken) {
            return redirect()->route('google.auth')->with('error', 'You need to authenticate with Google first.');
        }

        $client = new Google_Client();
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setRedirectUri(config('services.google.redirect_uri'));
        $client->setAccessToken($googleToken->access_token);

        if ($client->isAccessTokenExpired()) {
            $newToken = $client->fetchAccessTokenWithRefreshToken($googleToken->refresh_token);

            if (isset($newToken['error'])) {
                Log::error('Failed to refresh access token: ' . $newToken['error']);
                return redirect()->route('google.auth')->with('error', 'Failed to refresh access token. Please authenticate again.');
            }

            $googleToken->update([
                'access_token' => $newToken['access_token']
            ]);
        }

        $service = new Google_Service_Gmail($client);

        $boundary = uniqid(rand(), true);
        $rawMessageString = "From: no-reply@nibritaintech.com\r\n";
        $rawMessageString .= "To: " . $recipientEmail . "\r\n";
        $rawMessageString .= "Subject: " . $subject . "\r\n";
        $rawMessageString .= "MIME-Version: 1.0\r\n";
        $rawMessageString .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n\r\n";
        $rawMessageString .= "--{$boundary}\r\n";
        $rawMessageString .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
        $rawMessageString .= $body . "\r\n\r\n";

        foreach ($attachments as $filePath) {
            $fileData = file_get_contents($filePath);
            $fileType = mime_content_type($filePath);
            $fileName = basename($filePath);
            $rawMessageString .= "--{$boundary}\r\n";
            $rawMessageString .= "Content-Type: {$fileType}; name=\"{$fileName}\"\r\n";
            $rawMessageString .= "Content-Transfer-Encoding: base64\r\n";
            $rawMessageString .= "Content-Disposition: attachment; filename=\"{$fileName}\"\r\n\r\n";
            $rawMessageString .= chunk_split(base64_encode($fileData)) . "\r\n\r\n";
        }

        $rawMessageString .= "--{$boundary}--";

        $rawMessage = strtr(base64_encode($rawMessageString), array('+' => '-', '/' => '_'));
        $message = new Google_Service_Gmail_Message();
        $message->setRaw($rawMessage);

        $service->users_messages->send('me', $message);
    }

    public function sendEmailInvoice($recipientEmail, $subject, Mailable $mailable)
{
    $googleToken = GoogleToken::first();

    if (!$googleToken) {
        return redirect()->route('google.auth')->with('error', 'You need to authenticate with Google first.');
    }

    $client = new Google_Client();
    $client->setClientId(config('services.google.client_id'));
    $client->setClientSecret(config('services.google.client_secret'));
    $client->setRedirectUri(config('services.google.redirect_uri'));
    $client->setAccessType('offline'); // Request offline access
    $client->setIncludeGrantedScopes(true); // Ensure granted scopes are included
    $client->addScope(Google_Service_Gmail::MAIL_GOOGLE_COM);

    $client->setAccessToken($googleToken->access_token);

    if ($client->isAccessTokenExpired()) {
        $client->fetchAccessTokenWithRefreshToken($googleToken->refresh_token);
        $googleToken->update([
            'access_token' => $client->getAccessToken()['access_token']
        ]);
    }

    $service = new Google_Service_Gmail($client);
    if($mailable->build()->rawAttachments[0]){
    // Get the attachment from the mailable
    $pdfData = $mailable->build()->rawAttachments[0]['data'];
    $fileName = $mailable->build()->rawAttachments[0]['name'];
    $fileType = $mailable->build()->rawAttachments[0]['options']['mime'];
    }
    // Create a new message
    $message = new Google_Service_Gmail_Message();

    // Construct the email message
    $boundary = uniqid(rand(), true);
    $rawMessageString = "From: no-reply@nibritaintech.com\r\n";
    $rawMessageString .= "To: {$recipientEmail}\r\n";
    $rawMessageString .= "Subject: {$subject}\r\n";
    $rawMessageString .= "MIME-Version: 1.0\r\n";
    $rawMessageString .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n\r\n";
    $rawMessageString .= "--{$boundary}\r\n";
    $rawMessageString .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
    $rawMessageString .= $mailable->render() . "\r\n\r\n";
    $rawMessageString .= "--{$boundary}\r\n";

    if($mailable->build()->rawAttachments[0]){
    $rawMessageString .= "Content-Type: {$fileType}; name=\"{$fileName}\"\r\n";
    $rawMessageString .= "Content-Transfer-Encoding: base64\r\n";
    $rawMessageString .= "Content-Disposition: attachment; filename=\"{$fileName}\"\r\n\r\n";
    $rawMessageString .= chunk_split(base64_encode($pdfData)) . "\r\n\r\n";
    }
    $rawMessageString .= "--{$boundary}--";

    // Encode the message
    $rawMessage = strtr(base64_encode($rawMessageString), array('+' => '-', '/' => '_'));

    // Set the raw message
    $message->setRaw($rawMessage);

    // Send the message
    // $service->users_messages->send('me', $message);
    try {
        $response = $service->users_messages->send('me', $message);

        // Log the request and response
        Log::info('Send Email Request Body', [
            'recipientEmail' => $recipientEmail,
            'subject' => $subject,
            'body' => $mailable,
        ]);
        Log::info('Send Email Response', ['response' => $response]);

    } catch (Exception $e) {
        Log::error('Failed to send email', ['error' => $e->getMessage()]);
    }
}


}
