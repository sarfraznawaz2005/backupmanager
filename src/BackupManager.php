<?php

namespace Sarfraznawaz2005\BackupManager;

use App;
use DB;
use Log;
use Storage;
use Carbon\Carbon;

class BackupManager
{
    protected $disk = '';
    protected $backupPath;
    protected $backupSuffix;
    protected $fBackupName;
    protected $dBackupName;
    protected $fileVerifyName = 'backup-verify';

    /**
     * BackupManager constructor.
     */
    public function __construct()
    {
        $this->disk = config('backupmanager.backups.disk');
        $this->backupPath = config('backupmanager.backups.backup_path') . DIRECTORY_SEPARATOR;
        $this->backupSuffix = date(strtolower(config('backupmanager.backups.backup_file_date_suffix')));
        $this->fBackupName = "f_$this->backupSuffix.tar";
        $this->dBackupName = "d_$this->backupSuffix.gz";

        $this->mysql = config('backupmanager.paths.mysql', 'mysql');
        $this->mysqldump = config('backupmanager.paths.mysqldump', 'mysqldump');
        $this->tar = config('backupmanager.paths.tar', 'tar');
        $this->zcat = config('backupmanager.paths.zcat', 'zcat');

        Storage::disk($this->disk)->makeDirectory($this->backupPath);
    }

    /**
     * Gets list of backups
     */
    public function getBackups()
    {
        $files = Storage::disk($this->disk)->listContents($this->backupPath);

        $filesData = [];
        foreach ($files as $index => $file) {
            if ($file instanceof \League\Flysystem\FileAttributes) {
                if (!empty($file->path())) {
                    $name = $file->path();
                    $name = substr(str_replace(config('backupmanager.backups.backup_path'), '', $file->path()), 1);
                } else {
                    $name = $file->extraMetadata()['filename'] . "." . $file->extraMetadata()['extension'];
                }
                $array = explode('_', $name);
                $filesData[] = [
                    'name' => $name,
                    'size_raw' => $file->fileSize(),
                    'size' => $this->formatSizeUnits($file->fileSize()),
                    'type' => $array[0] === 'd' ? 'Database' : 'Files',
                    'date' => date('M d Y', $this->getFileTimeStamp($file))
                ];
            } else {
                $filesData[] = [
                    'name' => $file['basename'],
                    'size_raw' => $file['size'],
                    'size' => $this->formatSizeUnits($file['size']),
                    'type' => $file['basename'][0] === 'd' ? 'Database' : 'Files',
                    'date' => date('M d Y', $this->getFileTimeStamp($file))
                ];
            }
        }

        // sort by date
        $filesData = collect($filesData)->sortByDesc(function ($temp, $key) {
            return Carbon::parse($temp['date'])->getTimestamp();
        })->all();

        return array_values($filesData);
    }

    /**
     * Creates new backup
     *
     * @return array
     */
    public function createBackup()
    {
        ini_set('memory_limit', '-1');
        set_time_limit(0);

        Log::info('Starting backup process...');

        try {
            $filesResult = $this->backupFiles();
            $databaseResult = $this->backupDatabase();
            $this->deleteOldBackups();
            
            $status = $this->getBackupStatus();
            
            if ($status['f'] && $status['d']) {
                Log::info('Backup completed successfully');
            } else {
                Log::warning('Backup completed with issues', $status);
                
                // Log specific verification errors
                if (isset($status['errors'])) {
                    foreach ($status['errors'] as $error) {
                        Log::error('Backup verification error: ' . $error);
                    }
                }
            }
            
            return $status;
        } catch (\Exception $e) {
            Log::error('Backup process failed: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'f' => false,
                'd' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Restores database|fiels backups.
     * @param array $files
     * @return array|bool
     */
    public function restoreBackups(array $files)
    {
        ini_set('memory_limit', '-1');
        set_time_limit(0);

        Log::info('Starting restore process...', ['files' => $files]);
        $restoreStatus = [];

        foreach ($files as $file) {
            $isFiles = $file[0] === 'f';

            try {
                if ($isFiles) {
                    Log::info('Restoring files from: ' . $file);
                    $this->restoreFiles($file);
                } else {
                    Log::info('Restoring database from: ' . $file);
                    $this->restoreDatabase($file);
                }

                $status = $this->getRestoreStatus($isFiles);
                $restoreStatus[] = $status;
                
                if (($isFiles && $status['f']) || (!$isFiles && $status['d'])) {
                    Log::info('Restore successful for: ' . $file);
                } else {
                    Log::error('Restore failed for: ' . $file, $status);
                }
                
            } catch (\Exception $e) {
                Log::error('Restore failed for ' . $file . ': ' . $e->getMessage(), [
                    'exception' => $e,
                    'trace' => $e->getTraceAsString()
                ]);
                
                $restoreStatus[] = [
                    ($isFiles ? 'f' : 'd') => false,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $restoreStatus;
    }

    public function deleteBackups(array $files)
    {
        $status = false;

        foreach ($files as $file) {
            $status = Storage::disk($this->disk)->delete($this->backupPath . $file);
        }

        return $status;
    }

    /**
     * Backup files
     */
    public function backupFiles($bypass = false)
    {
        if (config('backupmanager.backups.files.enable') || $bypass === true) {
            
            Log::info('Starting files backup...');
            
            try {
                // Check if tar command exists
                if (!$this->commandExists($this->tar)) {
                    throw new \Exception("Tar command not found: {$this->tar}. Please install tar or update the path in config.");
                }

                // delete previous backup for same date
                if (Storage::disk($this->disk)->exists($this->backupPath . $this->fBackupName)) {
                    Storage::disk($this->disk)->delete($this->backupPath . $this->fBackupName);
                    Log::info('Deleted existing backup file: ' . $this->fBackupName);
                }

                # this will be used to verify later if restore was successful
                file_put_contents(base_path($this->fileVerifyName), 'backup');

                $itemsToBackup = config('backupmanager.backups.files.folders');
                
                if (empty($itemsToBackup)) {
                    Log::warning('No folders configured for backup in backupmanager.backups.files.folders');
                    return false;
                }

                $itemsToBackup = array_map(
                    function ($str) {
                        $pathPrefix = dirname(getcwd());

                        if (App::runningInConsole()) {
                            $pathPrefix = getcwd();
                        }

                        // Remove the base path prefix and normalize directory separators
                        $relativePath = str_replace($pathPrefix, '', $str);
                        // Remove leading directory separator
                        $relativePath = ltrim($relativePath, DIRECTORY_SEPARATOR);
                        // Convert backslashes to forward slashes for consistency
                        $relativePath = str_replace('\\', '/', $relativePath);

                        return $relativePath;
                    },
                    $itemsToBackup
                );

                // also add our backup verifier
                $itemsToBackup[] = $this->fileVerifyName;

                $itemsToBackup = implode(' ', $itemsToBackup);

                $command = 'cd ' . str_replace(
                    '\\',
                    '/',
                    base_path()
                ) . " && $this->tar -cpzf $this->fBackupName $itemsToBackup 2>&1";
                
                Log::info('Executing files backup command', ['command' => $command]);

                $output = shell_exec($command);
                
                if ($output && trim($output) !== '') {
                    Log::warning('Tar command output', ['output' => $output]);
                }

                if (file_exists(base_path($this->fBackupName))) {
                    $fileSize = filesize(base_path($this->fBackupName));
                    Log::info('Backup file created', [
                        'filename' => $this->fBackupName,
                        'size' => $this->formatSizeUnits($fileSize)
                    ]);
                    
                    try {
                        // Use streaming to avoid memory issues with large files
                        $storageLocal = Storage::createLocalDriver(['root' => base_path()]);
                        $stream = $storageLocal->readStream($this->fBackupName);
                        
                        if ($stream === false) {
                            throw new \Exception('Failed to open file stream for: ' . $this->fBackupName);
                        }
                        
                        Log::info('Uploading backup file to storage (streaming)...', ['size' => $this->formatSizeUnits($fileSize)]);
                        
                        $uploaded = Storage::disk($this->disk)->writeStream($this->backupPath . $this->fBackupName, $stream);
                        
                        if (is_resource($stream)) {
                            fclose($stream);
                        }
                        
                        if (!$uploaded) {
                            throw new \Exception('Failed to upload backup file to storage');
                        }
                        
                        Log::info('Backup file uploaded to storage', ['disk' => $this->disk, 'path' => $this->backupPath . $this->fBackupName]);

                        // delete local file
                        $storageLocal->delete($this->fBackupName);
                    } catch (\Exception $e) {
                        Log::error('Failed to upload backup file: ' . $e->getMessage());
                        // Clean up local file even if upload fails
                        if ($storageLocal->exists($this->fBackupName)) {
                            $storageLocal->delete($this->fBackupName);
                        }
                        throw $e;
                    }
                } else {
                    throw new \Exception('Backup file was not created. Command output: ' . ($output ?: 'No output'));
                }
                
                if ($bypass === true) {
                    $this->deleteOldBackups("f");
                }
                
                Log::info('Files backup completed successfully');
                return true;
                
            } catch (\Exception $e) {
                Log::error('Files backup failed: ' . $e->getMessage(), [
                    'exception' => $e,
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }
        }
        
        return true;
    }

    /**
     * Backup Database
     */
    public function backupDatabase($bypass = false)
    {
        if (config('backupmanager.backups.database.enable') || $bypass) {
            
            Log::info('Starting database backup...');
            
            try {
                // Check if mysqldump command exists
                if (!$this->commandExists($this->mysqldump)) {
                    throw new \Exception("Mysqldump command not found: {$this->mysqldump}. Please install MySQL client tools or update the path in config.");
                }

                // delete previous backup for same date
                if (Storage::disk($this->disk)->exists($this->backupPath . $this->dBackupName)) {
                    Storage::disk($this->disk)->delete($this->backupPath . $this->dBackupName);
                    Log::info('Deleted existing backup file: ' . $this->dBackupName);
                }

                # this will be used to verify later if restore was successful
                DB::statement(" INSERT INTO verifybackup (id, verify_status) VALUES (1, 'backup') ON DUPLICATE KEY UPDATE verify_status = 'backup' ");

                $connection = [
                    'host' => config('database.connections.mysql.host'),
                    'database' => config('database.connections.mysql.database'),
                    'username' => config('database.connections.mysql.username'),
                    'password' => config('database.connections.mysql.password'),
                ];
                
                // Validate database connection
                if (empty($connection['database'])) {
                    throw new \Exception('Database name is not configured in database.connections.mysql.database');
                }
                
                if (empty($connection['host'])) {
                    throw new \Exception('Database host is not configured in database.connections.mysql.host');
                }

                $tableOptions = '';
                $connectionOptions = "--user={$connection['username']} --password=\"{$connection['password']}\" --host={$connection['host']} {$connection['database']} ";

                // https://mariadb.com/kb/en/library/mysqldump/
                $options = [
                    '--single-transaction',
                    '--max-allowed-packet=4096',
                    '--quick',
                    '--skip-ssl', // Prevent SSL connection errors
                    // '--force', // ignore errors
                    //'--set-gtid-purged=OFF',
                    //'--skip-lock-tables',
                ];

                $options = implode(' ', $options);

                $itemsToBackup = config('backupmanager.backups.database.tables');

                if ($itemsToBackup) {

                    // also add our backup verifier
                    $itemsToBackup[] = 'verifybackup';

                    $tableOptions = implode(' ', $itemsToBackup);
                    Log::info('Backing up specific tables', ['tables' => $itemsToBackup]);
                } else {
                    Log::info('Backing up entire database', ['database' => $connection['database']]);
                }

                // Command without password for logging
                $logCommand = 'cd ' . str_replace(
                    '\\',
                    '/',
                    base_path()
                ) . " && $this->mysqldump $options --user={$connection['username']} --password=*** --host={$connection['host']} {$connection['database']} $tableOptions | gzip > $this->dBackupName";
                
                Log::info('Executing database backup command', ['command' => $logCommand]);
                
                $command = 'cd ' . str_replace(
                    '\\',
                    '/',
                    base_path()
                ) . " && $this->mysqldump $options $connectionOptions $tableOptions 2>&1 | gzip > $this->dBackupName";

                $output = shell_exec($command);
                
                if ($output && trim($output) !== '') {
                    // Check if output contains error messages
                    if (stripos($output, 'error') !== false || stripos($output, 'warning') !== false) {
                        Log::error('Mysqldump command encountered issues', ['output' => $output]);
                    } else {
                        Log::info('Mysqldump command output', ['output' => $output]);
                    }
                }

                if (file_exists(base_path($this->dBackupName))) {
                    $fileSize = filesize(base_path($this->dBackupName));
                    
                    // Check if file is too small (might indicate failure)
                    // A valid gzipped MySQL dump should be at least a few KB
                    $minSizeBytes = 1024; // 1KB minimum
                    
                    if ($fileSize < $minSizeBytes) {
                        // Try to read the gzipped content to provide better error info
                        $content = '';
                        $gz = @gzopen(base_path($this->dBackupName), 'r');
                        if ($gz) {
                            $content = @gzread($gz, 1000);
                            @gzclose($gz);
                        }
                        
                        $errorMsg = "Database backup file is suspiciously small ({$fileSize} bytes, minimum expected: {$minSizeBytes} bytes). ";
                        $errorMsg .= "This usually means mysqldump failed or the database is empty. ";
                        
                        if (!empty($content)) {
                            $errorMsg .= "File content preview: " . substr($content, 0, 200);
                        }
                        
                        Log::error('Database backup file too small', [
                            'size' => $fileSize,
                            'min_expected' => $minSizeBytes,
                            'content_preview' => substr($content, 0, 500)
                        ]);
                        
                        throw new \Exception($errorMsg);
                    }
                    
                    Log::info('Database backup file created', [
                        'filename' => $this->dBackupName,
                        'size' => $this->formatSizeUnits($fileSize)
                    ]);
                    
                    try {
                        // Use streaming to avoid memory issues with large databases
                        $storageLocal = Storage::createLocalDriver(['root' => base_path()]);
                        $stream = $storageLocal->readStream($this->dBackupName);
                        
                        if ($stream === false) {
                            throw new \Exception('Failed to open file stream for: ' . $this->dBackupName);
                        }
                        
                        Log::info('Uploading database backup to storage (streaming)...', ['size' => $this->formatSizeUnits($fileSize)]);
                        
                        $uploaded = Storage::disk($this->disk)->writeStream($this->backupPath . $this->dBackupName, $stream);
                        
                        if (is_resource($stream)) {
                            fclose($stream);
                        }
                        
                        if (!$uploaded) {
                            throw new \Exception('Failed to upload database backup to storage');
                        }
                        
                        Log::info('Database backup file uploaded to storage', ['disk' => $this->disk, 'path' => $this->backupPath . $this->dBackupName]);

                        // delete local file
                        $storageLocal->delete($this->dBackupName);
                    } catch (\Exception $e) {
                        Log::error('Failed to upload database backup: ' . $e->getMessage());
                        // Clean up local file even if upload fails
                        if ($storageLocal->exists($this->dBackupName)) {
                            $storageLocal->delete($this->dBackupName);
                        }
                        throw $e;
                    }
                } else {
                    throw new \Exception('Database backup file was not created. Command output: ' . ($output ?: 'No output'));
                }

                if ($bypass === true) {
                    $this->deleteOldBackups("d");
                }
                
                Log::info('Database backup completed successfully');
                return true;
                
            } catch (\Exception $e) {
                Log::error('Database backup failed: ' . $e->getMessage(), [
                    'exception' => $e,
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }
        }
        
        return true;
    }

    protected function restoreFiles($file)
    {
        if (!Storage::disk($this->disk)->exists($this->backupPath . $file)) {
            throw new \Exception("Backup file not found: {$this->backupPath}{$file}");
        }
        
        // Check if tar command exists
        if (!$this->commandExists($this->tar)) {
            throw new \Exception("Tar command not found: {$this->tar}. Please install tar or update the path in config.");
        }

        try {
            // Use streaming to avoid memory issues with large backups
            $storageLocal = Storage::createLocalDriver(['root' => base_path()]);
            $stream = Storage::disk($this->disk)->readStream($this->backupPath . $file);
            
            if ($stream === false) {
                throw new \Exception("Failed to open stream for backup file: {$this->backupPath}{$file}");
            }
            
            Log::info('Downloading backup file (streaming)...');
            
            $written = $storageLocal->writeStream($file, $stream);
            
            if (is_resource($stream)) {
                fclose($stream);
            }
            
            if (!$written) {
                throw new \Exception("Failed to download backup file: $file");
            }

            if (!file_exists(base_path($file))) {
                throw new \Exception("Failed to download backup file to local storage: $file");
            }

            file_put_contents(base_path($this->fileVerifyName), 'restore');

            $command = 'cd ' . str_replace('\\', '/', base_path()) . " && $this->tar -xzf $file 2>&1";
            
            Log::info('Executing files restore command', ['command' => $command]);
            $output = shell_exec($command);
            
            if ($output && trim($output) !== '') {
                if (stripos($output, 'error') !== false) {
                    Log::error('Tar extraction encountered errors', ['output' => $output]);
                    throw new \Exception('Tar extraction failed: ' . $output);
                } else {
                    Log::info('Tar extraction output', ['output' => $output]);
                }
            }

            // delete local file
            $storageLocal->delete($file);
        } catch (\Exception $e) {
            // Clean up local file if it exists
            $storageLocal = Storage::createLocalDriver(['root' => base_path()]);
            if ($storageLocal->exists($file)) {
                $storageLocal->delete($file);
            }
            throw $e;
        }
    }

    protected function restoreDatabase($file)
    {
        if (!Storage::disk($this->disk)->exists($this->backupPath . $file)) {
            throw new \Exception("Backup file not found: {$this->backupPath}{$file}");
        }
        
        // Check if required commands exist
        if (!$this->commandExists($this->zcat)) {
            throw new \Exception("Zcat command not found: {$this->zcat}. Please install gzip utilities or update the path in config.");
        }
        
        if (!$this->commandExists($this->mysql)) {
            throw new \Exception("MySQL command not found: {$this->mysql}. Please install MySQL client or update the path in config.");
        }

        try {
            // Use streaming to avoid memory issues with large databases
            $storageLocal = Storage::createLocalDriver(['root' => base_path()]);
            $stream = Storage::disk($this->disk)->readStream($this->backupPath . $file);
            
            if ($stream === false) {
                throw new \Exception("Failed to open stream for backup file: {$this->backupPath}{$file}");
            }
            
            Log::info('Downloading database backup (streaming)...');
            
            $written = $storageLocal->writeStream($file, $stream);
            
            if (is_resource($stream)) {
                fclose($stream);
            }
            
            if (!$written) {
                throw new \Exception("Failed to download database backup: $file");
            }

            if (!file_exists(base_path($file))) {
                throw new \Exception("Failed to download backup file to local storage: $file");
            }

            DB::statement(" INSERT INTO verifybackup (id, verify_status) VALUES (1, 'restore') ON DUPLICATE KEY UPDATE verify_status = 'restore' ");

            $connection = [
                'host' => config('database.connections.mysql.host'),
                'database' => config('database.connections.mysql.database'),
                'username' => config('database.connections.mysql.username'),
                'password' => config('database.connections.mysql.password'),
            ];

            $connectionOptions = "-u {$connection['username']} ";

            if (trim($connection['password'])) {
                $connectionOptions .= " -p\"{$connection['password']}\" ";
            }

            $connectionOptions .= " -h {$connection['host']} {$connection['database']} ";
            
            // Command without password for logging
            $logCommand = 'cd ' . str_replace(
                '\\',
                '/',
                base_path()
            ) . " && $this->zcat $file | mysql -u {$connection['username']} -p*** -h {$connection['host']} {$connection['database']}";
            
            Log::info('Executing database restore command', ['command' => $logCommand]);

            $command = 'cd ' . str_replace(
                '\\',
                '/',
                base_path()
            ) . " && $this->zcat $file | mysql $connectionOptions 2>&1";

            $output = shell_exec($command);
            
            if ($output && trim($output) !== '') {
                if (stripos($output, 'error') !== false) {
                    Log::error('Database restore encountered errors', ['output' => $output]);
                    throw new \Exception('Database restore failed: ' . $output);
                } else {
                    Log::info('Database restore output', ['output' => $output]);
                }
            }

            // delete local file
            $storageLocal->delete($file);
        } catch (\Exception $e) {
            // Clean up local file if it exists
            $storageLocal = Storage::createLocalDriver(['root' => base_path()]);
            if ($storageLocal->exists($file)) {
                $storageLocal->delete($file);
            }
            throw $e;
        }
    }

    /**
     * Verifies backup status for files and database
     *
     * @return array
     */
    protected function getBackupStatus()
    {
        @unlink(base_path($this->fileVerifyName));

        $fStatus = false;
        $dStatus = false;
        $errors = [];

        $okSizeBytes = 1024;

        // Check files backup
        if (config('backupmanager.backups.files.enable')) {
            if (!Storage::disk($this->disk)->exists($this->backupPath . $this->fBackupName)) {
                $errors[] = 'Files backup file does not exist at: ' . $this->backupPath . $this->fBackupName;
                Log::error('Files backup verification failed: file not found', ['path' => $this->backupPath . $this->fBackupName]);
            } else {
                $size = Storage::disk($this->disk)->size($this->backupPath . $this->fBackupName);
                if ($size <= $okSizeBytes) {
                    $errors[] = 'Files backup file is too small (' . $this->formatSizeUnits($size) . ', minimum: ' . $this->formatSizeUnits($okSizeBytes) . ')';
                    Log::error('Files backup verification failed: file too small', ['size' => $size, 'minimum' => $okSizeBytes]);
                } else {
                    $fStatus = true;
                }
            }
        } else {
            $fStatus = true; // Not enabled, so consider it successful
        }

        // Check database backup
        if (config('backupmanager.backups.database.enable')) {
            if (!Storage::disk($this->disk)->exists($this->backupPath . $this->dBackupName)) {
                $errors[] = 'Database backup file does not exist at: ' . $this->backupPath . $this->dBackupName;
                Log::error('Database backup verification failed: file not found', ['path' => $this->backupPath . $this->dBackupName]);
            } else {
                $size = Storage::disk($this->disk)->size($this->backupPath . $this->dBackupName);
                if ($size <= $okSizeBytes) {
                    $errors[] = 'Database backup file is too small (' . $this->formatSizeUnits($size) . ', minimum: ' . $this->formatSizeUnits($okSizeBytes) . '). This usually indicates mysqldump failed.';
                    Log::error('Database backup verification failed: file too small', ['size' => $size, 'minimum' => $okSizeBytes]);
                } else {
                    $dStatus = true;
                }
            }
        } else {
            $dStatus = true; // Not enabled, so consider it successful
        }

        $result = ['f' => $fStatus, 'd' => $dStatus];
        
        if (!empty($errors)) {
            $result['errors'] = $errors;
        }

        return $result;
    }

    protected function getRestoreStatus($isFiles)
    {
        // for files
        if ($isFiles) {
            $contents = file_get_contents(base_path($this->fileVerifyName));

            @unlink(base_path($this->fileVerifyName));

            return ['f' => $contents === 'backup'];
        }

        // for db
        $dbStatus = false;
        $data = DB::select(' SELECT verify_status FROM verifybackup WHERE id = 1 ');

        if ($data && isset($data[0])) {
            $dbStatus = $data[0]->verify_status;
        }

        return ['d' => $dbStatus === 'backup'];
    }

    /**
     * Deleted older backups
     *
     * @return void
     */
    protected function deleteOldBackups($del_specific = "")
    {
        $daysOldToDelete = (int) config('backupmanager.backups.delete_old_backup_days');
        $now = time();

        $files = Storage::disk($this->disk)->listContents($this->backupPath);
        foreach ($files as $file) {
            if ($file['type'] !== 'file') {
                continue;
            }
            if (empty($file['basename'])) {
                $filename = $file->path();
            } else {
                $filename = $this->backupPath . $file['basename'];
            }
            if ($del_specific !== "") {
                //skip delete if del_specific has value for specific deletes only
                if (!empty($file['basename'][0]) && $file['basename'][0] !== $del_specific) {
                    continue;
                }
                if (!empty($file->extraMetadata()['filename'][0]) && $file->extraMetadata()['filename'][0] . "." . $file->extraMetadata()['extension'][0] !== $del_specific) {
                    continue;
                }
            }
            if ($now - $this->getFileTimeStamp($file) >= 60 * 60 * 24 * $daysOldToDelete) {
                if (Storage::disk($this->disk)->exists($filename)) {
                    Storage::disk($this->disk)->delete($filename);
                    $name = str_replace($this->backupPath,"",$filename);
                    Log::info('Deleted old backup file: ' . $name);
                }
            }
        }
    }

    protected function getFileTimeStamp($file)
    {
        if ($file instanceof \League\Flysystem\FileAttributes) {
            return $file->lastModified();
        }else{
            if (isset($file['timestamp'])) {
                return $file['timestamp'];
            }
            // otherwise get date from file name
            $array = explode('_', $file['filename']);

            return strtotime(end($array));
        }
    }

    protected function formatSizeUnits($size)
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
        $power = $size > 0 ? floor(log($size, 1024)) : 0;

        return number_format($size / (1024 ** $power), 2, '.', ',') . ' ' . $units[$power];
    }
    
    /**
     * Check if a command exists in the system
     *
     * @param string $command
     * @return bool
     */
    protected function commandExists($command)
    {
        // Extract just the command name without path
        $commandName = basename($command);
        
        // Check on Windows
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $result = shell_exec("where $commandName 2>nul");
            return !empty($result);
        }
        
        // Check on Unix/Linux/Mac
        $result = shell_exec("which $commandName 2>/dev/null");
        return !empty($result);
    }
}
