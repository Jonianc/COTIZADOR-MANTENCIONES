<?php
if (!defined('ABSPATH')) { exit; }

class ACPDF_Routes {

    public static function add_rewrite_rules() {
        add_rewrite_rule('^agrocampo-cotizador/?$', 'index.php?acpdf_route=form', 'top');
        add_rewrite_rule('^agrocampo-cotizador/pdf/?$', 'index.php?acpdf_route=pdf', 'top');
        add_rewrite_rule('^agrocampo-cotizador/preview/?$', 'index.php?acpdf_route=preview', 'top');
        add_rewrite_rule('^agrocampo-cotizador/gestor/?$', 'index.php?acpdf_route=gestor', 'top');
        add_rewrite_rule('^agrocampo-cotizador/next/?$', 'index.php?acpdf_route=next', 'top');
    }

    public static function register_routes() {
        self::add_rewrite_rules();
        add_filter('query_vars', [__CLASS__, 'query_vars']);
    }

    public static function query_vars($vars) {
        $vars[] = 'acpdf_route';
        return $vars;
    }

    public static function handle_routes() {
        $route = get_query_var('acpdf_route');
        if (!$route) { return; }

        if ($route === 'form') {
            self::render_form();
            exit;
        }

        if ($route === 'gestor') {
            self::render_gestor();
            exit;
        }

        if ($route === 'next') {
            self::handle_next();
            exit;
        }

        if ($route === 'pdf') {
            self::handle_pdf();
            exit;
        }

        if ($route === 'preview') {
            self::handle_preview();
            exit;
        }

        status_header(404);
        exit;
    }

    protected static function render_form() {
        $settings = ACPDF_Settings::get();

        $logo_url = ACPDF_URL . 'assets/agrocampo-logo.png';
        if (!empty($settings['logo_id'])) {
            $custom_logo = wp_get_attachment_url(absint($settings['logo_id']));
            if ($custom_logo) { $logo_url = $custom_logo; }
        }

        $prefill = null;

        // Check for duplicate from transient
        if (isset($_GET['duplicate']) && $_GET['duplicate'] === '1' && current_user_can('manage_options')) {
            $dup_key = 'acpdf_duplicate_' . get_current_user_id();
            $dup_data = get_transient($dup_key);
            if ($dup_data && is_array($dup_data)) {
                $prefill = $dup_data;
                delete_transient($dup_key);
            }
        }

        // Check for prefill from log
        if (!$prefill && isset($_GET['prefill'])) {
            $index = absint($_GET['prefill']);
            $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
            $log = ACPDF_PDF::get_quote_log();
            if (isset($log[$index]) && wp_verify_nonce($nonce, 'acpdf_prefill_' . $index) && current_user_can('manage_options')) {
                $prefill = $log[$index]['payload'] ?? null;
            }
        }

        $title = 'Agrocampo – Cotizador PDF';
        $menu_links = [
            ['label' => 'Formulario', 'url' => home_url('/agrocampo-cotizador')],
            ['label' => 'Gestor', 'url' => home_url('/agrocampo-cotizador/gestor')],
            ['label' => 'Ajustes', 'url' => admin_url('admin.php?page=acpdf-settings')],
        ];

        ACPDF_View::render('form', compact('settings', 'logo_url', 'prefill', 'title', 'menu_links'));
    }

    protected static function render_gestor() {
        if (!current_user_can('manage_options')) {
            status_header(403);
            exit('Forbidden');
        }

        $log = ACPDF_PDF::get_quote_log();

        // Handle deletion
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acpdf_action']) && $_POST['acpdf_action'] === 'delete') {
            self::handle_delete($log);
            return;
        }

        // Handle duplication
        if (isset($_GET['duplicate'])) {
            self::handle_duplicate($log);
            return;
        }

        // Handle CSV export
        if (isset($_GET['export']) && $_GET['export'] === 'csv') {
            self::handle_export($log);
            return;
        }

        // Ver PDF desde log
        if (isset($_GET['view'])) {
            $index = absint($_GET['view']);
            if (!isset($log[$index])) {
                status_header(404);
                exit('Cotización no encontrada.');
            }
            $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
            if (!wp_verify_nonce($nonce, 'acpdf_view_quote_' . $index)) {
                status_header(403);
                exit('Acceso no autorizado.');
            }
            ACPDF_PDF::output_pdf_from_log($log[$index]);
            exit;
        }

        // Filters
        $hours_filter = isset($_GET['maint_hours']) ? sanitize_text_field(wp_unslash($_GET['maint_hours'])) : '';
        $hours_filter = preg_replace('/[^0-9]/', '', $hours_filter);

        $client_filter = isset($_GET['client']) ? sanitize_text_field(wp_unslash($_GET['client'])) : '';
        $client_filter = trim(substr($client_filter, 0, 120));

        $model_filter = isset($_GET['model']) ? sanitize_text_field(wp_unslash($_GET['model'])) : '';
        $model_filter = trim(substr($model_filter, 0, 120));

        $seller_filter = isset($_GET['seller']) ? sanitize_text_field(wp_unslash($_GET['seller'])) : '';
        $seller_filter = trim(substr($seller_filter, 0, 120));

        $quote_filter = isset($_GET['quote']) ? sanitize_text_field(wp_unslash($_GET['quote'])) : '';
        $quote_filter = trim(substr($quote_filter, 0, 50));

        $date_from = isset($_GET['date_from']) ? sanitize_text_field(wp_unslash($_GET['date_from'])) : '';
        if ($date_from && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
            $date_from = '';
        }

        $date_to = isset($_GET['date_to']) ? sanitize_text_field(wp_unslash($_GET['date_to'])) : '';
        if ($date_to && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
            $date_to = '';
        }

        // Pagination
        $per_page = isset($_GET['per_page']) ? absint($_GET['per_page']) : 25;
        $per_page = in_array($per_page, [25, 50, 100]) ? $per_page : 25;

        $current_page = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
        if ($current_page < 1) $current_page = 1;

        // Status messages
        $deleted = isset($_GET['deleted']) ? sanitize_text_field(wp_unslash($_GET['deleted'])) : '';
        $duplicated = isset($_GET['duplicated']) ? sanitize_text_field(wp_unslash($_GET['duplicated'])) : '';

        $hours_options = [100, 400, 500, 800, 1000, 1200, 1500];
        sort($hours_options);
        $seller_options = [];
        foreach ($log as $entry) {
            $seller_name = trim((string)($entry['payload']['seller']['name'] ?? ''));
            if ($seller_name === '') {
                $seller_options['__none'] = 'Sin vendedor';
                continue;
            }
            $seller_options[$seller_name] = $seller_name;
        }
        ksort($seller_options, SORT_NATURAL | SORT_FLAG_CASE);

        $title = 'Gestor de Cotizaciones';
        $menu_links = [
            ['label' => 'Formulario', 'url' => home_url('/agrocampo-cotizador')],
            ['label' => 'Gestor', 'url' => home_url('/agrocampo-cotizador/gestor')],
            ['label' => 'Ajustes', 'url' => admin_url('admin.php?page=acpdf-settings')],
        ];

        ACPDF_View::render('gestor', compact(
            'hours_filter', 'client_filter', 'model_filter', 'seller_filter', 'quote_filter',
            'date_from', 'date_to', 'deleted', 'duplicated',
            'log', 'hours_options', 'seller_options', 'title', 'menu_links',
            'per_page', 'current_page'
        ));
    }

    protected static function handle_delete($log) {
        $idx = isset($_POST['quote_index']) ? absint($_POST['quote_index']) : -1;
        $nonce = isset($_POST['acpdf_nonce']) ? sanitize_text_field(wp_unslash($_POST['acpdf_nonce'])) : '';
        $ok = ($idx >= 0) && wp_verify_nonce($nonce, 'acpdf_delete_quote_' . $idx);
        
        if (!$ok) {
            status_header(403);
            exit('Acceso no autorizado.');
        }

        $deleted = ACPDF_PDF::delete_quote_log_entry($idx) ? '1' : '0';

        // Preserve filters on redirect
        $redirect_args = ['deleted' => $deleted];
        
        $filter_keys = ['maint_hours', 'client', 'model', 'seller', 'quote', 'date_from', 'date_to', 'per_page', 'paged'];
        foreach ($filter_keys as $key) {
            $value = isset($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : '';
            if ($value !== '') {
                $redirect_args[$key] = $value;
            }
        }

        wp_safe_redirect(add_query_arg($redirect_args, home_url('/agrocampo-cotizador/gestor')));
        exit;
    }

    protected static function handle_duplicate($log) {
        $index = absint($_GET['duplicate']);
        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
        
        if (!isset($log[$index]) || !wp_verify_nonce($nonce, 'acpdf_duplicate_' . $index)) {
            wp_safe_redirect(add_query_arg(['duplicated' => '0'], home_url('/agrocampo-cotizador/gestor')));
            exit;
        }

        $entry = $log[$index];
        $payload = $entry['payload'] ?? null;

        if (!is_array($payload)) {
            wp_safe_redirect(add_query_arg(['duplicated' => '0'], home_url('/agrocampo-cotizador/gestor')));
            exit;
        }

        // Store duplicate data in transient for the form to pick up
        $dup_key = 'acpdf_duplicate_' . get_current_user_id();
        set_transient($dup_key, $payload, 300); // 5 minutes

        // Redirect to form
        wp_safe_redirect(home_url('/agrocampo-cotizador?duplicate=1'));
        exit;
    }

    protected static function handle_export($log) {
        // Apply same filters as gestor view
        $hours_filter = isset($_GET['maint_hours']) ? preg_replace('/[^0-9]/', '', sanitize_text_field(wp_unslash($_GET['maint_hours']))) : '';
        $client_filter = isset($_GET['client']) ? trim(sanitize_text_field(wp_unslash($_GET['client']))) : '';
        $model_filter = isset($_GET['model']) ? trim(sanitize_text_field(wp_unslash($_GET['model']))) : '';
        $seller_filter = isset($_GET['seller']) ? trim(sanitize_text_field(wp_unslash($_GET['seller']))) : '';
        $quote_filter = isset($_GET['quote']) ? trim(sanitize_text_field(wp_unslash($_GET['quote']))) : '';
        $date_from = isset($_GET['date_from']) ? sanitize_text_field(wp_unslash($_GET['date_from'])) : '';
        $date_to = isset($_GET['date_to']) ? sanitize_text_field(wp_unslash($_GET['date_to'])) : '';

        $filtered = [];
        foreach ($log as $entry) {
            // Hours filter
            $hours = isset($entry['maint_hours']) ? (string)$entry['maint_hours'] : '';
            if ($hours_filter !== '' && $hours_filter !== $hours) continue;

            // Client filter
            $client = isset($entry['client']) ? (string)$entry['client'] : '';
            if ($client_filter !== '' && stripos($client, $client_filter) === false) continue;

            // Model filter
            $model = isset($entry['model']) ? (string)$entry['model'] : '';
            if ($model_filter !== '' && stripos($model, $model_filter) === false) continue;

            $seller_name = trim((string)($entry['payload']['seller']['name'] ?? ''));
            if ($seller_filter !== '') {
                if ($seller_filter === '__none' && $seller_name !== '') continue;
                if ($seller_filter !== '__none' && strcasecmp($seller_name, $seller_filter) !== 0) continue;
            }

            // Quote filter
            $quote_no = isset($entry['quote_no']) ? (string)$entry['quote_no'] : '';
            if ($quote_filter !== '' && stripos($quote_no, $quote_filter) === false) continue;

            // Date range
            $entry_date = isset($entry['date_iso']) ? $entry['date_iso'] : '';
            if ($date_from !== '' && $entry_date < $date_from) continue;
            if ($date_to !== '' && $entry_date > $date_to) continue;

            $filtered[] = $entry;
        }

        // Generate CSV
        $filename = 'cotizaciones_' . date('Y-m-d_His') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        
        // BOM for Excel UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // Header row
        fputcsv($output, [
            'Fecha',
            'N° Cotización',
            'N° Interno',
            'Serie',
            'Modelo',
            'Detalle cotización',
            'Cliente',
            'RUT',
            'Teléfono',
            'Email',
            'Ubicación',
            'Horas',
            'Repuestos',
            'Neto',
            'IVA',
            'Total',
            'Observaciones'
        ], ';');

        // Data rows
        foreach (array_reverse($filtered) as $entry) {
            $payload = $entry['payload'] ?? [];
            fputcsv($output, [
                $entry['date_iso'] ?? '',
                $entry['quote_no'] ?? '',
                $payload['internal_no'] ?? '',
                $payload['serial_no'] ?? '',
                $entry['model'] ?? '',
                ($payload['quote_type'] ?? '') === 'other' || ($payload['quote_type'] ?? '') === 'insumos' ? ($payload['quote_detail'] ?? '') : '',
                $entry['client'] ?? '',
                $payload['rut'] ?? '',
                $payload['phone'] ?? '',
                $payload['email'] ?? '',
                $payload['location'] ?? '',
                $entry['maint_hours'] ?? '',
                $entry['parts_type'] ?? '',
                round(floatval($entry['neto'] ?? 0)),
                round(floatval($entry['iva'] ?? 0)),
                round(floatval($entry['total'] ?? 0)),
                $payload['observations'] ?? ''
            ], ';');
        }

        fclose($output);
        exit;
    }

    protected static function handle_next() {
        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');

        $date = isset($_GET['date']) ? sanitize_text_field(wp_unslash($_GET['date'])) : date_i18n('Y-m-d');
        $next = ACPDF_PDF::preview_next_quote_no($date);

        echo wp_json_encode(['next' => $next]);
    }

    protected static function should_reject_missing_other_detail(array $payload, array $request) {
        if (($payload['quote_type'] ?? '') !== 'other') {
            return false;
        }

        if (trim((string)($payload['quote_detail'] ?? '')) !== '') {
            return false;
        }

        // Backward compatibility: stale clients may still submit "insumos" without quote_detail.
        $raw_quote_type = sanitize_key(sanitize_text_field(wp_unslash($request['quote_type'] ?? '')));
        if ($raw_quote_type === 'insumos') {
            return false;
        }

        return true;
    }

    protected static function handle_pdf() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            status_header(405);
            exit;
        }
        if (!isset($_POST['acpdf_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['acpdf_nonce'])), 'acpdf_make_pdf')) {
            status_header(403);
            exit('Forbidden');
        }

        $payload = ACPDF_PDF::sanitize_payload($_POST);
        if (self::should_reject_missing_other_detail($payload, $_POST)) {
            status_header(400);
            exit('Detalle de cotización es obligatorio para tipo Otro.');
        }
        ACPDF_PDF::output_pdf($payload);
    }

    protected static function handle_preview() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            status_header(405);
            exit;
        }
        if (!isset($_POST['acpdf_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['acpdf_nonce'])), 'acpdf_make_pdf')) {
            status_header(403);
            exit('Forbidden');
        }

        $payload = ACPDF_PDF::sanitize_payload($_POST);
        if (self::should_reject_missing_other_detail($payload, $_POST)) {
            status_header(400);
            exit('Detalle de cotización es obligatorio para tipo Otro.');
        }
        ACPDF_PDF::output_preview($payload);
    }
}
