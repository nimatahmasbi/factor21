<?php
return [
    'description'=>'اصلاح و همگام‌سازی ساختار ثبت اطلاعات شرکت',
    'up'=>function(PDO $pdo,Migrator $m):void{
        $columns=[
            'legal_name'=>"VARCHAR(220) NULL AFTER name",
            'national_id'=>"VARCHAR(30) NULL AFTER legal_name",
            'economic_code'=>"VARCHAR(30) NULL AFTER national_id",
            'registration_no'=>"VARCHAR(30) NULL AFTER economic_code",
            'phone'=>"VARCHAR(30) NULL AFTER registration_no",
            'mobile'=>"VARCHAR(15) NULL AFTER phone",
            'email'=>"VARCHAR(190) NULL AFTER mobile",
            'website'=>"VARCHAR(190) NULL AFTER email",
            'address'=>"TEXT NULL AFTER website",
            'postal_code'=>"VARCHAR(20) NULL AFTER address",
            'logo_path'=>"VARCHAR(255) NULL AFTER postal_code",
            'stamp_path'=>"VARCHAR(255) NULL AFTER logo_path",
            'signature_path'=>"VARCHAR(255) NULL AFTER stamp_path",
            'brand_color'=>"VARCHAR(7) NOT NULL DEFAULT '#2563eb' AFTER signature_path",
            'default_tax'=>"DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER brand_color",
            'quote_prefix'=>"VARCHAR(20) NOT NULL DEFAULT 'PF' AFTER default_tax",
            'next_quote_number'=>"BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER quote_prefix",
            'bank_info'=>"TEXT NULL AFTER next_quote_number",
            'default_terms'=>"TEXT NULL AFTER bank_info",
        ];
        foreach($columns as $name=>$definition)if(!$m->columnExists('companies',$name))$pdo->exec("ALTER TABLE companies ADD COLUMN ".$name." ".$definition);
        if(!$m->indexExists('companies','user_id')&&!$m->indexExists('companies','idx_companies_user_id'))$pdo->exec("CREATE INDEX idx_companies_user_id ON companies(user_id)");
        $stmt=$pdo->prepare("INSERT INTO system_settings(setting_key,setting_value,is_encrypted,updated_by)VALUES('app.schema_version',?,0,NULL) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
        $stmt->execute(['1.2.1']);
    },
];
