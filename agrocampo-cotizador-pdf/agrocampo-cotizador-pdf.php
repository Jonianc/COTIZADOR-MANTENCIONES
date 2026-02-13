<?php
/**
 * Plugin Name: Agrocampo – Cotizador PDF
 * Description: Cotizador frontend (sin theme) que genera cotizaciones en PDF.
 * Version: 1.3.23
 * Author: Rocket Solutions
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) { exit; }

define('ACPDF_VER', '1.3.23');
define('ACPDF_SLUG', 'agrocampo-cotizador-pdf');
define('ACPDF_DIR', plugin_dir_path(__FILE__));
define('ACPDF_URL', plugin_dir_url(__FILE__));

require_once ACPDF_DIR . 'includes/fpdf/fpdf.php';
require_once ACPDF_DIR . 'includes/class-acpdf-settings.php';
require_once ACPDF_DIR . 'includes/class-acpdf-pdf.php';
require_once ACPDF_DIR . 'includes/class-acpdf-view.php';
require_once ACPDF_DIR . 'includes/class-acpdf-templates.php';
require_once ACPDF_DIR . 'includes/class-acpdf-routes.php';

class Agrocampo_Cotizador_PDF {

    public function __construct() {
        add_action('init', ['ACPDF_Routes', 'register_routes']);
        add_action('template_redirect', ['ACPDF_Routes', 'handle_routes']);
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_init', [$this, 'admin_init']);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
    }


    

    

    public function admin_menu() {
        $cap = 'manage_options';

        // Menú principal (lateral)
        add_menu_page(
            'Cotizador PDF',
            'Cotizador PDF',
            $cap,
            'acpdf-settings',
            ['ACPDF_Settings', 'render_page'],
            'dashicons-media-document',
            58
        );

        // Submenú Ajustes (misma pantalla)
        add_submenu_page(
            'acpdf-settings',
            'Ajustes',
            'Ajustes',
            $cap,
            'acpdf-settings',
            ['ACPDF_Settings', 'render_page']
        );

        // Submenú Gestor (pantalla admin)
        add_submenu_page(
            'acpdf-settings',
            'Gestor de Cotizaciones',
            'Gestor de Cotizaciones',
            $cap,
            'acpdf-quotes',
            [$this, 'render_quotes_page']
        );
    }

    public function admin_init() {
        ACPDF_Settings::register();
    }

    public function admin_assets($hook) {
        // Solo para páginas del plugin (logo upload usa Media Library)
        if (strpos($hook, 'acpdf') === false) {
            return;
        }
        wp_enqueue_media();
    }

    public function render_quotes_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Redirect to frontend gestor instead
        $gestor_url = home_url('/agrocampo-cotizador/gestor');
        ?>
        <div class="wrap">
          <h1>Gestor de Cotizaciones</h1>
          <p>El gestor de cotizaciones está disponible en el frontend:</p>
          <p><a href="<?php echo esc_url($gestor_url); ?>" class="button button-primary" target="_blank">Abrir Gestor de Cotizaciones</a></p>
          <p class="description">El gestor frontend ofrece más funcionalidades: filtros avanzados, paginación, exportación a CSV y duplicación de cotizaciones.</p>
        </div>
        <?php
    }

    
}

/**
 * Bootstrap (avoid side-effects during activation).
 */
function acpdf_bootstrap() {
    static $instance = null;
    if ($instance === null) {
        $instance = new Agrocampo_Cotizador_PDF();
    }
    return $instance;
}
add_action('plugins_loaded', 'acpdf_bootstrap');

/**
 * Activation / Deactivation
 */
function acpdf_activate() {
    if (class_exists('ACPDF_Routes')) {
        ACPDF_Routes::add_rewrite_rules();
    }
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'acpdf_activate');

function acpdf_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'acpdf_deactivate');
