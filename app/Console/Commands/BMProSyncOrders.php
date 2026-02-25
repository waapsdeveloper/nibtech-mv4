<?php

namespace App\Console\Commands;

use App\Services\BMPro\BMProOrderSyncService;
use App\Models\CommandRunLog;
use App\Console\Commands\BaseCommand;

class BMProSyncOrders extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bmpro:orders
        {--currency=* : Limit sync to one or more currency codes (EUR, GBP)}
        {--marketplace=* : Limit sync to specific marketplace IDs}
        {--fulfillment= : Filter BMPRO orders by fulfillment status}
        {--financial= : Filter BMPRO orders by financial status}
        {--page-size=100 : Page size for each BMPRO API request (max 200)}
        {--page= : Request a specific BMPRO page (auto pagination disabled)}
        {--bmpro-env=prod : Target BMPRO environment (prod or dev)}
        {--no-auto-paginate : Disable automatic pagination across BMPRO responses}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize Back Market Pro orders into the unified orders table.';

    public function __construct(private BMProOrderSyncService $syncService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        CommandRunLog::recordStart('bmpro-orders');
        $pageSize = max(1, (int) $this->option('page-size'));
        $page = $this->option('page');

        $filters = array_filter([
            'fulfillment_status' => $this->option('fulfillment') !== null && $this->option('fulfillment') !== ''
                ? $this->option('fulfillment')
                : 'fulfilled',
            'financial_status' => $this->option('financial'),
            'page-size' => min($pageSize, 200),
            'page' => $page,
        ], fn ($value) => $value !== null && $value !== '');

        $autoPaginate = ! $this->option('no-auto-paginate');
        if ($page !== null) {
            $autoPaginate = false;
        }

        $options = [
            'currencies' => $this->option('currency'),
            'marketplace_ids' => $this->option('marketplace'),
            'environment' => $this->option('bmpro-env') ?? 'prod',
            'auto_paginate' => $autoPaginate,
        ];

        $summary = $this->syncService->sync($filters, $options);

        foreach ($summary as $result) {
            $status = ($result['success'] ?? true) ? 'OK' : 'ERROR';
            $message = $result['message'] ?? '';

            $this->line(vsprintf('[%s] marketplace=%s currency=%s processed=%d failed=%d %s', [
                $status,
                $result['marketplace_id'] ?? 'n/a',
                $result['currency'] ?? 'EUR',
                $result['processed'] ?? 0,
                $result['failed'] ?? 0,
                $message,
            ]));
        }

        $hasFailures = collect($summary)->contains(fn ($row) => ($row['failed'] ?? 0) > 0 || ! ($row['success'] ?? true));

        $totalProcessed = collect($summary)->sum(fn ($row) => $row['processed'] ?? 0);
        $totalFailed = collect($summary)->sum(fn ($row) => $row['failed'] ?? 0);
        $note = collect($summary)->map(fn ($r) => sprintf('mp=%s cur=%s ok=%d fail=%d', $r['marketplace_id'] ?? 'n/a', $r['currency'] ?? 'EUR', $r['processed'] ?? 0, $r['failed'] ?? 0))->implode('; ');
        CommandRunLog::recordEnd('bmpro-orders', (int) $totalProcessed, (int) ($totalProcessed - $totalFailed), (int) $totalFailed, $note, $hasFailures ? 'failed' : 'completed');

        if ($hasFailures) {
            $this->error('BMPRO order sync completed with errors.');

            return self::FAILURE;
        }

        $this->info('BMPRO order sync completed successfully.');

        return self::SUCCESS;
    }
}
