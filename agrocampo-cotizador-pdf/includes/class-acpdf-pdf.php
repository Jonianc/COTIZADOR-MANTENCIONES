<?php
if (!defined('ABSPATH')) { exit; }

class ACPDF_PDF {

    private static function to_pdf_text($s) {
        $s = is_string($s) ? $s : strval($s);
        $s = wp_strip_all_tags($s);
        $s = html_entity_decode($s, ENT_QUOTES, 'UTF-8');
        $s = trim(str_replace(["\r"], [""], $s));
        if ($s === '') return '';
        // Normalize some common UTF-8 punctuation that breaks WinAnsi widths
        $s = str_replace([
            "\xE2\x80\x93", // –
            "\xE2\x80\x94", // —
            "\xE2\x80\x98", // ‘
            "\xE2\x80\x99", // ’
            "\xE2\x80\x9C", // “
            "\xE2\x80\x9D", // ”
            "\xC2\xA0",     // NBSP
            "\xEF\xBF\xBD", // replacement char
        ], ['-','-','\'','\'','"','"',' ','?'], $s);

        // Core fonts in FPDF are not UTF-8.
        if (function_exists('iconv')) {
            $out = @iconv('UTF-8', 'windows-1252//TRANSLIT', $s);
            if ($out !== false && $out !== null) return $out;
        }
        // utf8_decode() is deprecated since PHP 8.2
        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($s, 'ISO-8859-1', 'UTF-8');
        }
        return $s;
    }

    private static function parse_money($raw) {
        // CLP style: allow dots/commas as thousands separators, ignore decimals.
        $s = is_string($raw) ? $raw : strval($raw);
        $s = preg_replace('/[^0-9.,-]/', '', $s);
        // Remove any thousands separators
        $s = str_replace(['.', ','], '', $s);
        $n = floatval($s);
        return is_finite($n) ? $n : 0;
    }

    private static function parse_float($raw) {
        $s = is_string($raw) ? $raw : strval($raw);
        $s = preg_replace('/[^0-9.,-]/', '', $s);
        // If comma used as decimal separator
        if (strpos($s, ',') !== false && strpos($s, '.') === false) {
            $s = str_replace(',', '.', $s);
        } else {
            // remove thousands separators
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        }
        $n = floatval($s);
        return is_finite($n) ? $n : 0;
    }

    private static function date_long_es($date_iso) {
        $ts = strtotime($date_iso);
        if (!$ts) $ts = current_time('timestamp');
        $d = date_i18n('j', $ts);
        $y = date_i18n('Y', $ts);
        $m = intval(date_i18n('n', $ts));
        $months = [
            1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',
            7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'
        ];
        $mm = $months[$m] ?? date_i18n('F', $ts);
return $d.' de '.$mm.' del '.$y;
    }

    private static function money_clp($n) {
        $n = round(floatval($n));
        // format with dot thousands
        return '$' . number_format($n, 0, ',', '.');
    }

    private static function ddmmyy_from_iso($iso) {
        $ts = strtotime($iso);
        if (!$ts) $ts = current_time('timestamp');
        return date_i18n('dmy', $ts);
    }

    public static function preview_next_quote_no($date_iso) {
        $key = 'acpdf_quote_counter_' . self::ddmmyy_from_iso($date_iso);
        $cur = intval(get_option($key, 0));
        return self::ddmmyy_from_iso($date_iso) . '-' . ($cur + 1);
    }

    private static function reserve_quote_no($date_iso) {
        $key = 'acpdf_quote_counter_' . self::ddmmyy_from_iso($date_iso);
        $cur = intval(get_option($key, 0));
        $cur++;
        update_option($key, $cur, false);
        return self::ddmmyy_from_iso($date_iso) . '-' . $cur;
    }

    public static function sanitize_payload($post) {
        $settings = ACPDF_Settings::get();

        $get = function($key, $default='') use ($post) {
            if (!isset($post[$key])) return $default;
            $v = $post[$key];
            if (is_array($v)) return $default;
            return sanitize_text_field(wp_unslash($v));
        };

        $date_iso = $get('date_iso', date_i18n('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_iso)) {
            $date_iso = date_i18n('Y-m-d');
        }

        $maint_hours = preg_replace('/[^0-9]/', '', $get('maint_hours', '100'));
        if ($maint_hours === '') $maint_hours = '100';

        $internal_no = preg_replace('/[^0-9]/', '', $get('internal_no', ''));
        $serial_no = preg_replace('/[^a-zA-Z0-9-]/', '', $get('serial_no', ''));


        // Seller selection (multi-seller). Fallback to legacy settings.
        $seller_id = $get('seller_id', '');
        $seller_row = null;
        if (class_exists('ACPDF_Settings')) {
            $seller_row = ACPDF_Settings::get_seller_by_id($seller_id);
            if (!$seller_row) {
                $def_id = ACPDF_Settings::get_default_seller_id();
                $seller_row = ACPDF_Settings::get_seller_by_id($def_id);
                if ($seller_row) {
                    $seller_id = $def_id;
                }
            }
        }
        if (!$seller_row) {
            $seller_row = [
                'id' => 'legacy',
                'name' => $settings['seller_name'],
                'role' => $settings['seller_role'],
                'mobile' => $settings['seller_mobile'],
                'phone' => $settings['seller_phone'],
                'email' => $settings['seller_email'],
            ];
            $seller_id = ($settings['seller_name'] ?? '') !== '' ? 'legacy' : '';
        }

        $payload = [
            'date_iso' => $date_iso,
            'internal_no' => $internal_no,
            'serial_no' => $serial_no,
            'rut' => $get('rut', ''),
            'client' => $get('client', ''),
            'phone' => $get('phone', ''),
            'email' => sanitize_email(wp_unslash($post['email'] ?? '')),
            'model' => $get('model', ''),
            'location' => $get('location', ''),
            'maint_hours' => $maint_hours,
            'parts_type' => ($get('parts_type', 'ORIGINALES') === 'ALTERNATIVOS') ? 'ALTERNATIVOS' : 'ORIGINALES',
            'observations' => trim(wp_kses_post(wp_unslash($post['observations'] ?? ''))),
            'iva_percent' => floatval($settings['default_iva_percent']),
            'seller_id' => sanitize_key($seller_id),
            'seller' => [
                'name' => (string)($seller_row['name'] ?? ''),
                'role' => (string)($seller_row['role'] ?? ''),
                'mobile' => (string)($seller_row['mobile'] ?? ''),
                'phone' => (string)($seller_row['phone'] ?? ''),
                'email' => (string)($seller_row['email'] ?? ''),
            ],
            'company' => [
                'name' => $settings['company_name'],
                'rut' => $settings['company_rut'],
            ],
            'items' => [],
        ];

        // Items arrays
        $ns = $post['item_n'] ?? [];
        $codes = $post['item_code'] ?? [];
        $details = $post['item_detail'] ?? [];
        $ups = $post['item_unit_price'] ?? [];
        $units = $post['item_unit'] ?? [];
        $discs = $post['item_discount'] ?? [];
        $qtys = $post['item_qty'] ?? [];

        $count = max(count($details), count($codes));
        for ($i=0; $i<$count; $i++) {
            $detail = isset($details[$i]) ? sanitize_textarea_field(wp_unslash($details[$i])) : '';
            $code = isset($codes[$i]) ? sanitize_text_field(wp_unslash($codes[$i])) : '';
            $n = isset($ns[$i]) ? sanitize_text_field(wp_unslash($ns[$i])) : strval($i+1);
            $unit = isset($units[$i]) ? sanitize_text_field(wp_unslash($units[$i])) : 'UN';

            $unit_price = isset($ups[$i]) ? sanitize_text_field(wp_unslash($ups[$i])) : '';
            $discount = isset($discs[$i]) ? sanitize_text_field(wp_unslash($discs[$i])) : '';
            $qty = isset($qtys[$i]) ? sanitize_text_field(wp_unslash($qtys[$i])) : '';

            // normalize numbers
            $unit_price_f = self::parse_money($unit_price);
            $discount_f = self::parse_float($discount);
            $qty_f = self::parse_float($qty);

            // allow rows with '-' or 0 pricing
            $payload['items'][] = [
                'n' => $n,
                'code' => $code,
                'detail' => $detail,
                'unit_price' => $unit_price_f,
                'unit' => $unit !== '' ? $unit : 'UN',
                'discount' => $discount_f,
                'qty' => $qty_f,
            ];
        }

        // For reparación, inject additional cost lines into items (neto)
        if (($payload['quote_type'] ?? '') === 'repair') {
            $extra = [];

            $lh = floatval($payload['labor_hours'] ?? 0);
            $lr = floatval($payload['labor_rate'] ?? 0);
            if ($lh > 0 && $lr > 0) {
                $extra[] = [
                    'n' => 'MO',
                    'code' => '',
                    'detail' => 'Mano de obra',
                    'unit_price' => $lr,
                    'unit' => 'HH',
                    'discount' => 0,
                    'qty' => $lh,
                ];
            }

            $tr = floatval($payload['travel_amount'] ?? 0);
            if ($tr > 0) {
                $extra[] = [
                    'n' => 'TR',
                    'code' => '',
                    'detail' => 'Traslado',
                    'unit_price' => $tr,
                    'unit' => 'UN',
                    'discount' => 0,
                    'qty' => 1,
                ];
            }

            $ex = floatval($payload['external_amount'] ?? 0);
            if ($ex > 0) {
                $extra[] = [
                    'n' => 'SE',
                    'code' => '',
                    'detail' => 'Servicios externos',
                    'unit_price' => $ex,
                    'unit' => 'UN',
                    'discount' => 0,
                    'qty' => 1,
                ];
            }

            if (!empty($extra)) {
                // Prepend extras preserving user items
                $payload['items'] = array_merge($extra, $payload['items']);
            }
        }

        return $payload;
    }

    public static function output_pdf($payload) {
        // Reserve quote number once
        $quote_no = self::reserve_quote_no($payload['date_iso']);

        // Compute totals and log once
        list($neto, $iva, $total) = self::compute_totals($payload);
        self::log_quote($payload, $quote_no, $neto, $iva, $total);

        // Build two PDFs: interno (con códigos) y cliente (sin códigos)
        $bytes_internal = self::build_pdf_bytes($payload, $quote_no, true);
        $bytes_client   = self::build_pdf_bytes($payload, $quote_no, false);

        // Package as ZIP (single download containing both PDFs)
        if (!class_exists('ZipArchive')) {
            wp_die('No se puede generar el ZIP porque falta la extensión ZipArchive en el servidor.');
        }

        $zip = new ZipArchive();
        $tmp = tempnam(sys_get_temp_dir(), 'acpdf_');
        if (!$tmp) {
            wp_die('No se pudo crear un archivo temporal para el ZIP.');
        }

        if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
            @unlink($tmp);
            wp_die('No se pudo abrir el ZIP temporal.');
        }

        $f1 = 'Cotizacion_' . $quote_no . '_interno.pdf';
        $f2 = 'Cotizacion_' . $quote_no . '_cliente.pdf';
        $zip->addFromString($f1, $bytes_internal);
        $zip->addFromString($f2, $bytes_client);
        $zip->close();

        $zip_bytes = @file_get_contents($tmp);
        @unlink($tmp);
        if ($zip_bytes === false) {
            wp_die('No se pudo leer el ZIP generado.');
        }

        while (ob_get_level()) { @ob_end_clean(); }
        if (function_exists('ini_set')) { @ini_set('zlib.output_compression', '0'); }
        nocache_headers();
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="Cotizacion_' . $quote_no . '_PDFs.zip"');
        header('Content-Length: ' . strlen($zip_bytes));
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('X-Content-Type-Options: nosniff');
        echo $zip_bytes;
        exit;
    }

    public static function output_preview($payload) {
        // Preview without reserving quote number or logging
        $quote_no = self::preview_next_quote_no($payload['date_iso']) . ' (VISTA PREVIA)';
        self::render_pdf($payload, $quote_no, 'inline', true);
    }

    public static function output_pdf_from_log($entry) {
        $payload = $entry['payload'] ?? null;
        if (!is_array($payload)) {
            wp_die('Cotización inválida.');
        }
        $quote_no = isset($entry['quote_no']) ? sanitize_text_field($entry['quote_no']) : '';
        if ($quote_no === '') {
            $quote_no = self::preview_next_quote_no($payload['date_iso'] ?? date_i18n('Y-m-d'));
        }
        self::render_pdf($payload, $quote_no, 'inline', true);
    }

    private static function compute_totals($payload) {
        $neto = 0;
        foreach (($payload['items'] ?? []) as $it) {
            $unit_price = floatval($it['unit_price'] ?? 0);
            $qty = floatval($it['qty'] ?? 0);
            $disc = floatval($it['discount'] ?? 0);
            $line = $unit_price * $qty;
            if ($disc > 0) { $line *= (1 - $disc/100.0); }
            if ($line < 0) { $line = 0; }
            $neto += $line;
        }
        $iva_percent = floatval($payload['iva_percent'] ?? 19);
        $iva = $neto * ($iva_percent/100.0);
        $total = $neto + $iva;
        return [ $neto, $iva, $total ];
    }

    private static function render_pdf($payload, $quote_no, $disposition, $show_codes = true) {
        if (function_exists("ini_set")) { @ini_set("display_errors", "0"); }
        @error_reporting(0);

        $bytes = self::build_pdf_bytes($payload, $quote_no, $show_codes);
        $disposition = ($disposition === 'inline') ? 'inline' : 'attachment';

        $suffix = $show_codes ? '_interno' : '_cliente';
        $filename = 'Cotizacion_' . $quote_no . $suffix . '.pdf';

        while (ob_get_level()) { @ob_end_clean(); }
        if (function_exists('ini_set')) { @ini_set('zlib.output_compression', '0'); }
        nocache_headers();
        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . $disposition . '; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($bytes));
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('X-Content-Type-Options: nosniff');
        echo $bytes;
        exit;
    }

    private static function build_pdf_bytes($payload, $quote_no, $show_codes) {
        $settings = ACPDF_Settings::get();
        $qt = ($payload['quote_type'] ?? 'maintenance');
        if ($qt === 'repair') {
            $title = trim(($payload['model'] ? $payload['model'].' ' : '') . 'REPARACION');
        } else {
            $title = trim(($payload['model'] ? $payload['model'].' ' : '') . 'MANTENCION ' . $payload['maint_hours'] . ' HORAS');
        }

        $pdf = new ACPDF_FPDF('P', 'mm', 'A4');
        $pdf->SetAutoPageBreak(true, 16);
        $pdf->AddPage();
        $pageBottom = $pdf->GetPageHeight() - 16;

        // Logo
        $logo = ACPDF_DIR . 'assets/agrocampo-logo.png';
        $logo_id = absint($settings['logo_id'] ?? 0);
        if ($logo_id) {
            $custom_logo = get_attached_file($logo_id);
            if ($custom_logo && is_readable($custom_logo)) {
                $logo = $custom_logo;
            }
        }
        $logo_width = floatval($settings['logo_width_mm'] ?? 45);
        if ($logo_width <= 0) {
            $logo_width = 45;
        }
        if (is_readable($logo)) {
            // keep aspect by specifying width only
            $pdf->Image($logo, 12, 10, $logo_width);
        }

        $leftX = 12;

        // Header (quote number centered)
        $pdf->SetFont('Times','B',14);
        $pdf->SetXY(0, 34);
        $pdf->Cell(210, 8, self::to_pdf_text('COTIZACION N° '.$quote_no), 0, 1, 'C');

        // Info block similar to template
        $y0 = 46;
        $lineH = 5.2;
        $labelW = 24;
        $colonW = 3;
        $leftValueW = 84;
        $rightX = 118;
        $rightLabelW = 22;
        $rightValueW = 60;

        $pdf->SetFont('Times','B',9.5);

        // Left labels
        $pdf->SetXY($leftX, $y0);
        $pdf->Cell($labelW, $lineH, self::to_pdf_text('Fecha'), 0, 0, 'L');
        $pdf->Cell($colonW, $lineH, ':', 0, 0, 'L');
        $pdf->SetFont('Times','',9.5);
        $pdf->Cell($leftValueW, $lineH, self::to_pdf_text(self::date_long_es($payload['date_iso'])), 0, 0, 'L');

        // Right labels
        $pdf->SetFont('Times','B',9.5);
        $pdf->SetXY($rightX, $y0);
        $pdf->Cell($rightLabelW, $lineH, self::to_pdf_text('Rut'), 0, 0, 'L');
        $pdf->Cell($colonW, $lineH, ':', 0, 0, 'L');
        $pdf->SetFont('Times','',9.5);
        $pdf->Cell($rightValueW, $lineH, self::to_pdf_text($payload['rut']), 0, 1, 'L');

        $pdf->SetFont('Times','B',9.5);
        $pdf->SetXY($leftX, $y0 + 6);
        $pdf->Cell($labelW, $lineH, self::to_pdf_text('Cliente'), 0, 0, 'L');
        $pdf->Cell($colonW, $lineH, ':', 0, 0, 'L');
        $pdf->SetFont('Times','',9.5);
        $pdf->Cell($leftValueW, $lineH, self::to_pdf_text($payload['client']), 0, 0, 'L');
        $pdf->SetFont('Times','B',9.5);
        $pdf->SetXY($rightX, $y0 + 6);
        $pdf->Cell($rightLabelW, $lineH, self::to_pdf_text('Fono'), 0, 0, 'L');
        $pdf->Cell($colonW, $lineH, ':', 0, 0, 'L');
        $pdf->SetFont('Times','',9.5);
        $pdf->Cell($rightValueW, $lineH, self::to_pdf_text($payload['phone']), 0, 1, 'L');

        $pdf->SetFont('Times','B',9.5);
        $pdf->SetXY($leftX, $y0 + 12);
        $pdf->Cell($labelW, $lineH, self::to_pdf_text('Modelo'), 0, 0, 'L');
        $pdf->Cell($colonW, $lineH, ':', 0, 0, 'L');
        $pdf->SetFont('Times','',9.5);
        $pdf->Cell($leftValueW, $lineH, self::to_pdf_text($payload['model']), 0, 0, 'L');
        $pdf->SetFont('Times','B',9.5);
        $pdf->SetXY($rightX, $y0 + 12);
        $pdf->Cell($rightLabelW, $lineH, self::to_pdf_text('E-mail'), 0, 0, 'L');
        $pdf->Cell($colonW, $lineH, ':', 0, 0, 'L');
        $pdf->SetFont('Times','',9.5);
        $pdf->Cell($rightValueW, $lineH, self::to_pdf_text($payload['email']), 0, 1, 'L');

        $pdf->SetFont('Times','B',9.5);
        $pdf->SetXY($leftX, $y0 + 18);
        $pdf->Cell($labelW, $lineH, self::to_pdf_text('N° Interno'), 0, 0, 'L');
        $pdf->Cell($colonW, $lineH, ':', 0, 0, 'L');
        $pdf->SetFont('Times','',9.5);
        $pdf->Cell($leftValueW, $lineH, self::to_pdf_text($payload['internal_no']), 0, 0, 'L');
        $pdf->SetFont('Times','B',9.5);
        $pdf->SetXY($rightX, $y0 + 18);
        $pdf->Cell($rightLabelW, $lineH, self::to_pdf_text('Serie'), 0, 0, 'L');
        $pdf->Cell($colonW, $lineH, ':', 0, 0, 'L');
        $pdf->SetFont('Times','',9.5);
        $pdf->Cell($rightValueW, $lineH, self::to_pdf_text($payload['serial_no']), 0, 1, 'L');

        $pdf->SetFont('Times','B',9.5);
        $pdf->SetXY($rightX, $y0 + 24);
        $pdf->Cell($rightLabelW, $lineH, self::to_pdf_text('Ubicación'), 0, 0, 'L');
        $pdf->Cell($colonW, $lineH, ':', 0, 0, 'L');
        $pdf->SetFont('Times','',9.5);
        $pdf->Cell($rightValueW, $lineH, self::to_pdf_text($payload['location']), 0, 1, 'L');

        // Title
        $pdf->SetFont('Times','B',12);
        $pdf->SetXY(0, 76);
        $pdf->Cell(210, 7, self::to_pdf_text($title), 0, 1, 'C');

        $pdf->Ln(2);
        self::render_watermark($pdf, $payload);

        $col = [
            'n' => 7,
            'code' => 21,
            'detail' => 80,
            'unit_price' => 20,
            'unit' => 9,
            'discount' => 12,
            'qty' => 13,
            'total' => 22,
        ];

        if (!$show_codes) {
            $col['detail'] = $col['detail'] + $col['code'];
            $col['code'] = 0;
        }

        $rowHHeader = 6.5;
        $rowLineH = 4.4;
        $headerFn = function() use ($pdf, $leftX, $col, $rowHHeader, $show_codes) {
            $pdf->SetFont('Times','B',9);
            $pdf->SetFillColor(230,230,230);
            $pdf->SetX($leftX);
            $pdf->Cell($col['n'], $rowHHeader, self::to_pdf_text('N°'), 1, 0, 'C', true);
            if ($show_codes) {
                $pdf->Cell($col['code'], $rowHHeader, self::to_pdf_text('Código'), 1, 0, 'C', true);
            }
            $pdf->Cell($col['detail'], $rowHHeader, self::to_pdf_text('Detalle'), 1, 0, 'C', true);
            $pdf->Cell($col['unit_price'], $rowHHeader, self::to_pdf_text('Valor Neto'), 1, 0, 'C', true);
            $pdf->Cell($col['unit'], $rowHHeader, self::to_pdf_text('Un.'), 1, 0, 'C', true);
            $pdf->Cell($col['discount'], $rowHHeader, self::to_pdf_text('Descto'), 1, 0, 'C', true);
            $pdf->Cell($col['qty'], $rowHHeader, self::to_pdf_text('Cantidad'), 1, 0, 'C', true);
            $pdf->Cell($col['total'], $rowHHeader, self::to_pdf_text('V. Total'), 1, 1, 'C', true);
            $pdf->SetFont('Times','',9);
        };

        $headerFn();

        $neto = 0;

        foreach (($payload['items'] ?? []) as $it) {
            $detail = self::to_pdf_text($it['detail'] ?? '');

            // Manual code: in INTERNAL PDF we must never fallback to pauta/part.
            // If empty, explicitly request to fill it.
            $raw_code = trim((string)($it['code'] ?? ''));
            $missing_code = ($raw_code === '');

            // line calc
            $unit_price = floatval($it['unit_price'] ?? 0);
            $qty = floatval($it['qty'] ?? 0);
            $disc = floatval($it['discount'] ?? 0);
            $line = $unit_price * $qty;
            if ($disc > 0) $line *= (1 - $disc/100.0);
            if ($line < 0) $line = 0;
            $neto += $line;

            $priceText = $unit_price > 0 ? self::money_clp($unit_price) : '-';
            $totalText = $line > 0 ? self::money_clp($line) : '-';

            $lineCounts = [
                self::count_lines($pdf, $col['n'], (string)($it['n'] ?? '')),
                self::count_lines($pdf, $col['detail'], $detail),
                self::count_lines($pdf, $col['unit_price'], $priceText),
                self::count_lines($pdf, $col['unit'], (string)($it['unit'] ?? '')),
                self::count_lines($pdf, $col['discount'], $disc > 0 ? (rtrim(rtrim(number_format($disc, 2, '.', ''), '0'), '.') . '%') : ''),
                self::count_lines($pdf, $col['qty'], $qty > 0 ? rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.') : ''),
                self::count_lines($pdf, $col['total'], $totalText),
            ];
            if ($show_codes) {
                $lineCounts[] = self::count_lines($pdf, $col['code'], $missing_code ? 'INGRESAR' : $raw_code);
            }
            $maxLines = max($lineCounts);
            $rowHeight = max(6, $rowLineH * $maxLines);

            if ($pdf->GetY() + $rowHeight > $pageBottom) {
                $pdf->AddPage();
                $pageBottom = $pdf->GetPageHeight() - 16;
                self::render_watermark($pdf, $payload);
                $headerFn();
            }

            $startX = $leftX;
            $startY = $pdf->GetY();

            $pdf->Rect($startX, $startY, $col['n'], $rowHeight);
            $x = $startX + $col['n'];
            if ($show_codes) {
                $pdf->Rect($x, $startY, $col['code'], $rowHeight);
                $x += $col['code'];
            }
            $pdf->Rect($x, $startY, $col['detail'], $rowHeight);
            $x += $col['detail'];
            $pdf->Rect($x, $startY, $col['unit_price'], $rowHeight);
            $x += $col['unit_price'];
            $pdf->Rect($x, $startY, $col['unit'], $rowHeight);
            $x += $col['unit'];
            $pdf->Rect($x, $startY, $col['discount'], $rowHeight);
            $x += $col['discount'];
            $pdf->Rect($x, $startY, $col['qty'], $rowHeight);
            $x += $col['qty'];
            $pdf->Rect($x, $startY, $col['total'], $rowHeight);

            $pdf->SetXY($startX, $startY);
            $pdf->Cell($col['n'], $rowHeight, self::to_pdf_text((string)($it['n'] ?? '')), 0, 0, 'C');
            if ($show_codes) {
                $codeText = $missing_code ? 'INGRESAR' : $raw_code;
                if ($missing_code) {
                    $pdf->SetTextColor(200, 0, 0);
                }
                $pdf->Cell($col['code'], $rowHeight, self::to_pdf_text($codeText), 0, 0, 'L');
                if ($missing_code) {
                    $pdf->SetTextColor(0, 0, 0);
                }
            }

            $detailX = $pdf->GetX();
            $detailY = $pdf->GetY();
            $pdf->MultiCell($col['detail'], $rowLineH, $detail, 0, 'L');
            $pdf->SetXY($detailX + $col['detail'], $detailY);

            $pdf->Cell($col['unit_price'], $rowHeight, self::to_pdf_text($priceText), 0, 0, 'R');
            $pdf->Cell($col['unit'], $rowHeight, self::to_pdf_text($it['unit'] ?? ''), 0, 0, 'C');
            $pdf->Cell($col['discount'], $rowHeight, self::to_pdf_text($disc > 0 ? (rtrim(rtrim(number_format($disc, 2, '.', ''), '0'), '.') . '%') : ''), 0, 0, 'C');
            $pdf->Cell($col['qty'], $rowHeight, self::to_pdf_text($qty > 0 ? rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.') : ''), 0, 0, 'C');
            $pdf->Cell($col['total'], $rowHeight, self::to_pdf_text($totalText), 0, 1, 'R');

            $pdf->SetY($startY + $rowHeight);
        }

        // Observations line (template style)
        $pdf->SetXY($leftX, max($pdf->GetY() + 4, 168));
        $pdf->SetFont('Times','B',9.5);
        $obs = trim($payload['observations']);
        if ($obs !== '') {
            $pdf->MultiCell(120, 4.8, self::to_pdf_text('OBSERVACIONES: '.$obs), 0, 'L');
        }

        // Totals box (right)
        $pdf->Ln(4);
        $iva = $neto * (floatval($payload['iva_percent'])/100.0);
        $total = $neto + $iva;

        $boxX = 128;
        $boxW = 65;
        $rowH = 6;
        $yBox = max($pdf->GetY() + 6, 188);
        if ($yBox > 230) { $yBox = 230; }
        $pdf->SetXY($boxX, $yBox);
        $pdf->SetFont('Times','B',10);
        $pdf->SetFillColor(230,230,230);
        $pdf->Cell(30, $rowH, self::to_pdf_text('Valor Neto'), 1, 0, 'L', true);
        $pdf->Cell($boxW-30, $rowH, self::to_pdf_text(self::money_clp($neto)), 1, 1, 'R');
        $pdf->SetX($boxX);
        $pdf->Cell(30, $rowH, self::to_pdf_text(rtrim(rtrim(number_format($payload['iva_percent'],2,'.',''), '0'), '.').'% I.V.A.'), 1, 0, 'L', true);
        $pdf->Cell($boxW-30, $rowH, self::to_pdf_text(self::money_clp($iva)), 1, 1, 'R');
        $pdf->SetX($boxX);
        $pdf->Cell(30, $rowH, self::to_pdf_text('Valor Total'), 1, 0, 'L', true);
        $pdf->Cell($boxW-30, $rowH, self::to_pdf_text(self::money_clp($total)), 1, 1, 'R');

        // Seller block (bottom-right)
        $pdf->SetFont('Times','B',9.5);
        $sx = 120;
        $sy = 252;
        $pdf->SetXY($sx, $sy);
        $pdf->Cell(0, 4.8, self::to_pdf_text('Cordialmente,  '.$payload['seller']['name']), 0, 1, 'L');
        $pdf->SetX($sx);
        if (!empty($payload['seller']['mobile'])) $pdf->Cell(0, 4.8, self::to_pdf_text('Móvil: '.$payload['seller']['mobile']), 0, 1, 'L');
        $pdf->SetX($sx);
        if (!empty($payload['seller']['phone'])) $pdf->Cell(0, 4.8, self::to_pdf_text('Fono: '.$payload['seller']['phone']), 0, 1, 'L');
        $pdf->SetX($sx);
        if (!empty($payload['seller']['email'])) $pdf->Cell(0, 4.8, self::to_pdf_text($payload['seller']['email']), 0, 1, 'L');
        $pdf->SetX($sx);
        if (!empty($payload['seller']['role'])) $pdf->Cell(0, 4.8, self::to_pdf_text($payload['seller']['role']), 0, 1, 'L');

        return $pdf->Output('S');
    }

    private static function render_watermark($pdf, $payload) {
        if (($payload['parts_type'] ?? '') === 'ALTERNATIVOS') {
            return;
        }
        $prevX = $pdf->GetX();
        $prevY = $pdf->GetY();
        $pdf->SetTextColor(235,235,235);
        $pdf->SetFont('Times','B',30);
        $pdf->SetXY(0, 150);
        $pdf->Cell(210, 14, self::to_pdf_text('REPUESTOS 100% ORIGINALES'), 0, 0, 'C');
        $pdf->SetTextColor(0,0,0);
        $pdf->SetXY($prevX, $prevY);
    }

    public static function get_quote_log() {
        $log = get_option('acpdf_quote_log', []);
        if (!is_array($log)) {
            return [];
        }
        return $log;
    }

    public static function delete_quote_log_entry($index) {
        $index = is_numeric($index) ? (int)$index : -1;
        if ($index < 0) {
            return false;
        }

        $log = get_option('acpdf_quote_log', []);
        if (!is_array($log) || !isset($log[$index])) {
            return false;
        }

        unset($log[$index]);
        // Reindex array to avoid gaps
        $log = array_values($log);
        update_option('acpdf_quote_log', $log, false);
        return true;
    }

    private static function log_quote($payload, $quote_no, $neto, $iva, $total) {
        $log = get_option('acpdf_quote_log', []);
        if (!is_array($log)) {
            $log = [];
        }
        $log[] = [
            'created_at' => current_time('mysql'),
            'quote_no' => $quote_no,
            'date_iso' => $payload['date_iso'],
            'maint_hours' => ($payload['maint_hours'] ?? ''),
            'model' => $payload['model'],
            'client' => $payload['client'],
            'parts_type' => $payload['parts_type'],
            'neto' => round(floatval($neto)),
            'iva' => round(floatval($iva)),
            'total' => round(floatval($total)),
            'payload' => $payload,
        ];
        if (count($log) > 500) {
            $log = array_slice($log, -500);
        }
        update_option('acpdf_quote_log', $log, false);
    }

    private static function count_lines($pdf, $w, $txt) {
        $txt = (string)$txt;
        $txt = str_replace("\r", "", $txt);
        if (trim($txt) === '') return 1;
        $paras = explode("\n", $txt);
        $lines = 0;
        foreach ($paras as $para) {
            $para = trim($para);
            if ($para === '') { $lines++; continue; }
            $words = preg_split('/\s+/', $para);
            $cur = '';
            foreach ($words as $word) {
                $trial = ($cur === '') ? $word : ($cur.' '.$word);
                if ($pdf->GetStringWidth($trial) <= $w) {
                    $cur = $trial;
                    continue;
                }
                if ($cur !== '') { $lines++; $cur = ''; }
                if ($pdf->GetStringWidth($word) > $w) {
                    $chunk = '';
                    $len = strlen($word);
                    for ($i=0; $i<$len; $i++) {
                        $trial2 = $chunk . $word[$i];
                        if ($pdf->GetStringWidth($trial2) <= $w) {
                            $chunk = $trial2;
                        } else {
                            if ($chunk !== '') { $lines++; $chunk = $word[$i]; }
                            else { $lines++; $chunk = ''; }
                        }
                    }
                    if ($chunk !== '') { $lines++; }
                } else {
                    $cur = $word;
                }
            }
            if ($cur !== '') { $lines++; }
        }
        return max(1, $lines);
    }
}
