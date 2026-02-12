<?php

namespace App\Http\Controllers;

use App\Models\GoogleToken;
use App\Models\Order_model;
use App\Services\RefurbedZendeskMailboxService;
use Exception;
use Google_Client;
use Google_Service_Gmail;
use Google_Service_Gmail_Message;
use Illuminate\Http\Request;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GoogleController extends Controller
{
    private const REFURBED_MARKETPLACE_ID = 4;
    protected RefurbedZendeskMailboxService $mailboxService;

    public function __construct(RefurbedZendeskMailboxService $mailboxService)
    {
        $this->mailboxService = $mailboxService;
    }

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
            throw new \RuntimeException('Google account not connected. Please authenticate Gmail to send invoices.');
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
            $service->users_messages->send('me', $message);
        } catch (Exception $e) {
            Log::error('Failed to send email via Gmail API', [
                'recipient' => $recipientEmail,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Failed to send email via Gmail: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Retrieve recent Gmail messages using the stored OAuth token.
     */
    public function readEmails(Request $request)
    {
        try {
            $result = $this->mailboxService->fetchMessages([
                'labelIds' => $request->input('labelIds', ['INBOX']),
                'maxResults' => $request->input('maxResults', 10),
                'query' => $request->input('query'),
                'pageToken' => $request->input('pageToken'),
            ]);
        } catch (RuntimeException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 400);
        } catch (Exception $e) {
            Log::error('Failed to fetch Gmail messages (API)', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Unable to fetch Gmail messages.',
            ], 500);
        }

        return response()->json($result);
    }

    public function showRefurbedInbox(Request $request)
    {
        $defaultQuery = 'subject:"refurbed inquiry" OR from:refurbed-merchant.zendesk.com';
        $query = $request->input('query', $defaultQuery);
        $labelIds = $request->input('labelIds', ['INBOX']);
        $maxResults = (int) $request->input('maxResults', 25);

        try {
            $result = $this->mailboxService->fetchMessages([
                'labelIds' => $labelIds,
                'maxResults' => $maxResults,
                'query' => $query,
                'pageToken' => $request->input('pageToken'),
            ]);
        } catch (RuntimeException $e) {
            return redirect()->route('index')->with('error', $e->getMessage());
        } catch (Exception $e) {
            Log::error('Failed to render Refurbed Gmail inbox', [
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('index')->with('error', 'Unable to load Gmail inbox at this time.');
        }

        return view('google.refurbed_inbox', [
            'messages' => $result['messages'],
            'query' => $query,
            'labelIds' => is_array($labelIds) ? $labelIds : array_filter([$labelIds]),
            'maxResults' => $maxResults,
            'nextPageToken' => $result['nextPageToken'],
            'resultSizeEstimate' => $result['resultSizeEstimate'],
            'pageToken' => $request->input('pageToken'),
        ]);
    }


    public function attachRefurbedTicket(Request $request)
    {
        $data = $request->validate([
            'ticket_link' => ['required', 'url'],
            'ticket_id' => ['nullable', 'string'],
            'order_reference' => ['required', 'string'],
            'order_item_reference' => ['nullable', 'string'],
            'apply_to_all_items' => ['nullable', 'boolean'],
        ]);

        $ticketId = $data['ticket_id'] ?: $this->mailboxService->parseRefurbedTicketId($data['ticket_link']);

        if (! $ticketId) {
            return redirect()->back()->with('error', 'Unable to detect the Zendesk ticket ID from the selected email.');
        }

        $orderReference = trim($data['order_reference']);
        $order = Order_model::with('order_items')->where('reference_id', $orderReference)->first();

        if (! $order) {
            return redirect()->back()->with('error', 'Order not found for reference ID ' . $orderReference . '.');
        }

        if ((int) $order->marketplace_id !== self::REFURBED_MARKETPLACE_ID) {
            return redirect()->back()->with('error', 'Selected order is not a Refurbed marketplace order.');
        }

        $orderItems = $order->order_items;

        if ($orderItems->isEmpty()) {
            return redirect()->back()->with('error', 'The Refurbed order does not have any order items yet.');
        }

        $orderItemReference = $data['order_item_reference'] ? trim($data['order_item_reference']) : null;
        $applyToAll = (bool) $request->boolean('apply_to_all_items');

        if (! $applyToAll && ! $orderItemReference && $orderItems->count() > 1) {
            return redirect()->back()->with('error', 'Provide the Refurbed order item ID or enable "Apply to every order item".');
        }

        if ($orderItemReference) {
            $itemsToUpdate = $orderItems->where('reference_id', $orderItemReference);

            if ($itemsToUpdate->isEmpty()) {
                return redirect()->back()->with('error', 'No order item matches the provided Refurbed order line ID.');
            }
        } elseif ($applyToAll) {
            $itemsToUpdate = $orderItems;
        } else {
            $itemsToUpdate = $orderItems->take(1);
        }

        $updatedCount = 0;

        foreach ($itemsToUpdate as $item) {
            $item->care_id = $ticketId;
            $item->save();
            $updatedCount++;
        }

        session()->put('success', 'Linked Refurbed ticket #' . $ticketId . ' to ' . $updatedCount . ' order item' . ($updatedCount === 1 ? '' : 's') . '.');
        session()->put('copy', $data['ticket_link']);

        return redirect()->back();
    }

}
