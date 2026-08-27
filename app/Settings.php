<?php
namespace App;

final class Settings {
    private static array $cache = [];

    public static function get(string $key, mixed $default = null): mixed {
        if (array_key_exists($key, self::$cache)) return self::$cache[$key];
        try {
            $row = DB::one('SELECT setting_value,is_encrypted FROM system_settings WHERE setting_key=?', [$key]);
        } catch (\Throwable) {
            return $default;
        }
        if (!$row) return self::$cache[$key] = $default;
        $value = (string)$row['setting_value'];
        if ((int)$row['is_encrypted'] === 1) $value = self::decrypt($value);
        return self::$cache[$key] = $value;
    }

    public static function set(string $key, string $value, bool $encrypted, int $userId): void {
        $stored = $encrypted ? self::encrypt($value) : $value;
        DB::exec('INSERT INTO system_settings(setting_key,setting_value,is_encrypted,updated_by)VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),is_encrypted=VALUES(is_encrypted),updated_by=VALUES(updated_by)', [$key,$stored,$encrypted?1:0,$userId]);
        self::$cache[$key] = $value;
    }

    private static function key(): string {
        return hash('sha256', (string)env('APP_KEY'), true);
    }

    private static function encrypt(string $plain): string {
        if ($plain === '') return '';
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) throw new \RuntimeException('رمزگذاری تنظیمات ناموفق بود.');
        return base64_encode($iv . $tag . $cipher);
    }

    private static function decrypt(string $encoded): string {
        if ($encoded === '') return '';
        $raw = base64_decode($encoded, true);
        if ($raw === false || strlen($raw) < 29) return '';
        $plain = openssl_decrypt(substr($raw,28), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr($raw,0,12), substr($raw,12,16));
        return $plain === false ? '' : $plain;
    }
}
