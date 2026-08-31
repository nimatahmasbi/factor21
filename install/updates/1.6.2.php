<?php
return [
    'description' => 'همگام‌سازی نسخه بسته با دیتابیس + اصلاح جدول پیش‌نمایش (حذف ردیف‌های خالی)، لوگو و نام فاکتور 21، فونت‌های Dima و پیش‌نمایش',
    'up' => function (PDO $pdo, Migrator $m): void {
        // No schema change required for JS/CSS fixes, only version bump
        // Ensure typography and short_code columns exist (idempotent)
        if ($m->tableExists('output_templates')) {
            $cols = $pdo->query("SHOW COLUMNS FROM output_templates")->fetchAll(PDO::FETCH_COLUMN,0);
            if (!in_array('typography_json', $cols)) {
                $pdo->exec("ALTER TABLE output_templates ADD COLUMN typography_json LONGTEXT NULL AFTER config_json");
            }
            if (!in_array('user_id', $cols)) {
                $pdo->exec("ALTER TABLE output_templates ADD COLUMN user_id BIGINT UNSIGNED NULL AFTER created_by, ADD INDEX(user_id)");
            }
            if (!in_array('is_global', $cols)) {
                $pdo->exec("ALTER TABLE output_templates ADD COLUMN is_global TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active");
            }
        }
        if ($m->tableExists('quote_shares')) {
            $cols = $pdo->query("SHOW COLUMNS FROM quote_shares")->fetchAll(PDO::FETCH_COLUMN,0);
            if (!in_array('short_code', $cols)) {
                $pdo->exec("ALTER TABLE quote_shares ADD COLUMN short_code VARCHAR(16) NULL AFTER token_hash, ADD UNIQUE INDEX(short_code)");
            }
        }
        // Update app name to فاکتور 21 if not set
        $stmt = $pdo->prepare("INSERT INTO system_settings(setting_key,setting_value,is_encrypted,updated_by) VALUES('app.name','فاکتور 21',0,NULL) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
        // Do not overwrite if already customized, so check first
        $cur = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key='app.name'")->fetchColumn();
        if (!$cur || $cur==='پیش‌فاکتور من') {
            $pdo->prepare("UPDATE system_settings SET setting_value='فاکتور 21' WHERE setting_key='app.name'")->execute();
        }
        $pdo->prepare("INSERT INTO system_settings(setting_key,setting_value,is_encrypted,updated_by) VALUES('app.schema_version',?,0,NULL) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)")->execute(['1.6.2']);
    },
];
