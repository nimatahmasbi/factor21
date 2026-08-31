<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';

use App\Auth;
use App\View;

$path = trim((string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
$base = trim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
if ($base !== '' && str_starts_with($path, $base)) $path = trim(substr($path, strlen($base)), '/');
if ($path === 'api' || str_starts_with($path, 'api/')) { require __DIR__ . '/app/api.php'; exit; }
if (preg_match('#^q/([a-f0-9]{64})$#',$path,$match)) { $shareToken=$match[1]; require __DIR__ . '/resources/views/public_quote.php'; exit; }
if (preg_match('#^s/([A-Za-z0-9\-_]{6,16})$#',$path,$match)) { $shortCode=$match[1]; require __DIR__ . '/resources/views/short_link.php'; exit; }
if ($path === 'logout') { Auth::logout(); header('Location: ' . url('')); exit; }
if (!Auth::check()) { View::render('login', ['title' => t('login_title')]); exit; }
View::render('dashboard', ['title' => t('app_name'), 'user' => Auth::user()]);
