<?php
App::uses('ConnectionManager', 'Model');
require_once APP . '/Vendor/forward-sql/ForwardSQL.php';
class ForwardShell extends AppShell {
    public function migrate() {
        $pdo = ConnectionManager::getDataSource('default')->getConnection();
        $forward = new ForwardSQL($pdo, dirname(APP) . '/sql');
        $forward->setOutputMethod(function($message) {
            $this->out($message);
        });
        $forward->migrate();
    }
}
