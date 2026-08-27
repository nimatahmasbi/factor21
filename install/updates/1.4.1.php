<?php
return [
    'description'=>'افزودن مجموعه قالب‌های آماده به طراح فرم خروجی',
    'up'=>function(PDO $pdo,Migrator $m):void{
        $baseColumns=['code'=>true,'unit'=>true,'quantity'=>true,'unit_price'=>true,'gross'=>true,'discount'=>true,'after_discount'=>true,'tax'=>true,'total'=>true];
        $baseSections=['seller'=>true,'buyer'=>true,'notes'=>true,'payment'=>true,'signatures'=>true,'footer'=>true];
        $order=['header','seller','buyer','items','notes','signatures','footer'];
        $templates=[
            ['فرم رسمی پیش‌فرض','a4','landscape','formal',$baseColumns,$baseSections],
            ['رسمی A4 عمودی','a4','portrait','formal',$baseColumns,$baseSections],
            ['رسمی A5 افقی','a5','landscape','formal',$baseColumns,$baseSections],
            ['رسمی A5 عمودی','a5','portrait','formal',array_merge($baseColumns,['code'=>false,'gross'=>false,'after_discount'=>false]),$baseSections],
            ['مدرن شرکتی A4 افقی','a4','landscape','modern',$baseColumns,$baseSections],
            ['مدرن شرکتی A4 عمودی','a4','portrait','modern',array_merge($baseColumns,['code'=>false]),$baseSections],
            ['کم‌جوهر A4 افقی','a4','landscape','minimal',array_merge($baseColumns,['code'=>false,'discount'=>false,'after_discount'=>false,'tax'=>false]),array_merge($baseSections,['footer'=>false])],
            ['فرم خلاصه A5 افقی','a5','landscape','minimal',array_merge($baseColumns,['code'=>false,'gross'=>false,'discount'=>false,'after_discount'=>false,'tax'=>false]),array_merge($baseSections,['seller'=>false,'footer'=>false])],
            ['Letter عمودی بین‌المللی','letter','portrait','modern',array_merge($baseColumns,['code'=>false]),$baseSections],
        ];
        $exists=$pdo->prepare('SELECT id FROM output_templates WHERE name=? LIMIT 1');$insert=$pdo->prepare('INSERT INTO output_templates(name,paper_size,orientation,style,config_json,is_default,is_active)VALUES(?,?,?,?,?,0,1)');
        foreach($templates as [$name,$paper,$orientation,$style,$columns,$sections]){$exists->execute([$name]);if($exists->fetchColumn())continue;$config=json_encode(['columns'=>$columns,'sections'=>$sections,'order'=>$order],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$insert->execute([$name,$paper,$orientation,$style,$config]);}
        $stmt=$pdo->prepare("INSERT INTO system_settings(setting_key,setting_value,is_encrypted,updated_by)VALUES('app.schema_version',?,0,NULL) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");$stmt->execute(['1.4.1']);
    },
];
