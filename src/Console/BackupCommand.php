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
        
        try {
            if ($argument===null) {
                $result = BackupManager::createBackup();
            }elseif($argument==='files'){
                $result = BackupManager::backupFiles(true);
            }else{
                $result = BackupManager::backupDatabase(true);
            }
        } catch (\Exception $e) {
            $this->error('Backup process failed with error:');
            $this->error($e->getMessage());
            $this->newLine();
            $this->warn('Check the logs for more details: storage/logs/laravel.log');
            Log::error('Backup command failed: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }

        // Display verification errors if present
        if (isset($result['errors']) && is_array($result['errors'])) {
            $this->newLine();
            $this->error('Backup Verification Errors:');
            foreach ($result['errors'] as $error) {
                $this->error('  • ' . $error);
            }
            $this->newLine();
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
                
                // Display detailed error if available
                if (isset($result['error'])) {
                    $this->error('Error: ' . $result['error']);
                    $this->newLine();
                }
                
                $this->warn('Check the logs for detailed error information: storage/logs/laravel.log');
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
                
                // Display detailed error if available
                if (isset($result['error'])) {
                    $this->error('Error: ' . $result['error']);
                    $this->newLine();
                }
                
                $this->warn('Check the logs for detailed error information: storage/logs/laravel.log');
                Log::error($message);
            }
        }
        
        return 0;
    }

}
