<?php
if (!defined('ABSPATH')) { exit; }

class ACPDF_Settings {

    public static function defaults() {
        return [
            'default_iva_percent' => 19,
            'logo_id' => 0,
            'logo_width_mm' => 45,

            // Legacy single-seller fields (kept for backward compatibility)
            'seller_name' => '',
            'seller_role' => 'Asesor de Servicio',
            'seller_mobile' => '',
            'seller_phone' => '',
            'seller_email' => '',

            // Multi-seller manager
            'sellers' => [],
            'default_seller_id' => '',

            'company_name' => 'AGROCAMPO NEGOCIOS AGRICOLA LIMITADA',
            'company_rut' => '76.155.060-8',
        ];
    }

    public static function key() {
        return 'acpdf_settings';
    }

    public static function get() {
        $opts = get_option(self::key(), []);
        $d = self::defaults();
        if (!is_array($opts)) $opts = [];
        return array_merge($d, $opts);
    }

    public static function register() {
        register_setting('acpdf_settings_group', self::key(), [
            'type' => 'array',
            'sanitize_callback' => [__CLASS__, 'sanitize'],
            'default' => self::defaults(),
        ]);
    }

    public static function sanitize($input) {
        $d = self::defaults();
        $out = [];

        // Multi-seller
        $raw_sellers = isset($input['sellers']) && is_array($input['sellers']) ? $input['sellers'] : [];
        $sellers = [];
        foreach ($raw_sellers as $row) {
            if (!is_array($row)) continue;
            $id = isset($row['id']) ? sanitize_key(wp_unslash($row['id'])) : '';
            if ($id === '') {
                $id = function_exists('wp_generate_password')
                    ? sanitize_key('v' . wp_generate_password(8, false, false))
                    : sanitize_key('v' . substr(md5(uniqid('', true)), 0, 8));
            }

            $name = isset($row['name']) ? sanitize_text_field(wp_unslash($row['name'])) : '';
            $role = isset($row['role']) ? sanitize_text_field(wp_unslash($row['role'])) : '';
            $mobile = isset($row['mobile']) ? sanitize_text_field(wp_unslash($row['mobile'])) : '';
            $phone = isset($row['phone']) ? sanitize_text_field(wp_unslash($row['phone'])) : '';
            $email = isset($row['email']) ? sanitize_email(wp_unslash($row['email'])) : '';

            if ($name === '') continue;
            $sellers[] = [
                'id' => $id,
                'name' => $name,
                'role' => $role,
                'mobile' => $mobile,
                'phone' => $phone,
                'email' => $email,
            ];
        }

        $default_seller_id = isset($input['default_seller_id']) ? sanitize_key(wp_unslash($input['default_seller_id'])) : '';

        foreach ($d as $k => $v) {
            if ($k === 'sellers') {
                $out[$k] = $sellers;
                continue;
            }
            if ($k === 'default_seller_id') {
                $out[$k] = $default_seller_id;
                continue;
            }
            if ($k === 'default_iva_percent') {
                $out[$k] = isset($input[$k]) ? floatval($input[$k]) : floatval($v);
                continue;
            }
            if ($k === 'logo_id') {
                $out[$k] = isset($input[$k]) ? absint($input[$k]) : 0;
                continue;
            }
            if ($k === 'logo_width_mm') {
                $width = isset($input[$k]) ? floatval($input[$k]) : floatval($v);
                if ($width <= 0) {
                    $width = floatval($v);
                }
                $out[$k] = min(max($width, 5), 120);
                continue;
            }
            $out[$k] = isset($input[$k]) ? sanitize_text_field(wp_unslash($input[$k])) : $v;
        }

        // If default seller isn't valid, pick first
        if (!empty($out['sellers'])) {
            $ids = array_map(function($r){ return $r['id'] ?? ''; }, $out['sellers']);
            if ($out['default_seller_id'] === '' || !in_array($out['default_seller_id'], $ids, true)) {
                $out['default_seller_id'] = $ids[0] ?? '';
            }
        } else {
            $out['default_seller_id'] = '';
        }

        return $out;
    }

    /**
     * Sellers helper: always return a usable list.
     * - If multi-seller list is empty, build a single seller from legacy fields.
     */
    public static function get_sellers() {
        $s = self::get();
        $sellers = isset($s['sellers']) && is_array($s['sellers']) ? $s['sellers'] : [];

        // Normalize rows
        $normalized = [];
        foreach ($sellers as $row) {
            if (!is_array($row)) continue;
            $id = isset($row['id']) ? sanitize_key($row['id']) : '';
            $name = isset($row['name']) ? sanitize_text_field($row['name']) : '';
            if ($id === '' || $name === '') continue;
            $normalized[] = [
                'id' => $id,
                'name' => $name,
                'role' => isset($row['role']) ? sanitize_text_field($row['role']) : '',
                'mobile' => isset($row['mobile']) ? sanitize_text_field($row['mobile']) : '',
                'phone' => isset($row['phone']) ? sanitize_text_field($row['phone']) : '',
                'email' => isset($row['email']) ? sanitize_email($row['email']) : '',
            ];
        }

        if (!empty($normalized)) {
            return $normalized;
        }

        // Legacy fallback
        $legacy_name = trim((string)($s['seller_name'] ?? ''));
        if ($legacy_name !== '') {
            return [[
                'id' => 'legacy',
                'name' => $legacy_name,
                'role' => (string)($s['seller_role'] ?? ''),
                'mobile' => (string)($s['seller_mobile'] ?? ''),
                'phone' => (string)($s['seller_phone'] ?? ''),
                'email' => (string)($s['seller_email'] ?? ''),
            ]];
        }

        return [];
    }

    public static function get_default_seller_id() {
        $s = self::get();
        $sellers = self::get_sellers();
        $default = sanitize_key($s['default_seller_id'] ?? '');
        if ($default !== '') {
            foreach ($sellers as $r) {
                if (($r['id'] ?? '') === $default) return $default;
            }
        }
        return $sellers[0]['id'] ?? '';
    }

    public static function get_seller_by_id($seller_id) {
        $seller_id = sanitize_key($seller_id);
        if ($seller_id === '') return null;
        $sellers = self::get_sellers();
        foreach ($sellers as $r) {
            if (($r['id'] ?? '') === $seller_id) {
                return $r;
            }
        }
        return null;
    }

    public static function render_page() {
        if (!current_user_can('manage_options')) return;
        $s = self::get();
        $sellers = self::get_sellers();
        $default_seller_id = self::get_default_seller_id();
        ?>
        <div class="wrap">
          <h1>Cotizador PDF</h1>
          <form method="post" action="options.php">
            <?php settings_fields('acpdf_settings_group'); ?>
            <table class="form-table" role="presentation">
              <tr>
                <th scope="row"><label>IVA por defecto (%)</label></th>
                <td><input name="<?php echo esc_attr(self::key()); ?>[default_iva_percent]" value="<?php echo esc_attr($s['default_iva_percent']); ?>" class="small-text"></td>
              </tr>
              <tr>
                <th scope="row"><label>Logo PDF</label></th>
                <td>
                  <?php $logo_url = $s['logo_id'] ? wp_get_attachment_url(absint($s['logo_id'])) : ''; ?>
                  <input type="hidden" id="acpdf-logo-id" name="<?php echo esc_attr(self::key()); ?>[logo_id]" value="<?php echo esc_attr($s['logo_id']); ?>">
                  <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                    <img id="acpdf-logo-preview" src="<?php echo esc_url($logo_url); ?>" alt="" style="max-width:180px;max-height:80px;<?php echo $logo_url ? '' : 'display:none;'; ?>">
                    <div>
                      <button type="button" class="button" id="acpdf-logo-select">Seleccionar logo</button>
                      <button type="button" class="button" id="acpdf-logo-remove" <?php echo $logo_url ? '' : 'style="display:none;"'; ?>>Quitar</button>
                      <p class="description">Sube o selecciona un logo desde la librería.</p>
                    </div>
                  </div>
                </td>
              </tr>
              <tr>
                <th scope="row"><label>Ancho logo en PDF (mm)</label></th>
                <td><input name="<?php echo esc_attr(self::key()); ?>[logo_width_mm]" value="<?php echo esc_attr($s['logo_width_mm']); ?>" class="small-text" type="number" step="0.1" min="5" max="120"></td>
              </tr>
              <tr><th colspan="2"><h2>Vendedores (para seleccionar en el formulario)</h2></th></tr>
              <tr>
                <th scope="row"><label>Lista de vendedores</label></th>
                <td>
                  <p class="description">Estos vendedores aparecerán en el formulario del cotizador. Marca uno como <strong>por defecto</strong>.</p>

                  <style>
                    /* Sellers UI: avoid clipped columns on smaller admin widths */
                    .acpdf-table-wrap{max-width:100%;overflow-x:auto;border:1px solid #ccd0d4;border-radius:6px;background:#fff;}
                    .acpdf-sellers-table{min-width:980px;margin:0;border:0;}
                    .acpdf-sellers-table th,.acpdf-sellers-table td{vertical-align:top;}
                    .acpdf-sellers-table input.regular-text{width:100%;max-width:100%;}
                    .acpdf-sellers-table .column-tight{width:140px;}
                    @media (max-width: 782px){
                      .acpdf-sellers-table{min-width:920px;}
                    }
                  </style>

                  <div class="acpdf-table-wrap">
                  <table class="widefat striped acpdf-sellers-table">
                    <thead>
                      <tr>
                        <th style="width:70px;">Default</th>
                        <th>Nombre</th>
                        <th class="column-tight">Cargo</th>
                        <th class="column-tight">Celular</th>
                        <th class="column-tight">Teléfono</th>
                        <th class="column-tight">Email</th>
                        <th style="width:80px;"></th>
                      </tr>
                    </thead>
                    <tbody id="acpdf-sellers-body">
                      <?php if (!empty($sellers)) : ?>
                        <?php foreach ($sellers as $i => $row) :
                          $sid = sanitize_key($row['id'] ?? '');
                          $sname = $row['name'] ?? '';
                        ?>
                          <tr class="acpdf-seller-row">
                            <td style="text-align:center;">
                              <label>
                                <input type="radio" name="<?php echo esc_attr(self::key()); ?>[default_seller_id]" value="<?php echo esc_attr($sid); ?>" <?php checked($default_seller_id, $sid); ?> />
                              </label>
                            </td>
                            <td>
                              <input type="hidden" name="<?php echo esc_attr(self::key()); ?>[sellers][<?php echo esc_attr($i); ?>][id]" value="<?php echo esc_attr($sid); ?>" />
                              <input name="<?php echo esc_attr(self::key()); ?>[sellers][<?php echo esc_attr($i); ?>][name]" value="<?php echo esc_attr($sname); ?>" class="regular-text" placeholder="Nombre" />
                            </td>
                            <td><input name="<?php echo esc_attr(self::key()); ?>[sellers][<?php echo esc_attr($i); ?>][role]" value="<?php echo esc_attr($row['role'] ?? ''); ?>" class="regular-text" placeholder="Cargo" /></td>
                            <td><input name="<?php echo esc_attr(self::key()); ?>[sellers][<?php echo esc_attr($i); ?>][mobile]" value="<?php echo esc_attr($row['mobile'] ?? ''); ?>" class="regular-text" placeholder="+56 9 ..." /></td>
                            <td><input name="<?php echo esc_attr(self::key()); ?>[sellers][<?php echo esc_attr($i); ?>][phone]" value="<?php echo esc_attr($row['phone'] ?? ''); ?>" class="regular-text" placeholder="Fono" /></td>
                            <td><input name="<?php echo esc_attr(self::key()); ?>[sellers][<?php echo esc_attr($i); ?>][email]" value="<?php echo esc_attr($row['email'] ?? ''); ?>" class="regular-text" placeholder="correo@..." /></td>
                            <td style="text-align:right;">
                              <button type="button" class="button acpdf-seller-remove">Quitar</button>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      <?php else : ?>
                        <tr><td colspan="7"><em>No hay vendedores configurados. Agrega uno abajo.</em></td></tr>
                      <?php endif; ?>
                    </tbody>
                  </table>
                  </div>

                  <p style="margin-top:10px;">
                    <button type="button" class="button" id="acpdf-seller-add">+ Agregar vendedor</button>
                  </p>

                  <details style="margin-top:12px;">
                    <summary><strong>Compatibilidad: vendedor único (antiguo)</strong></summary>
                    <p class="description">Se mantiene por compatibilidad. Si la lista de vendedores está vacía, el sistema usará estos datos.</p>
                    <table class="form-table" role="presentation">
                      <tr><th scope="row"><label>Nombre</label></th><td><input name="<?php echo esc_attr(self::key()); ?>[seller_name]" value="<?php echo esc_attr($s['seller_name']); ?>" class="regular-text"></td></tr>
                      <tr><th scope="row"><label>Cargo</label></th><td><input name="<?php echo esc_attr(self::key()); ?>[seller_role]" value="<?php echo esc_attr($s['seller_role']); ?>" class="regular-text"></td></tr>
                      <tr><th scope="row"><label>Celular</label></th><td><input name="<?php echo esc_attr(self::key()); ?>[seller_mobile]" value="<?php echo esc_attr($s['seller_mobile']); ?>" class="regular-text"></td></tr>
                      <tr><th scope="row"><label>Teléfono</label></th><td><input name="<?php echo esc_attr(self::key()); ?>[seller_phone]" value="<?php echo esc_attr($s['seller_phone']); ?>" class="regular-text"></td></tr>
                      <tr><th scope="row"><label>Email</label></th><td><input name="<?php echo esc_attr(self::key()); ?>[seller_email]" value="<?php echo esc_attr($s['seller_email']); ?>" class="regular-text"></td></tr>
                    </table>
                  </details>
                </td>
              </tr>
              <tr><th colspan="2"><h2>Empresa</h2></th></tr>
              <tr><th scope="row"><label>Razón Social</label></th><td><input name="<?php echo esc_attr(self::key()); ?>[company_name]" value="<?php echo esc_attr($s['company_name']); ?>" class="regular-text"></td></tr>
              <tr><th scope="row"><label>RUT</label></th><td><input name="<?php echo esc_attr(self::key()); ?>[company_rut]" value="<?php echo esc_attr($s['company_rut']); ?>" class="regular-text"></td></tr>
            </table>
            <?php submit_button(); ?>
          </form>
          <hr>
          <p><strong>Frontend:</strong> <code><?php echo esc_html(home_url('/agrocampo-cotizador')); ?></code></p>
          <p><strong>Gestor frontend:</strong> <code><?php echo esc_html(home_url('/agrocampo-cotizador/gestor')); ?></code></p>
          <p><strong>Menú rápido:</strong>
            <a href="<?php echo esc_url(home_url('/agrocampo-cotizador')); ?>">Formulario</a> |
            <a href="<?php echo esc_url(home_url('/agrocampo-cotizador/gestor')); ?>">Gestor</a>
          </p>
        </div>
        <script>
          (function(){
            if (!window.wp || !wp.media) return;
            const selectBtn = document.getElementById('acpdf-logo-select');
            const removeBtn = document.getElementById('acpdf-logo-remove');
            const preview = document.getElementById('acpdf-logo-preview');
            const input = document.getElementById('acpdf-logo-id');
            if (!selectBtn || !removeBtn || !preview || !input) return;

            let frame;
            selectBtn.addEventListener('click', function(){
              if (frame) { frame.open(); return; }
              frame = wp.media({
                title: 'Seleccionar logo',
                button: { text: 'Usar este logo' },
                library: { type: 'image' },
                multiple: false
              });
              frame.on('select', function(){
                const attachment = frame.state().get('selection').first().toJSON();
                input.value = attachment.id || '';
                preview.src = attachment.url || '';
                preview.style.display = attachment.url ? 'block' : 'none';
                removeBtn.style.display = attachment.url ? 'inline-block' : 'none';
              });
              frame.open();
            });

            removeBtn.addEventListener('click', function(){
              input.value = '';
              preview.src = '';
              preview.style.display = 'none';
              removeBtn.style.display = 'none';
            });
          })();
        </script>

        <script>
          (function(){
            // Sellers manager (simple JS)
            const body = document.getElementById('acpdf-sellers-body');
            const addBtn = document.getElementById('acpdf-seller-add');
            if (!body || !addBtn) return;

            function newId(){
              return 'v' + Math.random().toString(36).slice(2, 10);
            }

            function reindex(){
              const rows = body.querySelectorAll('tr.acpdf-seller-row');
              rows.forEach((tr, idx) => {
                const inputs = tr.querySelectorAll('input,select,textarea');
                inputs.forEach((el) => {
                  if (!el.name) return;
                  el.name = el.name.replace(/\[sellers\]\[\d+\]/, '[sellers]['+idx+']');
                });
              });
            }

            body.addEventListener('click', function(e){
              const btn = e.target && e.target.classList && e.target.classList.contains('acpdf-seller-remove') ? e.target : null;
              if (!btn) return;
              const row = btn.closest('tr');
              if (row) row.remove();
              reindex();
            });

            addBtn.addEventListener('click', function(){
              // If empty placeholder row exists, remove it
              const placeholder = body.querySelector('tr:not(.acpdf-seller-row)');
              if (placeholder && body.querySelectorAll('tr.acpdf-seller-row').length === 0) {
                placeholder.remove();
              }

              const idx = body.querySelectorAll('tr.acpdf-seller-row').length;
              const sid = newId();
              const key = '<?php echo esc_js(self::key()); ?>';
              const tr = document.createElement('tr');
              tr.className = 'acpdf-seller-row';
              tr.innerHTML = `
                <td style="text-align:center;">
                  <label><input type="radio" name="${key}[default_seller_id]" value="${sid}" ${idx===0 ? 'checked' : ''} /></label>
                </td>
                <td>
                  <input type="hidden" name="${key}[sellers][${idx}][id]" value="${sid}" />
                  <input name="${key}[sellers][${idx}][name]" value="" class="regular-text" placeholder="Nombre" />
                </td>
                <td><input name="${key}[sellers][${idx}][role]" value="" class="regular-text" placeholder="Cargo" /></td>
                <td><input name="${key}[sellers][${idx}][mobile]" value="" class="regular-text" placeholder="+56 9 ..." /></td>
                <td><input name="${key}[sellers][${idx}][phone]" value="" class="regular-text" placeholder="Fono" /></td>
                <td><input name="${key}[sellers][${idx}][email]" value="" class="regular-text" placeholder="correo@..." /></td>
                <td style="text-align:right;"><button type="button" class="button acpdf-seller-remove">Quitar</button></td>
              `;
              body.appendChild(tr);
              reindex();
            });
          })();
        </script>
        <?php
    }
}
