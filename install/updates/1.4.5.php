<?php
return [
    'description' => 'افزودن جدول گفتگوی آنلاین کاربر و مدیر سامانه',
    'up' => function (PDO $pdo, Migrator $m): void {
        if (!$m->tableExists('chat_messages')) {
            $pdo->exec("CREATE TABLE chat_messages (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                sender_id BIGINT UNSIGNED NOT NULL,
                sender_role ENUM('user','admin') NOT NULL,
                body TEXT NOT NULL,
                is_read_user TINYINT(1) NOT NULL DEFAULT 0,
                is_read_admin TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY(sender_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX(user_id,created_at),
                INDEX(user_id,sender_role,is_read_admin),
                INDEX(user_id,sender_role,is_read_user)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
        $stmt = $pdo->prepare("INSERT INTO system_settings(setting_key,setting_value,is_encrypted,updated_by)VALUES('app.schema_version',?,0,NULL) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
        $stmt->execute(['1.4.5']);
    },
];
