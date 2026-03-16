<?php
/**
 * routepermissions / IssabelPBX 2.12.0.3 Debian 12
 * v1.4.8 - page.routepermissions.php
 */
routepermissions_ensure_schema();
if ((isset($_REQUEST['rpajax']) && $_REQUEST['rpajax'] === 'save_embedded')) {
    $res = routepermissions_embedded_save_from_request();
    @header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($res);
    exit;
}
$rp_result         = routepermissions_handle_admin_post();
$rp_routes         = routepermissions_get_routes_with_ids();
$rp_global         = routepermissions_get_global_defaults();
$rp_rules          = routepermissions_get_all_rules(1000);
$rp_extensions     = routepermissions_get_extensions();
$rp_destinations   = function_exists('drawselects');

include dirname(__FILE__) . '/views/routepermissions.php';
