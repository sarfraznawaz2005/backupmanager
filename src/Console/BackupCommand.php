<?php

namespace Sarfraznawaz2005\BackupManager\Console;

use Illuminate\Console\Command;
use Log;
use Sarfraznawaz2005\BackupManager\Facades\BackupManager;

class BackupCommand extends Command
{
    protected $signature = 'backupmanager:create';
    protected $description = 'Creates backup of files and/or database.';

    public function handle()
    {
        $result = BackupManager::createBackup();

        // set status messages
        if ($result['f'] === true) {
            $message = 'Files Backup Taken Successfully';
            $this->info($message);
            Log::info($message);
        } else {
            if (config('backupmanager.backups.files.enable')) {
                $message = 'Files Backup Failed';
                $this->error($message);
                Log::error($message);
            }
        }

        if ($result['d'] === true) {
            $message = 'Database Backup Taken Successfully';
            $this->info($message);
            Log::info($message);
        } else {
            if (config('backupmanager.backups.database.enable')) {
                $message = 'Database Backup Failed';
                $this->error($message);
                Log::error($message);
            }
        }
    }

}
