<?php
return [
    'description' => 'قالب اختصاصی هر کاربر + فونت/سایز مجزا + لینک کوتاه + بهبود جلالی و عنوان خروجی',
    'up' => function (PDO $pdo, Migrator $m): void {
        // 1) Add user_id and typography to output_templates
        if ($m->tableExists('output_templates')) {
            $cols = $pdo->query("SHOW COLUMNS FROM output_templates")->fetchAll(PDO::FETCH_COLUMN,0);
            if (!in_array('user_id', $cols)) {
                $pdo->exec("ALTER TABLE output_templates ADD COLUMN user_id BIGINT UNSIGNED NULL AFTER created_by, ADD INDEX(user_id), ADD FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE");
            }
            if (!in_array('typography_json', $cols)) {
                $pdo->exec("ALTER TABLE output_templates ADD COLUMN typography_json LONGTEXT NULL AFTER config_json");
            }
            // expand paper_size enum to include a3,a6,legal
            try { $pdo->exec("ALTER TABLE output_templates MODIFY paper_size ENUM('a3','a4','a5','a6','letter','legal') NOT NULL DEFAULT 'a4'"); } catch(Throwable $e){}
            // is_global flag for admin templates shared
            if (!in_array('is_global', $cols)) {
                $pdo->exec("ALTER TABLE output_templates ADD COLUMN is_global TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active");
                $pdo->exec("UPDATE output_templates SET is_global=1 WHERE is_default=1 OR created_by IS NULL");
            }
        }
        // 2) Add short link support to quote_shares
        if ($m->tableExists('quote_shares')) {
            $cols = $pdo->query("SHOW COLUMNS FROM quote_shares")->fetchAll(PDO::FETCH_COLUMN,0);
            if (!in_array('short_code', $cols)) {
                $pdo->exec("ALTER TABLE quote_shares ADD COLUMN short_code VARCHAR(16) NULL AFTER token_hash, ADD UNIQUE INDEX(short_code)");
            }
            if (!in_array('view_count', $cols)) {
                // ensure exists (some versions)
                try { $pdo->exec("ALTER TABLE quote_shares ADD COLUMN view_count INT UNSIGNED NOT NULL DEFAULT 0"); } catch(Throwable $e){}
            }
        }
        // 3) Ensure quote_approvals pattern code setting
        $stmt = $pdo->prepare("INSERT INTO system_settings(setting_key,setting_value,is_encrypted,updated_by) VALUES('sms.approval_pattern_code','',0,NULL) ON DUPLICATE KEY UPDATE setting_value=setting_value");
        $stmt->execute();
        $stmt = $pdo->prepare("INSERT INTO system_settings(setting_key,setting_value,is_encrypted,updated_by) VALUES('app.schema_version',?,0,NULL) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
        $stmt->execute(['1.4.7']);
    },
];
