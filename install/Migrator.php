<?php
declare(strict_types=1);

final class Migrator {
    public function __construct(private PDO $pdo, private string $updatesDir) {}

    public function ensureRepository(): void {
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
            version VARCHAR(30) PRIMARY KEY,
            description VARCHAR(255) NOT NULL,
            checksum CHAR(64) NOT NULL,
            execution_ms INT UNSIGNED NOT NULL DEFAULT 0,
            applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function installedVersion(): string {
        if(!$this->tableExists('schema_migrations'))return 'legacy';
        $versions=$this->pdo->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
        if(!$versions)return '0.0.0';usort($versions,'version_compare');return (string)end($versions);
    }

    public function previewPending(): array {
        if(!$this->tableExists('schema_migrations'))return $this->all();
        return $this->pending();
    }

    public function all(): array {
        $files = glob($this->updatesDir . '/*.php') ?: [];
        $updates = [];
        foreach ($files as $file) {
            $version = basename($file, '.php');
            if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) continue;
            $definition = require $file;
            if (!is_array($definition) || !isset($definition['description'],$definition['up']) || !is_callable($definition['up'])) {
                throw new RuntimeException('ساختار فایل بروزرسانی ' . $version . ' معتبر نیست.');
            }
            $checksumSource=(string)file_get_contents($file);$sql=substr($file,0,-4).'.sql';if(is_file($sql))$checksumSource.=(string)file_get_contents($sql);
            $updates[$version] = ['file'=>$file,'checksum'=>hash('sha256',$checksumSource),'description'=>(string)$definition['description'],'up'=>$definition['up']];
        }
        uksort($updates, 'version_compare');
        return $updates;
    }

    public function pending(): array {
        $this->ensureRepository();
        $applied = [];
        foreach ($this->pdo->query('SELECT version,checksum FROM schema_migrations')->fetchAll(PDO::FETCH_ASSOC) as $row) $applied[$row['version']] = $row['checksum'];
        $pending = [];
        foreach ($this->all() as $version=>$update) {
            if (isset($applied[$version])) {
                if (!hash_equals($applied[$version],$update['checksum'])) throw new RuntimeException('فایل بروزرسانی اجراشده ' . $version . ' پس از اجرا تغییر کرده است.');
                continue;
            }
            $pending[$version] = $update;
        }
        return $pending;
    }

    public function migrate(?callable $progress = null): array {
        $this->ensureRepository();
        $locked = (int)$this->pdo->query("SELECT GET_LOCK('pishfactor_schema_migration',10)")->fetchColumn() === 1;
        if (!$locked) throw new RuntimeException('یک بروزرسانی دیگر در حال اجرا است. کمی بعد دوباره تلاش کنید.');
        $done = [];
        try {
            foreach ($this->pending() as $version=>$update) {
                $started = microtime(true);
                if ($progress) $progress($version,$update['description'],'running');
                ($update['up'])($this->pdo,$this);
                $elapsed = (int)round((microtime(true)-$started)*1000);
                $stmt=$this->pdo->prepare('INSERT INTO schema_migrations(version,description,checksum,execution_ms)VALUES(?,?,?,?)');
                $stmt->execute([$version,$update['description'],$update['checksum'],$elapsed]);
                $done[]=$version;
                if ($progress) $progress($version,$update['description'],'done');
            }
        } finally {
            $this->pdo->query("SELECT RELEASE_LOCK('pishfactor_schema_migration')");
        }
        return $done;
    }

    public function tableExists(string $table): bool {
        $stmt=$this->pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$stmt->execute([$table]);return (int)$stmt->fetchColumn()>0;
    }

    public function columnExists(string $table,string $column): bool {
        $stmt=$this->pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');$stmt->execute([$table,$column]);return (int)$stmt->fetchColumn()>0;
    }

    public function indexExists(string $table,string $index): bool {
        $stmt=$this->pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?');$stmt->execute([$table,$index]);return (int)$stmt->fetchColumn()>0;
    }

    public function executeSqlFile(string $file): void {
        $sql=file_get_contents($file);if($sql===false)throw new RuntimeException('فایل SQL خوانده نشد: '.basename($file));
        $parts=preg_split('/;\s*(?:\r?\n|$)/',$sql,-1,PREG_SPLIT_NO_EMPTY);
        foreach($parts as $part){$part=trim($part);if($part!=='')$this->pdo->exec($part);}
    }
}
