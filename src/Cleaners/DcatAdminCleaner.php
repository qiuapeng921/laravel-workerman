<?php

namespace Qiuapeng\LaravelWorkerman\Cleaners;

use Illuminate\Foundation\Application;
use Qiuapeng\LaravelWorkerman\Contracts\CleanerInterface;

class DcatAdminCleaner implements CleanerInterface
{
    protected $instances = [
        'admin.app',
        'admin.asset',
        'admin.color',
//        'admin.sections',
        'admin.extend',
        'admin.extend.update',
        'admin.extend.version',
        'admin.navbar',
        'admin.menu',
        'admin.context',
        'admin.setting',
        'admin.web-uploader',
        'admin.translator',
    ];

    /**
     * @param Application $app
     *
     * @return void
     */
    public function clean($app): void
    {
        foreach ($this->instances as $instance) {
            $app->forgetInstance($instance);
        }
    }
}