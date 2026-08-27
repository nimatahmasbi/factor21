<?php
return [
    'description'=>'طراح مدیریتی فرم‌ها و ستون‌های خروجی',
    'up'=>function(PDO $pdo,Migrator $m):void{
        if(!$m->tableExists('output_templates'))$pdo->exec("CREATE TABLE output_templates (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,name VARCHAR(120) NOT NULL,
            paper_size ENUM('a4','a5','letter') NOT NULL DEFAULT 'a4',orientation ENUM('portrait','landscape') NOT NULL DEFAULT 'landscape',
            style ENUM('formal','modern','minimal') NOT NULL DEFAULT 'formal',config_json LONGTEXT NOT NULL,
            is_default TINYINT(1) NOT NULL DEFAULT 0,is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_by BIGINT UNSIGNED NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,INDEX(is_active,is_default)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $count=(int)$pdo->query('SELECT COUNT(*) FROM output_templates')->fetchColumn();if($count===0){$config=json_encode(['columns'=>['code'=>true,'unit'=>true,'quantity'=>true,'unit_price'=>true,'gross'=>true,'discount'=>true,'after_discount'=>true,'tax'=>true,'total'=>true],'sections'=>['seller'=>true,'buyer'=>true,'notes'=>true,'payment'=>true,'signatures'=>true,'footer'=>true],'order'=>['header','seller','buyer','items','notes','signatures','footer']],JSON_UNESCAPED_UNICODE);$stmt=$pdo->prepare("INSERT INTO output_templates(name,paper_size,orientation,style,config_json,is_default,is_active)VALUES('فرم رسمی پیش‌فرض','a4','landscape','formal',?,1,1)");$stmt->execute([$config]);}
        $stmt=$pdo->prepare("INSERT INTO system_settings(setting_key,setting_value,is_encrypted,updated_by)VALUES('app.schema_version',?,0,NULL) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");$stmt->execute(['1.4.0']);
    },
];
