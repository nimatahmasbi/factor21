<?php
return [
    'description' => 'بسته کامل UI: داشبورد پویا، قالب من کامل، لوگو، فونت Dima',
    'up' => function (PDO $pdo, Migrator $m): void {
        if($m->tableExists('quotes')){$cols=$pdo->query("SHOW COLUMNS FROM quotes")->fetchAll(PDO::FETCH_COLUMN,0);if(in_array('is_latest',$cols)){$pdo->exec("UPDATE quotes SET is_latest=1 WHERE is_latest IS NULL");}}
        $pdo->prepare("INSERT INTO system_settings(setting_key,setting_value,is_encrypted,updated_by) VALUES('app.schema_version',?,0,NULL) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)")->execute(['1.6.7']);
    },
];