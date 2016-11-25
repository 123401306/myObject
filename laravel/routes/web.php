<?php

use App\Model\alidayu\alidayu;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of the routes that are handled
| by your application. Just tell Laravel the URIs it should respond
| to using a Closure or controller method. Build something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/sentMsg',function(App\Model\alidayu\sms $sent){
    $oid ='123';
    $phone = '123';
    $param['oid'] = (string)$oid;
    return $sent->send($phone,$param,'SMS_12445723');
});