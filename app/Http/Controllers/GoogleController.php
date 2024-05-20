<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Google_Client;
use Google_Service_Gmail;
use Google_Service_Gmail_Message;
use App\Models\GoogleToken;
use Swift_Attachment;

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
        dd($token);
        GoogleToken::updateOrCreate(
            ['user_id' => auth()->id()],
            [
                'access_token' => $token['access_token'],
                'refresh_token' => $token['refresh_token'],
            ]
        );

        return redirect()->route('home')->with('success', 'Google OAuth Token stored successfully!');
    }

    public function sendEmail($recipientEmail, $subject, $body, $attachments = [])
    {
        $googleToken = GoogleToken::where('user_id', auth()->id())->first();

        if (!$googleToken) {
            return redirect()->route('google.auth')->with('error', 'You need to authenticate with Google first.');
        }

        $client = new Google_Client();
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setRedirectUri(config('services.google.redirect_uri'));
        $client->setAccessToken($googleToken->access_token);

        if ($client->isAccessTokenExpired()) {
            $client->fetchAccessTokenWithRefreshToken($googleToken->refresh_token);
            $googleToken->update([
                'access_token' => $client->getAccessToken()['access_token']
            ]);
        }

        $service = new Google_Service_Gmail($client);

        $boundary = uniqid(rand(), true);
        $rawMessageString = "From: your-email@gmail.com\r\n";
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
}
