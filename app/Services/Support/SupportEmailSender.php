<?php

namespace App\Services\Support;

use App\Models\GoogleToken;
use Google_Client;
use Google_Service_Gmail;
use Google_Service_Gmail_Message;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class SupportEmailSender
{
    /**
     * Send a HTML email via the connected Gmail API account.
     */
    public function sendHtml(string $recipient, string $subject, string $htmlBody, array $attachments = []): void
    {
        if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('A valid recipient email is required.');
        }

        $token = GoogleToken::first();

        if (! $token) {
            throw new RuntimeException('Google account is not connected. Please authenticate first.');
        }

        $client = new Google_Client();
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setRedirectUri(config('services.google.redirect_uri'));
        $client->setAccessToken($token->access_token);
        $client->setAccessType('offline');
        $client->setIncludeGrantedScopes(true);

        if ($client->isAccessTokenExpired()) {
            $newToken = $client->fetchAccessTokenWithRefreshToken($token->refresh_token);

            if (isset($newToken['error'])) {
                throw new RuntimeException('Unable to refresh Google access token. Please reconnect Gmail.');
            }

            $token->update([
                'access_token' => $newToken['access_token'],
            ]);

            $client->setAccessToken($newToken['access_token']);
        }

        $service = new Google_Service_Gmail($client);
        $boundary = uniqid('support_reply_', true);

        $rawMessage = "From: no-reply@nibritaintech.com\r\n";
        $rawMessage .= "To: {$recipient}\r\n";
        $rawMessage .= 'Subject: ' . $this->encodeHeader($subject) . "\r\n";
        $rawMessage .= "MIME-Version: 1.0\r\n";
        $rawMessage .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n\r\n";
        $rawMessage .= "--{$boundary}\r\n";
        $rawMessage .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
        $rawMessage .= strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody)) . "\r\n\r\n";
        $rawMessage .= "--{$boundary}\r\n";
        $rawMessage .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
        $rawMessage .= $htmlBody . "\r\n\r\n";

        foreach ($attachments as $attachment) {
            if (! isset($attachment['data'], $attachment['name'], $attachment['mime'])) {
                continue;
            }

            $rawMessage .= "--{$boundary}\r\n";
            $rawMessage .= "Content-Type: {$attachment['mime']}; name=\"{$attachment['name']}\"\r\n";
            $rawMessage .= "Content-Transfer-Encoding: base64\r\n";
            $rawMessage .= "Content-Disposition: attachment; filename=\"{$attachment['name']}\"\r\n\r\n";
            $rawMessage .= chunk_split(base64_encode($attachment['data'])) . "\r\n\r\n";
        }

        $rawMessage .= "--{$boundary}--";

        $gmailMessage = new Google_Service_Gmail_Message();
        $gmailMessage->setRaw($this->base64UrlEncode($rawMessage));

        try {
            $service->users_messages->send('me', $gmailMessage);
        } catch (Throwable $exception) {
            Log::error('Failed to send support reply via Gmail', [
                'recipient' => $recipient,
                'subject' => $subject,
                'error' => $exception->getMessage(),
            ]);

            throw new RuntimeException('Gmail rejected the message. Please try again.');
        }
    }

    protected function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    protected function encodeHeader(string $value): string
    {
        return sprintf('=?UTF-8?B?%s?=', base64_encode($value));
    }
}
