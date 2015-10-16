<?php

class ForwardSQL {
    protected $pdo;
    protected $sql_dir;
    protected $output_method;

    public function __construct(PDO $pdo, $sql_dir) {
        $this->pdo = $pdo;
        $this->sql_dir = $sql_dir;
    }

    public function setOutputMethod(callable $method) {
        $this->output_method = $method;
    }

    public function out($message) {
        if($this->output_method)
            call_user_func($this->output_method, $message);
    }

    public function migrate() {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS schema_version (version INT NOT NULL DEFAULT 0) ENGINE=InnoDB");

        $stmt = $this->pdo->query("SELECT version FROM schema_version ORDER BY version DESC LIMIT 1");
        $version = (int) $stmt->fetchColumn();

        foreach($this->getMigrations($version) as $migration) {
            $this->out($migration['filename']);

            foreach($this->getStatements($migration['sql']) as $sql)
                $this->pdo->exec($sql);

            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare("UPDATE schema_version SET version = ?");
            $stmt->execute(array($migration['version']));

            if($stmt->rowCount() == 0) {
                $stmt = $this->pdo->prepare("INSERT INTO schema_version SET version = ?");
                $stmt->execute(array($migration['version']));
            }

            $this->pdo->commit();
        }
    }

    protected function getStatements($sql) {
        $sql = preg_replace('|^\s*-- .*$|m', '', $sql);
        $stmts = preg_split('|;\s*$|m', $sql);
        $stmts = array_map('trim', $stmts);
        $stmts = array_filter($stmts, 'strlen');
        return $stmts;
    }

    protected function getMigrations($from_version) {
        if(!is_dir($this->sql_dir))
            throw new RuntimeException("SQL dir does not exist or not a directory: {$this->sql_dir}");

        $files = scandir($this->sql_dir);

        if(!$files)
            fail("Can't read directory: {$this->sql_dir}");

        $migrations = array();

        foreach($files as $file) {
            if(!preg_match('/^([0-9]{4})_.+\.sql$/', $file, $match))
                continue;
            $version = (int) $match[1];
            if($version <= $from_version)
                continue;
            if(isset($migrations[$version]))
                throw new RuntimeException("Duplicate migration version: {$version}");
            $sql = file_get_contents($this->sql_dir . '/' . $file);
            if($sql === false)
                throw new RuntimeException("Could not read migration file: {$file}");
            $migrations[$version] = array(
                'version'  => $version,
                'filename' => $file,
                'sql'      => $sql,
            );
        }

        return $migrations;
    }
}
