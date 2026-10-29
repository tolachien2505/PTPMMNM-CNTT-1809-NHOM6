<?php
/**
 * Web Routes (Laravel Architecture)
 * Định tuyến toàn bộ trang giao diện Client & Admin Portal
 */

use App\Http\Controllers\HomeController;

$render = function(string $route = 'home', string $adminSub = 'dashboard') {
    $controller = new HomeController(Database::getPdo());
    $controller->render($route, $adminSub);
};

// Client Web Routes
Route::get('/', function() use ($render) { $render('home'); });
Route::get('/home', function() use ($render) { $render('home'); });
Route::get('/documents', function() use ($render) { $render('documents'); });
Route::get('/editor', function() use ($render) { $render('editor'); });
Route::get('/upgrade', function() use ($render) { $render('upgrade'); });
Route::get('/history', function() use ($render) { $render('history'); });
Route::get('/settings', function() use ($render) { $render('settings'); });

// Legacy Editor Route Aliases
Route::get('/reorder', function() use ($render) { $render('editor'); });
Route::get('/merge', function() use ($render) { $render('editor'); });
Route::get('/split', function() use ($render) { $render('editor'); });

// Admin Portal Web Routes
Route::get('/admin', function() use ($render) { $render('admin', 'dashboard'); });
Route::get('/admin/dashboard', function() use ($render) { $render('admin', 'dashboard'); });
Route::get('/admin/users', function() use ($render) { $render('admin', 'users'); });
Route::get('/admin/documents', function() use ($render) { $render('admin', 'documents'); });
Route::get('/admin/exports', function() use ($render) { $render('admin', 'exports'); });
Route::get('/admin/payments', function() use ($render) { $render('admin', 'payments'); });
Route::get('/admin/plans', function() use ($render) { $render('admin', 'plans'); });
Route::get('/admin/maintenance', function() use ($render) { $render('admin', 'maintenance'); });
