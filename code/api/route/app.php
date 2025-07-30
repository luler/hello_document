<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
use think\facade\Route;

Route::group(function () {
    Route::group('api', function () {
        //公共接口
        Route::get('test', 'admin.CommonController/test');
        Route::post('getAccessToken', 'admin.LoginController/getAccessToken')->middleware(\app\middleware\Throttle::class, '10,1');
        Route::get('logout', 'admin.LoginController/logout');
        Route::get('casLogin', 'admin.LoginController/casLogin');
        //需要登录的
        Route::group(function () {
            Route::get('getUserInfo', 'admin.UserController/getUserInfo');
            Route::post('changPassword', 'admin.UserController/changPassword');
            //文档管理相关
            Route::post('uploadFile', 'admin.FileController/uploadFile');
            Route::post('getFileList', 'admin.FileController/getFileList');
            Route::post('delFile', 'admin.FileController/delFile');
            Route::get('downloadFile', 'admin.FileController/downloadFile');
        })->middleware(\app\middleware\Auth::class);
    });

    /**
     * 没有匹配项就抛异常
     */
    Route::miss(function () {
        throw new \think\exception\RouteNotFoundException();
    });
})->allowCrossDomain();

