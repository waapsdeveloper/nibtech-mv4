<?php

namespace App\Http\Controllers;

use App\Models\GoogleToken;
use Exception;
use Google_Client;
use Google_Service_Gmail;
use Google_Service_Gmail_Message;
use Illuminate\Http\Request;
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
        $client->setAccessType('offline');
        $client->setIncludeGrantedScopes(true);
        $client->addScope(Google_Service_Gmail::MAIL_GOOGLE_COM);
        $client->setPrompt('consent');

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

            if (! $refreshToken) {
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

        if (! $googleToken) {
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
                'access_token' => $newToken['access_token'],
            ]);
        }

        $service = new Google_Service_Gmail($client);

        $boundary = uniqid(rand(), true);
        $rawMessageString = "From: no-reply@nibritaintech.com\r\n";
        $rawMessageString .= "To: {$recipientEmail}\r\n";
        $rawMessageString .= "Subject: {$subject}\r\n";
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

        $rawMessage = strtr(base64_encode($rawMessageString), ['+' => '-', '/' => '_']);
        $message = new Google_Service_Gmail_Message();
        $message->setRaw($rawMessage);

        $service->users_messages->send('me', $message);
    }

    public function sendEmailInvoice($recipientEmail, $subject, Mailable $mailable)
    {
        $googleToken = GoogleToken::first();

        if (! $googleToken) {
            return redirect()->route('google.auth')->with('error', 'You need to authenticate with Google first.');
        }

        $client = new Google_Client();
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setRedirectUri(config('services.google.redirect_uri'));
        $client->setAccessType('offline');
        $client->setIncludeGrantedScopes(true);
        $client->addScope(Google_Service_Gmail::MAIL_GOOGLE_COM);
        $client->setAccessToken($googleToken->access_token);

        if ($client->isAccessTokenExpired()) {
            $client->fetchAccessTokenWithRefreshToken($googleToken->refresh_token);
            $googleToken->update([
                'access_token' => $client->getAccessToken()['access_token'],
            ]);
        }

        $service = new Google_Service_Gmail($client);
        $rawAttachments = $mailable->build()->rawAttachments ?? [];
        $pdfData = $rawAttachments[0]['data'] ?? null;
        $fileName = $rawAttachments[0]['name'] ?? null;
        $fileType = $rawAttachments[0]['options']['mime'] ?? null;

        $message = new Google_Service_Gmail_Message();
        $boundary = uniqid(rand(), true);
        $rawMessageString = "From: no-reply@nibritaintech.com\r\n";
        $rawMessageString .= "To: {$recipientEmail}\r\n";
        $rawMessageString .= "Subject: {$subject}\r\n";
        $rawMessageString .= "MIME-Version: 1.0\r\n";
        $rawMessageString .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n\r\n";
        $rawMessageString .= "--{$boundary}\r\n";
        $rawMessageString .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
        $rawMessageString .= $mailable->render() . "\r\n\r\n";

        if ($pdfData && $fileName && $fileType) {
            $rawMessageString .= "--{$boundary}\r\n";
            $rawMessageString .= "Content-Type: {$fileType}; name=\"{$fileName}\"\r\n";
            $rawMessageString .= "Content-Transfer-Encoding: base64\r\n";
            $rawMessageString .= "Content-Disposition: attachment; filename=\"{$fileName}\"\r\n\r\n";
            $rawMessageString .= chunk_split(base64_encode($pdfData)) . "\r\n\r\n";
        }

        $rawMessageString .= "--{$boundary}--";
        $rawMessage = strtr(base64_encode($rawMessageString), ['+' => '-', '/' => '_']);
        $message->setRaw($rawMessage);

        try {
            $response = $service->users_messages->send('me', $message);

            Log::info('Send Email Request Body', [
                'recipientEmail' => $recipientEmail,
                'subject' => $subject,
            ]);
            Log::info('Send Email Response', ['response' => $response]);
        } catch (Exception $e) {
            Log::error('Failed to send email', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Retrieve recent Gmail messages using the stored OAuth token.
     */
    public function readEmails(Request $request)
    {
        $googleToken = GoogleToken::first();

        if (! $googleToken) {
            return response()->json([
                'error' => 'Google account is not connected. Please authenticate first.',
            ], 400);
        }

        $client = new Google_Client();
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setRedirectUri(config('services.google.redirect_uri'));
        $client->setAccessType('offline');
        $client->setIncludeGrantedScopes(true);
        $client->addScope(Google_Service_Gmail::MAIL_GOOGLE_COM);
        $client->setAccessToken($googleToken->access_token);

        if ($client->isAccessTokenExpired()) {
            $newToken = $client->fetchAccessTokenWithRefreshToken($googleToken->refresh_token);

            if (isset($newToken['error'])) {
                Log::error('Failed to refresh access token while reading emails', [
                    'error' => $newToken['error'],
                ]);

                return response()->json([
                    'error' => 'Unable to refresh Google token. Please re-authenticate.',
                ], 400);
            }

            $googleToken->update([
                'access_token' => $newToken['access_token'],
            ]);
        }

        $service = new Google_Service_Gmail($client);

        $labelIds = $request->input('labelIds', ['INBOX']);
        if (! is_array($labelIds)) {
            $labelIds = array_filter([$labelIds]);
        }

        $maxResults = (int) $request->input('maxResults', 10);
        $maxResults = $maxResults > 0 ? min($maxResults, 100) : 10;

        $query = $request->input('query');
        $pageToken = $request->input('pageToken');

        $listParams = array_filter([
            'labelIds' => $labelIds,
            'maxResults' => $maxResults,
            'q' => $query,
            'pageToken' => $pageToken,
        ]);

        try {
            $messagesResponse = $service->users_messages->listUsersMessages('me', $listParams);
        } catch (Exception $e) {
            Log::error('Failed to fetch Gmail messages', [
                'error' => $e->getMessage(),
                'params' => $listParams,
            ]);

            return response()->json([
                'error' => 'Unable to fetch Gmail messages.',
            ], 500);
        }

        $messages = [];

        foreach ($messagesResponse->getMessages() ?? [] as $message) {
            try {
                $messageDetail = $service->users_messages->get('me', $message->getId(), [
                    'format' => 'metadata',
                    'metadataHeaders' => ['Subject', 'From', 'Date'],
                ]);
            } catch (Exception $e) {
                Log::warning('Failed to load Gmail message detail', [
                    'message_id' => $message->getId(),
                    'error' => $e->getMessage(),
                ]);
                continue;
            }

            $headers = [];
            $payloadHeaders = $messageDetail->getPayload()->getHeaders() ?? [];
            foreach ($payloadHeaders as $header) {
                $headers[$header->getName()] = $header->getValue();
            }

            $messages[] = [
                'id' => $message->getId(),
                'threadId' => $messageDetail->getThreadId(),
                'snippet' => $messageDetail->getSnippet(),
                'subject' => $headers['Subject'] ?? null,
                'from' => $headers['From'] ?? null,
                'date' => $headers['Date'] ?? null,
                'labelIds' => $messageDetail->getLabelIds(),
            ];
        }

        return response()->json([
            'messages' => $messages,
            'nextPageToken' => $messagesResponse->getNextPageToken(),
            'resultSizeEstimate' => $messagesResponse->getResultSizeEstimate(),
        ]);
    }
}
