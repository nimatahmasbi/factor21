<?php
return [
    'description' => 'افزودن امکان ثبت اصلاحیه نسخه‌دار برای فاکتورهای نهایی‌شده',
    'up' => function (PDO $pdo, Migrator $m): void {
        if (!$m->columnExists('quotes', 'parent_quote_id')) {
            $pdo->exec("ALTER TABLE quotes ADD COLUMN parent_quote_id BIGINT UNSIGNED NULL AFTER company_id");
        }
        if (!$m->columnExists('quotes', 'revision_no')) {
            $pdo->exec("ALTER TABLE quotes ADD COLUMN revision_no INT UNSIGNED NOT NULL DEFAULT 1 AFTER version");
        }
        if (!$m->columnExists('quotes', 'is_latest')) {
            $pdo->exec("ALTER TABLE quotes ADD COLUMN is_latest TINYINT(1) NOT NULL DEFAULT 1 AFTER revision_no");
        }
        if (!$m->indexExists('quotes', 'idx_parent_quote')) {
            $pdo->exec("ALTER TABLE quotes ADD INDEX idx_parent_quote (parent_quote_id)");
        }
        // اگر ستون parent_quote_id تازه اضافه شده باشد، اکنون کلید خارجی آن را هم اضافه می‌کنیم
        $fkExists = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='quotes' AND CONSTRAINT_NAME='fk_quote_parent'")->fetchColumn();
        if ($fkExists === 0) {
            $pdo->exec("ALTER TABLE quotes ADD CONSTRAINT fk_quote_parent FOREIGN KEY (parent_quote_id) REFERENCES quotes(id) ON DELETE SET NULL");
        }
        $stmt = $pdo->prepare("INSERT INTO system_settings(setting_key,setting_value,is_encrypted,updated_by)VALUES('app.schema_version',?,0,NULL) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
        $stmt->execute(['1.4.4']);
    },
];
