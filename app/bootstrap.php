<?php
declare(strict_types=1);
spl_autoload_register(function (string $class): void {
    if (!str_starts_with($class, 'App\\')) return;
    $file = __DIR__ . '/' . str_replace('App\\', '', $class) . '.php';
    if (is_file($file)) require $file;
});
function env(string $key, mixed $default = null): mixed {
    static $values;
    if ($values === null) {
        $values = []; $file = dirname(__DIR__) . '/.env';
        if (is_file($file)) foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
            [$k, $v] = array_map('trim', explode('=', $line, 2)); $values[$k] = trim($v, "\"'");
        }
    }
    $v = $values[$key] ?? $_ENV[$key] ?? $default;
    return match (strtolower((string)$v)) { 'true' => true, 'false' => false, 'null' => null, default => $v };
}
if (!is_file(dirname(__DIR__) . '/.env')) {
    http_response_code(503);
    echo '<meta charset="utf-8"><div dir="rtl" style="font-family:sans-serif;padding:40px">فایل <code>.env</code> وجود ندارد. ابتدا README.md را مطالعه کنید.</div>'; exit;
}
date_default_timezone_set('Asia/Tehran');
ini_set('display_errors', env('APP_DEBUG', false) ? '1' : '0');
ini_set('log_errors', '1'); ini_set('error_log', dirname(__DIR__) . '/storage/logs/php-error.log');
session_name('PISHFACTOR_SESSION');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>(bool)env('SESSION_SECURE', true),'httponly'=>true,'samesite'=>'Lax']);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
function url(string $path = ''): string { return rtrim((string)env('APP_URL', ''), '/') . '/' . ltrim($path, '/'); }
function e(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function t(string $key): string { static $l; $l ??= require __DIR__ . '/../resources/lang/fa.php'; if($key==='app_name'){try{return (string)\App\Settings::get('app.name',$l[$key]??$key);}catch(\Throwable){}} return $l[$key] ?? $key; }
function csrf_token(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function json_input(): array { $v = json_decode(file_get_contents('php://input') ?: '{}', true); return is_array($v) ? $v : []; }
function json_response(array $data, int $status = 200): never { http_response_code($status); header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store'); echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit; }
function amount_to_words_fa(float $amount): string {
    $ones=['','یک','دو','سه','چهار','پنج','شش','هفت','هشت','نه'];$teens=['ده','یازده','دوازده','سیزده','چهارده','پانزده','شانزده','هفده','هجده','نوزده'];
    $tens=['','ده','بیست','سی','چهل','پنجاه','شصت','هفتاد','هشتاد','نود'];$hundreds=['','صد','دویست','سیصد','چهارصد','پانصد','ششصد','هفتصد','هشتصد','نهصد'];$scales=['','هزار','میلیون','میلیارد','تریلیون'];
    $n=(int)floor(abs($amount));if($n===0)return'صفر';
    $three=function(int $num)use($ones,$teens,$tens,$hundreds):string{
        $h=intdiv($num,100);$t=intdiv($num%100,10);$o=$num%10;$parts=[];
        if($h)$parts[]=$hundreds[$h];
        if($t===1)$parts[]=$teens[$o];else{if($t)$parts[]=$tens[$t];if($o)$parts[]=$ones[$o];}
        return implode(' و ',$parts);
    };
    $groups=[];while($n>0){array_unshift($groups,$n%1000);$n=intdiv($n,1000);}
    $words=[];$count=count($groups);
    foreach($groups as $i=>$g){if(!$g)continue;$scale=$scales[$count-1-$i]??'';$words[]=trim($three($g).($scale?' '.$scale:''));}
    return implode(' و ',$words);
}
