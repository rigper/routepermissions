<?php
/**
 * routepermissions / IssabelPBX 5
 * v1.0.0 - views/routepermissions.php
 */
?>
<style>
.routeperm-wrap{max-width:100%}.routeperm-card{background:#fff;border:1px solid #d9e2ef;border-radius:8px;padding:16px;margin-bottom:16px}.routeperm-title{margin:0 0 12px;font-size:18px;font-weight:600}.routeperm-muted{color:#6b7280}.routeperm-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px}.routeperm-table{width:100%;border-collapse:collapse;font-size:13px}.routeperm-table th,.routeperm-table td{border:1px solid #e5e7eb;padding:8px;vertical-align:top}.routeperm-table th{background:#f8fafc}.routeperm-alert{padding:10px 12px;border-radius:6px;margin-bottom:10px}.routeperm-ok{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0}.routeperm-err{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}.routeperm-actions{display:flex;gap:8px;flex-wrap:wrap}.routeperm-small{font-size:12px}.routeperm-code{font-family:monospace;background:#f3f4f6;padding:2px 4px;border-radius:4px}.routeperm-input,.routeperm-select{width:100%;box-sizing:border-box;padding:7px 8px;border:1px solid #cbd5e1;border-radius:4px}.routeperm-btn{padding:8px 12px;border:0;border-radius:6px;background:#2563eb;color:#fff;cursor:pointer}.routeperm-btn.secondary{background:#475569}.routeperm-badge{display:inline-block;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700}.routeperm-badge.yes{background:#dcfce7;color:#166534}.routeperm-badge.no{background:#fee2e2;color:#991b1b}.routeperm-badge.inherit{background:#e0f2fe;color:#075985}.routeperm-badge.meta{background:#ede9fe;color:#5b21b6}@media (max-width:900px){.routeperm-table{font-size:12px}}
</style>
<div class="routeperm-wrap">
  <div class="routeperm-card">
    <h2 class="routeperm-title">Route Permissions Pro</h2>
    <div class="routeperm-muted">Módulo legacy adaptado para IssabelPBX 5. Permite controlar acceso por extensión a rutas salientes, con defaults por ruta, destino alterno y redirección mediante prefijo.</div>
  </div>
  <?php if (!empty($rp_result['messages'])): foreach ($rp_result['messages'] as $msg): ?>
      <div class="routeperm-alert routeperm-ok"><?php echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?></div>
  <?php endforeach; endif; ?>
  <?php if (!empty($rp_result['errors'])): foreach ($rp_result['errors'] as $msg): ?>
      <div class="routeperm-alert routeperm-err"><?php echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?></div>
  <?php endforeach; endif; ?>
  <div class="routeperm-card">
    <h3 class="routeperm-title">Resumen</h3>
    <div class="routeperm-grid">
      <div><strong>Rutas detectadas:</strong> <?php echo count($rp_routes); ?></div>
      <div><strong>Extensiones detectadas:</strong> <?php echo count($rp_extensions); ?></div>
      <div><strong>Reglas cargadas:</strong> <?php echo count($rp_rules); ?></div>
      <div><strong>Destino global si se deniega:</strong> <span class="routeperm-code"><?php echo htmlspecialchars($rp_global['faildest'] ?: '(vacío)', ENT_QUOTES, 'UTF-8'); ?></span></div>
    </div>
  </div>
  <div class="routeperm-card">
    <h3 class="routeperm-title">Defaults por ruta</h3>
    <form method="post">
      <input type="hidden" name="rp_form_action" value="save_defaults" />
      <table class="routeperm-table">
        <thead><tr><th style="width:70px">ID</th><th>Ruta</th><th style="width:130px">Policy default</th><th style="width:160px">Prefijo</th><th style="width:270px">Destino alterno</th><th>Notas</th></tr></thead>
        <tbody>
          <?php foreach ($rp_routes as $routeId => $route): $default = routepermissions_get_rule('-1', $routeId); ?>
            <tr>
              <td>#<?php echo (int)$routeId; ?></td>
              <td><strong><?php echo htmlspecialchars($route['name'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
              <td><select class="routeperm-select" name="default_policy_<?php echo (int)$routeId; ?>"><?php $sel = is_array($default) ? $default['allowed'] : 'INHERIT'; ?><option value="INHERIT" <?php echo $sel === 'INHERIT' ? 'selected' : ''; ?>>INHERIT</option><option value="YES" <?php echo $sel === 'YES' ? 'selected' : ''; ?>>YES</option><option value="NO" <?php echo $sel === 'NO' ? 'selected' : ''; ?>>NO</option></select></td>
              <td><input class="routeperm-input" type="text" name="default_prefix_<?php echo (int)$routeId; ?>" value="<?php echo htmlspecialchars(is_array($default) ? $default['prefix'] : '', ENT_QUOTES, 'UTF-8'); ?>" /></td>
              <td><?php $destName = 'default_faildest_' . (int)$routeId; $destVal  = is_array($default) ? $default['faildest'] : ''; echo routepermissions_get_destinations_select_html($destVal, $destName, $destName); ?></td>
              <td><input class="routeperm-input" type="text" name="default_notes_<?php echo (int)$routeId; ?>" value="<?php echo htmlspecialchars(is_array($default) ? $default['notes'] : '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="opcional" /></td>
            </tr>
          <?php endforeach; ?>
          <tr>
            <td>#G</td>
            <td><strong>Global</strong><div class="routeperm-small routeperm-muted">Se usa si una ruta denegada no tiene faildest específico.</div></td>
            <td><span class="routeperm-badge meta">meta</span></td><td>—</td>
            <td><?php echo routepermissions_get_destinations_select_html($rp_global['faildest'], 'global_faildest', 'global_faildest'); ?></td>
            <td><input class="routeperm-input" type="text" name="global_notes" value="<?php echo htmlspecialchars($rp_global['notes'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="notas globales" /></td>
          </tr>
        </tbody>
      </table>
      <div style="margin-top:12px" class="routeperm-actions"><button class="routeperm-btn" type="submit">Guardar defaults</button></div>
    </form>
  </div>
  <div class="routeperm-card">
    <h3 class="routeperm-title">Aplicación masiva</h3>
    <form method="post">
      <input type="hidden" name="rp_form_action" value="bulk_apply" />
      <div class="routeperm-grid">
        <div><label><strong>Ruta</strong></label><select class="routeperm-select" name="bulk_route_id" required><option value="">Seleccione</option><?php foreach ($rp_routes as $routeId => $route): ?><option value="<?php echo (int)$routeId; ?>">#<?php echo (int)$routeId; ?> - <?php echo htmlspecialchars($route['name'], ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select></div>
        <div><label><strong>Acción</strong></label><select class="routeperm-select" name="bulk_policy"><option value="YES">Permitir</option><option value="NO">Denegar</option><option value="inherit">Eliminar / heredar default</option></select></div>
        <div><label><strong>Rango</strong></label><input class="routeperm-input" type="text" name="bulk_range" placeholder="100,101,200-250" required /></div>
        <div><label><strong>Prefijo</strong></label><input class="routeperm-input" type="text" name="bulk_prefix" placeholder="opcional" /></div>
      </div>
      <div class="routeperm-grid" style="margin-top:12px">
        <div><label><strong>Destino alterno</strong></label><?php echo routepermissions_get_destinations_select_html('', 'bulk_faildest', 'bulk_faildest'); ?></div>
        <div><label><strong>Notas</strong></label><input class="routeperm-input" type="text" name="bulk_notes" placeholder="opcional" /></div>
      </div>
      <div class="routeperm-small routeperm-muted" style="margin-top:10px">El rango acepta valores como <span class="routeperm-code">100,105,200-220</span>. Si eliges <strong>Eliminar / heredar default</strong>, se eliminan las reglas explícitas de esas extensiones para la ruta.</div>
      <div style="margin-top:12px" class="routeperm-actions"><button class="routeperm-btn secondary" type="submit">Aplicar cambios masivos</button></div>
    </form>
  </div>
  <div class="routeperm-card">
    <h3 class="routeperm-title">Reglas actuales</h3>
    <table class="routeperm-table">
      <thead><tr><th>Extensión</th><th>Ruta</th><th>Policy</th><th>Prefijo</th><th>Destino alterno</th><th>Notas</th><th>Actualizado</th><th style="width:90px">Acción</th></tr></thead>
      <tbody>
        <?php if (empty($rp_rules)): ?><tr><td colspan="8" class="routeperm-muted">No hay reglas cargadas.</td></tr><?php else: foreach ($rp_rules as $row): $isDefault = ($row['exten'] === '-1'); $badgeClass = strtolower($row['allowed']); if (!in_array($badgeClass, array('yes','no','inherit'))) { $badgeClass = 'inherit'; } ?>
            <tr>
              <td><?php if ($isDefault): ?><span class="routeperm-badge meta">default</span><?php else: ?><?php echo htmlspecialchars($row['exten'], ENT_QUOTES, 'UTF-8'); ?><?php endif; ?></td>
              <td>#<?php echo (int)$row['route_id']; ?> - <?php echo htmlspecialchars($row['routename'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td><span class="routeperm-badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($row['allowed'], ENT_QUOTES, 'UTF-8'); ?></span></td>
              <td><span class="routeperm-code"><?php echo htmlspecialchars($row['prefix'] ?: '—', ENT_QUOTES, 'UTF-8'); ?></span></td>
              <td><span class="routeperm-code"><?php echo htmlspecialchars($row['faildest'] ?: '—', ENT_QUOTES, 'UTF-8'); ?></span></td>
              <td><?php echo htmlspecialchars($row['notes'] ?: '—', ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars($row['updated_at'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php if (!$isDefault): ?><form method="post" onsubmit="return confirm('¿Eliminar esta regla?');"><input type="hidden" name="rp_form_action" value="delete_rule" /><input type="hidden" name="delete_exten" value="<?php echo htmlspecialchars($row['exten'], ENT_QUOTES, 'UTF-8'); ?>" /><input type="hidden" name="delete_route_id" value="<?php echo (int)$row['route_id']; ?>" /><button type="submit" class="routeperm-btn secondary">Eliminar</button></form><?php else: ?><span class="routeperm-muted">—</span><?php endif; ?></td>
            </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <div class="routeperm-card">
    <h3 class="routeperm-title">Notas de operación</h3>
    <ul>
      <li>El hook agrega el AGI <span class="routeperm-code">checkperms.agi</span> al flujo saliente y define <span class="routeperm-code">__ROUTEPERM_ROUTE_ID</span> y <span class="routeperm-code">__ROUTENAME</span> en cada contexto <span class="routeperm-code">outrt-N</span>.</li>
      <li>Si una llamada es denegada, el AGI evalúa en este orden: regla explícita de la extensión, default de la ruta y destino global de fallback.</li>
      <li>Si se define un prefijo de redirección, se antepone al número marcado y la llamada se reinyecta a <span class="routeperm-code">from-internal</span>.</li>
      <li>El destino alterno acepta destinos estándar de FreePBX/Issabel, por ejemplo <span class="routeperm-code">from-did-direct,100,1</span> o <span class="routeperm-code">ext-local,600,1</span>.</li>
    </ul>
  </div>
</div>
