<?php

namespace App\Mail;

use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\RawMessage;
use Google_Client;
use Google_Service_Gmail;
use Google_Service_Gmail_Message;
use Illuminate\Support\Facades\Log;

class GmailTransport implements TransportInterface
{
    protected $client;

    public function __construct(Google_Client $client)
    {
        $this->client = $client;
    }

    public function send(RawMessage $message, Envelope $envelope = null): ?SentMessage
    {
        $service = new Google_Service_Gmail($this->client);

        $data = $message->toString();
        $encodedMessage = rtrim(strtr(base64_encode($data), '+/', '-_'), '=');

        $gmailMessage = new Google_Service_Gmail_Message();
        $gmailMessage->setRaw($encodedMessage);

        try {
            $service->users_messages->send('me', $gmailMessage);
        } catch (\Exception $e) {
            Log::error('Failed to send email via Gmail API: ' . $e->getMessage());
            throw new TransportExceptionInterface($e->getMessage(), $e->getCode(), $e);
        }

        return new SentMessage($message, $envelope);
    }

    public function __toString(): string
    {
        return 'gmail';
    }
}
