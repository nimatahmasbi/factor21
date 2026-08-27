<?php
return [
    'description'=>'قالب‌های چندگانه خروجی و اشتراک‌گذاری امن پیش‌فاکتور',
    'up'=>function(PDO $pdo,Migrator $m):void{
        if(!$m->tableExists('quote_shares'))$pdo->exec("CREATE TABLE quote_shares (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            quote_id BIGINT UNSIGNED NOT NULL,user_id BIGINT UNSIGNED NOT NULL,
            token_hash CHAR(64) NOT NULL UNIQUE,password_hash VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,view_count INT UNSIGNED NOT NULL DEFAULT 0,
            last_viewed_at DATETIME NULL,revoked_at DATETIME NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(quote_id) REFERENCES quotes(id) ON DELETE CASCADE,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX(quote_id),INDEX(user_id,expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $stmt=$pdo->prepare("INSERT INTO system_settings(setting_key,setting_value,is_encrypted,updated_by)VALUES('app.schema_version',?,0,NULL) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
        $stmt->execute(['1.3.0']);
    },
];
