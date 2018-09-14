<?php

namespace Sarfraznawaz2005\BackupManager\Console;

use Illuminate\Console\Command;
use Log;
use Sarfraznawaz2005\BackupManager\Facades\BackupManager;
use Storage;

class BackupRestoreCommand extends Command
{
    protected $signature = 'backupmanager:restore';
    protected $description = 'Restores a backup already taken.';

    // other vars
    protected $disk = '';
    protected $backupPath = '';

    public function __construct()
    {
        $this->disk = config('backupmanager.backups.disk');
        $this->backupPath = config('backupmanager.backups.backup_path') . DIRECTORY_SEPARATOR;

        parent::__construct();
    }

    public function handle()
    {
        $tableData = BackupManager::getBackups();

        $headers = ['Name', 'Size', 'Type', 'Date'];

        // show available backups
        $this->table($headers, $tableData);

        // ask for backup file
        $backupFilename = $this->ask('Which file would you like to restore?');

        if (!Storage::disk($this->disk)->exists($this->backupPath . $backupFilename)) {
            $this->error('Specified backup file does not exist.');
            return false;
        }

        $results = BackupManager::restoreBackups([$backupFilename]);

        foreach ($results as $result) {
            if (isset($result['f'])) {
                if ($result['f'] === true) {
                    $message = 'Files Backup Restored Successfully';

                    $this->info($message);
                    Log::info($message);
                } else {
                    $message = 'Files Restoration Failed';

                    $this->error($message);
                    Log::error($message);
                }
            } elseif (isset($result['d'])) {
                if ($result['d'] === true) {
                    $message = 'Database Backup Restored Successfully';

                    $this->info($message);
                    Log::info($message);
                } else {
                    $message = 'Database Restoration Failed';

                    $this->error($message);
                    Log::error($message);
                }
            }
        }
    }
}
