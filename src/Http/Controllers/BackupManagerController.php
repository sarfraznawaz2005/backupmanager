<?php

namespace Sarfraznawaz2005\BackupManager\Http\Controllers;

use Illuminate\Routing\Controller as BaseController;
use Log;
use Sarfraznawaz2005\BackupManager\Facades\BackupManager;
use Session;

class BackupManagerController extends BaseController
{
    public function __construct()
    {
        if (config('backupmanager.http_authentication')) {
            $this->middleware('auth.basic');
        }
    }

    public function index()
    {
        $title = 'Backup List';

        $backups = BackupManager::getBackups();

        return view('backupmanager::index', compact('title', 'backups'));
    }

    public function createBackup()
    {
        $messages = [];

        // create backups
        $result = BackupManager::createBackup();

        // set status messages
        if ($result['f'] === true) {
            $message = 'Files Backup Taken Successfully';

            $messages[] = [
                'type' => 'success',
                'message' => $message
            ];

            Log::info($message);
        } else {
            if (config('backupmanager.backups.files.enable')) {
                $message = 'Files Backup Failed';

                $messages[] = [
                    'type' => 'danger',
                    'message' => $message
                ];

                Log::error($message);
            }
        }

        if ($result['d'] === true) {
            $message = 'Database Backup Taken Successfully';

            $messages[] = [
                'type' => 'success',
                'message' => $message
            ];

            Log::info($message);
        } else {
            if (config('backupmanager.backups.database.enable')) {
                $message = 'Database Backup Failed';

                $messages[] = [
                    'type' => 'danger',
                    'message' => $message
                ];

                Log::error($message);
            }
        }

        Session::flash('messages', $messages);
        return redirect()->back();
    }

    public function restoreOrDeleteBackups()
    {
        $messages = [];
        $backups = request()->backups;
        $type = request()->type;

        if ($type === 'restore' && count($backups) > 2) {
            $messages[] = [
                'type' => 'danger',
                'message' => 'Max of two backups can be restored at a time.'
            ];

            Session::flash('messages', $messages);
            return redirect()->back();
        }

        if ($type === 'restore') {
            // restore backups

            $results = BackupManager::restoreBackups($backups);

            // set status messages
            foreach ($results as $result) {
                if (isset($result['f'])) {
                    if ($result['f'] === true) {
                        $message = 'Files Backup Restored Successfully';

                        $messages[] = [
                            'type' => 'success',
                            'message' => $message
                        ];

                        Log::info($message);
                    } else {
                        $message = 'Files Restoration Failed';

                        $messages[] = [
                            'type' => 'danger',
                            'message' => $message
                        ];

                        Log::error($message);
                    }
                } elseif (isset($result['d'])) {
                    if ($result['d'] === true) {
                        $message = 'Database Backup Restored Successfully';

                        $messages[] = [
                            'type' => 'success',
                            'message' => $message
                        ];

                        Log::info($message);
                    } else {
                        $message = 'Database Restoration Failed';

                        $messages[] = [
                            'type' => 'danger',
                            'message' => $message
                        ];

                        Log::error($message);
                    }
                }
            }

        } else {
            // delete backups

            $results = BackupManager::deleteBackups($backups);

            if ($results) {
                $messages[] = [
                    'type' => 'success',
                    'message' => 'Backup(s) deleted successfully.'
                ];
            } else {
                $messages[] = [
                    'type' => 'danger',
                    'message' => 'Deletion failed.'
                ];
            }
        }

        Session::flash('messages', $messages);
        return redirect()->back();
    }
}
