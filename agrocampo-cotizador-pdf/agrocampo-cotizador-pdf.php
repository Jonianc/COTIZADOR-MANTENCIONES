<?php
/**
 * Plugin Name: Agrocampo – Cotizador PDF
 * Description: Genera cotizaciones en PDF con el formato Agrocampo desde un endpoint propio.
 * Version: 1.0.0
 * Author: Agrocampo
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Agrocampo_Cotizador_PDF
{
    private const REST_NAMESPACE = 'agrocampo-cotizador/v1';

    public function __construct()
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void
    {
        register_rest_route(self::REST_NAMESPACE, '/pdf', [
            'methods' => ['GET', 'POST'],
            'callback' => [$this, 'handle_pdf_request'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function handle_pdf_request(WP_REST_Request $request): WP_REST_Response
    {
        $payload = $this->normalize_payload($request);
        $pdf = new Agrocampo_PDF_Builder();
        $pdf->build($payload);

        $filename = 'cotizacion-agrocampo.pdf';
        $download = $request->get_param('download') ? 'attachment' : 'inline';

        $response = new WP_REST_Response($pdf->output());
        $response->header('Content-Type', 'application/pdf');
        $response->header('Content-Disposition', $download . '; filename="' . $filename . '"');

        return $response;
    }

    private function normalize_payload(WP_REST_Request $request): array
    {
        $body = $request->get_json_params();
        if (is_array($body) && !empty($body)) {
            return wp_parse_args($body, $this->default_data());
        }

        $params = $request->get_params();
        if (!empty($params)) {
            return wp_parse_args($params, $this->default_data());
        }

        return $this->default_data();
    }

    private function default_data(): array
    {
        return [
            'quote_number' => '120126-4',
            'date' => 'Talca, 12 de Enero del 2026',
            'client' => 'CONSTRUCTORA PEHUENCHE LTDA',
            'attention' => ' ',
            'model' => 'EUROPARD TB504',
            'rut' => '78.246.760-3',
            'phone' => '',
            'email' => '',
            'location' => '',
            'subtitle' => 'MANTENCION 1200 HORAS LOVOL EUROPARD TB504',
            'items' => [
                [
                    'number' => '1',
                    'code' => '1408502610101',
                    'detail' => 'FILTRO DE MOTOR',
                    'unit_price' => 9880,
                    'discount' => '',
                    'quantity' => 1,
                ],
                [
                    'number' => '2',
                    'code' => 'CX0708N',
                    'detail' => 'FILTRO DE COMBUSTIBLE',
                    'unit_price' => 9100,
                    'discount' => '',
                    'quantity' => 1,
                ],
                [
                    'number' => '3',
                    'code' => 'AV15X950LE',
                    'detail' => 'CORREA VENTILADOR',
                    'unit_price' => 7033,
                    'discount' => '',
                    'quantity' => 1,
                ],
                [
                    'number' => '4',
                    'code' => 'TB4009601',
                    'detail' => 'FILTRO DE ACEITE HIDRAULICO MALLA\nACEITE DE MOTOR 15W40 CK4 VALVOLINE 19',
                    'unit_price' => 31490,
                    'discount' => '',
                    'quantity' => 1,
                ],
                [
                    'number' => '5',
                    'code' => '100727',
                    'detail' => 'LTRS',
                    'unit_price' => 76900,
                    'discount' => '15%',
                    'quantity' => 1,
                ],
                [
                    'number' => '6',
                    'code' => '100170',
                    'detail' => 'ACEITE HIDRAULICO TRACTO FLUID 19 LTRS',
                    'unit_price' => 69800,
                    'discount' => '15%',
                    'quantity' => 3,
                ],
                [
                    'number' => '7',
                    'code' => '',
                    'detail' => 'INSUMOS PAÑOL',
                    'unit_price' => 15000,
                    'discount' => '',
                    'quantity' => 1,
                ],
                [
                    'number' => '8',
                    'code' => '',
                    'detail' => 'MANO DE OBRA+TRASLADO (SIN COSTO)',
                    'unit_price' => 0,
                    'discount' => '',
                    'quantity' => 1,
                ],
            ],
            'notes' => 'SERVICIO A SER EJECUTADO EN TERRENO POR MANTENCION 1200 HORAS UNIDAD EUROPARD TB504 INSUMOS 100% COSTO CLIENTE. MANO DE OBRA Y TRASLADO SIN COSTO',
            'contact_name' => 'Ivonne Chacon R.',
            'contact_title' => 'Gestion Comercial Post Venta',
            'contact_mobile' => '569-? ',
            'contact_phone' => '71-2245252',
            'contact_email' => 'paolacisterna@agrocampo.cl',
        ];
    }
}

final class Agrocampo_PDF_Builder
{
    private const MM_TO_PT = 2.83464567;
    private const PAGE_WIDTH_MM = 210;
    private const PAGE_HEIGHT_MM = 297;

    private array $objects = [];
    private array $offsets = [];
    private string $content = '';

    public function build(array $data): void
    {
        $this->content = '';
        $this->draw_background();
        $this->draw_header($data);
        $this->draw_info_block($data);
        $this->draw_table($data);
        $this->draw_notes($data);
        $this->draw_totals($data);
        $this->draw_footer($data);
    }

    public function output(): string
    {
        $stream = $this->content;
        $compressed = gzcompress($stream);
        $use_compression = $compressed !== false;

        $content_stream = $use_compression ? $compressed : $stream;
        $content_length = strlen($content_stream);

        $this->objects = [];
        $this->offsets = [];

        $this->objects[] = "<< /Type /Catalog /Pages 2 0 R >>";
        $this->objects[] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
        $page_resources = "<< /Font << /F1 4 0 R /F2 5 0 R >> >>";
        $this->objects[] = "<< /Type /Page /Parent 2 0 R /Resources {$page_resources} /MediaBox [0 0 595.28 841.89] /Contents 6 0 R >>";
        $this->objects[] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";
        $this->objects[] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>";

        $filter = $use_compression ? "/Filter /FlateDecode" : "";
        $this->objects[] = "<< /Length {$content_length} {$filter} >>\nstream\n" . $content_stream . "\nendstream";

        $pdf = "%PDF-1.4\n";
        foreach ($this->objects as $index => $object) {
            $this->offsets[$index + 1] = strlen($pdf);
            $pdf .= ($index + 1) . " 0 obj\n" . $object . "\nendobj\n";
        }

        $xref_offset = strlen($pdf);
        $pdf .= "xref\n0 " . (count($this->objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        foreach ($this->offsets as $offset) {
            $pdf .= sprintf('%010d 00000 n ', $offset) . "\n";
        }

        $pdf .= "trailer\n<< /Size " . (count($this->objects) + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xref_offset}\n%%EOF";

        return $pdf;
    }

    private function draw_background(): void
    {
        $this->set_fill_color(204, 204, 204);
        $this->rect(10, 10, 190, 277, true);
        $this->set_fill_color(255, 255, 255);
        $this->rect(16, 16, 178, 265, true);
    }

    private function draw_header(array $data): void
    {
        $this->set_text_color(200, 0, 0);
        $this->text(26, 30, 'Agrocampo', 22, true);
        $this->text(38, 38, 'Negocios Agricolas', 10, false);

        $this->set_text_color(120, 120, 120);
        $this->text(142, 30, 'LOVOL', 24, true);

        $this->set_text_color(0, 0, 0);
        $this->text_center(105, 48, 'COTIZACION N° ' . $data['quote_number'], 11, true);
    }

    private function draw_info_block(array $data): void
    {
        $y = 58;
        $left_label_x = 24;
        $left_value_x = 52;
        $right_label_x = 128;
        $right_value_x = 152;

        $this->text($left_label_x, $y, 'Fecha', 9, true);
        $this->text($left_value_x, $y, ': ' . $data['date'], 9);

        $y += 6;
        $this->text($left_label_x, $y, 'Cliente', 9, true);
        $this->text($left_value_x, $y, ': ' . $data['client'], 9);

        $y += 6;
        $this->text($left_label_x, $y, 'Atención a', 9, true);
        $this->text($left_value_x, $y, ': ' . $data['attention'], 9);

        $y += 6;
        $this->text($left_label_x, $y, 'Modelo', 9, true);
        $this->text($left_value_x, $y, ': ' . $data['model'], 9);

        $y = 58;
        $this->text($right_label_x, $y, 'Rut', 9, true);
        $this->text($right_value_x, $y, ': ' . $data['rut'], 9);

        $y += 6;
        $this->text($right_label_x, $y, 'Fono', 9, true);
        $this->text($right_value_x, $y, ': ' . $data['phone'], 9);

        $y += 6;
        $this->text($right_label_x, $y, 'E-mail', 9, true);
        $this->text($right_value_x, $y, ': ' . $data['email'], 9);

        $y += 6;
        $this->text($right_label_x, $y, 'Ubicación', 9, true);
        $this->text($right_value_x, $y, ': ' . $data['location'], 9);

        $this->text_center(105, 84, $data['subtitle'], 9, true);
    }

    private function draw_table(array $data): void
    {
        $start_y = 90;
        $this->set_fill_color(215, 215, 215);
        $this->rect(18, $start_y, 174, 7, true);

        $this->set_text_color(0, 0, 0);
        $this->text(20, $start_y + 5, 'N°', 8, true);
        $this->text(30, $start_y + 5, 'Codigo', 8, true);
        $this->text(58, $start_y + 5, 'Detalle', 8, true);
        $this->text(120, $start_y + 5, 'Valor Neto Un.', 8, true);
        $this->text(147, $start_y + 5, 'Descto', 8, true);
        $this->text(164, $start_y + 5, 'Cantidad', 8, true);
        $this->text(184, $start_y + 5, 'Valor Neto Total', 8, true);

        $y = $start_y + 10;
        $row_height = 6;
        foreach ($data['items'] as $item) {
            $unit_price = (float) $item['unit_price'];
            $quantity = (int) $item['quantity'];
            $discount = (string) $item['discount'];
            $total = $this->apply_discount($unit_price * $quantity, $discount);

            $this->text(20, $y, $item['number'], 8);
            $this->text(30, $y, $item['code'], 8);
            $this->multi_text(58, $y, $item['detail'], 8, 60);
            $this->text(120, $y, '$', 8);
            $this->text_right(136, $y, $this->format_money($unit_price), 8);
            $this->text(147, $y, $discount, 8);
            $this->text_right(172, $y, (string) $quantity, 8);
            $this->text(176, $y, '$', 8);
            $this->text_right(192, $y, $total > 0 ? $this->format_money($total) : '-', 8);

            $lines = substr_count($item['detail'], "\n");
            $y += $row_height + ($lines * 4);
        }

        $this->set_text_color(210, 210, 210);
        $this->text_center(105, 178, 'REPUESTOS 100% ORIGINALES', 24, true);
        $this->set_text_color(0, 0, 0);
    }

    private function draw_notes(array $data): void
    {
        $this->text(20, 200, 'OBSERVACIONES: ' . $data['notes'], 7, true);
    }

    private function draw_totals(array $data): void
    {
        $net = 0;
        foreach ($data['items'] as $item) {
            $unit_price = (float) $item['unit_price'];
            $quantity = (int) $item['quantity'];
            $discount = (string) $item['discount'];
            $net += $this->apply_discount($unit_price * $quantity, $discount);
        }
        $tax = round($net * 0.19);
        $total = $net + $tax;

        $x = 135;
        $y = 228;
        $this->set_fill_color(235, 235, 235);
        $this->rect($x, $y, 55, 18, true);
        $this->set_text_color(0, 0, 0);

        $this->text($x + 2, $y + 5, 'Valor Neto', 8, true);
        $this->text_right($x + 52, $y + 5, '$ ' . $this->format_money($net), 8, true);

        $this->text($x + 2, $y + 11, '19 % I.V.A.', 8, false);
        $this->text_right($x + 52, $y + 11, '$ ' . $this->format_money($tax), 8, false);

        $this->set_fill_color(200, 200, 200);
        $this->rect($x, $y + 12, 55, 6, true);
        $this->text($x + 2, $y + 16, 'Valor Total', 8, true);
        $this->text_right($x + 52, $y + 16, '$ ' . $this->format_money($total), 8, true);
    }

    private function draw_footer(array $data): void
    {
        $y = 252;
        $this->text(112, $y, 'Cordialmente, ' . $data['contact_name'], 8, true);
        $this->text(112, $y + 5, 'Móvil: ' . $data['contact_mobile'], 8, false);
        $this->text(112, $y + 10, 'Fono: ' . $data['contact_phone'], 8, false);
        $this->text(112, $y + 15, $data['contact_email'], 8, false);
        $this->text(112, $y + 20, $data['contact_title'], 8, true);
    }

    private function format_money(float $amount): string
    {
        return number_format($amount, 0, ',', '.');
    }

    private function apply_discount(float $amount, string $discount): float
    {
        if ($discount === '') {
            return $amount;
        }
        $discount = rtrim($discount, '%');
        if (!is_numeric($discount)) {
            return $amount;
        }
        $percentage = (float) $discount;
        return round($amount * (1 - ($percentage / 100)));
    }

    private function set_text_color(int $r, int $g, int $b): void
    {
        $this->content .= sprintf("%0.3f %0.3f %0.3f rg\n", $r / 255, $g / 255, $b / 255);
    }

    private function set_fill_color(int $r, int $g, int $b): void
    {
        $this->content .= sprintf("%0.3f %0.3f %0.3f rg\n", $r / 255, $g / 255, $b / 255);
    }

    private function rect(float $x_mm, float $y_mm, float $w_mm, float $h_mm, bool $fill = false): void
    {
        $x = $this->mm_to_pt($x_mm);
        $y = $this->mm_to_pt(self::PAGE_HEIGHT_MM - $y_mm - $h_mm);
        $w = $this->mm_to_pt($w_mm);
        $h = $this->mm_to_pt($h_mm);
        $this->content .= sprintf("%0.2f %0.2f %0.2f %0.2f re %s\n", $x, $y, $w, $h, $fill ? 'f' : 'S');
    }

    private function text(float $x_mm, float $y_mm, string $text, int $size, bool $bold = false): void
    {
        $x = $this->mm_to_pt($x_mm);
        $y = $this->mm_to_pt(self::PAGE_HEIGHT_MM - $y_mm);
        $font = $bold ? '/F2' : '/F1';
        $escaped = $this->escape($text);
        $this->content .= "BT {$font} {$size} Tf {$x} {$y} Td ({$escaped}) Tj ET\n";
    }

    private function text_center(float $center_x_mm, float $y_mm, string $text, int $size, bool $bold = false): void
    {
        $width = $this->text_width($text, $size);
        $x = $center_x_mm - ($width / 2);
        $this->text($x, $y_mm, $text, $size, $bold);
    }

    private function text_right(float $right_x_mm, float $y_mm, string $text, int $size, bool $bold = false): void
    {
        $width = $this->text_width($text, $size);
        $x = $right_x_mm - $width;
        $this->text($x, $y_mm, $text, $size, $bold);
    }

    private function multi_text(float $x_mm, float $y_mm, string $text, int $size, float $max_width_mm): void
    {
        $lines = explode("\n", $text);
        $offset = 0;
        foreach ($lines as $line) {
            $this->text($x_mm, $y_mm + $offset, $this->truncate_text($line, $size, $max_width_mm), $size, false);
            $offset += 4;
        }
    }

    private function truncate_text(string $text, int $size, float $max_width_mm): string
    {
        $max_width = $max_width_mm;
        if ($this->text_width($text, $size) <= $max_width) {
            return $text;
        }
        $trimmed = $text;
        while (strlen($trimmed) > 0 && $this->text_width($trimmed . '…', $size) > $max_width) {
            $trimmed = substr($trimmed, 0, -1);
        }
        return rtrim($trimmed) . '…';
    }

    private function text_width(string $text, int $size): float
    {
        return (strlen($text) * $size * 0.48) / self::MM_TO_PT;
    }

    private function escape(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    private function mm_to_pt(float $mm): float
    {
        return $mm * self::MM_TO_PT;
    }
}

new Agrocampo_Cotizador_PDF();
