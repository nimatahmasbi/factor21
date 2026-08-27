<?php
namespace App;
final class Otp {
    public static function normalize(string $mobile):string {
        $mobile=preg_replace('/\D+/','',strtr(trim($mobile),['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9']))??'';
        if(str_starts_with($mobile,'98'))$mobile='0'.substr($mobile,2);
        return preg_match('/^09\d{9}$/',$mobile)?$mobile:'';
    }
    public static function sendPattern(string $mobile,string $pattern,array $params):array {
        $mobile=self::normalize($mobile);if(!$mobile)return[false,'شماره موبایل معتبر نیست.'];
        $apiKey=(string)Settings::get('sms.api_key',(string)env('IPPANEL_API_KEY',''));if($apiKey===''||$pattern==='')return[false,'سرویس یا الگوی پیامک تنظیم نشده است.'];
        $payload=['sending_type'=>'pattern','from_number'=>(string)Settings::get('sms.from_number',(string)env('IPPANEL_FROM_NUMBER')),'code'=>$pattern,'recipients'=>['+98'.substr($mobile,1)],'params'=>$params];
        $ch=curl_init('https://edge.ippanel.com/v1/api/send');curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>12,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_HTTPHEADER=>['Authorization: '.(string)Settings::get('sms.auth_prefix',(string)env('IPPANEL_AUTH_PREFIX','')).$apiKey,'Content-Type: application/json','Accept: application/json'],CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
        $raw=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);$decoded=json_decode((string)$raw,true);$ok=$raw!==false&&$status>=200&&$status<300&&(($decoded['meta']['status']??true)!==false);
        if(!$ok){error_log('IPPanel pattern send failed status='.$status.' error='.$err.' response='.substr((string)$raw,0,500));return[false,'ارسال پیامک انجام نشد.'];}return[true,'پیامک ارسال شد.'];
    }
    public static function send(string $mobile):array {
        $mobile=self::normalize($mobile);if(!$mobile)return[false,'شماره موبایل معتبر نیست.'];
        $ip=substr($_SERVER['REMOTE_ADDR']??'0.0.0.0',0,45);$cool=(int)Settings::get('otp.resend_seconds',(string)env('OTP_RESEND_SECONDS',60));
        if(DB::one('SELECT id FROM otp_codes WHERE (mobile=? OR request_ip=?) AND created_at>DATE_SUB(NOW(),INTERVAL ? SECOND) LIMIT 1',[$mobile,$ip,$cool]))return[false,'لطفاً تا پایان زمان ارسال مجدد صبر کنید.'];
        $rate=DB::one('SELECT COUNT(*) c FROM otp_codes WHERE (mobile=? OR request_ip=?) AND created_at>DATE_SUB(NOW(),INTERVAL 1 HOUR)',[$mobile,$ip]);
        if((int)($rate['c']??0)>=10)return[false,'تعداد درخواست بیش از حد مجاز است. کمی بعد تلاش کنید.'];
        $code=(string)random_int(100000,999999);
        DB::exec('UPDATE otp_codes SET used_at=NOW() WHERE mobile=? AND used_at IS NULL',[$mobile]);
        DB::exec('INSERT INTO otp_codes(mobile,code_hash,request_ip,expires_at)VALUES(?,?,?,DATE_ADD(NOW(),INTERVAL ? SECOND))',[$mobile,password_hash($code,PASSWORD_DEFAULT),$ip,(int)Settings::get('otp.ttl_seconds',(string)env('OTP_TTL_SECONDS',120))]);
        $apiKey=(string)Settings::get('sms.api_key',(string)env('IPPANEL_API_KEY',''));
        if($apiKey===''){if(env('APP_ENV')==='local')return[true,'کد آزمایشی: '.$code];return[false,'سرویس پیامک تنظیم نشده است.'];}
        $payload=['sending_type'=>'pattern','from_number'=>(string)Settings::get('sms.from_number',(string)env('IPPANEL_FROM_NUMBER')),'code'=>(string)Settings::get('sms.pattern_code',(string)env('IPPANEL_PATTERN_CODE')),'recipients'=>['+98'.substr($mobile,1)],'params'=>[(string)Settings::get('sms.otp_param',(string)env('IPPANEL_OTP_PARAM','code'))=>$code]];
        $ch=curl_init('https://edge.ippanel.com/v1/api/send');
        curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>10,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_HTTPHEADER=>['Authorization: '.(string)Settings::get('sms.auth_prefix',(string)env('IPPANEL_AUTH_PREFIX','')).$apiKey,'Content-Type: application/json','Accept: application/json'],CURLOPT_POSTFIELDS=>json_encode($payload)]);
        $raw=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);$decoded=json_decode((string)$raw,true);
        if($raw===false||$status!==200||!($decoded['meta']['status']??false)){error_log('IPPanel send failed status='.$status.' error='.$err);return[false,'ارسال پیامک انجام نشد. دوباره تلاش کنید.'];}
        return[true,'کد تأیید ارسال شد.'];
    }
    public static function verify(string $mobile,string $code):array {
        $mobile=self::normalize($mobile);if(!$mobile||!preg_match('/^\d{6}$/',$code))return[false,'اطلاعات واردشده معتبر نیست.'];
        $row=DB::one('SELECT * FROM otp_codes WHERE mobile=? AND used_at IS NULL AND expires_at>NOW() ORDER BY id DESC LIMIT 1',[$mobile]);
        if(!$row||(int)$row['attempts']>=5||!password_verify($code,$row['code_hash'])){if($row)DB::exec('UPDATE otp_codes SET attempts=attempts+1 WHERE id=?',[$row['id']]);return[false,'کد تأیید نادرست یا منقضی شده است.'];}
        DB::exec('UPDATE otp_codes SET used_at=NOW() WHERE id=?',[$row['id']]);$user=DB::one('SELECT id FROM users WHERE mobile=?',[$mobile]);
        if(!$user){DB::exec('INSERT INTO users(mobile)VALUES(?)',[$mobile]);$id=(int)DB::pdo()->lastInsertId();}else $id=(int)$user['id'];
        Auth::login($id);return[true,'ورود موفق بود.'];
    }
}
