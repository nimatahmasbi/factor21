<?php
return [
    'description' => 'افزودن تایید مشتری با امضای دیجیتال OTP و متن تعهدنامه پیش‌فرض',
    'up' => function (PDO $pdo, Migrator $m): void {
        if (!$m->tableExists('quote_approvals')) {
            $pdo->exec("CREATE TABLE quote_approvals (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                quote_id BIGINT UNSIGNED NOT NULL,
                mobile VARCHAR(15) NOT NULL,
                otp_verified_at DATETIME NOT NULL,
                ip VARCHAR(45),
                user_agent VARCHAR(500),
                consent_text LONGTEXT NOT NULL,
                amount_payable DECIMAL(18,2) NOT NULL,
                quote_number VARCHAR(60) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(quote_id) REFERENCES quotes(id) ON DELETE CASCADE,
                INDEX(quote_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
        $default = "اینجانب با مشاهده کامل پیش‌فاکتور شماره {quote_number} به مبلغ قابل پرداخت {payable} ریال ({payable_words} ریال)، صحت اقلام، مبلغ و شرایط پرداخت مندرج در آن را تایید می‌نمایم و متعهد می‌شوم مطابق شرایط پرداخت ذکرشده نسبت به تسویه اقدام کنم. در صورت عدم پرداخت در مهلت مقرر، فروشنده مجاز به پیگیری قانونی است. این تاییدیه با احراز هویت از طریق کد یکبارمصرف (OTP) ارسال‌شده به شماره موبایل اینجانب امضا و ثبت شده و به منزله امضای دیجیتال معتبر و قابل استناد است.";
        $stmt = $pdo->prepare("INSERT INTO system_settings(setting_key,setting_value,is_encrypted,updated_by)VALUES('quote.approval_text',?,0,NULL) ON DUPLICATE KEY UPDATE setting_value=setting_value");
        $stmt->execute([$default]);
        $stmt = $pdo->prepare("INSERT INTO system_settings(setting_key,setting_value,is_encrypted,updated_by)VALUES('app.schema_version',?,0,NULL) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
        $stmt->execute(['1.4.6']);
    },
];
