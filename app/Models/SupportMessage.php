<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'support_thread_id',
        'direction',
        'author_name',
        'author_email',
        'body_text',
        'body_html',
        'attachments',
        'external_message_id',
        'sent_at',
        'is_internal_note',
        'metadata',
    ];

    protected $casts = [
        'attachments' => 'array',
        'metadata' => 'array',
        'is_internal_note' => 'boolean',
        'sent_at' => 'datetime',
    ];

    protected $appends = ['clean_body_html', 'detected_links'];

    protected static array $bodyStartMarkers = [
        'Latest public message in the ticket:',
        'Latest message in the ticket:',
    ];

    protected static array $bodyEndMarkers = [
        'This email is a service',
        'View in Support',
        'Ticket-Id:',
        'Account-Subdomain:',
    ];

    protected static array $noiseLinePatterns = [
        '/^Open\s+Ticket/i',
        '/^Requester/i',
        '/^Assignee/i',
        '/^CCs/i',
        '/^Followers/i',
        '/^Group/i',
        '/^Organisation/i',
        '/^Brand/i',
        '/^Type/i',
        '/^Channel/i',
        '/^Priority/i',
        '/^View in Support/i',
        '/Ticket-Id:/i',
        '/Account-Subdomain:/i',
        '/^Link:/i',
        '/^Overdue on:/i',
        '/^refurbed takeover on:/i',
        '/^Health Status:/i',
        '/^Topic:/i',
        '/^Market:/i',
        '/^Order (ID|Item ID)/i',
        '/^Customer:/i',
        '/^This email is a service/i',
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(SupportThread::class, 'support_thread_id');
    }

    public function getCleanBodyHtmlAttribute(): string
    {
        $text = $this->prepareRawBody();

        if ($text === '') {
            return '';
        }

        $text = $this->clipToRelevantBody($text);
        $text = $this->removeNoiseLines($text);

        return nl2br(e($text));
    }

    public function getDetectedLinksAttribute(): array
    {
        $links = [];

        if ($this->body_html) {
            preg_match_all('/<a[^>]+href=["\"]([^"\"]+)["\"][^>]*>(.*?)<\/a>/is', $this->body_html, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $url = $this->normalizeLinkUrl($match[1] ?? '');
                if (! $url) {
                    continue;
                }
                $label = trim(strip_tags($match[2] ?? '')) ?: $url;
                $links[$url] = $label;
            }
        }

        $text = $this->prepareRawBody();
        if ($text !== '') {
            preg_match_all('/https?:\/\/[^\s<>]+/i', $text, $textMatches);
            foreach ($textMatches[0] as $candidate) {
                $url = $this->normalizeLinkUrl($candidate);
                if (! $url) {
                    continue;
                }

                if (! isset($links[$url])) {
                    $links[$url] = $url;
                }
            }
        }

        return collect($links)
            ->map(fn ($label, $url) => ['url' => $url, 'label' => $label])
            ->values()
            ->all();
    }

    protected function prepareRawBody(): string
    {
        $source = $this->body_text;

        if (! $source && $this->body_html) {
            $source = $this->convertHtmlToText($this->body_html);
        }

        if (! $source) {
            return '';
        }

        $source = str_replace(["\r\n", "\r"], "\n", $source);
        $source = preg_replace('/\n{3,}/', "\n\n", $source);

        return trim($source);
    }

    protected function convertHtmlToText(string $html): string
    {
        $normalized = str_replace(['<br>', '<br/>', '<br />'], "\n", $html);
        $normalized = preg_replace('/<(\/?)(p|div|table|tr)[^>]*>/', "\n", $normalized);
        $normalized = strip_tags($normalized);

        return trim(html_entity_decode($normalized, ENT_QUOTES | ENT_HTML5));
    }

    protected function clipToRelevantBody(string $text): string
    {
        $startPosition = null;
        foreach (self::$bodyStartMarkers as $marker) {
            $pos = stripos($text, $marker);
            if ($pos !== false) {
                $candidate = $pos + strlen($marker);
                if ($startPosition === null || $candidate < $startPosition) {
                    $startPosition = $candidate;
                }
            }
        }

        if ($startPosition !== null) {
            $text = ltrim(substr($text, $startPosition));
        }

        $endPosition = null;
        foreach (self::$bodyEndMarkers as $marker) {
            $pos = stripos($text, $marker);
            if ($pos !== false) {
                if ($endPosition === null || $pos < $endPosition) {
                    $endPosition = $pos;
                }
            }
        }

        if ($endPosition !== null) {
            $text = substr($text, 0, $endPosition);
        }

        return trim($text);
    }

    protected function removeNoiseLines(string $text): string
    {
        $lines = array_map('trim', preg_split('/\n/', $text));
        $filtered = [];

        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }

            $isNoise = false;
            foreach (self::$noiseLinePatterns as $pattern) {
                if (preg_match($pattern, $line)) {
                    $isNoise = true;
                    break;
                }
            }

            if (! $isNoise) {
                $filtered[] = $line;
            }
        }

        return trim(implode("\n", $filtered));
    }

    protected function normalizeLinkUrl(?string $url): ?string
    {
        if (! is_string($url)) {
            return null;
        }

        $clean = trim($url);
        $clean = rtrim($clean, '.,)');

        if ($clean === '') {
            return null;
        }

        if (! str_starts_with($clean, ['http://', 'https://'])) {
            $clean = 'https://' . ltrim($clean, '/');
        }

        return filter_var($clean, FILTER_VALIDATE_URL) ? $clean : null;
    }
}
