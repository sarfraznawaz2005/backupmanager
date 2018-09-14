<?php

namespace Sarfraznawaz2005\BackupManager\Console;

use Illuminate\Console\Command;
use Sarfraznawaz2005\BackupManager\Facades\BackupManager;

class BackupListCommand extends Command
{
    protected $signature = 'backupmanager:list';
    protected $description = 'Shows list of backups taken.';

    public function handle()
    {
        $tableData = BackupManager::getBackups();

        $headers = ['Name', 'Size', 'Type', 'Date'];

        $this->table($headers, $tableData);
    }
}
