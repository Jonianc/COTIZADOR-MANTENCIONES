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
        return utf8_decode($s);
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

    private static function date_long_es($date_iso, $city) {
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
        $city = trim($city);
        if ($city === '') $city = 'Talca';
        return $city.', '.$d.' de '.$mm.' del '.$y;
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

        $payload = [
            'city' => $get('city', $settings['default_city']),
            'date_iso' => $date_iso,
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
            'seller' => [
                'name' => $settings['seller_name'],
                'role' => $settings['seller_role'],
                'mobile' => $settings['seller_mobile'],
                'phone' => $settings['seller_phone'],
                'email' => $settings['seller_email'],
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

        return $payload;
    }

    public static function output_pdf($payload) {
        // Reserve quote number at generation time
        $quote_no = self::reserve_quote_no($payload['date_iso']);
        $title = trim(($payload['model'] ? $payload['model'].' ' : '') . 'MANTENCION ' . $payload['maint_hours'] . ' HORAS');

        if (function_exists("ini_set")) { @ini_set("display_errors", "0"); }
        @error_reporting(0);

        $pdf = new ACPDF_FPDF('P', 'mm', 'A4');
        $pdf->SetAutoPageBreak(true, 14);
        $pdf->AddPage();

        // Logo
        $logo = ACPDF_DIR . 'assets/agrocampo-logo.png';
        if (is_readable($logo)) {
            // keep aspect by specifying width only
            $pdf->Image($logo, 12, 10, 55);
        }

        $leftX = 14;

        // Header (quote number centered)
        $pdf->SetFont('Times','B',14);
        $pdf->SetXY(0, 34);
        $pdf->Cell(210, 8, self::to_pdf_text('COTIZACION N° '.$quote_no), 0, 1, 'C');

        // Info block similar to template
        $y0 = 46;
        $pdf->SetFont('Times','B',10);

        // Left labels
        $pdf->SetXY($leftX, $y0);
        $pdf->Cell(22, 5, self::to_pdf_text('Fecha'), 0, 0, 'L');
        $pdf->Cell(3, 5, ':', 0, 0, 'L');
        $pdf->SetFont('Times','B',10);
        $pdf->Cell(90, 5, self::to_pdf_text(self::date_long_es($payload['date_iso'], $payload['city'])), 0, 0, 'L');

        // Right labels
        $pdf->SetFont('Times','B',10);
        $pdf->SetXY(120, $y0);
        $pdf->Cell(22, 5, self::to_pdf_text('Rut'), 0, 0, 'L');
        $pdf->Cell(3, 5, ':', 0, 0, 'L');
        $pdf->Cell(60, 5, self::to_pdf_text($payload['rut']), 0, 1, 'L');

        $pdf->SetFont('Times','B',10);
        $pdf->SetXY($leftX, $y0+6);
        $pdf->Cell(22, 5, self::to_pdf_text('Cliente'), 0, 0, 'L');
        $pdf->Cell(3, 5, ':', 0, 0, 'L');
        $pdf->Cell(90, 5, self::to_pdf_text($payload['client']), 0, 0, 'L');
        $pdf->SetXY(120, $y0+6);
        $pdf->Cell(22, 5, self::to_pdf_text('Fono'), 0, 0, 'L');
        $pdf->Cell(3, 5, ':', 0, 0, 'L');
        $pdf->Cell(60, 5, self::to_pdf_text($payload['phone']), 0, 1, 'L');

        $pdf->SetXY($leftX, $y0+12);
        $pdf->Cell(22, 5, self::to_pdf_text('Modelo'), 0, 0, 'L');
        $pdf->Cell(3, 5, ':', 0, 0, 'L');
        $pdf->Cell(90, 5, self::to_pdf_text($payload['model']), 0, 0, 'L');
        $pdf->SetXY(120, $y0+12);
        $pdf->Cell(22, 5, self::to_pdf_text('E-mail'), 0, 0, 'L');
        $pdf->Cell(3, 5, ':', 0, 0, 'L');
        $pdf->Cell(60, 5, self::to_pdf_text($payload['email']), 0, 1, 'L');

        $pdf->SetXY(120, $y0+18);
        $pdf->Cell(22, 5, self::to_pdf_text('Ubicación'), 0, 0, 'L');
        $pdf->Cell(3, 5, ':', 0, 0, 'L');
        $pdf->Cell(60, 5, self::to_pdf_text($payload['location']), 0, 1, 'L');

        // Title
        $pdf->SetFont('Times','B',12);
        $pdf->SetXY(0, 72);
        $pdf->Cell(210, 7, self::to_pdf_text($title), 0, 1, 'C');

        // Table header
        $pdf->Ln(2);
        $pdf->SetFont('Times','B',9);
        $pdf->SetFillColor(230,230,230);

        $col = [
            'n' => 8,
	            'code' => 24,
	            'detail' => 72,
            'unit_price' => 22,
            'unit' => 10,
            'discount' => 14,
            'qty' => 16,
	            'total' => 20,
        ];

        $pdf->SetX($leftX);
        $pdf->Cell($col['n'], 7, self::to_pdf_text('N°'), 1, 0, 'C', true);
        $pdf->Cell($col['code'], 7, self::to_pdf_text('Codigo'), 1, 0, 'C', true);
        $pdf->Cell($col['detail'], 7, self::to_pdf_text('Detalle'), 1, 0, 'C', true);
        $pdf->Cell($col['unit_price'], 7, self::to_pdf_text('Valor Neto'), 1, 0, 'C', true);
        $pdf->Cell($col['unit'], 7, self::to_pdf_text('Un.'), 1, 0, 'C', true);
        $pdf->Cell($col['discount'], 7, self::to_pdf_text('Descto'), 1, 0, 'C', true);
        $pdf->Cell($col['qty'], 7, self::to_pdf_text('Cantidad'), 1, 0, 'C', true);
        $pdf->Cell($col['total'], 7, self::to_pdf_text('Valor Neto Total'), 1, 1, 'C', true);

        $pdf->SetFont('Times','',9);

        $neto = 0;

        foreach ($payload['items'] as $it) {
            $pdf->SetX($leftX);
            $detail = self::to_pdf_text($it['detail']);

            // line calc
            $unit_price = floatval($it['unit_price']);
            $qty = floatval($it['qty']);
            $disc = floatval($it['discount']);
            $line = $unit_price * $qty;
            if ($disc > 0) $line *= (1 - $disc/100.0);
            if ($line < 0) $line = 0;
            $neto += $line;

            $priceText = $unit_price > 0 ? self::money_clp($unit_price) : '-';
            $totalText = $line > 0 ? self::money_clp($line) : '-';

            // compute row height using MultiCell approach
            $startX = $pdf->GetX();
            $startY = $pdf->GetY();

            // Pre-calc lines based on width
            $lines = self::count_lines($pdf, $col['detail'], $detail);
            $h = max(6, 4.5 * $lines);

            $pdf->Cell($col['n'], $h, self::to_pdf_text((string)$it['n']), 1, 0, 'C');
            $pdf->Cell($col['code'], $h, self::to_pdf_text($it['code']), 1, 0, 'L');

            $xDetail = $pdf->GetX();
            $yDetail = $pdf->GetY();
            $pdf->MultiCell($col['detail'], 4.5, $detail, 1, 'L');
            $pdf->SetXY($xDetail + $col['detail'], $yDetail);

            $pdf->Cell($col['unit_price'], $h, self::to_pdf_text($priceText), 1, 0, 'R');
            $pdf->Cell($col['unit'], $h, self::to_pdf_text($it['unit']), 1, 0, 'C');
            $pdf->Cell($col['discount'], $h, self::to_pdf_text($disc > 0 ? (rtrim(rtrim(number_format($disc, 2, '.', ''), '0'), '.') . '%') : ''), 1, 0, 'C');
            $pdf->Cell($col['qty'], $h, self::to_pdf_text($qty > 0 ? rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.') : ''), 1, 0, 'C');
            $pdf->Cell($col['total'], $h, self::to_pdf_text($totalText), 1, 1, 'R');

            // Align Y
            $pdf->SetY($startY + $h);
        }

        // Watermark (parts type)
        $rep_big = ($payload['parts_type'] === 'ALTERNATIVOS') ? 'REPUESTOS ALTERNATIVOS' : 'REPUESTOS 100% ORIGINALES';
        $pdf->SetTextColor(210,210,210);
        $pdf->SetFont('Times','B',34);
        $pdf->SetXY(0, 150);
        $pdf->Cell(210, 16, self::to_pdf_text($rep_big), 0, 1, 'C');
        $pdf->SetTextColor(0,0,0);

        // Totals box (right)
        $pdf->Ln(4);
        $iva = $neto * (floatval($payload['iva_percent'])/100.0);
        $total = $neto + $iva;

        $boxX = 130;
        $boxW = 65;
        $rowH = 6;
        $yBox = max($pdf->GetY(), 190);
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

        // Observations line (template style)
        $pdf->SetXY($leftX, 175);
        $pdf->SetFont('Times','B',9.5);
        $obs = trim($payload['observations']);
        if ($obs !== '') {
            $pdf->MultiCell(120, 4.8, self::to_pdf_text('OBSERVACIONES: '.$obs), 0, 'L');
        }

        // Seller block (bottom-right)
        $pdf->SetFont('Times','B',9.5);
        $sx = 118;
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

        // Output as direct download (avoid Chrome PDF viewer range/network issues)
        $filename = 'Cotizacion_'.$quote_no.'.pdf';
        $bytes = $pdf->Output('S');

        while (ob_get_level()) { @ob_end_clean(); }
        if (function_exists('ini_set')) { @ini_set('zlib.output_compression', '0'); }
        nocache_headers();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        header('Content-Length: '.strlen($bytes));
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('X-Content-Type-Options: nosniff');
        echo $bytes;
        exit;
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
