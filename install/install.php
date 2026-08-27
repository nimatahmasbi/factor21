<?php
declare(strict_types=1);

const ROOT = __DIR__ . '/..';
require __DIR__ . '/Migrator.php';

session_name('PISHFACTOR_INSTALLER');
session_start();
$_SESSION['install_csrf'] ??= bin2hex(random_bytes(32));

function h(mixed $value):string{return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function readEnvFile(string $file):array{$out=[];if(!is_file($file))return$out;foreach(file($file,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES)?:[] as $line){$line=trim($line);if($line===''||str_starts_with($line,'#')||!str_contains($line,'='))continue;[$k,$v]=array_map('trim',explode('=',$line,2));$out[$k]=trim($v,"\"'");}return$out;}
function envQuote(string $value):string{$value=str_replace(["\r","\n",'"'],['','','\\"'],trim($value));return'"'.$value.'"';}
function defaultUrl():string{$https=!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off';$host=preg_replace('/[^a-zA-Z0-9.\-:\[\]]/','',$_SERVER['HTTP_HOST']??'localhost');$path=str_replace('\\','/',dirname(dirname($_SERVER['SCRIPT_NAME']??'/install/install.php')));return($https?'https':'http').'://'.$host.rtrim($path,'/');}
function connect(array $cfg):PDO{return new PDO('mysql:host='.$cfg['DB_HOST'].';port='.($cfg['DB_PORT']??3306).';dbname='.$cfg['DB_NAME'].';charset=utf8mb4',$cfg['DB_USER'],$cfg['DB_PASS'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);}
function normalizeMobile(string $mobile):string{$mobile=preg_replace('/\D+/','',strtr($mobile,['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9']))??'';return preg_match('/^09\d{9}$/',$mobile)?$mobile:'';}
function validPassword(string $password):bool{return strlen($password)>=8&&strlen($password)<=128&&preg_match('/[A-Za-z]/',$password)&&preg_match('/\d/',$password);}
function maskMobile(string $mobile):string{return strlen($mobile)>=8?substr($mobile,0,4).'***'.substr($mobile,-4):$mobile;}
function backupWrite($handle,string $text,bool $gzip):void{if($gzip){if(gzwrite($handle,$text)===false)throw new RuntimeException('نوشتن فایل Backup ناموفق بود.');}elseif(fwrite($handle,$text)===false)throw new RuntimeException('نوشتن فایل Backup ناموفق بود.');}
function createDatabaseBackup(PDO $pdo,string $database):string{
 $dir=ROOT.'/storage/backups';if(!is_dir($dir)&&!mkdir($dir,0750,true))throw new RuntimeException('ساخت پوشه Backup ممکن نشد.');
 $gzip=function_exists('gzopen');$name='pishfactor-db-'.date('Ymd-His').'-'.bin2hex(random_bytes(3)).'.sql'.($gzip?'.gz':'');$final=$dir.'/'.$name;$tmp=$final.'.part';$handle=$gzip?gzopen($tmp,'wb9'):fopen($tmp,'wb');if($handle===false)throw new RuntimeException('ساخت فایل Backup ممکن نشد.');
 try{
  backupWrite($handle,"-- Pishfactor automatic database backup\n-- Database: ".str_replace(["\r","\n"],'',$database)."\n-- Created: ".gmdate('c')."\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n",$gzip);
  $tables=[];foreach($pdo->query("SHOW FULL TABLES WHERE Table_type='BASE TABLE'")->fetchAll(PDO::FETCH_NUM) as $row)$tables[]=(string)$row[0];
  foreach($tables as $table){
   if(!preg_match('/^[A-Za-z0-9_]+$/',$table))continue;$quoted=chr(96).$table.chr(96);$create=$pdo->query('SHOW CREATE TABLE '.$quoted)->fetch(PDO::FETCH_NUM);if(!$create||!isset($create[1]))throw new RuntimeException('خواندن ساختار جدول '.$table.' ناموفق بود.');
   backupWrite($handle,"DROP TABLE IF EXISTS ".$quoted.";\n".$create[1].";\n",$gzip);
   $stmt=$pdo->query('SELECT * FROM '.$quoted);$columns=[];for($i=0;$i<$stmt->columnCount();$i++)$columns[]=chr(96).$stmt->getColumnMeta($i)['name'].chr(96);
   while($row=$stmt->fetch(PDO::FETCH_NUM)){$values=array_map(fn($value)=>$value===null?'NULL':$pdo->quote((string)$value),$row);backupWrite($handle,'INSERT INTO '.$quoted.'('.implode(',',$columns).') VALUES('.implode(',',$values).");\n",$gzip);}
   $stmt->closeCursor();backupWrite($handle,"\n",$gzip);
  }
  backupWrite($handle,"SET FOREIGN_KEY_CHECKS=1;\n",$gzip);
 }catch(Throwable $e){$gzip?gzclose($handle):fclose($handle);@unlink($tmp);throw$e;}
 $gzip?gzclose($handle):fclose($handle);if(!rename($tmp,$final))throw new RuntimeException('نهایی‌سازی فایل Backup ممکن نشد.');@chmod($final,0640);return$final;
}

$envFile=ROOT.'/.env';$installed=is_file($envFile);$env=readEnvFile($envFile);$targetVersion=(string)require ROOT.'/config/version.php';
$requirements=[
 ['name'=>'PHP 8.1 یا بالاتر','ok'=>version_compare(PHP_VERSION,'8.1.0','>=')],
 ['name'=>'PDO MySQL','ok'=>extension_loaded('pdo_mysql')],
 ['name'=>'cURL','ok'=>extension_loaded('curl')],
 ['name'=>'mbstring','ok'=>extension_loaded('mbstring')],
 ['name'=>'Fileinfo','ok'=>extension_loaded('fileinfo')],
 ['name'=>'OpenSSL','ok'=>extension_loaded('openssl')],
 ['name'=>'دسترسی نوشتن Storage','ok'=>is_writable(ROOT.'/storage')],
 ['name'=>'دسترسی نوشتن مسیر برنامه','ok'=>$installed||is_writable(ROOT)],
];
$requirementsOk=!in_array(false,array_column($requirements,'ok'),true);
$error='';$success='';$applied=[];$currentVersion=$installed?'نامشخص':'نصب نشده';$pendingPreview=[];$modernSchema=false;$admins=[];$backupLink='';

$values=[
 'app_url'=>defaultUrl(),'db_host'=>'localhost','db_port'=>'3306','db_name'=>'','db_user'=>'','db_pass'=>'',
 'ippanel_api_key'=>'','ippanel_auth_prefix'=>'','ippanel_from_number'=>'','ippanel_pattern_code'=>'','ippanel_otp_param'=>'code',
 'admin_id'=>'','admin_name'=>'','admin_mobile'=>'','admin_password'=>'','legacy_app_key'=>'',
];
if($_SERVER['REQUEST_METHOD']==='POST')foreach($values as $key=>$unused)$values[$key]=trim((string)($_POST[$key]??''));

try{
 if(isset($_GET['download_backup'],$_SESSION['backup_token'],$_SESSION['backup_file'])&&hash_equals((string)$_SESSION['backup_token'],(string)$_GET['download_backup'])){
  $file=(string)$_SESSION['backup_file'];$base=realpath(ROOT.'/storage/backups');$real=realpath($file);if(!$base||!$real||!str_starts_with($real,$base.DIRECTORY_SEPARATOR)||!is_file($real))throw new RuntimeException('فایل Backup پیدا نشد.');
  header('Content-Type: application/octet-stream');header('Content-Disposition: attachment; filename="'.basename($real).'"');header('Content-Length: '.filesize($real));header('X-Content-Type-Options: nosniff');readfile($real);exit;
 }
 if($installed){
  foreach(['DB_HOST','DB_NAME','DB_USER','DB_PASS'] as $key)if(!isset($env[$key]))throw new RuntimeException('فایل تنظیمات دیتابیس ناقص است.');
  $pdo=connect($env);$migrator=new Migrator($pdo,__DIR__.'/updates');$currentVersion=$migrator->installedVersion();$pendingPreview=$migrator->previewPending();$modernSchema=$migrator->columnExists('users','password_hash')&&$migrator->columnExists('users','role');
  if($modernSchema)$admins=$pdo->query("SELECT id,name,mobile FROM users WHERE role='admin' AND is_active=1 ORDER BY id")->fetchAll();
 }
 if($_SERVER['REQUEST_METHOD']==='POST'){
  if(!hash_equals($_SESSION['install_csrf'],(string)($_POST['csrf']??'')))throw new RuntimeException('نشست نصب منقضی شده است؛ صفحه را تازه کنید.');
  if(!$requirementsOk)throw new RuntimeException('ابتدا پیش‌نیازهای قرمز را در cPanel برطرف کنید.');
  if(!$installed){
   if(!filter_var($values['app_url'],FILTER_VALIDATE_URL))throw new RuntimeException('آدرس سامانه معتبر نیست.');
   if($values['db_host']===''||$values['db_name']===''||$values['db_user']==='')throw new RuntimeException('مشخصات دیتابیس کامل نیست.');
   if(!ctype_digit($values['db_port'])||(int)$values['db_port']<1||(int)$values['db_port']>65535)throw new RuntimeException('پورت دیتابیس معتبر نیست.');
   $mobile=normalizeMobile($values['admin_mobile']);if(!$mobile)throw new RuntimeException('شماره موبایل مدیر معتبر نیست.');
   if(mb_strlen($values['admin_name'])<2)throw new RuntimeException('نام مدیر را وارد کنید.');
   if(!validPassword($values['admin_password']))throw new RuntimeException('رمز مدیر باید حداقل ۸ نویسه و شامل حرف و عدد باشد.');
   if(!hash_equals($values['admin_password'],(string)($_POST['admin_password_confirmation']??'')))throw new RuntimeException('تکرار رمز مدیر یکسان نیست.');
   if($values['ippanel_api_key']!==''&&($values['ippanel_from_number']===''||$values['ippanel_pattern_code']===''))throw new RuntimeException('تنظیمات IPPanel کامل نیست.');
   $cfg=['DB_HOST'=>$values['db_host'],'DB_PORT'=>$values['db_port'],'DB_NAME'=>$values['db_name'],'DB_USER'=>$values['db_user'],'DB_PASS'=>$values['db_pass']];
   $pdo=connect($cfg);$migrator=new Migrator($pdo,__DIR__.'/updates');$applied=$migrator->migrate();
   $stmt=$pdo->prepare("INSERT INTO users(mobile,name,password_hash,role,is_active)VALUES(?,?,?,'admin',1) ON DUPLICATE KEY UPDATE name=VALUES(name),password_hash=VALUES(password_hash),role='admin',is_active=1");
   $stmt->execute([$mobile,$values['admin_name'],password_hash($values['admin_password'],PASSWORD_DEFAULT)]);
   $appKey=bin2hex(random_bytes(32));
   $lines=['APP_NAME='.envQuote('پیش‌فاکتور من'),'APP_URL='.envQuote(rtrim($values['app_url'],'/')),'APP_ENV=production','APP_DEBUG=false','APP_KEY='.envQuote($appKey),'SESSION_SECURE='.(str_starts_with($values['app_url'],'https://')?'true':'false'),'DB_HOST='.envQuote($values['db_host']),'DB_PORT='.envQuote($values['db_port']),'DB_NAME='.envQuote($values['db_name']),'DB_USER='.envQuote($values['db_user']),'DB_PASS='.envQuote($values['db_pass']),'IPPANEL_API_KEY='.envQuote($values['ippanel_api_key']),'IPPANEL_AUTH_PREFIX='.envQuote($values['ippanel_auth_prefix']),'IPPANEL_FROM_NUMBER='.envQuote($values['ippanel_from_number']),'IPPANEL_PATTERN_CODE='.envQuote($values['ippanel_pattern_code']),'IPPANEL_OTP_PARAM='.envQuote($values['ippanel_otp_param']?:'code'),'OTP_TTL_SECONDS=120','OTP_RESEND_SECONDS=60',''];
   $tmp=ROOT.'/.env.installing-'.bin2hex(random_bytes(4));if(file_put_contents($tmp,implode(PHP_EOL,$lines),LOCK_EX)===false)throw new RuntimeException('ساخت فایل .env ممکن نشد.');@chmod($tmp,0640);if(!rename($tmp,$envFile)){@unlink($tmp);throw new RuntimeException('ثبت فایل .env ممکن نشد.');}
   file_put_contents(ROOT.'/storage/installed.lock','installed_at='.gmdate('c').PHP_EOL.'version='.$targetVersion.PHP_EOL,LOCK_EX);$installed=true;$currentVersion=$targetVersion;$success='نصب کامل شد و تمام بروزرسانی‌ها تا نسخه '.$targetVersion.' اجرا شدند.';
  }else{
   if(!$pendingPreview){$success='دیتابیس از قبل روی آخرین نسخه '.$targetVersion.' قرار دارد.';}
   else{
    if($modernSchema){
     $adminId=(int)$values['admin_id'];$stmt=$pdo->prepare("SELECT id,password_hash FROM users WHERE id=? AND role='admin' AND is_active=1");$stmt->execute([$adminId]);$admin=$stmt->fetch();
     if(!$admin||!$admin['password_hash']||!password_verify($values['admin_password'],$admin['password_hash']))throw new RuntimeException('احراز هویت مدیر سامانه ناموفق بود.');
    }else{
     if(!isset($env['APP_KEY'])||!hash_equals((string)$env['APP_KEY'],$values['legacy_app_key']))throw new RuntimeException('کلید ارتقای نسخه قدیمی نادرست است.');
     $mobile=normalizeMobile($values['admin_mobile']);if(!$mobile||mb_strlen($values['admin_name'])<2||!validPassword($values['admin_password']))throw new RuntimeException('مشخصات مدیر نسخه قدیمی کامل یا معتبر نیست.');
    }
    $backupFile=createDatabaseBackup($pdo,(string)$env['DB_NAME']);$backupToken=bin2hex(random_bytes(20));$_SESSION['backup_file']=$backupFile;$_SESSION['backup_token']=$backupToken;$backupLink='?download_backup='.$backupToken;
    $applied=$migrator->migrate();
    if(!$modernSchema){$stmt=$pdo->prepare("INSERT INTO users(mobile,name,password_hash,role,is_active)VALUES(?,?,?,'admin',1) ON DUPLICATE KEY UPDATE name=VALUES(name),password_hash=VALUES(password_hash),role='admin',is_active=1");$stmt->execute([$mobile,$values['admin_name'],password_hash($values['admin_password'],PASSWORD_DEFAULT)]);}
    file_put_contents(ROOT.'/storage/installed.lock','updated_at='.gmdate('c').PHP_EOL.'version='.$targetVersion.PHP_EOL,LOCK_EX);$currentVersion=$targetVersion;$pendingPreview=[];$success='بروزرسانی با موفقیت انجام شد: '.($applied?implode(' ← ',$applied):'بدون تغییر دیتابیس');
   }
  }
 }
}catch(Throwable $e){if(isset($_SESSION['backup_token']))$backupLink='?download_backup='.$_SESSION['backup_token'];$error=$e instanceof PDOException?'اتصال یا عملیات دیتابیس ناموفق بود. مشخصات، دسترسی‌ها و فضای دیتابیس را بررسی کنید.':$e->getMessage();error_log('Installer/Updater: '.$e->getMessage());}
?>
<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>نصب و بروزرسانی سامانه</title><style>
:root{font-family:Tahoma,"Segoe UI",sans-serif;color:#172033;background:#f3f6fb}*{box-sizing:border-box}body{margin:0}.wrap{width:min(940px,calc(100% - 28px));margin:30px auto}.head,.card{background:#fff;border:1px solid #e1e7f0;border-radius:16px;padding:24px;margin-bottom:16px;box-shadow:0 5px 22px #1720330b}.head{background:linear-gradient(135deg,#172554,#2563eb);color:#fff}.head h1{margin:0 0 8px}.head p{margin:0;color:#dbeafe}.status{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-top:20px}.status div{padding:11px;background:#ffffff16;border-radius:9px;text-align:center}.reqs,.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.req{display:flex;justify-content:space-between;border:1px solid #e5e7eb;border-radius:9px;padding:10px}.ok{color:#15803d}.bad{color:#b91c1c}label{display:grid;gap:7px;font-weight:600;color:#344054}.span{grid-column:1/-1}input,select{width:100%;padding:11px;border:1px solid #cfd8e6;border-radius:9px;direction:ltr;text-align:left;background:#fff}.hint{font-size:12px;color:#667085;font-weight:400}.alert{padding:13px;border-radius:9px;margin-bottom:14px}.error{background:#fee2e2;color:#991b1b}.success{background:#dcfce7;color:#166534}.warn{background:#fffbeb;color:#854d0e}.backup{background:#eff6ff;color:#1e40af}.backup a{display:inline-block;margin-top:8px;font-weight:700;color:#1d4ed8}.btn{border:0;border-radius:9px;padding:12px 20px;background:#2563eb;color:#fff;font-weight:700;cursor:pointer}.versions{display:flex;flex-wrap:wrap;gap:8px}.version{background:#eff6ff;color:#1d4ed8;padding:6px 9px;border-radius:20px}@media(max-width:700px){.grid,.reqs,.status{grid-template-columns:1fr}.span{grid-column:auto}}</style></head>
<body><main class="wrap"><header class="head"><h1>نصب و بروزرسانی سامانه پیش‌فاکتور</h1><p>اجرای خودکار و ترتیبی فایل‌های بروزرسانی دیتابیس</p><div class="status"><div>وضعیت: <?=$installed?'نصب‌شده':'نصب تازه'?></div><div>نسخه دیتابیس: <?=h($currentVersion)?></div><div>نسخه بسته: <?=h($targetVersion)?></div></div></header>
<section class="card"><h2>پیش‌نیازها</h2><div class="reqs"><?php foreach($requirements as $r):?><div class="req"><span><?=h($r['name'])?></span><strong class="<?=$r['ok']?'ok':'bad'?>"><?=$r['ok']?'آماده':'نیاز به اصلاح'?></strong></div><?php endforeach;?></div></section>
<?php if($error):?><div class="alert error"><?=h($error)?></div><?php endif;?><?php if($success):?><div class="alert success"><strong><?=h($success)?></strong><br><br><a href="../">ورود به سامانه</a></div><?php endif;?><?php if($backupLink):?><div class="alert backup"><strong>نسخه پشتیبان دیتابیس پیش از بروزرسانی ساخته شد.</strong><br><a href="<?=h($backupLink)?>">دانلود فایل Backup دیتابیس</a></div><?php endif;?>
<?php if($installed):?><section class="card"><h2>بروزرسانی‌های در انتظار</h2><div class="versions"><?php if(!$pendingPreview):?><span class="ok">بروزرسانی جدیدی وجود ندارد.</span><?php else:foreach($pendingPreview as $v=>$u):?><span class="version"><?=h($v)?> — <?=h($u['description'])?></span><?php endforeach;endif;?></div></section><?php endif;?>
<?php if(!$installed||$pendingPreview):?><form class="card" method="post" autocomplete="off"><input type="hidden" name="csrf" value="<?=h($_SESSION['install_csrf'])?>">
<?php if(!$installed):?><h2>تنظیمات نصب تازه</h2><div class="grid"><label class="span">آدرس سامانه<input name="app_url" value="<?=h($values['app_url'])?>" required></label><label>میزبان دیتابیس<input name="db_host" value="<?=h($values['db_host'])?>" required></label><label>پورت<input name="db_port" value="<?=h($values['db_port'])?>" required></label><label>نام دیتابیس<input name="db_name" value="<?=h($values['db_name'])?>" required></label><label>کاربر دیتابیس<input name="db_user" value="<?=h($values['db_user'])?>" required></label><label class="span">رمز دیتابیس<input type="password" name="db_pass"></label></div>
<h2>IPPanel</h2><div class="grid"><label class="span">Access Key<input type="password" name="ippanel_api_key"></label><label>شماره ارسال‌کننده<input name="ippanel_from_number" value="<?=h($values['ippanel_from_number'])?>"></label><label>کد Pattern<input name="ippanel_pattern_code" value="<?=h($values['ippanel_pattern_code'])?>"></label><label>نام متغیر OTP<input name="ippanel_otp_param" value="<?=h($values['ippanel_otp_param'])?>"></label><label>پیشوند Authorization<input name="ippanel_auth_prefix" value="<?=h($values['ippanel_auth_prefix'])?>"></label></div><?php endif;?>
<h2><?=$installed?'احراز هویت برای بروزرسانی':'مدیر اولیه سامانه'?></h2><div class="grid">
<?php if(!$installed):?><label>نام مدیر<input name="admin_name" value="<?=h($values['admin_name'])?>" required></label><label>موبایل مدیر<input name="admin_mobile" value="<?=h($values['admin_mobile'])?>" required></label><label>رمز مدیر<input type="password" name="admin_password" required></label><label>تکرار رمز مدیر<input type="password" name="admin_password_confirmation" required></label>
<?php elseif($modernSchema):?><label>مدیر اجراکننده بروزرسانی<select name="admin_id" required><?php foreach($admins as $admin):?><option value="<?=h($admin['id'])?>"><?=h(($admin['name']?:'مدیر').' — '.maskMobile($admin['mobile']))?></option><?php endforeach;?></select></label><label>رمز عبور مدیر<input type="password" name="admin_password" required autocomplete="current-password"></label>
<?php else:?><label>نام مدیر جدید<input name="admin_name" value="<?=h($values['admin_name'])?>" required></label><label>موبایل مدیر<input name="admin_mobile" value="<?=h($values['admin_mobile'])?>" required></label><label>رمز مدیر<input type="password" name="admin_password" required></label><label class="span">کلید ارتقای نسخه قدیمی<input type="password" name="legacy_app_key" required><span class="hint">مقدار APP_KEY فایل .env را وارد کنید.</span></label><?php endif;?></div>
<?php if($installed):?><div class="alert warn" style="margin-top:18px">پیش از اعمال تغییرات، Backup کامل دیتابیس به‌صورت خودکار ساخته می‌شود و لینک دانلود آن در همین صفحه قرار می‌گیرد.</div><?php endif;?><button class="btn" <?=$requirementsOk?'':'disabled'?>><?=$installed?'ساخت Backup و اجرای بروزرسانی‌ها':'نصب و اجرای تمام نسخه‌ها'?></button></form><?php endif;?>
</main></body></html>
