<?php
/**
 * Plugin Name: Agrocampo – Cotizador PDF
 * Description: Cotizador frontend (sin theme) que genera cotizaciones en PDF.
 * Version: 1.0.3
 * Author: Rocket Solutions
 */

if (!defined('ABSPATH')) { exit; }

define('ACPDF_VER', '1.0.3');
define('ACPDF_SLUG', 'agrocampo-cotizador-pdf');
define('ACPDF_DIR', plugin_dir_path(__FILE__));
define('ACPDF_URL', plugin_dir_url(__FILE__));

require_once ACPDF_DIR . 'includes/fpdf/fpdf.php';
require_once ACPDF_DIR . 'includes/class-acpdf-settings.php';
require_once ACPDF_DIR . 'includes/class-acpdf-pdf.php';

class Agrocampo_Cotizador_PDF {

    public function __construct() {
        add_action('init', [$this, 'register_routes']);
        add_action('template_redirect', [$this, 'handle_routes']);
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_init', [$this, 'admin_init']);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
    }

    public function register_assets() {
        // Assets are enqueued only on our frontend route.
    }

    public function register_routes() {
        add_rewrite_rule('^agrocampo-cotizador/?$', 'index.php?acpdf_route=form', 'top');
        add_rewrite_rule('^agrocampo-cotizador/pdf/?$', 'index.php?acpdf_route=pdf', 'top');
        add_rewrite_rule('^agrocampo-cotizador/next/?$', 'index.php?acpdf_route=next', 'top');
        add_filter('query_vars', function($vars){
            $vars[] = 'acpdf_route';
            return $vars;
        });
    }

    public function admin_menu() {
        add_options_page('Cotizador PDF', 'Cotizador PDF', 'manage_options', 'acpdf-settings', ['ACPDF_Settings', 'render_page']);
        add_submenu_page('options-general.php', 'Gestor de Cotizaciones', 'Gestor de Cotizaciones', 'manage_options', 'acpdf-quotes', [$this, 'render_quotes_page']);
    }

    public function admin_init() {
        ACPDF_Settings::register();
    }

    public function admin_assets($hook) {
        if ($hook !== 'settings_page_acpdf-settings') {
            return;
        }
        wp_enqueue_media();
    }

    private function render_head($title='Agrocampo – Cotizador PDF') {
        nocache_headers();
        header('Content-Type: text/html; charset=utf-8');
        ?><!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title><?php echo esc_html($title); ?></title>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <style>
    :root{--red:#c60000;--gray:#f5f6f8;--text:#111;--border:#d9dde3}
    *{box-sizing:border-box}
    body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;color:var(--text);background:#fff}
    .wrap{max-width:1100px;margin:20px auto;padding:0 14px}
    .card{border:1px solid var(--border);border-radius:14px;padding:16px;background:#fff}
    .top{display:flex;align-items:center;gap:14px;margin-bottom:14px}
    .logo{width:220px;max-width:45vw;height:auto}
    h1{font-size:18px;margin:0}
    .grid{display:grid;grid-template-columns:repeat(12,1fr);gap:10px}
    label{font-size:12px;color:#333;display:block;margin-bottom:4px}
    input,select,textarea{width:100%;padding:10px 10px;border:1px solid var(--border);border-radius:10px;font-size:14px}
    textarea{min-height:90px;resize:vertical}
    .col-3{grid-column:span 3}
    .col-4{grid-column:span 4}
    .col-6{grid-column:span 6}
    .col-8{grid-column:span 8}
    .col-12{grid-column:span 12}
    .muted{color:#666;font-size:12px}
    .row{display:flex;gap:10px;align-items:center}
    .btn{appearance:none;border:0;border-radius:10px;padding:10px 14px;font-weight:600;cursor:pointer}
    .btn-primary{background:var(--red);color:#fff}
    .btn-ghost{background:#eef0f3;color:#111}
    table{width:100%;border-collapse:collapse;margin-top:10px}
    th,td{border:1px solid var(--border);padding:8px;font-size:12.5px;vertical-align:top}
    th{background:#f2f3f6;text-align:left}
    .t-right{text-align:right}
    .t-center{text-align:center}
    .small{font-size:11px}
    .toolbar{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-top:12px}
    .totals{margin-left:auto;min-width:280px}
    .totals .line{display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px dashed #e3e6eb}
    .totals .line:last-child{border-bottom:0}
    @media (max-width:900px){.col-3,.col-4,.col-6,.col-8{grid-column:span 12}.logo{width:180px}}
  </style>
</head>
<body>
<div class="wrap">
<?php
    }

    private function render_foot() {
        ?></div>
</body>
</html><?php
    }

    public function render_quotes_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        if (isset($_GET['view'])) {
            $index = absint($_GET['view']);
            $log = ACPDF_PDF::get_quote_log();
            if (!isset($log[$index])) {
                wp_die('Cotización no encontrada.');
            }
            $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
            if (!wp_verify_nonce($nonce, 'acpdf_view_quote_' . $index)) {
                wp_die('Acceso no autorizado.');
            }
            ACPDF_PDF::output_pdf_from_log($log[$index]);
            exit;
        }
        $hours_filter = isset($_GET['maint_hours']) ? sanitize_text_field(wp_unslash($_GET['maint_hours'])) : '';
        $hours_filter = preg_replace('/[^0-9]/', '', $hours_filter);
        $log = ACPDF_PDF::get_quote_log();
        $hours_options = [100, 400, 800, 1200, 500, 1000, 1500];
        sort($hours_options);
        ?>
        <div class="wrap">
          <h1>Gestor de Cotizaciones</h1>
          <p class="description">Los PDFs se abren en una nueva pestaña para facilitar volver al listado.</p>
          <form method="get" style="margin:12px 0;">
            <input type="hidden" name="page" value="acpdf-quotes">
            <label for="acpdf-hours-filter" style="margin-right:8px;">Filtro por horas</label>
            <select name="maint_hours" id="acpdf-hours-filter">
              <option value="">Todas</option>
              <?php foreach ($hours_options as $opt) : ?>
                <option value="<?php echo esc_attr($opt); ?>" <?php selected($hours_filter, (string)$opt); ?>><?php echo esc_html($opt); ?></option>
              <?php endforeach; ?>
            </select>
            <?php submit_button('Filtrar', 'secondary', '', false); ?>
          </form>

          <table class="widefat striped">
            <thead>
              <tr>
                <th>Fecha</th>
                <th>Cotización N°</th>
                <th>Modelo</th>
                <th>Cliente</th>
                <th>Horas</th>
                <th>Repuestos</th>
                <th>Neto</th>
                <th>IVA</th>
                <th>Total</th>
                <th>PDF</th>
                <th>Gestionar</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $has_rows = false;
              foreach (array_reverse($log, true) as $index => $entry) {
                  $hours = isset($entry['maint_hours']) ? (string)$entry['maint_hours'] : '';
                  if ($hours_filter !== '' && $hours_filter !== $hours) {
                      continue;
                  }
                  $has_rows = true;
                  ?>
                  <tr>
                    <td><?php echo esc_html($entry['date_iso'] ?? ''); ?></td>
                    <td><?php echo esc_html($entry['quote_no'] ?? ''); ?></td>
                    <td><?php echo esc_html($entry['model'] ?? ''); ?></td>
                    <td><?php echo esc_html($entry['client'] ?? ''); ?></td>
                    <td><?php echo esc_html($entry['maint_hours'] ?? ''); ?></td>
                    <td><?php echo esc_html($entry['parts_type'] ?? ''); ?></td>
                    <td><?php echo esc_html(number_format(floatval($entry['neto'] ?? 0), 0, ',', '.')); ?></td>
                    <td><?php echo esc_html(number_format(floatval($entry['iva'] ?? 0), 0, ',', '.')); ?></td>
                    <td><?php echo esc_html(number_format(floatval($entry['total'] ?? 0), 0, ',', '.')); ?></td>
                    <td>
                      <?php if (!empty($entry['payload']) && is_array($entry['payload'])) : ?>
                        <?php
                        $url = wp_nonce_url(
                            admin_url('options-general.php?page=acpdf-quotes&view=' . $index),
                            'acpdf_view_quote_' . $index
                        );
                        ?>
                        <a class="button button-small" href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer">Ver PDF</a>
                      <?php else : ?>
                        <span class="dashicons dashicons-minus"></span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php if (!empty($entry['payload']) && is_array($entry['payload'])) : ?>
                        <?php
                        $edit_url = wp_nonce_url(
                            home_url('/agrocampo-cotizador?prefill=' . $index),
                            'acpdf_prefill_' . $index
                        );
                        ?>
                        <a class="button button-small" href="<?php echo esc_url($edit_url); ?>" target="_blank" rel="noopener noreferrer">Abrir formulario</a>
                      <?php else : ?>
                        <span class="dashicons dashicons-minus"></span>
                      <?php endif; ?>
                    </td>
                  </tr>
                  <?php
              }
              if (!$has_rows) :
              ?>
                <tr>
                  <td colspan="11">No hay cotizaciones registradas para este filtro.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <?php
    }

    public function handle_routes() {
        $route = get_query_var('acpdf_route');
        if (!$route) return;

        if ($route === 'form') {
            $settings = ACPDF_Settings::get();
            $logo_url = ACPDF_URL . 'assets/agrocampo-logo.png';
            $prefill = null;
            if (!empty($settings['logo_id'])) {
                $custom_logo = wp_get_attachment_url(absint($settings['logo_id']));
                if ($custom_logo) {
                    $logo_url = $custom_logo;
                }
            }
            if (isset($_GET['prefill'])) {
                $index = absint($_GET['prefill']);
                $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
                $log = ACPDF_PDF::get_quote_log();
                if (isset($log[$index]) && wp_verify_nonce($nonce, 'acpdf_prefill_' . $index) && current_user_can('manage_options')) {
                    $prefill = $log[$index]['payload'] ?? null;
                }
            }
            $this->render_head('Agrocampo – Cotizador PDF');
            ?>
            <div class="card">
              <div class="top">
                <img class="logo" src="<?php echo esc_url($logo_url); ?>" alt="Agrocampo">
                <div>
                  <h1>Cotizador Mantenciones PDF</h1>
                </div>
              </div>

              <form id="acpdf-form" method="post" action="<?php echo esc_url(home_url('/agrocampo-cotizador/pdf')); ?>">
                <?php wp_nonce_field('acpdf_make_pdf', 'acpdf_nonce'); ?>

                <div class="grid">
                  <div class="col-3">
                    <label>Ciudad</label>
                    <input name="city" value="<?php echo esc_attr($settings['default_city']); ?>" />
                  </div>
                  <div class="col-3">
                    <label>Fecha</label>
                    <input type="date" name="date_iso" id="acpdf-date" value="<?php echo esc_attr(date_i18n('Y-m-d')); ?>" />
                  </div>
                  <div class="col-3">
                    <label>RUT</label>
                    <input name="rut" placeholder="76.155.060-8" />
                  </div>

                  <div class="col-6">
                    <label>Cliente</label>
                    <input name="client" />
                  </div>
                  <div class="col-3">
                    <label>Teléfono</label>
                    <input name="phone" />
                  </div>
                  <div class="col-3">
                    <label>Email</label>
                    <input type="email" name="email" />
                  </div>

                  <div class="col-4">
                    <label>Modelo</label>
                    <input name="model" id="acpdf-model" />
                  </div>
                  <div class="col-4">
                    <label>Ubicación</label>
                    <input name="location" />
                  </div>

                  <div class="col-4">
                    <label>Set de horas</label>
                    <select name="hours_set" id="acpdf-hours-set">
                      <option value="A">100 - 400 - 800 - 1200</option>
                      <option value="B">100 - 500 - 1000 - 1500</option>
                    </select>
                  </div>
                  <div class="col-4">
                    <label>Tipo mantención (horas)</label>
                    <select name="maint_hours" id="acpdf-hours"></select>
                  </div>
                  <div class="col-4">
                    <label>FILTROS</label>
                    <select name="parts_type">
                      <option value="ORIGINALES">ORIGINALES</option>
                      <option value="ALTERNATIVOS">ALTERNATIVOS</option>
                    </select>
                  </div>
                  <div class="col-4">
                    <label>Título (PDF)</label>
                    <input name="title" id="acpdf-title" readonly />
                    <div class="muted small">Automático: Modelo + tipo mantención.</div>
                  </div>

                  <div class="col-12">
                    <label>Observaciones</label>
                    <textarea name="observations"></textarea>
                  </div>

                  <div class="col-12">
                    <label>Ítems</label>
                    <table id="acpdf-items">
                      <thead>
                        <tr>
                          <th style="width:55px">N°</th>
                          <th style="width:120px">Código</th>
                          <th>Detalle</th>
                          <th style="width:120px">Valor Neto</th>
                          <th style="width:70px">Un.</th>
                          <th style="width:80px">Descto %</th>
                          <th style="width:90px">Cantidad</th>
                          <th style="width:120px">V. Total</th>
                          <th style="width:60px"></th>
                        </tr>
                      </thead>
                      <tbody></tbody>
                    </table>

                    <div class="toolbar">
                      <button type="button" class="btn btn-ghost" id="acpdf-add">+ Agregar fila</button>

                      <div class="totals">
                        <div class="line"><span>Neto</span><strong id="acpdf-neto">$0</strong></div>
                        <div class="line"><span>IVA (<?php echo esc_html(rtrim(rtrim(number_format($settings['default_iva_percent'],2,'.',''), '0'), '.')); ?>%)</span><strong id="acpdf-iva">$0</strong></div>
                        <div class="line"><span>Total</span><strong id="acpdf-total">$0</strong></div>
                      </div>

                      <button type="submit" class="btn btn-primary">Generar PDF</button>
                    </div>
                  </div>
                </div>
              </form>
            </div>

            <script>
              (function(){
                const IVA = <?php echo json_encode(floatval($settings['default_iva_percent'])); ?>;
                const PREFILL = <?php echo wp_json_encode($prefill); ?>;
                const fmt = (n)=>{
                  n = Math.round((Number(n)||0));
                  return '$' + n.toString().replace(/\B(?=(\d{3})+(?!\d))/g,'.');
                };

                const hours = {
                  A:[100,400,800,1200],
                  B:[100,500,1000,1500]
                };

                const $set = document.getElementById('acpdf-hours-set');
                const $hours = document.getElementById('acpdf-hours');
                const $model = document.getElementById('acpdf-model');
                const $title = document.getElementById('acpdf-title');

                function fillHours(){
                  const list = hours[$set.value] || hours.A;
                  $hours.innerHTML = list.map(v=>`<option value="${v}">${v}</option>`).join('');
                  updateTitle();
                }

                function updateTitle(){
                  const m = ($model.value||'').trim();
                  const h = ($hours.value||'').trim();
                  const t = (m ? m + ' ' : '') + 'MANTENCION ' + h + ' HORAS';
                  $title.value = t.trim();
                }

                const tbody = document.querySelector('#acpdf-items tbody');

                function addRow(data={}){
                  const tr = document.createElement('tr');
                  tr.innerHTML = `
                    <td class="t-center"><input name="item_n[]" value="${data.n||''}" /></td>
                    <td><input name="item_code[]" value="${data.code||''}" /></td>
                    <td><textarea name="item_detail[]" style="min-height:44px">${data.detail||''}</textarea></td>
                    <td><input name="item_unit_price[]" value="${data.unit_price||''}" class="t-right" /></td>
                    <td><input name="item_unit[]" value="${data.unit||'UN'}" class="t-center" /></td>
                    <td><input name="item_discount[]" value="${data.discount||''}" class="t-center" /></td>
                    <td><input name="item_qty[]" value="${data.qty||''}" class="t-center" /></td>
                    <td class="t-right"><span class="acpdf-line">$0</span></td>
                    <td class="t-center"><button type="button" class="btn btn-ghost acpdf-del" style="padding:6px 10px">X</button></td>
                  `;
                  tbody.appendChild(tr);
                  renumber();
                  calc();
                }

                function renumber(){
                  [...tbody.querySelectorAll('tr')].forEach((tr,i)=>{
                    const inp = tr.querySelector('input[name="item_n[]"]');
                    if (inp && !inp.value) inp.value = String(i+1);
                  });
                }

                function num(v){
                  v = (v||'').toString().replace(/[^0-9.,-]/g,'').replace(/\./g,'').replace(',', '.');
                  const n = parseFloat(v);
                  return isFinite(n) ? n : 0;
                }

                function calc(){
                  let neto = 0;
                  [...tbody.querySelectorAll('tr')].forEach(tr=>{
                    const up = num(tr.querySelector('input[name="item_unit_price[]"]').value);
                    const qty = num(tr.querySelector('input[name="item_qty[]"]').value);
                    const disc = num(tr.querySelector('input[name="item_discount[]"]').value);
                    let line = up * qty;
                    if (disc > 0) line = line * (1 - disc/100);
                    if (line < 0) line = 0;
                    neto += line;
                    tr.querySelector('.acpdf-line').textContent = line > 0 ? fmt(line) : '$0';
                  });
                  const iva = neto * (IVA/100);
                  const total = neto + iva;
                  document.getElementById('acpdf-neto').textContent = fmt(neto);
                  document.getElementById('acpdf-iva').textContent = fmt(iva);
                  document.getElementById('acpdf-total').textContent = fmt(total);
                }

                function applyPrefill(data){
                  if (!data || typeof data !== 'object') return;
                  const setField = (name, value)=>{
                    const el = document.querySelector(`[name="${name}"]`);
                    if (el) el.value = value || '';
                  };
                  setField('city', data.city);
                  setField('date_iso', data.date_iso);
                  setField('rut', data.rut);
                  setField('client', data.client);
                  setField('phone', data.phone);
                  setField('email', data.email);
                  setField('model', data.model);
                  setField('location', data.location);
                  setField('parts_type', data.parts_type);
                  setField('observations', data.observations);

                  const hoursValue = String(data.maint_hours || '');
                  $set.value = (hoursValue === '500' || hoursValue === '1000' || hoursValue === '1500') ? 'B' : 'A';
                  fillHours();
                  if ($hours) {
                    $hours.value = hoursValue || $hours.value;
                  }
                  updateTitle();

                  tbody.innerHTML = '';
                  const items = Array.isArray(data.items) ? data.items : [];
                  if (items.length) {
                    items.forEach((item, idx)=>{
                      addRow({
                        n: item.n || (idx + 1),
                        code: item.code || '',
                        detail: item.detail || '',
                        unit_price: item.unit_price || '',
                        unit: item.unit || 'UN',
                        discount: item.discount || '',
                        qty: item.qty || ''
                      });
                    });
                  } else {
                    addRow({n:1});
                  }
                  calc();
                }

                document.getElementById('acpdf-add').addEventListener('click', ()=>addRow({}));
                tbody.addEventListener('click', (e)=>{
                  if (e.target && e.target.classList.contains('acpdf-del')){
                    e.preventDefault();
                    e.target.closest('tr').remove();
                    renumber();
                    calc();
                  }
                });
                tbody.addEventListener('input', calc);

                $set.addEventListener('change', ()=>{fillHours();});
                $hours.addEventListener('change', updateTitle);
                $model.addEventListener('input', updateTitle);
                fillHours();
                if (PREFILL) {
                  applyPrefill(PREFILL);
                } else {
                  addRow({n:1});
                }
              })();
            </script>
            <?php
            $this->render_foot();
            exit;
        }

        if ($route === 'next') {
            nocache_headers();
            header('Content-Type: application/json; charset=utf-8');
            $date = isset($_GET['date']) ? sanitize_text_field(wp_unslash($_GET['date'])) : date_i18n('Y-m-d');
            $next = ACPDF_PDF::preview_next_quote_no($date);
            echo wp_json_encode(['next' => $next]);
            exit;
        }

        if ($route === 'pdf') {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                status_header(405);
                exit;
            }
            if (!isset($_POST['acpdf_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['acpdf_nonce'])), 'acpdf_make_pdf')) {
                status_header(403);
                exit('Forbidden');
            }
            $payload = ACPDF_PDF::sanitize_payload($_POST);
            ACPDF_PDF::output_pdf($payload);
            exit;
        }
    }
}

new Agrocampo_Cotizador_PDF();

register_activation_hook(__FILE__, function(){
    // Flush rules
    (new Agrocampo_Cotizador_PDF())->register_routes();
    flush_rewrite_rules();
});
register_deactivation_hook(__FILE__, function(){
    flush_rewrite_rules();
});
