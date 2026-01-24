<?php
if (!defined('ABSPATH')) { exit; }

class ACPDF_Settings {

    public static function defaults() {
        return [
            'default_city' => 'Talca',
            'default_iva_percent' => 19,
            'logo_id' => 0,
            'logo_width_mm' => 45,
            'seller_name' => '',
            'seller_role' => 'Asesor de Servicio',
            'seller_mobile' => '',
            'seller_phone' => '',
            'seller_email' => '',
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
        foreach ($d as $k => $v) {
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
        return $out;
    }

    public static function render_page() {
        if (!current_user_can('manage_options')) return;
        $s = self::get();
        ?>
        <div class="wrap">
          <h1>Cotizador PDF</h1>
          <form method="post" action="options.php">
            <?php settings_fields('acpdf_settings_group'); ?>
            <table class="form-table" role="presentation">
              <tr>
                <th scope="row"><label>Ciudad por defecto</label></th>
                <td><input name="<?php echo esc_attr(self::key()); ?>[default_city]" value="<?php echo esc_attr($s['default_city']); ?>" class="regular-text"></td>
              </tr>
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
              <tr><th colspan="2"><h2>Datos del vendedor (pie)</h2></th></tr>
              <tr><th scope="row"><label>Nombre</label></th><td><input name="<?php echo esc_attr(self::key()); ?>[seller_name]" value="<?php echo esc_attr($s['seller_name']); ?>" class="regular-text"></td></tr>
              <tr><th scope="row"><label>Cargo</label></th><td><input name="<?php echo esc_attr(self::key()); ?>[seller_role]" value="<?php echo esc_attr($s['seller_role']); ?>" class="regular-text"></td></tr>
              <tr><th scope="row"><label>Celular</label></th><td><input name="<?php echo esc_attr(self::key()); ?>[seller_mobile]" value="<?php echo esc_attr($s['seller_mobile']); ?>" class="regular-text"></td></tr>
              <tr><th scope="row"><label>Teléfono</label></th><td><input name="<?php echo esc_attr(self::key()); ?>[seller_phone]" value="<?php echo esc_attr($s['seller_phone']); ?>" class="regular-text"></td></tr>
              <tr><th scope="row"><label>Email</label></th><td><input name="<?php echo esc_attr(self::key()); ?>[seller_email]" value="<?php echo esc_attr($s['seller_email']); ?>" class="regular-text"></td></tr>
              <tr><th colspan="2"><h2>Empresa</h2></th></tr>
              <tr><th scope="row"><label>Razón Social</label></th><td><input name="<?php echo esc_attr(self::key()); ?>[company_name]" value="<?php echo esc_attr($s['company_name']); ?>" class="regular-text"></td></tr>
              <tr><th scope="row"><label>RUT</label></th><td><input name="<?php echo esc_attr(self::key()); ?>[company_rut]" value="<?php echo esc_attr($s['company_rut']); ?>" class="regular-text"></td></tr>
            </table>
            <?php submit_button(); ?>
          </form>
          <hr>
          <p><strong>Frontend:</strong> <code><?php echo esc_html(home_url('/agrocampo-cotizador')); ?></code></p>
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
        <?php
    }
}
