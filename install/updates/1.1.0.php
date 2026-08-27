<?php
return [
    'description'=>'افزودن ورود رمز، نقش مدیر و تنظیمات سامانه',
    'up'=>function(PDO $pdo,Migrator $m):void{
        if(!$m->columnExists('users','email'))$pdo->exec("ALTER TABLE users ADD COLUMN email VARCHAR(190) NULL AFTER name");
        if(!$m->columnExists('users','password_hash'))$pdo->exec("ALTER TABLE users ADD COLUMN password_hash VARCHAR(255) NULL AFTER email");
        if(!$m->columnExists('users','role'))$pdo->exec("ALTER TABLE users ADD COLUMN role ENUM('user','admin') NOT NULL DEFAULT 'user' AFTER password_hash");
        if(!$m->columnExists('users','last_login_at'))$pdo->exec("ALTER TABLE users ADD COLUMN last_login_at DATETIME NULL AFTER is_active");
        $pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (
            setting_key VARCHAR(100) PRIMARY KEY,setting_value LONGTEXT NULL,is_encrypted TINYINT(1) NOT NULL DEFAULT 0,updated_by BIGINT UNSIGNED NULL,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY(updated_by)REFERENCES users(id)ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,mobile VARCHAR(15) NOT NULL,request_ip VARCHAR(45) NOT NULL,was_successful TINYINT(1) NOT NULL DEFAULT 0,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX(mobile,created_at),INDEX(request_ip,created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    },
];

