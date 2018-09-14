<?php
namespace Sarfraznawaz2005\BackupManager\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Returns instance of logged in user.
 *
 * @return \Sarfraznawaz2005\BackupManager\BackupManager
 */
class BackupManager extends Facade
{
    /**
     * Get the binding in the IoC container
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'BackupManager';
    }

} 