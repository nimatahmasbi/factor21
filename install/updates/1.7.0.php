<?php
return [
    'description' => 'افزودن سیستم تیکت و مدیریت پرسنل پشتیبانی',
    'up' => function (PDO $pdo, Migrator $m): void {
        // 1) Tickets table
        if (!$m->tableExists('tickets')) {
            $pdo->exec("CREATE TABLE tickets (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                company_id BIGINT UNSIGNED NULL,
                subject VARCHAR(255) NOT NULL,
                status ENUM('open','in_progress','waiting_customer','closed') NOT NULL DEFAULT 'open',
                priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
                assigned_to BIGINT UNSIGNED NULL,
                last_reply_at TIMESTAMP NULL DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY(company_id) REFERENCES companies(id) ON DELETE SET NULL,
                FOREIGN KEY(assigned_to) REFERENCES users(id) ON DELETE SET NULL,
                INDEX(user_id,status),
                INDEX(assigned_to,status),
                INDEX(last_reply_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }

        // 2) Ticket messages table
        if (!$m->tableExists('ticket_messages')) {
            $pdo->exec("CREATE TABLE ticket_messages (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                ticket_id BIGINT UNSIGNED NOT NULL,
                sender_id BIGINT UNSIGNED NOT NULL,
                sender_role ENUM('user','support','admin') NOT NULL,
                body TEXT NOT NULL,
                is_internal TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
                FOREIGN KEY(sender_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX(ticket_id,created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }

        // 3) Support staff table (users with support role)
        if (!$m->tableExists('support_staff')) {
            $pdo->exec("CREATE TABLE support_staff (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL UNIQUE,
                department VARCHAR(100) NULL,
                max_tickets INT UNSIGNED NOT NULL DEFAULT 20,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }

        // 4) Add support role to users if not exists
        if ($m->tableExists('users')) {
            $cols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN,0);
            // Add role enum support if needed (already has 'admin' and 'user')
        }

        $stmt = $pdo->prepare("INSERT INTO system_settings(setting_key,setting_value,is_encrypted,updated_by) VALUES('app.schema_version',?,0,NULL) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
        $stmt->execute(['1.7.0']);
    },
];