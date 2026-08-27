<?php
return [
    'description'=>'افزودن انواع کاغذ A3، A6 و Legal به طراح خروجی',
    'up'=>function(PDO $pdo,Migrator $m):void{
        $pdo->exec("ALTER TABLE output_templates MODIFY paper_size ENUM('a3','a4','a5','a6','letter','legal') NOT NULL DEFAULT 'a4'");
        $stmt=$pdo->prepare("INSERT INTO system_settings(setting_key,setting_value,is_encrypted,updated_by)VALUES('app.schema_version',?,0,NULL) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");$stmt->execute(['1.4.2']);
    },
];
