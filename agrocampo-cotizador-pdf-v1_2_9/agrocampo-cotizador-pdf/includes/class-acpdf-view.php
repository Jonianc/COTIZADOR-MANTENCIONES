<?php
if (!defined('ABSPATH')) { exit; }

class ACPDF_View {

    public static function render($template, $vars = []) {
        if (!is_array($vars)) { $vars = []; }
        extract($vars, EXTR_SKIP);

        $template_file = ACPDF_DIR . 'templates/' . $template . '.php';
        if (!file_exists($template_file)) {
            status_header(500);
            exit('Template no encontrado: ' . esc_html($template));
        }
        include $template_file;
    }
}
