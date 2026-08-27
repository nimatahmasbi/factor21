<?php
namespace App;
final class Auth {
    public static function check():bool{if(!isset($_SESSION['user_id']))return false;try{return DB::one('SELECT id FROM users WHERE id=? AND is_active=1',[(int)$_SESSION['user_id']])!==null;}catch(\Throwable){return false;}}
    public static function id():int{return (int)($_SESSION['user_id']??0);}
    public static function user():?array{return self::check()?DB::one('SELECT id,mobile,name,email,role,locale,last_login_at FROM users WHERE id=? AND is_active=1',[self::id()]):null;}
    public static function login(int $id):void{session_regenerate_id(true);$_SESSION['user_id']=$id;$_SESSION['csrf']=bin2hex(random_bytes(32));DB::exec('UPDATE users SET last_login_at=NOW() WHERE id=?',[$id]);}
    public static function logout():void{$_SESSION=[];if(ini_get('session.use_cookies'))setcookie(session_name(),'',time()-42000,'/');session_destroy();}
    public static function require():void{if(!self::check())json_response(['ok'=>false,'message'=>'نیاز به ورود دارید.'],401);}
    public static function csrf():void{$token=$_SERVER['HTTP_X_CSRF_TOKEN']??'';if(!hash_equals($_SESSION['csrf']??'',$token))json_response(['ok'=>false,'message'=>'درخواست نامعتبر است. صفحه را تازه کنید.'],419);}
    public static function admin():bool{return (self::user()['role']??'')==='admin';}
    public static function requireAdmin():void{self::require();if(!self::admin())json_response(['ok'=>false,'message'=>'این بخش فقط برای مدیر سامانه است.'],403);}
    public static function passwordLogin(string $mobile,string $password):array{
        $mobile=Otp::normalize($mobile);if(!$mobile||$password==='')return[false,'شماره موبایل یا رمز عبور نادرست است.'];
        $ip=substr($_SERVER['REMOTE_ADDR']??'0.0.0.0',0,45);
        $rate=DB::one('SELECT COUNT(*) c FROM login_attempts WHERE was_successful=0 AND (mobile=? OR request_ip=?) AND created_at>DATE_SUB(NOW(),INTERVAL 15 MINUTE)',[$mobile,$ip]);
        if((int)($rate['c']??0)>=8)return[false,'تلاش‌های ناموفق بیش از حد مجاز است. ۱۵ دقیقه بعد دوباره امتحان کنید.'];
        $user=DB::one('SELECT id,password_hash,is_active FROM users WHERE mobile=?',[$mobile]);
        if(!$user||!(int)$user['is_active']||!$user['password_hash']||!password_verify($password,$user['password_hash'])){DB::exec('INSERT INTO login_attempts(mobile,request_ip)VALUES(?,?)',[$mobile,$ip]);usleep(300000);return[false,'شماره موبایل یا رمز عبور نادرست است.'];}
        DB::exec('INSERT INTO login_attempts(mobile,request_ip,was_successful)VALUES(?,?,1)',[$mobile,$ip]);
        self::login((int)$user['id']);return[true,'ورود موفق بود.'];
    }
}
