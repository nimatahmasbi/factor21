# راهنمای ساخت بروزرسانی دیتابیس

برای هر تغییری که به دیتابیس نیاز دارد، یک فایل PHP جدید با شماره نسخه ایجاد کنید:

~~~text
1.2.1.php
1.2.2.php
1.3.0.php
~~~

نمونه:

~~~php
<?php
return [
    'description' => 'افزودن ستون نمونه',
    'up' => function (PDO $pdo, Migrator $m): void {
        if (!$m->columnExists('customers', 'sample_column')) {
            $pdo->exec("ALTER TABLE customers ADD COLUMN sample_column VARCHAR(100) NULL");
        }
    },
];
~~~

قواعد:

- نام فایل باید دقیقاً از الگوی x.y.z پیروی کند.
- نسخه منتشرشده هرگز ویرایش نشود؛ اصلاح جدید باید شماره جدید داشته باشد.
- Migration باید تا حد ممکن idempotent و قابل ادامه باشد.
- پیش از ALTER یا CREATE، وجود ستون، جدول یا Index بررسی شود.
- حذف جدول یا ستون فقط در نسخه جداگانه و پس از Backup انجام شود.
- رمز، اطلاعات کاربر یا داده حساس در فایل Migration و Log نوشته نشود.
- موتور نصب فایل‌ها را با version_compare مرتب و یک‌به‌یک اجرا می‌کند.
- نتیجه هر نسخه و checksum آن در schema_migrations ثبت می‌شود.
- اگر همان نسخه قبلاً اجرا شده باشد، دوباره اجرا نخواهد شد.
