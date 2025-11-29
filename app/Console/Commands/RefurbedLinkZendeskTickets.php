<?php

namespace App\Console\Commands;

use App\Services\RefurbedZendeskTicketLinkService;
use Illuminate\Console\Command;

class RefurbedLinkZendeskTickets extends Command
{
    protected $signature = 'refurbed:link-tickets
        {--query= : Override the Gmail search query}
        {--label=* : One or more Gmail label IDs to filter}
        {--max-results= : Maximum number of Gmail messages to fetch}
        {--max-age-minutes= : Ignore emails older than this many minutes}
        {--max-pages= : Maximum Gmail pages to scan (0 = all)}
        {--force : Process messages even if they were handled before}';

    protected $description = 'Automatically attach Refurbed Zendesk tickets to their corresponding order items.';

    public function __construct(protected RefurbedZendeskTicketLinkService $linkService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $options = array_filter([
            'query' => $this->option('query') ?: null,
            'labelIds' => $this->labelsOption(),
            'maxResults' => $this->option('max-results') !== null ? (int) $this->option('max-results') : null,
            'max_age_minutes' => $this->option('max-age-minutes') !== null ? (int) $this->option('max-age-minutes') : null,
            'max_pages' => $this->option('max-pages') !== null ? (int) $this->option('max-pages') : null,
            'force' => (bool) $this->option('force'),
        ], function ($value) {
            if (is_array($value)) {
                return count($value) > 0;
            }

            return $value !== null && $value !== '' && $value !== false;
        });

        $stats = $this->linkService->autoLink($options);

        $this->info(sprintf(
            'Processed %d email(s) across %d page(s); linked %d ticket(s); skipped %d; ignored %d.',
            $stats['processed'],
            $stats['pages_processed'] ?? 1,
            $stats['linked'],
            $stats['skipped'],
            $stats['ignored'],
        ));

        if (! empty($stats['result_size_estimate'])) {
            $this->line(sprintf(
                'Gmail result size estimate: %d (max %d per page).',
                $stats['result_size_estimate'],
                $stats['max_results_per_page'] ?? 50,
            ));
        }

        $details = $stats['details'] ?? [];
        if (! empty($details)) {
            $rows = array_map(function ($detail) {
                return [
                    $detail['message_id'] ?? '-',
                    $detail['ticket_id'] ?? '-',
                    $detail['order_reference'] ?? '-',
                    $detail['order_item_reference'] ?? '-',
                    $detail['status'] ?? '-',
                    $detail['updated'] ?? 0,
                ];
            }, array_slice($details, 0, 15));

            $this->table(['Message', 'Ticket', 'Order', 'Order Item', 'Status', 'Updated'], $rows);

            if (count($details) > 15) {
                $this->line(sprintf('... %d additional rows omitted', count($details) - 15));
            }
        }

        return Command::SUCCESS;
    }

    protected function labelsOption(): ?array
    {
        $labels = $this->option('label');
        if (! is_array($labels) || empty($labels)) {
            return null;
        }

        return array_values(array_unique(array_filter($labels)));
    }
}
