<?php

namespace App\Mail;

use Google_Client;
use Google_Service_Gmail;
use Google_Service_Gmail_Message;
use Illuminate\Mail\Transport\Transport;
use Swift_Mime_SimpleMessage;

class GmailTransport extends Transport
{
    protected $client;

    public function __construct(Google_Client $client)
    {
        $this->client = $client;
    }

    public function send(Swift_Mime_SimpleMessage $message, &$failedRecipients = null)
    {
        $service = new Google_Service_Gmail($this->client);

        $data = $message->toString();
        $encodedMessage = rtrim(strtr(base64_encode($data), '+/', '-_'), '=');

        $gmailMessage = new Google_Service_Gmail_Message();
        $gmailMessage->setRaw($encodedMessage);

        try {
            $service->users_messages->send('me', $gmailMessage);
        } catch (\Exception $e) {
            \Log::error('Failed to send email via Gmail API: ' . $e->getMessage());
            throw $e;
        }
    }
}
