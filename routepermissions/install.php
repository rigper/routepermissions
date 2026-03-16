<?php
/**
 * routepermissions / IssabelPBX 5
 * v1.0.1 - install.php
 */
global $db;

function routepermissions_exec_sql_file($file) {
    if (!file_exists($file)) {
        return;
    }
    $sql = file_get_contents($file);
    if ($sql === false) {
        return;
    }
    $parts = preg_split('/;\s*\n/', $sql);
    foreach ($parts as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '') {
            continue;
        }
        sql($stmt);
    }
}

function routepermissions_copy_agi() {
    $src = dirname(__FILE__) . '/agi-bin/checkperms.agi';
    $targets = array(
        '/var/lib/asterisk/agi-bin/checkperms.agi',
        '/usr/share/asterisk/agi-bin/checkperms.agi',
    );
    if (!file_exists($src)) {
        return;
    }
    foreach ($targets as $dst) {
        $dir = dirname($dst);
        if (is_dir($dir) && is_writable($dir)) {
            @copy($src, $dst);
            @chmod($dst, 0755);
        }
    }
}

routepermissions_exec_sql_file(dirname(__FILE__) . '/install.sql');
routepermissions_copy_agi();
