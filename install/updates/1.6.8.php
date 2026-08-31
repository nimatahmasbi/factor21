<?php
return [
    'description' => 'بازگردانی کامل: قالب من، لوگو، داشبورد و داده‌های کاربران',
    'up' => function (PDO $pdo, Migrator $m): void {
        if($m->tableExists('quotes')){$cols=$pdo->query("SHOW COLUMNS FROM quotes")->fetchAll(PDO::FETCH_COLUMN,0);if(in_array('is_latest',$cols)){$pdo->exec("UPDATE quotes SET is_latest=1 WHERE is_latest IS NULL OR is_latest=0");}}
        if($m->tableExists('output_templates')){$cols=$pdo->query("SHOW COLUMNS FROM output_templates")->fetchAll(PDO::FETCH_COLUMN,0);if(!in_array('user_id',$cols)) $pdo->exec("ALTER TABLE output_templates ADD COLUMN user_id BIGINT UNSIGNED NULL AFTER created_by, ADD INDEX(user_id)");if(!in_array('typography_json',$cols)) $pdo->exec("ALTER TABLE output_templates ADD COLUMN typography_json LONGTEXT NULL AFTER config_json");if(!in_array('is_global',$cols)) $pdo->exec("ALTER TABLE output_templates ADD COLUMN is_global TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active");}
        $pdo->prepare("INSERT INTO system_settings(setting_key,setting_value,is_encrypted,updated_by) VALUES('app.schema_version',?,0,NULL) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)")->execute(['1.6.8']);
    },
];