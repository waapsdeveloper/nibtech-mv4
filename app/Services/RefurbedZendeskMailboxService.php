<?php

namespace App\Services;

use App\Models\GoogleToken;
use Exception;
use Google_Client;
use Google_Service_Gmail;
use Google_Service_Gmail_Message;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class RefurbedZendeskMailboxService
{
    /**
     * Fetch Gmail messages matching the provided options.
     *
     * @param  array  $options
     * @return array{messages: array<int, array<string, mixed>>, nextPageToken: ?string, resultSizeEstimate: int}
     */
    public function fetchMessages(array $options = []): array
    {
        $service = $this->makeGmailService();

        $labelIds = $options['labelIds'] ?? ['INBOX'];
        if (! is_array($labelIds)) {
            $labelIds = array_filter([$labelIds]);
        }

        $maxResults = (int) ($options['maxResults'] ?? 10);
        $maxResults = $maxResults > 0 ? min($maxResults, 100) : 10;

        $query = $options['query'] ?? null;
        $pageToken = $options['pageToken'] ?? null;
        $includeBody = (bool) ($options['include_body'] ?? false);

        $listParams = array_filter([
            'labelIds' => $labelIds,
            'maxResults' => $maxResults,
            'q' => $query,
            'pageToken' => $pageToken,
        ], function ($value) {
            if (is_array($value)) {
                return count($value) > 0;
            }

            return $value !== null && $value !== '';
        });

        try {
            $messagesResponse = $service->users_messages->listUsersMessages('me', $listParams);
        } catch (Exception $e) {
            Log::error('Failed to fetch Gmail messages', [
                'error' => $e->getMessage(),
                'params' => $listParams,
            ]);

            throw new RuntimeException('Unable to fetch Gmail messages at this time.');
        }

        $messages = [];

        foreach ($messagesResponse->getMessages() ?? [] as $message) {
            try {
                $messageDetail = $service->users_messages->get('me', $message->getId(), [
                    'format' => 'full',
                    'metadataHeaders' => ['Subject', 'From', 'Date'],
                ]);
            } catch (Exception $e) {
                Log::warning('Failed to load Gmail message detail', [
                    'message_id' => $message->getId(),
                    'error' => $e->getMessage(),
                ]);
                continue;
            }

            $headers = $this->extractHeaders($messageDetail);
            $payload = $messageDetail->getPayload();

            $ticketLink = $this->extractRefurbedTicketLink($payload, $messageDetail->getSnippet());
            $ticketId = $this->parseRefurbedTicketId($ticketLink);
            $body = $includeBody ? $this->extractBodyContent($payload) : ['text' => null, 'html' => null];

            $messages[] = [
                'id' => $message->getId(),
                'threadId' => $messageDetail->getThreadId(),
                'snippet' => $messageDetail->getSnippet(),
                'subject' => $headers['Subject'] ?? null,
                'from' => $headers['From'] ?? null,
                'date' => $headers['Date'] ?? null,
                'labelIds' => $messageDetail->getLabelIds(),
                'ticketLink' => $ticketLink,
                'ticketId' => $ticketId,
                'bodyText' => $body['text'],
                'bodyHtml' => $body['html'],
            ];
        }

        return [
            'messages' => $messages,
            'nextPageToken' => $messagesResponse->getNextPageToken(),
            'resultSizeEstimate' => $messagesResponse->getResultSizeEstimate(),
        ];
    }

    public function parseRefurbedTicketId(?string $ticketLink): ?string
    {
        if (! $ticketLink) {
            return null;
        }

        if (preg_match('/tickets\/(\d+)/', $ticketLink, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public function extractRefurbedTicketLink($payload, ?string $snippet = null): ?string
    {
        $link = $this->findZendeskLinkInText($snippet);

        if ($link) {
            return $link;
        }

        if ($payload === null) {
            return null;
        }

        return $this->findZendeskLinkInBody($payload);
    }

    public function findZendeskLinkInText(?string $text): ?string
    {
        if (! $text) {
            return null;
        }

        if (preg_match('/(https?:\/\/)?(refurbed-merchant\.zendesk\.com\/agent\/tickets\/\d+)/i', $text, $matches)) {
            $url = $matches[0];
            if (! preg_match('/^https?:\/\//i', $url)) {
                $url = 'https://' . $matches[2];
            }
            return $url;
        }

        if (preg_match('/Link:\s*(https?:\/\/)?(refurbed-merchant\.zendesk\.com\/agent\/tickets\/\d+)/i', $text, $matches)) {
            $url = str_ireplace('Link:', '', $matches[0]);
            $url = trim($url);
            if (! preg_match('/^https?:\/\//i', $url)) {
                $url = 'https://' . trim($matches[2]);
            }
            return $url;
        }

        return null;
    }

    protected function findZendeskLinkInBody($payload): ?string
    {
        if ($payload === null) {
            return null;
        }

        $body = $payload->getBody();
        if ($body && $body->getSize() > 0) {
            $decoded = $this->decodeBody($body->getData());
            if ($decoded) {
                $link = $this->findZendeskLinkInText($decoded);
                if ($link) {
                    return $link;
                }
            }
        }

        foreach ($payload->getParts() ?? [] as $part) {
            $link = $this->findZendeskLinkInBody($part);
            if ($link) {
                return $link;
            }
        }

        return null;
    }

    /**
     * @param  Google_Service_Gmail_Message  $message
     * @return array<string, string>
     */
    protected function extractHeaders(Google_Service_Gmail_Message $message): array
    {
        $headers = [];
        $payloadHeaders = $message->getPayload()->getHeaders() ?? [];
        foreach ($payloadHeaders as $header) {
            $headers[$header->getName()] = $header->getValue();
        }

        return $headers;
    }

    /**
     * Extract plain text and HTML body strings.
     *
     * @return array{text: ?string, html: ?string}
     */
    protected function extractBodyContent($payload): array
    {
        if ($payload === null) {
            return ['text' => null, 'html' => null];
        }

        $plainParts = [];
        $htmlParts = [];
        $this->collectBodyParts($payload, $plainParts, $htmlParts);

        $plain = $this->normalizeBodyString($plainParts);
        $html = $this->normalizeBodyString($htmlParts);

        if ($plain === null && $html !== null) {
            $plain = trim(preg_replace('/\s+/', ' ', strip_tags($html))) ?: null;
        }

        return [
            'text' => $plain,
            'html' => $html,
        ];
    }

    protected function collectBodyParts($part, array &$plainParts, array &$htmlParts): void
    {
        $mimeType = $part->getMimeType();
        $data = $part->getBody()?->getData();
        $decoded = $data ? $this->decodeBody($data) : null;

        if ($decoded !== null && $mimeType) {
            if (stripos($mimeType, 'text/plain') === 0) {
                $plainParts[] = $decoded;
            } elseif (stripos($mimeType, 'text/html') === 0) {
                $htmlParts[] = $decoded;
            } elseif (! $part->getParts()) {
                $plainParts[] = $decoded;
            }
        }

        foreach ($part->getParts() ?? [] as $child) {
            $this->collectBodyParts($child, $plainParts, $htmlParts);
        }
    }

    protected function normalizeBodyString(array $parts): ?string
    {
        if (empty($parts)) {
            return null;
        }

        $merged = trim(implode("\n\n", array_filter(array_map(function ($text) {
            return trim($text);
        }, $parts))));

        return $merged === '' ? null : $merged;
    }

    protected function decodeBody(?string $data): ?string
    {
        if (! $data) {
            return null;
        }

        $decoded = base64_decode(strtr($data, '-_', '+/'));
        if ($decoded === false) {
            return null;
        }

        return $decoded;
    }

    protected function makeGmailService(): Google_Service_Gmail
    {
        $googleToken = GoogleToken::first();

        if (! $googleToken) {
            throw new RuntimeException('Google account is not connected. Please authenticate first.');
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

                throw new RuntimeException('Unable to refresh Google token. Please re-authenticate.');
            }

            $googleToken->update([
                'access_token' => $newToken['access_token'],
            ]);

            $client->setAccessToken($newToken);
        }

        return new Google_Service_Gmail($client);
    }
}
