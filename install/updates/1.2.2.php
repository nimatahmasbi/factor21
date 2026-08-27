<?php
return [
    'description'=>'ثبت نسخه رابط فهرست و مدیریت شرکت‌ها',
    'up'=>function(PDO $pdo,Migrator $m):void{
        $stmt=$pdo->prepare("INSERT INTO system_settings(setting_key,setting_value,is_encrypted,updated_by)VALUES('app.schema_version',?,0,NULL) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
        $stmt->execute(['1.2.2']);
    },
];

