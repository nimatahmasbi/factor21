<?php
return [
    'description' => 'رفع نمایش دیتای قبل کاربر - اطمینان از is_latest و بازیابی شرکت‌ها و فاکتورها',
    'up' => function (PDO $pdo, Migrator $m): void {
        // Ensure is_latest is set for old quotes (if column exists)
        if ($m->tableExists('quotes')) {
            $cols = $pdo->query("SHOW COLUMNS FROM quotes")->fetchAll(PDO::FETCH_COLUMN,0);
            if (in_array('is_latest', $cols)) {
                $pdo->exec("UPDATE quotes SET is_latest=1 WHERE is_latest IS NULL OR is_latest=0");
                // Ensure at least the latest per company is marked
                $pdo->exec("UPDATE quotes q JOIN (SELECT MAX(id) as max_id FROM quotes GROUP BY quote_number) m ON m.max_id=q.id SET q.is_latest=1 WHERE q.is_latest=0");
            }
            if (in_array('parent_quote_id', $cols)) {
                $pdo->exec("UPDATE quotes SET parent_quote_id=NULL WHERE parent_quote_id=0");
            }
        }
        // Ensure companies are not filtered incorrectly
        if ($m->tableExists('companies')) {
            // No change, just ensure next_quote_number is valid
            $pdo->exec("UPDATE companies SET next_quote_number=GREATEST(next_quote_number,1) WHERE next_quote_number IS NULL OR next_quote_number<1");
        }
        $pdo->prepare("INSERT INTO system_settings(setting_key,setting_value,is_encrypted,updated_by) VALUES('app.schema_version',?,0,NULL) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)")->execute(['1.6.3']);
    },
];
