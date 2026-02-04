<?php
if (!defined('ABSPATH')) { exit; }

$title = isset($title) ? $title : 'Agrocampo – Cotizador PDF';
$menu_links = isset($menu_links) ? $menu_links : [];
$css_url = ACPDF_URL . 'assets/cotizador.css?ver=' . ACPDF_VER;

// Sellers (multi-seller)
$sellers = class_exists('ACPDF_Settings') ? ACPDF_Settings::get_sellers() : [];
$default_seller_id = class_exists('ACPDF_Settings') ? ACPDF_Settings::get_default_seller_id() : '';

$selected_seller_id = '';
if (is_array($prefill ?? null)) {
  if (!empty($prefill['seller_id'])) {
    $selected_seller_id = sanitize_key($prefill['seller_id']);
  } elseif (!empty($prefill['seller']['name']) && !empty($sellers)) {
    $pref_name = trim((string)$prefill['seller']['name']);
    foreach ($sellers as $r) {
      if (strcasecmp(trim((string)($r['name'] ?? '')), $pref_name) === 0) {
        $selected_seller_id = sanitize_key($r['id'] ?? '');
        break;
      }
    }
  }
}
if ($selected_seller_id === '') {
  $selected_seller_id = $default_seller_id;
}

include ACPDF_DIR . 'templates/partials/head.php';
?>
            <div class="card">
              <div class="top">
                <img class="logo" src="<?php echo esc_url($logo_url); ?>" alt="Agrocampo">
                <div>
                  <h1>Cotizador Mantenciones PDF</h1>
                  <div class="muted">Complete los datos y genere la cotización en PDF</div>
                </div>
              </div>

              <form id="acpdf-form" method="post" action="<?php echo esc_url(home_url('/agrocampo-cotizador/pdf')); ?>">
                <?php wp_nonce_field('acpdf_make_pdf', 'acpdf_nonce'); ?>

                <div class="grid">

                  <!-- Fila 1: Datos internos y fecha -->
                  <div class="col-3">
                    <label for="internal_no">N° Interno</label>
                    <input type="number" name="internal_no" id="internal_no" inputmode="numeric" min="0" step="1" />
                  </div>
                  <div class="col-3">
                    <label for="serial_no">Serie</label>
                    <input type="text" name="serial_no" id="serial_no" inputmode="text" />
                  </div>
                  <div class="col-3">
                    <label for="date_iso">Fecha</label>
                    <input type="date" name="date_iso" id="acpdf-date" value="<?php echo esc_attr(date_i18n('Y-m-d')); ?>" />
                  </div>
                  <div class="col-3">
                    <label for="rut">RUT</label>
                    <input name="rut" id="rut" placeholder="76.155.060-8" />
                  </div>

                  <!-- Fila 2: Datos cliente -->
                  <div class="col-6">
                    <label for="client">Cliente <span style="color:var(--red)">*</span></label>
                    <input name="client" id="client" required />
                  </div>
                  <div class="col-3">
                    <label for="phone">Teléfono</label>
                    <input name="phone" id="phone" type="tel" />
                  </div>
                  <div class="col-3">
                    <label for="email">Email</label>
                    <input type="email" name="email" id="email" />
                  </div>

                  <!-- Vendedor -->
                  <div class="col-4">
                    <label for="acpdf-seller">Vendedor</label>
                    <select name="seller_id" id="acpdf-seller">
                      <?php if (!empty($sellers)) : ?>
                        <option value="">— Seleccionar —</option>
                        <?php foreach ($sellers as $r) :
                          $sid = sanitize_key($r['id'] ?? '');
                          $sname = trim((string)($r['name'] ?? ''));
                          if ($sid === '' || $sname === '') continue;
                        ?>
                          <option value="<?php echo esc_attr($sid); ?>" <?php selected($selected_seller_id, $sid); ?>><?php echo esc_html($sname); ?></option>
                        <?php endforeach; ?>
                      <?php else : ?>
                        <option value="">— Sin vendedores (configurar en Ajustes) —</option>
                      <?php endif; ?>
                    </select>
                    <div class="muted small">Se usa en el pie del PDF. Configurable en Ajustes.</div>
                  </div>

                  <!-- Fila 3: Marca, pauta y modelo -->
                  <div class="col-4">
                    <label for="acpdf-brand">Marca</label>
                    <select name="brand_key" id="acpdf-brand">
                      <option value="massey_ferguson">Massey Ferguson</option>
                    </select>
                    <div class="muted small">Selecciona la marca para filtrar pautas.</div>
                  </div>

                  <div class="col-4">
                    <label for="acpdf-template">Pauta (precarga)</label>
                    <select name="template_key" id="acpdf-template">
                      <option value="">— Sin precarga —</option>
                    </select>
                    <div class="muted small">Selecciona una pauta para cargar ítems automáticamente.</div>
                  </div>

                  <div class="col-4">
                    <label for="acpdf-model">Modelo <span style="color:var(--red)">*</span></label>
                    <input name="model" id="acpdf-model" required />
                  </div>

                  <!-- Fila 4: Configuración mantención -->
                  <div class="col-3">
                    <label for="acpdf-hours-set">Set de horas</label>
                    <select name="hours_set" id="acpdf-hours-set">
                      <option value="A">100 - 400 - 800 - 1200</option>
                      <option value="B">100 - 500 - 1000 - 1500</option>
                    </select>
                  </div>
                  <div class="col-3">
                    <label for="acpdf-hours">Tipo mantención (horas)</label>
                    <select name="maint_hours" id="acpdf-hours"></select>
                  </div>
                  <div class="col-3">
                    <label for="parts_type">FILTROS</label>
                    <select name="parts_type" id="parts_type">
                      <option value="ORIGINALES">ORIGINALES</option>
                      <option value="ALTERNATIVOS">ALTERNATIVOS</option>
                    </select>
                  </div>
                  <div class="col-3">
                    <label for="acpdf-title">Título (PDF)</label>
                    <input name="title" id="acpdf-title" readonly />
                    <div class="muted small">Automático: Modelo + tipo mantención.</div>
                  </div>

                  <!-- Observaciones -->
                  <div class="col-12">
                    <label for="observations">Observaciones</label>
                    <textarea name="observations" id="observations" style="min-height:60px"></textarea>
                  </div>

                  <!-- Tabla de ítems -->
                  <div class="col-12">
                    <label>Ítems de la cotización</label>
                    <div class="table-responsive">
                    <table id="acpdf-items">
                      <thead>
                        <tr>
                          <th style="width:70px">N°</th>
                          <th style="width:120px">Código</th>
                          <th>Detalle</th>
                          <th style="width:120px">Valor Neto</th>
                          <th style="width:70px">Un.</th>
                          <th style="width:80px">Descto %</th>
                          <th style="width:90px">Cantidad</th>
                          <th style="width:120px">V. Total</th>
                          <th style="width:50px"></th>
                        </tr>
                      </thead>
                      <tbody></tbody>
                    </table>
                    </div>

                    <div class="toolbar">
                      <button type="button" class="btn btn-ghost" id="acpdf-add">+ Agregar fila</button>

                      <div class="totals">
                        <div class="line"><span>Neto</span><strong id="acpdf-neto">$0</strong></div>
                        <div class="line"><span>IVA (<?php echo esc_html(rtrim(rtrim(number_format($settings['default_iva_percent'],2,'.',''), '0'), '.')); ?>%)</span><strong id="acpdf-iva">$0</strong></div>
                        <div class="line"><span>Total</span><strong id="acpdf-total">$0</strong></div>
                      </div>

                      <div style="display:flex;gap:8px;">
                        <button type="button" class="btn btn-ghost" id="acpdf-preview">👁️ Vista previa</button>
                        <button type="submit" class="btn btn-primary">📄 Generar PDF</button>
                      </div>
                    </div>
                  </div>
                </div>
              </form>
            </div>

            
<script>
  window.ACPDF_CONFIG = <?php echo wp_json_encode([
    'iva' => floatval($settings['default_iva_percent']),
    'prefill' => $prefill,
  ], JSON_UNESCAPED_UNICODE); ?>;
  window.ACPDF_TEMPLATES = <?php echo wp_json_encode(ACPDF_Templates::catalog(), JSON_UNESCAPED_UNICODE); ?>;
</script>
<script defer src="<?php echo esc_url(ACPDF_URL . 'assets/cotizador.js?ver=' . ACPDF_VER); ?>"></script>

<?php include ACPDF_DIR . 'templates/partials/foot.php'; ?>
