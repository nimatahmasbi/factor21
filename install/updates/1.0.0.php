<?php
return [
    'description'=>'ساخت ساختار پایه سامانه پیش‌فاکتور',
    'up'=>function(PDO $pdo,Migrator $m):void{
        if(!$m->tableExists('users')){$m->executeSqlFile(__DIR__.'/1.0.0.sql');return;}
        foreach(['otp_codes','companies','customers','catalog_items','quotes','quote_items','price_history','audit_logs'] as $table)if(!$m->tableExists($table))throw new RuntimeException('ساختار دیتابیس قدیمی ناقص یا ناشناخته است؛ بروزرسانی خودکار متوقف شد.');
    },
];
