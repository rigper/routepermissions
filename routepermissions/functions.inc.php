<?php
/**
 * routepermissions / IssabelPBX 2.12.0.3 Debian 12
 * v1.4.9-fixed-ui-load - functions.inc.php
 */


function routepermissions_debug_log($msg) {
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . "
";
    $targets = array('/tmp/routepermissions_debug.log', dirname(__FILE__) . '/routepermissions_debug.log');
    foreach ($targets as $file) {
        @file_put_contents($file, $line, FILE_APPEND);
    }
}

function routepermissions_debug_dump($label, $data = null) {
    if ($data === null) {
        routepermissions_debug_log($label);
        return;
    }
    if (is_array($data) || is_object($data)) {
        routepermissions_debug_log($label . ': ' . print_r($data, true));
        return;
    }
    routepermissions_debug_log($label . ': ' . (string)$data);
}



function routepermissions_embedded_save_from_request() {
    $extdisplay = routepermissions_normalize_exten(routepermissions_detect_extdisplay());
    $routes = routepermissions_get_routes_with_ids();
    $saved = 0;
    $deleted = 0;
    $details = array();

    routepermissions_debug_dump('embedded_save request', array(
        'method' => isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '',
        'extdisplay' => $extdisplay,
        'post_keys' => array_keys($_POST),
        'request_keys' => array_keys($_REQUEST),
    ));

    if ($extdisplay === '') {
        return array('ok'=>false,'message'=>'No se detectó la extensión a guardar.','saved'=>0,'deleted'=>0,'details'=>$details);
    }
    if (empty($routes)) {
        return array('ok'=>false,'message'=>'No se encontraron rutas salientes.','saved'=>0,'deleted'=>0,'details'=>$details);
    }

    foreach ($routes as $routeId => $route) {
        $hasAnyField = isset($_POST["rp_policy_{$routeId}"]) || isset($_REQUEST["rp_policy_{$routeId}"]) ||
                       isset($_POST["rp_prefix_{$routeId}"]) || isset($_REQUEST["rp_prefix_{$routeId}"]) ||
                       isset($_POST["rp_faildest_{$routeId}"]) || isset($_REQUEST["rp_faildest_{$routeId}"]);
        if (!$hasAnyField) {
            $details[] = array('route_id'=>$routeId,'action'=>'preserve-missing');
            continue;
        }

        $policy = routepermissions_request_value("rp_policy_{$routeId}", 'INHERIT');
        $prefix = routepermissions_request_value("rp_prefix_{$routeId}", '');
        $fail   = routepermissions_request_value("rp_faildest_{$routeId}", '');
        if (is_array($policy)) { $policy = reset($policy); }
        if (is_array($prefix)) { $prefix = reset($prefix); }
        if (is_array($fail)) { $fail = reset($fail); }
        $policy = routepermissions_normalize_policy($policy);
        $prefix = preg_replace('/[^0-9\*#A-Za-z]/', '', trim((string)$prefix));
        $fail   = trim((string)$fail);

        if ($policy === 'INHERIT' && $prefix === '' && $fail === '') {
            routepermissions_delete_rule($extdisplay, $routeId);
            $deleted++;
            $details[] = array('route_id'=>$routeId,'action'=>'delete');
            continue;
        }

        routepermissions_upsert_rule($extdisplay, $routeId, $route['name'], $policy, $prefix, $fail, '');
        $saved++;
        $details[] = array('route_id'=>$routeId,'action'=>'upsert','policy'=>$policy,'prefix'=>$prefix,'faildest'=>$fail);
    }

    return array('ok'=>true,'message'=>'Reglas procesadas correctamente.','saved'=>$saved,'deleted'=>$deleted,'details'=>$details,'exten'=>$extdisplay);
}

function routepermissions_users_configprocess() {
    routepermissions_debug_log('users_configprocess hook');
    routepermissions_configprocess();
}

function routepermissions_extensions_configprocess() {
    routepermissions_debug_log('extensions_configprocess hook');
    routepermissions_configprocess();
}

function routepermissions_detect_extdisplay() {
    $candidates = array('rp_target_ext', 'extdisplay', 'extension', 'exten', 'account', 'accountcode', 'user', 'id');
    foreach ($candidates as $key) {
        if (isset($_REQUEST[$key]) && $_REQUEST[$key] !== '') {
            $v = routepermissions_normalize_exten($_REQUEST[$key]);
            if ($v !== '') { return $v; }
        }
        if (isset($_POST[$key]) && $_POST[$key] !== '') {
            $v = routepermissions_normalize_exten($_POST[$key]);
            if ($v !== '') { return $v; }
        }
    }
    return '';
}

function routepermissions_array_has_route_fields($bag) {
    if (!is_array($bag)) { return false; }
    foreach ($bag as $key => $value) {
        if (preg_match('/^(rp_policy_|rp_prefix_|rp_faildest_|rp_dirty_|rp_orig_policy_|rp_orig_prefix_|rp_orig_faildest_)\d+$/', (string)$key) || (string)$key === 'rp_target_ext') {
            return true;
        }
        if (is_array($value) && routepermissions_array_has_route_fields($value)) {
            return true;
        }
    }
    return false;
}

function routepermissions_db_escape($val) {
    global $db;
    if (is_object($db) && method_exists($db, 'escapeSimple')) {
        return $db->escapeSimple($val);
    }
    return addslashes($val);
}

function routepermissions_sql($sql, $mode = 'query') {
    global $db;

    // Para lectura de filas/colecciones usamos SIEMPRE $db con FETCHMODE_ASSOC.
    // El wrapper legacy sql() en Issabel puede devolver estructuras no confiables
    // para getRow/getAll en este módulo.
    if (isset($db) && is_object($db)) {
        switch ($mode) {
            case 'getAll':
                return $db->getAll($sql, DB_FETCHMODE_ASSOC);
            case 'getRow':
                return $db->getRow($sql, DB_FETCHMODE_ASSOC);
            case 'getOne':
                return $db->getOne($sql);
            default:
                return $db->query($sql);
        }
    }

    if (function_exists('sql')) {
        return sql($sql, $mode);
    }

    return false;
}

function routepermissions_assoc_row($row) {
    if (!is_array($row) || empty($row)) {
        return array();
    }

    // Si ya viene asociativo, lo dejamos.
    if (isset($row['exten']) || isset($row['allowed']) || isset($row['route_id'])) {
        return $row;
    }

    // Fallback por índice numérico para routepermissions:
    // id, exten, route_id, routename, allowed, prefix, faildest, notes, updated_at
    $mapped = array(
        'id'         => isset($row[0]) ? $row[0] : null,
        'exten'      => isset($row[1]) ? $row[1] : '',
        'route_id'   => isset($row[2]) ? $row[2] : 0,
        'routename'  => isset($row[3]) ? $row[3] : '',
        'allowed'    => isset($row[4]) ? $row[4] : 'INHERIT',
        'prefix'     => isset($row[5]) ? $row[5] : '',
        'faildest'   => isset($row[6]) ? $row[6] : '',
        'notes'      => isset($row[7]) ? $row[7] : '',
        'updated_at' => isset($row[8]) ? $row[8] : '',
    );

    return $mapped;
}


function routepermissions_table_columns($table) {
    $table = preg_replace('/[^A-Za-z0-9_]/', '', (string)$table);
    if ($table === '') { return array(); }
    $rows = routepermissions_sql("SHOW COLUMNS FROM `{$table}`", 'getAll');
    $cols = array();
    if (is_array($rows)) {
        foreach ($rows as $row) {
            if (isset($row['Field'])) {
                $cols[strtolower($row['Field'])] = true;
            }
        }
    }
    return $cols;
}

function routepermissions_ensure_schema() {
    $exists = routepermissions_sql("SHOW TABLES LIKE 'routepermissions'", 'getOne');
    if (!$exists) {
        $file = dirname(__FILE__) . '/install.sql';
        if (file_exists($file)) {
            $sql = file_get_contents($file);
            $parts = preg_split('/;\s*\n/', $sql);
            foreach ($parts as $stmt) {
                $stmt = trim($stmt);
                if ($stmt !== '') {
                    routepermissions_sql($stmt);
                }
            }
        }
    }
    $meta = routepermissions_sql("SELECT id FROM routepermissions WHERE exten='-1' AND route_id=-1 LIMIT 1", 'getOne');
    if (!$meta) {
        routepermissions_sql("INSERT INTO routepermissions (exten, route_id, routename, allowed, prefix, faildest, notes) VALUES ('-1', -1, '__GLOBAL__', 'INHERIT', '', '', 'Meta global defaults')");
    }
}

function routepermissions_normalize_policy($value) {
    $value = trim((string)$value);
    if ($value === '') {
        return 'INHERIT';
    }
    // En algunos flujos legacy de Issabel el radio llega como "rp_policy_2=YES"
    // en lugar de solo "YES". Nos quedamos con el valor a la derecha del último '='.
    if (strpos($value, '=') !== false) {
        $parts = explode('=', $value);
        $value = trim((string)end($parts));
    }
    $value = strtoupper($value);
    if ($value === 'ALLOW') {
        $value = 'YES';
    } elseif ($value === 'DENY') {
        $value = 'NO';
    }
    if (!in_array($value, array('YES', 'NO', 'INHERIT'), true)) {
        $value = 'INHERIT';
    }
    return $value;
}

function routepermissions_normalize_exten($exten) {
    $exten = trim((string)$exten);
    return preg_replace('/[^0-9A-Za-z_\-\*#]/', '', $exten);
}

function routepermissions_request_value($key, $default = '') {
    if (isset($_POST[$key])) { return $_POST[$key]; }
    if (isset($_REQUEST[$key])) { return $_REQUEST[$key]; }
    return $default;
}

function routepermissions_request_has_route_fields() {
    return routepermissions_array_has_route_fields($_POST) || routepermissions_array_has_route_fields($_REQUEST);
}

function routepermissions_is_truthy($value) {
    if (is_array($value)) { $value = reset($value); }
    $value = strtolower(trim((string)$value));
    return in_array($value, array('1','true','yes','on','dirty'), true);
}

function routepermissions_route_dirty($routeId) {
    return routepermissions_is_truthy(routepermissions_request_value('rp_dirty_' . (int)$routeId, '0'));
}


function routepermissions_get_global_defaults() {
    routepermissions_ensure_schema();
    $row = routepermissions_sql("SELECT * FROM routepermissions WHERE exten='-1' AND route_id=-1 LIMIT 1", 'getRow');
    $row = routepermissions_assoc_row($row);

    if (empty($row)) {
        $row = array(
            'exten'     => '-1',
            'route_id'  => -1,
            'routename' => '__GLOBAL__',
            'allowed'   => 'INHERIT',
            'prefix'    => '',
            'faildest'  => '',
            'notes'     => ''
        );
    }

    return $row;
}

function routepermissions_set_global_defaults($faildest, $notes = '') {
    routepermissions_ensure_schema();
    $faildest = routepermissions_db_escape(trim((string)$faildest));
    $notes    = routepermissions_db_escape(trim((string)$notes));
    $sql = "UPDATE routepermissions SET faildest='{$faildest}', notes='{$notes}', allowed='INHERIT', prefix='' WHERE exten='-1' AND route_id=-1";
    routepermissions_sql($sql);
}

function routepermissions_get_routes_with_ids() {
    $routes = array();
    if (function_exists('core_routing_list')) {
        $list = core_routing_list();
        if (is_array($list)) {
            foreach ($list as $row) {
                $rid  = isset($row['route_id']) ? (int)$row['route_id'] : 0;
                $name = isset($row['name']) ? $row['name'] : '';
                if ($rid > 0) {
                    $routes[$rid] = array('route_id'=>$rid,'name'=>$name);
                }
            }
        }
    }
    if (empty($routes)) {
        $sql = "SELECT a.route_id, a.name FROM outbound_routes a LEFT JOIN outbound_route_sequence b ON a.route_id = b.route_id GROUP BY a.route_id, a.name ORDER BY MIN(COALESCE(b.seq, 999999)), a.name";
        $rows = routepermissions_sql($sql, 'getAll');
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $rid = isset($row['route_id']) ? (int)$row['route_id'] : 0;
                if ($rid > 0) {
                    $routes[$rid] = array('route_id'=>$rid,'name'=>isset($row['name']) ? $row['name'] : ('Route ' . $rid));
                }
            }
        }
    }
    uasort($routes, function ($a, $b) { return strcasecmp($a['name'], $b['name']); });
    return $routes;
}

function routepermissions_get_route_patterns_by_id($routeId) {
    $routeId = (int)$routeId;
    $patterns = array();
    if (function_exists('core_routing_getroutepatternsbyid')) {
        $patterns = core_routing_getroutepatternsbyid($routeId);
        if (is_array($patterns) && !empty($patterns)) {
            return $patterns;
        }
    }
    $sql = "SELECT match_pattern_prefix, match_pattern_pass FROM outbound_route_patterns WHERE route_id = " . $routeId . " ORDER BY seq, match_pattern_prefix, match_pattern_pass";
    $rows = routepermissions_sql($sql, 'getAll');
    return is_array($rows) ? $rows : array();
}

function routepermissions_get_extensions() {
    $exts = array();

    $usersCols = routepermissions_table_columns('users');
    if (isset($usersCols['extension'])) {
        $nameCol = isset($usersCols['name']) ? 'name' : (isset($usersCols['description']) ? 'description' : "''");
        $sql = ($nameCol === "''")
            ? "SELECT extension, '' AS name FROM users ORDER BY extension"
            : "SELECT extension, {$nameCol} AS name FROM users ORDER BY extension";
        $rows = routepermissions_sql($sql, 'getAll');
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $ext = isset($row['extension']) ? trim($row['extension']) : '';
                if ($ext === '' || isset($exts[$ext])) { continue; }
                $exts[$ext] = array('extension'=>$ext,'name'=>isset($row['name']) ? trim($row['name']) : '');
            }
        }
    }

    $devicesCols = routepermissions_table_columns('devices');
    if (!empty($devicesCols)) {
        $extCol  = isset($devicesCols['extension']) ? 'extension' : (isset($devicesCols['id']) ? 'id' : '');
        $nameCol = isset($devicesCols['name']) ? 'name' : (isset($devicesCols['description']) ? 'description' : '');
        if ($extCol !== '') {
            $sql = ($nameCol === '')
                ? "SELECT {$extCol} AS extension, '' AS name FROM devices ORDER BY {$extCol}"
                : "SELECT {$extCol} AS extension, {$nameCol} AS name FROM devices ORDER BY {$extCol}";
            $rows = routepermissions_sql($sql, 'getAll');
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    $ext = isset($row['extension']) ? trim($row['extension']) : '';
                    if ($ext === '' || isset($exts[$ext])) { continue; }
                    $exts[$ext] = array('extension'=>$ext,'name'=>isset($row['name']) ? trim($row['name']) : '');
                }
            }
        }
    }

    ksort($exts, SORT_NATURAL);
    return $exts;
}

function routepermissions_get_rule($exten, $routeId) {
    routepermissions_ensure_schema();
    $exten   = routepermissions_db_escape(routepermissions_normalize_exten($exten));
    $routeId = (int)$routeId;

    $row = routepermissions_sql(
        "SELECT * FROM routepermissions WHERE exten='{$exten}' AND route_id={$routeId} LIMIT 1",
        'getRow'
    );

    $row = routepermissions_assoc_row($row);

    routepermissions_debug_dump('get_rule result', array(
        'exten'   => $exten,
        'routeId' => $routeId,
        'row'     => $row,
    ));

    return $row;
}

function routepermissions_get_effective_rule($exten, $routeId) {
    $routeId = (int)$routeId;

    $explicit = routepermissions_get_rule($exten, $routeId);
    if (is_array($explicit) && !empty($explicit)) {
        $explicit['_source'] = 'explicit';
        return $explicit;
    }

    $default = routepermissions_get_rule('-1', $routeId);
    if (is_array($default) && !empty($default)) {
        $default['_source'] = 'route-default';
        return $default;
    }

    $global = routepermissions_get_global_defaults();
    $global['_source'] = 'global-default';
    return $global;
}

function routepermissions_upsert_rule($exten, $routeId, $routeName, $allowed, $prefix = '', $faildest = '', $notes = '') {
    routepermissions_ensure_schema();
    $exten    = routepermissions_normalize_exten($exten);
    $routeId  = (int)$routeId;
    $routeName= trim((string)$routeName);
    $allowed  = routepermissions_normalize_policy($allowed);
    $prefix   = trim((string)$prefix);
    $faildest = trim((string)$faildest);
    $notes    = trim((string)$notes);
    $sexten   = routepermissions_db_escape($exten);
    $srname   = routepermissions_db_escape($routeName);
    $sallowed = routepermissions_db_escape($allowed);
    $sprefix  = routepermissions_db_escape($prefix);
    $sfail    = routepermissions_db_escape($faildest);
    $snotes   = routepermissions_db_escape($notes);
    $exists = routepermissions_sql("SELECT id FROM routepermissions WHERE exten='{$sexten}' AND route_id={$routeId} LIMIT 1", 'getOne');
    if ($allowed === 'INHERIT' && $exten !== '-1' && $prefix === '' && $faildest === '' && $notes === '') {
        routepermissions_delete_rule($exten, $routeId);
        return;
    }
    if ($exists) {
        $sql = "UPDATE routepermissions SET routename='{$srname}', allowed='{$sallowed}', prefix='{$sprefix}', faildest='{$sfail}', notes='{$snotes}' WHERE exten='{$sexten}' AND route_id={$routeId}";
    } else {
        $sql = "INSERT INTO routepermissions (exten, route_id, routename, allowed, prefix, faildest, notes) VALUES ('{$sexten}', {$routeId}, '{$srname}', '{$sallowed}', '{$sprefix}', '{$sfail}', '{$snotes}')";
    }
    routepermissions_debug_dump('upsert sql', $sql);
    routepermissions_sql($sql);
}

function routepermissions_delete_rule($exten, $routeId) {
    routepermissions_ensure_schema();
    $exten   = routepermissions_db_escape(routepermissions_normalize_exten($exten));
    $routeId = (int)$routeId;
    $sql = "DELETE FROM routepermissions WHERE exten='{$exten}' AND route_id={$routeId}";
    routepermissions_debug_dump('delete sql', $sql);
    routepermissions_sql($sql);
}

function routepermissions_parse_range($range) {
    $range = trim((string)$range);
    if ($range === '') { return array(); }
    $result = array();
    $chunks = preg_split('/\s*,\s*/', $range);
    foreach ($chunks as $chunk) {
        $chunk = trim($chunk);
        if ($chunk === '') { continue; }
        if (preg_match('/^(\d+)\s*-\s*(\d+)$/', $chunk, $m)) {
            $start = (int)$m[1]; $end = (int)$m[2];
            if ($end < $start) { $tmp = $start; $start = $end; $end = $tmp; }
            for ($i = $start; $i <= $end; $i++) { $result[(string)$i] = (string)$i; }
            continue;
        }
        if (preg_match('/^\d+$/', $chunk)) { $result[$chunk] = $chunk; }
    }
    ksort($result, SORT_NATURAL);
    return array_values($result);
}

function routepermissions_bulk_apply($routeId, $action, $range, $prefix = '', $faildest = '', $notes = '') {
    $routeId = (int)$routeId;
    $routes  = routepermissions_get_routes_with_ids();
    if (!isset($routes[$routeId])) { return array('ok'=>false,'message'=>'Ruta no válida.'); }
    $extensions = routepermissions_parse_range($range);
    if (empty($extensions)) { return array('ok'=>false,'message'=>'No se encontraron extensiones válidas en el rango.'); }
    $count = 0;
    foreach ($extensions as $exten) {
        if ($action === 'delete' || strtolower($action) === 'inherit') {
            routepermissions_delete_rule($exten, $routeId);
        } else {
            routepermissions_upsert_rule($exten, $routeId, $routes[$routeId]['name'], $action, $prefix, $faildest, $notes);
        }
        $count++;
    }
    return array('ok'=>true,'message'=>"Se aplicó la operación a {$count} extensión(es).",'count'=>$count);
}

function routepermissions_get_all_rules($limit = 500) {
    routepermissions_ensure_schema();
    $limit = (int)$limit; if ($limit < 1) { $limit = 500; }
    $sql = "SELECT * FROM routepermissions WHERE NOT (exten='-1' AND route_id=-1) ORDER BY CASE WHEN exten='-1' THEN 0 ELSE 1 END, route_id, exten LIMIT {$limit}";
    $rows = routepermissions_sql($sql, 'getAll');
    return is_array($rows) ? $rows : array();
}

function routepermissions_get_destinations_select_html($selected, $name, $id = '') {
    if (function_exists('drawselects')) { return drawselects($selected, $name, false, $id); }
    $selected = htmlspecialchars((string)$selected, ENT_QUOTES, 'UTF-8');
    $name = htmlspecialchars((string)$name, ENT_QUOTES, 'UTF-8');
    $id   = htmlspecialchars((string)$id, ENT_QUOTES, 'UTF-8');
    return '<input type="text" class="form-control" name="'.$name.'" id="'.$id.'" value="'.$selected.'" placeholder="ej: ext-local,100,1 o from-did-direct,200,1" />';
}

function routepermissions_configpageinit($pagename) {
    global $currentcomponent;
    routepermissions_debug_dump('configpageinit pagename', $pagename);
    routepermissions_debug_dump('configpageinit request meta', array('method'=>(isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : ''), 'display'=>(isset($_REQUEST['display']) ? $_REQUEST['display'] : ''), 'action'=>(isset($_REQUEST['action']) ? $_REQUEST['action'] : '')));
    if ($pagename !== 'users' && $pagename !== 'extensions') { return true; }

    // Fallback defensivo: en algunas variantes legacy de Issabel el callback
    // registrado con addprocessfunc no siempre persiste nuestros campos
    // personalizados. Si detectamos POST del módulo, guardamos aquí mismo
    // antes de que continúe el flujo normal del formulario.
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        routepermissions_debug_dump('configpageinit POST keys', array_keys($_POST));
        routepermissions_debug_dump('configpageinit REQUEST action', isset($_REQUEST['action']) ? $_REQUEST['action'] : '');
        if (isset($_POST['rp_target_ext']) || routepermissions_request_has_route_fields()) {
            routepermissions_debug_dump('configpageinit detected module fields', true);
            routepermissions_configprocess();
        } else {
            routepermissions_debug_dump('configpageinit detected module fields', false);
        }
    }

    routepermissions_applyhooks();
    if (is_object($currentcomponent) && method_exists($currentcomponent, 'addprocessfunc')) {
        $currentcomponent->addprocessfunc('routepermissions_configprocess', 5);
        $currentcomponent->addprocessfunc('routepermissions_users_configprocess', 6);
        $currentcomponent->addprocessfunc('routepermissions_extensions_configprocess', 7);
    }
    return true;
}

function routepermissions_applyhooks() {
    global $currentcomponent;
    $currentcomponent->addoptlistitem('routepermissions_policy', 'INHERIT', _('Heredar default'));
    $currentcomponent->addoptlistitem('routepermissions_policy', 'YES', _('Permitir'));
    $currentcomponent->addoptlistitem('routepermissions_policy', 'NO', _('Denegar'));
    $currentcomponent->setoptlistopts('routepermissions_policy', 'sort', false);
    $currentcomponent->addguifunc('routepermissions_configpageload');
}


function routepermissions_emit_embedded_submit_js() {
    static $done = false;
    if ($done) { return; }
    $done = true;
    $url = "config.php?display=routepermissions&rpajax=save_embedded";
    echo "<script>(function(){
";
    echo "  function rpFindForm(){
";
    echo "    var forms=document.getElementsByTagName('form');
";
    echo "    for(var i=0;i<forms.length;i++){
";
    echo "      if(forms[i].querySelector('[name=\"rp_target_ext\"]')){ return forms[i]; }
";
    echo "    }
";
    echo "    return forms.length ? forms[0] : null;
";
    echo "  }
";
    echo "  function rpSyncPolicies(form){
";
    echo "    if(!form){return;}
";
    echo "    var hidden=form.querySelectorAll('[name^=\"rp_saved_policy_\"]');
";
    echo "    for(var i=0;i<hidden.length;i++){
";
    echo "      var h=hidden[i]; var routeId=h.name.replace('rp_saved_policy_',''); var value=(h.value||'INHERIT').toUpperCase();
";
    echo "      var radios=form.querySelectorAll('input[type=radio][name=\"rp_policy_'+routeId+'\"]');
";
    echo "      for(var j=0;j<radios.length;j++){ radios[j].checked=((radios[j].value||'').toUpperCase()===value); }
";
    echo "    }
";
    echo "  }
";
    echo "  function rpMarkDirty(form){
";
    echo "    if(!form){return;}
";
    echo "    form.addEventListener('change', function(ev){
";
    echo "      var t=ev.target; if(!t || !t.name){return;}
";
    echo "      var m=t.name.match(/^rp_policy_(\\d+)$/);
";
    echo "      if(m){ var d=form.querySelector('[name=\"rp_dirty_'+m[1]+'\"]'); if(d){ d.value='1'; } }
";
    echo "    }, true);
";
    echo "  }
";
    echo "  function rpCollectPairs(form){
";
    echo "    var out=[];
";
    echo "    var els=form.querySelectorAll('[name^=\"rp_policy_\"],[name^=\"rp_prefix_\"],[name^=\"rp_faildest_\"],[name=\"rp_target_ext\"]');
";
    echo "    for(var i=0;i<els.length;i++){
";
    echo "      var el=els[i]; if(!el.name){continue;}
";
    echo "      var type=(el.type||'').toLowerCase();
";
    echo "      if((type==='radio' || type==='checkbox') && !el.checked){continue;}
";
    echo "      out.push(encodeURIComponent(el.name)+'='+encodeURIComponent(el.value||''));
";
    echo "    }
";
    echo "    return out.join('&');
";
    echo "  }
";
    echo "  function rpAttach(){
";
    echo "    var form=rpFindForm(); if(!form){ return; }
";
    echo "    rpSyncPolicies(form);
";
    echo "    if(form.getAttribute('data-rp-bound')==='1'){ return; }
";
    echo "    form.setAttribute('data-rp-bound','1');
";
    echo "    rpMarkDirty(form);
";
    echo "    form.addEventListener('submit', function(){
";
    echo "      try {
";
    echo "        var payload=rpCollectPairs(form);
";
    echo "        if(!payload){return true;}
";
    echo "        var xhr=new XMLHttpRequest();
";
    echo "        xhr.open('POST', '" + addslashes($url) + "', false);
";
    echo "        xhr.setRequestHeader('Content-Type','application/x-www-form-urlencoded; charset=UTF-8');
";
    echo "        xhr.send(payload);
";
    echo "      } catch (e) { }
";
    echo "      return true;
";
    echo "    }, true);
";
    echo "  }
";
    echo "  if(document.readyState==='loading'){ document.addEventListener('DOMContentLoaded', rpAttach); } else { rpAttach(); }
";
    echo "  window.setTimeout(rpAttach, 300);
";
    echo "  window.setTimeout(rpAttach, 900);
";
    echo "})();</script>";
}

function routepermissions_policy_widget_html($fieldName, $currentValue) {
    $fieldName = htmlspecialchars((string)$fieldName, ENT_QUOTES, 'UTF-8');
    $currentValue = routepermissions_normalize_policy($currentValue);
    $options = array(
        'INHERIT' => 'Heredar default',
        'YES'     => 'Permitir',
        'NO'      => 'Denegar',
    );
    $html  = '<style>';
    $html .= '.routeperm-policy-group{display:inline-flex;flex-wrap:wrap;gap:0;align-items:center}';
    $html .= '.routeperm-policy-group label{margin:0}';
    $html .= '.routeperm-policy-group input[type=radio]{position:absolute;left:-9999px;opacity:0}';
    $html .= '.routeperm-policy-chip{display:inline-block;padding:8px 12px;border:1px solid #cbd5e1;background:#fff;cursor:pointer;font-size:12px;line-height:1.2;color:#334155}';
    $html .= '.routeperm-policy-group label:first-child .routeperm-policy-chip{border-radius:6px 0 0 6px}';
    $html .= '.routeperm-policy-group label:last-child .routeperm-policy-chip{border-radius:0 6px 6px 0}';
    $html .= '.routeperm-policy-group label + label .routeperm-policy-chip{border-left:none}';
    $html .= '.routeperm-policy-group input[type=radio]:checked + .routeperm-policy-chip.inherit{background:#c4b5fd;color:#fff;border-color:#a78bfa}';
    $html .= '.routeperm-policy-group input[type=radio]:checked + .routeperm-policy-chip.yes{background:#16a34a;color:#fff;border-color:#15803d}';
    $html .= '.routeperm-policy-group input[type=radio]:checked + .routeperm-policy-chip.no{background:#dc2626;color:#fff;border-color:#b91c1c}';
    $html .= '</style>';
    $html .= '<div class="routeperm-policy-group">';
    foreach ($options as $value => $label) {
        $checked = ($currentValue === $value) ? ' checked="checked"' : '';
        $cls = strtolower($value);
        if ($cls === 'inherit') {
            $cls = 'inherit';
        }
        $html .= '<label>';
        $html .= '<input type="radio" name="'.$fieldName.'" value="'.$value.'"'.$checked.' />';
        $html .= '<span class="routeperm-policy-chip '.$cls.'">'.htmlspecialchars($label, ENT_QUOTES, 'UTF-8').'</span>';
        $html .= '</label>';
    }
    $html .= '</div>';
    return $html;
}

function routepermissions_configpageload() {
    global $currentcomponent;
    $action     = isset($_REQUEST['action']) ? $_REQUEST['action'] : null;
    $extdisplay = routepermissions_detect_extdisplay();
    routepermissions_debug_dump('configpageload action/extdisplay', array('action'=>$action,'extdisplay'=>$extdisplay, 'method'=>(isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '')));

    // En IssabelPBX 2.12.x hay instalaciones donde configpageinit/addprocessfunc no
    // reciben de forma confiable el submit de users/extensions. Reintentamos la
    // persistencia también desde configpageload antes de dibujar los controles.
    if ((isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '') === 'POST') {
        routepermissions_debug_dump('configpageload POST keys', array_keys($_POST));
        if (isset($_POST['rp_target_ext']) || routepermissions_request_has_route_fields()) {
            routepermissions_debug_log('configpageload detected routepermission POST, calling configprocess');
            routepermissions_configprocess();
        }
    }

    if ($action === 'del' || !$extdisplay) { return; }
    $routes = routepermissions_get_routes_with_ids();
    if (empty($routes)) { return; }
    $section = _('Route Permissions Pro');

    if (class_exists('gui_hidden')) {
        $currentcomponent->addguielem($section, new gui_hidden('rp_target_ext', $extdisplay));
    }
    routepermissions_emit_embedded_submit_js();
    // IssabelPBX 2.12.0.3 en Debian 12 no expone gui_html en este flujo,
    // así que evitamos inyectar JavaScript embebido desde el componente.
    // Dejamos solo controles GUI nativos para no romper la edición de extensiones.
    foreach ($routes as $routeId => $route) {
        $effective = routepermissions_get_effective_rule($extdisplay, $routeId);
        $explicit  = routepermissions_get_rule($extdisplay, $routeId);
        $policy = is_array($explicit) && !empty($explicit) ? routepermissions_normalize_policy($explicit['allowed']) : 'INHERIT';
        $prefix = is_array($explicit) && isset($explicit['prefix']) ? $explicit['prefix'] : '';
        $fail   = is_array($explicit) && isset($explicit['faildest']) ? $explicit['faildest'] : '';
        $policyLabelMap = array('INHERIT' => _('Heredar default'), 'YES' => _('Permitir'), 'NO' => _('Denegar'));
        $policyLabel = isset($policyLabelMap[$policy]) ? $policyLabelMap[$policy] : $policy;
        $effectiveAllowed = isset($effective['allowed']) ? routepermissions_normalize_policy($effective['allowed']) : 'INHERIT';
        $effectiveLabel = isset($policyLabelMap[$effectiveAllowed]) ? $policyLabelMap[$effectiveAllowed] : $effectiveAllowed;
        $sourceLabel = isset($effective['_source']) ? $effective['_source'] : 'n/a';
        $routeLabel = $route['name'] . ' — ' . sprintf(_('Guardado: %s | Efectivo: %s'), $policyLabel, $effectiveLabel);
        $help = sprintf(_('Ruta #%s. Guardado: %s. Efectivo: %s (%s). Solo se actualiza si cambias esta ruta; las demás conservan su valor guardado.'), $routeId, $policyLabel, $effectiveLabel, $sourceLabel);
        $currentcomponent->addguielem($section, new gui_hidden("rp_saved_policy_{$routeId}", $policy));
        $currentcomponent->addguielem($section, new gui_hidden("rp_orig_policy_{$routeId}", $policy));
        $currentcomponent->addguielem($section, new gui_hidden("rp_orig_prefix_{$routeId}", $prefix));
        $currentcomponent->addguielem($section, new gui_hidden("rp_orig_faildest_{$routeId}", $fail));
        $currentcomponent->addguielem($section, new gui_hidden("rp_dirty_{$routeId}", '0'));
        $currentcomponent->addguielem($section, new gui_radio("rp_policy_{$routeId}", $currentcomponent->getoptlist('routepermissions_policy'), $policy, $routeLabel, $help, null));
        $currentcomponent->addguielem($section, new gui_textbox("rp_prefix_{$routeId}", $prefix, $route['name'] . ' ' . _('Prefijo de redirección'), _('Opcional. Si la ruta se deniega y existe un prefijo, se antepone al número marcado y se reinyecta al dialplan.'), '', '', true, 0, null));
        $currentcomponent->addguielem($section, new gui_textbox("rp_faildest_{$routeId}", $fail, $route['name'] . ' ' . _('Destino alterno'), _('Opcional. Destino FreePBX/Issabel para enviar la llamada si la ruta fue denegada.'), '', '', true, 0, null));
    }
}

function routepermissions_configprocess() {
    static $alreadyRan = false;
    routepermissions_debug_dump('configprocess enter alreadyRan', $alreadyRan ? 'yes' : 'no');
    if ($alreadyRan) { return; }

    $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : null;
    $extdisplay = routepermissions_detect_extdisplay();
    routepermissions_debug_dump('configprocess request summary', array('method'=>isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '', 'action'=>$action, 'extdisplay'=>$extdisplay, 'has_route_fields'=>routepermissions_request_has_route_fields(), 'post_keys'=>array_keys($_POST), 'request_keys'=>array_keys($_REQUEST)));
    if (!$extdisplay && !routepermissions_request_has_route_fields()) { return; }
    $extdisplay = routepermissions_normalize_exten($extdisplay);
    if ($extdisplay === '' && routepermissions_request_has_route_fields()) {
        routepermissions_debug_log('No se detectó extensión al guardar. REQUEST=' . print_r($_REQUEST, true) . ' POST=' . print_r($_POST, true));
        return;
    }
    if ($extdisplay === '') { return; }

    $routes = routepermissions_get_routes_with_ids();

    if ($action === 'del') {
        foreach ($routes as $routeId => $route) { routepermissions_delete_rule($extdisplay, $routeId); }
        $alreadyRan = true;
        return;
    }

    // En Issabel/FreePBX legacy la acción de guardado puede variar según la pantalla
    // (users, extensions, add, edit, etc.). Si detectamos campos del módulo, persistimos
    // sin depender estrictamente del valor de action.
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !routepermissions_request_has_route_fields()) {
        routepermissions_debug_dump('configprocess skipped', array('reason'=>'not-post-or-no-fields'));
        return;
    }
    routepermissions_debug_log('Guardando reglas para extensión ' . $extdisplay . ' action=' . $action . ' REQUEST_KEYS=' . implode(',', array_keys($_REQUEST)) . ' POST=' . print_r($_POST, true));

    foreach ($routes as $routeId => $route) {
        $dirty = routepermissions_route_dirty($routeId);
        $policy = routepermissions_request_value("rp_policy_{$routeId}", 'INHERIT');
        routepermissions_debug_dump('route field raw', array('route_id'=>$routeId,'dirty'=>$dirty ? '1' : '0','policy_key'=>"rp_policy_{$routeId}",'policy_raw'=>isset($_POST["rp_policy_{$routeId}"]) ? $_POST["rp_policy_{$routeId}"] : (isset($_REQUEST["rp_policy_{$routeId}"]) ? $_REQUEST["rp_policy_{$routeId}"] : null)));
        $prefix = routepermissions_request_value("rp_prefix_{$routeId}", '');
        $fail   = routepermissions_request_value("rp_faildest_{$routeId}", '');
        if (is_array($policy)) { $policy = reset($policy); }
        if (is_array($prefix)) { $prefix = reset($prefix); }
        if (is_array($fail)) { $fail = reset($fail); }
        $policy = routepermissions_normalize_policy($policy);
        $prefix = preg_replace('/[^0-9\*#A-Za-z]/', '', trim((string)$prefix));
        $fail   = trim((string)$fail);

        $hasAnyField = isset($_POST["rp_policy_{$routeId}"]) || isset($_REQUEST["rp_policy_{$routeId}"]) ||
                       isset($_POST["rp_prefix_{$routeId}"]) || isset($_REQUEST["rp_prefix_{$routeId}"]) ||
                       isset($_POST["rp_faildest_{$routeId}"]) || isset($_REQUEST["rp_faildest_{$routeId}"]);
        if (!$hasAnyField) {
            routepermissions_debug_log('PRESERVE exten=' . $extdisplay . ' route_id=' . $routeId . ' (campos no presentes en esta petición)');
            continue;
        }

        // Si el usuario dejó la ruta en heredar y sin extras, eliminamos la regla explícita.
        if ($policy === 'INHERIT' && $prefix === '' && $fail === '') {
            routepermissions_delete_rule($extdisplay, $routeId);
            routepermissions_debug_log('DELETE exten=' . $extdisplay . ' route_id=' . $routeId);
            continue;
        }

        routepermissions_upsert_rule($extdisplay, $routeId, $route['name'], $policy, $prefix, $fail, '');
        routepermissions_debug_log('UPSERT exten=' . $extdisplay . ' route_id=' . $routeId . ' policy=' . $policy . ' prefix=' . $prefix . ' faildest=' . $fail);
    }

    $alreadyRan = true;
}


function routepermissions_hookGet_config($engine) {
    global $ext;
    if ($engine !== 'asterisk') { return; }
    routepermissions_ensure_schema();
    $routes = routepermissions_get_routes_with_ids();
    $contexts = array(
        // En IssabelPBX 5 no siempre existen macro-dialout-dundi ni macro-dialout-enum.
        // Splice sobre contextos inexistentes rompe retrieve_conf, así que nos enganchamos
        // únicamente al flujo estándar de salida.
        array('context' => 'macro-dialout-trunk', 'extension' => 's', 'priority' => 1),
    );
    foreach ($contexts as $ctx) {
        if (method_exists($ext, 'splice')) { $ext->splice($ctx['context'], $ctx['extension'], $ctx['priority'], new ext_agi('checkperms.agi')); }
        $ext->add($ctx['context'], 'barred', '', new ext_noop('RoutePermissions: llamada denegada por política de ruta'));
        $ext->add($ctx['context'], 'barred', '', new ext_playback('ss-noservice'));
        $ext->add($ctx['context'], 'barred', '', new ext_hangup());
        $ext->add($ctx['context'], 'reroute', '', new ext_noop('RoutePermissions: reencaminando llamada'));
        $ext->add($ctx['context'], 'reroute', '', new ext_goto('from-internal,${ARG2},1'));
    }
    foreach ($routes as $routeId => $route) {
        $context = 'outrt-' . $routeId;
        $patterns = routepermissions_get_route_patterns_by_id($routeId);
        if (empty($patterns)) {
            if (method_exists($ext, 'splice')) {
                $ext->splice($context, '_X.', 1, new ext_setvar('__ROUTEPERM_ROUTE_ID', $routeId));
                $ext->splice($context, '_X.', 2, new ext_setvar('__ROUTENAME', $route['name']));
            }
            continue;
        }
        foreach ($patterns as $rt) {
            $extension = '';
            $prefix = isset($rt['match_pattern_prefix']) ? $rt['match_pattern_prefix'] : '';
            $pass   = isset($rt['match_pattern_pass']) ? $rt['match_pattern_pass'] : '';
            $extension = $prefix . $pass;
            if ($extension === '') { $extension = '_X.'; }
            if (preg_match('/\.|z|x|\[|\]/i', $extension)) { $extension = '_' . $extension; }
            if (method_exists($ext, 'splice')) {
                $ext->splice($context, $extension, 1, new ext_setvar('__ROUTEPERM_ROUTE_ID', $routeId));
                $ext->splice($context, $extension, 2, new ext_setvar('__ROUTENAME', $route['name']));
            }
        }
    }
}

function routepermissions_handle_admin_post() {
    $result = array('messages' => array(), 'errors' => array());
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { return $result; }
    $action = isset($_POST['rp_form_action']) ? $_POST['rp_form_action'] : '';
    if ($action === 'save_defaults') {
        $routes = routepermissions_get_routes_with_ids();
        foreach ($routes as $routeId => $route) {
            $policy   = isset($_POST["default_policy_{$routeId}"]) ? $_POST["default_policy_{$routeId}"] : 'INHERIT';
            $prefix   = isset($_POST["default_prefix_{$routeId}"]) ? $_POST["default_prefix_{$routeId}"] : '';
            $faildest = isset($_POST["default_faildest_{$routeId}"]) ? $_POST["default_faildest_{$routeId}"] : '';
            $notes    = isset($_POST["default_notes_{$routeId}"]) ? $_POST["default_notes_{$routeId}"] : '';
            routepermissions_upsert_rule('-1', $routeId, $route['name'], $policy, $prefix, $faildest, $notes);
        }
        $gFail  = isset($_POST['global_faildest']) ? $_POST['global_faildest'] : '';
        $gNotes = isset($_POST['global_notes']) ? $_POST['global_notes'] : '';
        routepermissions_set_global_defaults($gFail, $gNotes);
        $result['messages'][] = 'Defaults guardados correctamente.';
        return $result;
    }
    if ($action === 'bulk_apply') {
        $routeId  = isset($_POST['bulk_route_id']) ? (int)$_POST['bulk_route_id'] : 0;
        $policy   = isset($_POST['bulk_policy']) ? $_POST['bulk_policy'] : 'INHERIT';
        $range    = isset($_POST['bulk_range']) ? $_POST['bulk_range'] : '';
        $prefix   = isset($_POST['bulk_prefix']) ? $_POST['bulk_prefix'] : '';
        $faildest = isset($_POST['bulk_faildest']) ? $_POST['bulk_faildest'] : '';
        $notes    = isset($_POST['bulk_notes']) ? $_POST['bulk_notes'] : '';
        $res = routepermissions_bulk_apply($routeId, $policy, $range, $prefix, $faildest, $notes);
        if (!empty($res['ok'])) { $result['messages'][] = $res['message']; } else { $result['errors'][] = $res['message']; }
        return $result;
    }
    if ($action === 'delete_rule') {
        $exten   = isset($_POST['delete_exten']) ? $_POST['delete_exten'] : '';
        $routeId = isset($_POST['delete_route_id']) ? (int)$_POST['delete_route_id'] : 0;
        if ($exten !== '' && $routeId > 0) {
            routepermissions_delete_rule($exten, $routeId);
            $result['messages'][] = "Regla eliminada para la extensión {$exten}.";
        }
        return $result;
    }
    return $result;
}
