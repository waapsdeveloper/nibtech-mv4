<?php

namespace App\Console\Commands;

use App\Http\Controllers\GoogleController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class BackupAndEmail extends Command
{
    protected $signature = 'backup:email';
    protected $description = 'Backup the database and email';

    public function handle()
    {
        // Get the current timestamp
        $timestamp = Carbon::now()->format('Y-m-d_H-i-s');

        // File path for the backup SQL file
        $backupFile = "backup_$timestamp.sql";
        $backupPath = storage_path("app/backups/$backupFile");

        // Prepare the mysqldump command
        $command = sprintf(
            'mysqldump --column-statistics=0 --user=%s --password=%s --host=%s --port=%s %s > %s',
            escapeshellarg(env('DB_USERNAME')),
            escapeshellarg(env('DB_PASSWORD')),
            escapeshellarg(env('DB_HOST')),
            escapeshellarg(env('DB_PORT')),
            escapeshellarg(env('DB_DATABASE')),
            $backupPath
        );

        // Execute the command
        // dd($command);
        $result = shell_exec($command);
        // if($result == null){
        //     // Delete the backup file after sending email

        //     $this->info("Incorrect Path $backupFile");
        //     unlink($backupPath);

        //     die;
        // }

        // Send email with the backup file attached
        Mail::raw('Database Backup', function ($message) use ($backupFile, $backupPath) {
            $message->to('wethesd@gmail.com')
                    ->subject('Database Backup ' . $backupFile)
                    ->attach($backupPath);
        });

        $recipientEmail = 'wethesd@gmail.com';
        $subject = 'Database Backup ' . $backupFile;
        $body = 'Here is your Backup for the recent purchase.';
        $attachments = [
            $backupPath,
            // storage_path('app/other_attachments/somefile.pdf')
        ];

        app(GoogleController::class)->sendEmail($recipientEmail, $subject, $body, $attachments);
        // Delete the backup file after sending email
        // unlink($backupPath);

        $this->info("Database backup created and emailed: $backupFile");
    }
    public function sendInvoice($order)
    {
        app(GoogleController::class)->sendEmail($order);
    }

}
