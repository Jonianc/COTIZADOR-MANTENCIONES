<?php
if (!defined('ABSPATH')) { exit; }

class ACPDF_Settings {

    public static function defaults() {
        return [
            'default_city' => 'Talca',
            'default_iva_percent' => 19,
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
        <?php
    }
}
