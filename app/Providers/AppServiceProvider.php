<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        
        ### 设置默认字符串长度
        Schema::defaultStringLength(191);
        
        ### 注册全局助手函数
        require_once base_path('app/Helpers/common.php');
        require_once base_path('app/Helpers/function.php');
//        require_once base_path('app/Helpers/constants.php');
        
    }
}
