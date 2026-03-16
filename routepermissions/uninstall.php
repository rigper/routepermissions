<?php
/**
 * routepermissions / IssabelPBX 5
 * v1.0.1 - uninstall.php
 */
global $db;
if (function_exists('sql')) {
    sql("DROP TABLE IF EXISTS routepermissions");
} elseif (isset($db)) {
    $db->query("DROP TABLE IF EXISTS routepermissions");
}
