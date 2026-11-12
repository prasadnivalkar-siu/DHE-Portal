<?php

namespace Config;

use CodeIgniter\Config\BaseService;

/**
 * Services Configuration file.
 *
 * Services are simply other classes/libraries that the system uses
 * to do its job. This is used by CodeIgniter to allow the core of the
 * framework to be swapped out easily without affecting the usage within
 * the rest of your application.
 *
 * This file holds any application-specific services, or service overrides
 * that you might need. An example has been included with the general
 * method format you should use for your service methods. For more examples,
 * see the core Services file at system/Config/Services.php.
 */
class Services extends BaseService
{
    public static function vendorActivityRecorder(bool $getShared = true): \App\Services\VendorActivityRecorder
    {
        if ($getShared) {
            return static::getSharedInstance('vendorActivityRecorder');
        }
        return new \App\Services\VendorActivityRecorder();
    }

    public static function vendorApiContext(bool $getShared = true): \App\Services\VendorApiContext
    {
        if ($getShared) {
            return static::getSharedInstance('vendorApiContext');
        }

        return new \App\Services\VendorApiContext();
    }

    public static function vendorApiKeys(bool $getShared = true): \App\Services\VendorApiKeyService
    {
        if ($getShared) {
            return static::getSharedInstance('vendorApiKeys');
        }

        return new \App\Services\VendorApiKeyService();
    }

    /*
     * public static function example($getShared = true)
     * {
     *     if ($getShared) {
     *         return static::getSharedInstance('example');
     *     }
     *
     *     return new \CodeIgniter\Example();
     * }
     */
}
