<?php

namespace Sarfraznawaz2005\BackupManager\Console;

use Illuminate\Console\Command;
use Log;
use Sarfraznawaz2005\BackupManager\Facades\BackupManager;

class BackupCommand extends Command
{

    //added option for --only=files, --only=db
    protected $signature = 'backupmanager:create {--only=}';
    protected $description = 'Creates backup of files and/or database.';

    public function handle()
    {
        $argument = $this->option('only');
        if ($argument!==null && !in_array($argument,['db','files']) ) {
            return $this->info('You can only select "files" or "db" argument!');
        }
        if ($argument===null) {
            $result = BackupManager::createBackup();
        }elseif($argument==='files'){
            $result = BackupManager::backupFiles(true);
        }else{
            $result = BackupManager::backupDatabase(true);
        }

        // set status messages
        if (isset($result['f']) && $result['f'] === true) {
            $message = 'Files Backup Taken Successfully';
            $this->info($message);
            Log::info($message);
        } elseif(isset($result['f']) && $result['f'] === false) {
            if (config('backupmanager.backups.files.enable')) {
                $message = 'Files Backup Failed';
                $this->error($message);
                Log::error($message);
            }
        }

        if (isset($result['d']) && $result['d'] === true) {
            $message = 'Database Backup Taken Successfully';
            $this->info($message);
            Log::info($message);
        } elseif(isset($result['d']) && $result['d'] === false) {
            if (config('backupmanager.backups.database.enable')) {
                $message = 'Database Backup Failed';
                $this->error($message);
                Log::error($message);
            }
        }
    }

}
