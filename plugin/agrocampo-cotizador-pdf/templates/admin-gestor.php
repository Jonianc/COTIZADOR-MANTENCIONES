<?php
if (!defined('ABSPATH')) { exit; }

$build_url = function($args = []) use ($base_url, $hours_filter, $order_by, $order) {
    $query = array_merge([
        'maint_hours' => $hours_filter,
        'orderby' => $order_by,
        'order' => $order,
    ], $args);
    return add_query_arg(array_filter($query, function($value) {
        return $value !== '' && $value !== null;
    }), $base_url);
};

$toggle_order = function($column) use ($order_by, $order) {
    if ($order_by === $column) {
        return ($order === 'ASC') ? 'DESC' : 'ASC';
    }
    return 'ASC';
};

$sortable_headers = [
    'date' => 'Fecha',
    'quote' => 'Cotización N°',
    'total' => 'Total',
];
?>
<div class="wrap acpdf-admin-gestor">
  <h1><?php echo esc_html__('Gestor de Cotizaciones', 'agrocampo-cotizador-pdf'); ?></h1>
  <p class="description"><?php echo esc_html__('Los PDFs se abren en una nueva pestaña para facilitar volver al listado.', 'agrocampo-cotizador-pdf'); ?></p>

  <form method="get" class="acpdf-filters">
    <input type="hidden" name="page" value="acpdf-quotes">
    <label for="acpdf-hours-filter"><?php echo esc_html__('Filtro por horas', 'agrocampo-cotizador-pdf'); ?></label>
    <select name="maint_hours" id="acpdf-hours-filter">
      <option value=""><?php echo esc_html__('Todas', 'agrocampo-cotizador-pdf'); ?></option>
      <?php foreach ($hours_options as $opt) : ?>
        <option value="<?php echo esc_attr($opt); ?>" <?php selected($hours_filter, (string)$opt); ?>><?php echo esc_html($opt); ?></option>
      <?php endforeach; ?>
    </select>
    <?php submit_button(__('Filtrar', 'agrocampo-cotizador-pdf'), 'secondary', '', false); ?>
  </form>

  <table class="widefat striped">
    <thead>
      <tr>
        <?php foreach ($sortable_headers as $key => $label) : ?>
          <?php
          $next_order = $toggle_order($key);
          $url = $build_url(['orderby' => $key, 'order' => $next_order, 'paged' => 1]);
          ?>
          <th scope="col">
            <a href="<?php echo esc_url($url); ?>">
              <?php echo esc_html($label); ?>
              <?php if ($order_by === $key) : ?>
                <span class="sorting-indicator"></span>
              <?php endif; ?>
            </a>
          </th>
        <?php endforeach; ?>
        <th><?php echo esc_html__('Modelo', 'agrocampo-cotizador-pdf'); ?></th>
        <th><?php echo esc_html__('Cliente', 'agrocampo-cotizador-pdf'); ?></th>
        <th><?php echo esc_html__('Horas', 'agrocampo-cotizador-pdf'); ?></th>
        <th><?php echo esc_html__('Repuestos', 'agrocampo-cotizador-pdf'); ?></th>
        <th><?php echo esc_html__('Neto', 'agrocampo-cotizador-pdf'); ?></th>
        <th><?php echo esc_html__('IVA', 'agrocampo-cotizador-pdf'); ?></th>
        <th><?php echo esc_html__('PDF', 'agrocampo-cotizador-pdf'); ?></th>
        <th><?php echo esc_html__('Gestionar', 'agrocampo-cotizador-pdf'); ?></th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($rows)) : ?>
        <tr>
          <td colspan="11"><?php echo esc_html__('No hay cotizaciones registradas para este filtro.', 'agrocampo-cotizador-pdf'); ?></td>
        </tr>
      <?php else : ?>
        <?php foreach ($rows as $index => $entry) : ?>
          <?php
          $pdf_url = wp_nonce_url(
              admin_url('options-general.php?page=acpdf-quotes&view=' . $index),
              'acpdf_view_quote_' . $index
          );
          $edit_url = wp_nonce_url(
              home_url('/agrocampo-cotizador?prefill=' . $index),
              'acpdf_prefill_' . $index
          );
          ?>
          <tr>
            <td><?php echo esc_html($entry['date_iso'] ?? ''); ?></td>
            <td><?php echo esc_html($entry['quote_no'] ?? ''); ?></td>
            <td><?php echo esc_html(number_format(floatval($entry['total'] ?? 0), 0, ',', '.')); ?></td>
            <td><?php echo esc_html($entry['model'] ?? ''); ?></td>
            <td><?php echo esc_html($entry['client'] ?? ''); ?></td>
            <td><?php echo esc_html($entry['maint_hours'] ?? ''); ?></td>
            <td><?php echo esc_html($entry['parts_type'] ?? ''); ?></td>
            <td><?php echo esc_html(number_format(floatval($entry['neto'] ?? 0), 0, ',', '.')); ?></td>
            <td><?php echo esc_html(number_format(floatval($entry['iva'] ?? 0), 0, ',', '.')); ?></td>
            <td class="acpdf-actions">
              <?php if (!empty($entry['payload']) && is_array($entry['payload'])) : ?>
                <a class="button button-small" href="<?php echo esc_url($pdf_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__('Ver PDF', 'agrocampo-cotizador-pdf'); ?></a>
              <?php else : ?>
                <span class="dashicons dashicons-minus"></span>
              <?php endif; ?>
            </td>
            <td class="acpdf-actions">
              <?php if (!empty($entry['payload']) && is_array($entry['payload'])) : ?>
                <a class="button button-small" href="<?php echo esc_url($edit_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__('Abrir formulario', 'agrocampo-cotizador-pdf'); ?></a>
              <?php else : ?>
                <span class="dashicons dashicons-minus"></span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>

  <?php if ($total_pages > 1) : ?>
    <div class="tablenav">
      <div class="tablenav-pages">
        <?php
        $pagination_base = $build_url(['paged' => '%#%']);
        echo wp_kses_post(paginate_links([
            'base' => $pagination_base,
            'format' => '',
            'current' => $paged,
            'total' => $total_pages,
            'add_args' => false,
            'prev_text' => __('&laquo; Anterior', 'agrocampo-cotizador-pdf'),
            'next_text' => __('Siguiente &raquo;', 'agrocampo-cotizador-pdf'),
        ]));
        ?>
      </div>
    </div>
  <?php endif; ?>
</div>
