<?php
namespace App\Http\Controllers\Api;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::prefix('v1')->group(function(){
    Route::get('/test-api', [MikrotikController::class, 'test_api']);

    Route::get('/mikrotikos-connect', [MikrotikController::class, 'mikrotikos_connection']);

    Route::get('/set-interface', [MikrotikController::class, 'set_interface']);

    Route::get('/add-new-address', [MikrotikController::class, 'add_new_address']);

    Route::get('/add-ip-route', [MikrotikController::class, 'add_ip_route']);

    Route::get('/add-dns-server', [MikrotikController::class, 'add_dns_servers']);

    Route::get('/routeros-reboot', [MikrotikController::class, 'routeros_reboot']);

    Route::get('/add-user', [MikrotikController::class, 'add_user']);

    Route::get('/set-bandwidth-limit', [MikrotikController::class, 'set_bandwidth_limit']);

    Route::get('/create-user-group', [MikrotikController::class, 'create_user_group']);
});