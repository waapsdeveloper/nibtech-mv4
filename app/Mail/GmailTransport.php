<?php

namespace App\Mail;

use Google_Client;
use Google_Service_Gmail;
use Google_Service_Gmail_Message;
use Swift_Mime_SimpleMessage;
use Swift_Transport;

class GmailTransport implements Swift_Transport
{
    protected $client;

    public function __construct(Google_Client $client)
    {
        $this->client = $client;
    }

    public function isStarted()
    {
        return true;
    }

    public function start()
    {
        // No action needed to start the transport
    }

    public function stop()
    {
        // No action needed to stop the transport
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

    public function registerPlugin(Swift_Events_EventListener $plugin)
    {
        // No action needed to register plugins
    }
}
