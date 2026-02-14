<?php
if (!defined('ABSPATH')) { exit; }

$title = isset($title) ? $title : 'Gestor de Cotizaciones';
$menu_links = isset($menu_links) ? $menu_links : [];
$client_filter = isset($client_filter) ? (string)$client_filter : '';
$model_filter = isset($model_filter) ? (string)$model_filter : '';
$seller_filter = isset($seller_filter) ? (string)$seller_filter : '';
$quote_filter = isset($quote_filter) ? (string)$quote_filter : '';
$date_from = isset($date_from) ? (string)$date_from : '';
$date_to = isset($date_to) ? (string)$date_to : '';
$deleted = isset($deleted) ? (string)$deleted : '';
$duplicated = isset($duplicated) ? (string)$duplicated : '';
$export = isset($export) ? (string)$export : '';
$per_page = isset($per_page) ? (int)$per_page : 25;
$current_page = isset($current_page) ? (int)$current_page : 1;
$seller_options = isset($seller_options) && is_array($seller_options) ? $seller_options : [];
$css_url = ACPDF_URL . 'assets/cotizador.css?ver=' . ACPDF_VER;

include ACPDF_DIR . 'templates/partials/head.php';

// Apply filters
$filtered_log = [];
foreach ($log as $index => $entry) {
    // Hours filter
    $hours = isset($entry['maint_hours']) ? (string)$entry['maint_hours'] : '';
    if ($hours_filter !== '' && $hours_filter !== $hours) {
        continue;
    }

    // Client filter
    $client = isset($entry['client']) ? (string)$entry['client'] : '';
    if ($client_filter !== '' && stripos($client, $client_filter) === false) {
        continue;
    }

    // Model filter
    $model = isset($entry['model']) ? (string)$entry['model'] : '';
    if ($model_filter !== '' && stripos($model, $model_filter) === false) {
        continue;
    }

    // Seller filter
    $seller_name = trim((string)($entry['payload']['seller']['name'] ?? ''));
    if ($seller_filter !== '') {
        if ($seller_filter === '__none' && $seller_name !== '') {
            continue;
        }
        if ($seller_filter !== '__none' && strcasecmp($seller_name, $seller_filter) !== 0) {
            continue;
        }
    }

    // Quote number filter
    $quote_no = isset($entry['quote_no']) ? (string)$entry['quote_no'] : '';
    if ($quote_filter !== '' && stripos($quote_no, $quote_filter) === false) {
        continue;
    }

    // Date range filter
    $entry_date = isset($entry['date_iso']) ? $entry['date_iso'] : '';
    if ($date_from !== '' && $entry_date < $date_from) {
        continue;
    }
    if ($date_to !== '' && $entry_date > $date_to) {
        continue;
    }

    $filtered_log[$index] = $entry;
}

// Reverse for newest first
$filtered_log = array_reverse($filtered_log, true);

// Pagination
$total_items = count($filtered_log);
$total_pages = max(1, ceil($total_items / $per_page));
$current_page = max(1, min($current_page, $total_pages));
$offset = ($current_page - 1) * $per_page;
$paged_log = array_slice($filtered_log, $offset, $per_page, true);

// Build base URL for pagination
$base_url = home_url('/agrocampo-cotizador/gestor');
$filter_args = array_filter([
    'maint_hours' => $hours_filter,
    'client' => $client_filter,
    'model' => $model_filter,
    'seller' => $seller_filter,
    'quote' => $quote_filter,
    'date_from' => $date_from,
    'date_to' => $date_to,
    'per_page' => $per_page !== 25 ? $per_page : '',
]);
?>
            <div class="card">
              <div class="top">
                <div>
                  <h1>Gestor de Cotizaciones</h1>
                  <div class="muted">Total: <?php echo esc_html($total_items); ?> cotizaciones<?php echo $total_items !== count($log) ? ' (filtradas de ' . count($log) . ')' : ''; ?></div>
                </div>
              </div>

              <?php if ($deleted !== '') : ?>
                <div class="alert <?php echo $deleted === '1' ? 'alert-success' : 'alert-error'; ?>">
                  <?php if ($deleted === '1') : ?>
                    <strong>✓</strong> Cotización eliminada correctamente.
                  <?php else : ?>
                    <strong>✕</strong> No se pudo eliminar la cotización.
                  <?php endif; ?>
                </div>
              <?php endif; ?>

              <?php if ($duplicated !== '') : ?>
                <div class="alert <?php echo $duplicated === '1' ? 'alert-success' : 'alert-error'; ?>">
                  <?php if ($duplicated === '1') : ?>
                    <strong>✓</strong> Cotización duplicada. <a href="<?php echo esc_url(home_url('/agrocampo-cotizador')); ?>">Abrir formulario</a>
                  <?php else : ?>
                    <strong>✕</strong> No se pudo duplicar la cotización.
                  <?php endif; ?>
                </div>
              <?php endif; ?>

              <!-- Filtros avanzados -->
              <form method="get" class="filter-bar">
                <input type="hidden" name="acpdf_route" value="gestor">

                <div class="filter-group">
                  <label for="acpdf-quote-filter">N° Cotización</label>
                  <input type="text" name="quote" id="acpdf-quote-filter" value="<?php echo esc_attr($quote_filter); ?>" placeholder="Ej: 310126-1">
                </div>

                <div class="filter-group">
                  <label for="acpdf-hours-filter">Horas</label>
                  <select name="maint_hours" id="acpdf-hours-filter">
                    <option value="">Todas</option>
                    <?php foreach ($hours_options as $opt) : ?>
                      <option value="<?php echo esc_attr($opt); ?>" <?php selected($hours_filter, (string)$opt); ?>><?php echo esc_html($opt); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="filter-group">
                  <label for="acpdf-client-filter">Cliente</label>
                  <input type="text" name="client" id="acpdf-client-filter" value="<?php echo esc_attr($client_filter); ?>" placeholder="Buscar...">
                </div>

                <div class="filter-group">
                  <label for="acpdf-model-filter">Modelo</label>
                  <input type="text" name="model" id="acpdf-model-filter" value="<?php echo esc_attr($model_filter); ?>" placeholder="Buscar...">
                </div>

                <div class="filter-group">
                  <label for="acpdf-seller-filter">Vendedor</label>
                  <select name="seller" id="acpdf-seller-filter">
                    <option value="">Todos</option>
                    <?php foreach ($seller_options as $value => $label) : ?>
                      <option value="<?php echo esc_attr($value); ?>" <?php selected($seller_filter, (string)$value); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="filter-group">
                  <label for="acpdf-date-from">Desde</label>
                  <input type="date" name="date_from" id="acpdf-date-from" value="<?php echo esc_attr($date_from); ?>">
                </div>

                <div class="filter-group">
                  <label for="acpdf-date-to">Hasta</label>
                  <input type="date" name="date_to" id="acpdf-date-to" value="<?php echo esc_attr($date_to); ?>">
                </div>

                <div class="filter-group">
                  <label for="acpdf-per-page">Por página</label>
                  <select name="per_page" id="acpdf-per-page">
                    <option value="25" <?php selected($per_page, 25); ?>>25</option>
                    <option value="50" <?php selected($per_page, 50); ?>>50</option>
                    <option value="100" <?php selected($per_page, 100); ?>>100</option>
                  </select>
                </div>

                <div class="filter-actions">
                  <span class="muted small filter-hint">Tip: presiona Enter en un campo para filtrar.</span>
                  <button type="submit" class="btn btn-ghost">Filtrar</button>
                  <a class="btn btn-danger" href="<?php echo esc_url($base_url); ?>" title="Limpiar todos los filtros">Limpiar</a>
                  <?php if ($total_items > 0) : ?>
                    <button type="submit" name="export" value="csv" class="btn btn-success" title="Exportar resultados filtrados">📥 CSV</button>
                  <?php endif; ?>
                </div>
              </form>

              <div class="table-responsive">
              <table class="gestor-table">
                <thead>
                  <tr>
                    <th>Fecha</th>
                    <th>Cotización N°</th>
                    <th>N° Interno</th>
                    <th>Serie</th>
                    <th>Modelo</th>
                    <th>Cliente</th>
                    <th>Horas</th>
                    <th>Repuestos</th>
                    <th>Neto</th>
                    <th>IVA</th>
                    <th>Total</th>
                    <th>Acciones</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  if (empty($paged_log)) :
                  ?>
                    <tr>
                      <td colspan="12" class="t-center muted" style="padding:20px;">No hay cotizaciones para mostrar.</td>
                    </tr>
                  <?php
                  else :
                    $grouped = [];
                    foreach ($paged_log as $index => $entry) {
                        $seller_name = trim((string)($entry['payload']['seller']['name'] ?? ''));
                        $group_key = $seller_name !== '' ? $seller_name : 'Sin vendedor';
                        if (!isset($grouped[$group_key])) {
                            $grouped[$group_key] = [];
                        }
                        $grouped[$group_key][$index] = $entry;
                    }
                    foreach ($grouped as $seller_label => $entries) {
                        ?>
                        <tr class="group-row">
                          <td colspan="12">
                            <strong><?php echo esc_html($seller_label); ?></strong>
                            <span class="muted small">— <?php echo esc_html(count($entries)); ?> cotización(es)</span>
                          </td>
                        </tr>
                        <?php
                        foreach ($entries as $index => $entry) {
                        $pdf_url = wp_nonce_url(
                            home_url('/agrocampo-cotizador/gestor?view=' . $index),
                            'acpdf_view_quote_' . $index
                        );
                        $edit_url = wp_nonce_url(
                            home_url('/agrocampo-cotizador?prefill=' . $index),
                            'acpdf_prefill_' . $index
                        );
                        $duplicate_url = wp_nonce_url(
                            home_url('/agrocampo-cotizador/gestor?duplicate=' . $index),
                            'acpdf_duplicate_' . $index
                        );
                        ?>
                        <tr>
                          <td><?php echo esc_html($entry['date_iso'] ?? ''); ?></td>
                          <td><strong><?php echo esc_html($entry['quote_no'] ?? ''); ?></strong></td>
                          <td><?php echo esc_html($entry['payload']['internal_no'] ?? ''); ?></td>
                          <td><?php echo esc_html($entry['payload']['serial_no'] ?? ''); ?></td>
                          <td><?php echo esc_html($entry['model'] ?? ''); ?></td>
                          <td><?php echo esc_html($entry['client'] ?? ''); ?></td>
                          <td class="t-center"><?php echo esc_html($entry['maint_hours'] ?? ''); ?></td>
                          <td><?php echo esc_html($entry['parts_type'] ?? ''); ?></td>
                          <td class="t-right"><?php echo esc_html(number_format(floatval($entry['neto'] ?? 0), 0, ',', '.')); ?></td>
                          <td class="t-right"><?php echo esc_html(number_format(floatval($entry['iva'] ?? 0), 0, ',', '.')); ?></td>
                          <td class="t-right"><strong><?php echo esc_html(number_format(floatval($entry['total'] ?? 0), 0, ',', '.')); ?></strong></td>
                          <td>
                            <div class="action-buttons">
                              <?php if (!empty($entry['payload']) && is_array($entry['payload'])) : ?>
                                <a class="btn btn-ghost btn-sm" href="<?php echo esc_url($pdf_url); ?>" target="_blank" rel="noopener noreferrer" title="Ver PDF">📄 Ver PDF</a>
                                <a class="btn btn-ghost btn-sm" href="<?php echo esc_url($edit_url); ?>" target="_blank" rel="noopener noreferrer" title="Editar">✏️ Editar</a>
                                <a class="btn btn-ghost btn-sm" href="<?php echo esc_url($duplicate_url); ?>" title="Duplicar">📋 Duplicar</a>
                              <?php endif; ?>
                              <form method="post" action="<?php echo esc_url($base_url); ?>" style="display:inline;" onsubmit="return confirm('¿Eliminar la cotización N° <?php echo esc_js($entry['quote_no'] ?? ''); ?>?');">
                                <input type="hidden" name="acpdf_action" value="delete">
                                <input type="hidden" name="quote_index" value="<?php echo esc_attr($index); ?>">
                                <input type="hidden" name="acpdf_nonce" value="<?php echo esc_attr(wp_create_nonce('acpdf_delete_quote_' . $index)); ?>">
                                <?php foreach ($filter_args as $k => $v) : ?>
                                  <input type="hidden" name="<?php echo esc_attr($k); ?>" value="<?php echo esc_attr($v); ?>">
                                <?php endforeach; ?>
                                <button type="submit" class="btn btn-danger btn-sm" title="Eliminar">🗑️ Eliminar</button>
                              </form>
                            </div>
                          </td>
                        </tr>
                        <?php
                        }
                    }
                  endif;
                  ?>
                </tbody>
              </table>
              </div>

              <?php if ($total_pages > 1) : ?>
              <!-- Paginación -->
              <div class="pagination">
                <?php
                // Previous
                if ($current_page > 1) {
                    $prev_url = add_query_arg(array_merge($filter_args, ['paged' => $current_page - 1]), $base_url);
                    echo '<a href="' . esc_url($prev_url) . '">← Anterior</a>';
                } else {
                    echo '<span class="disabled">← Anterior</span>';
                }

                // Page numbers
                $range = 2;
                $start = max(1, $current_page - $range);
                $end = min($total_pages, $current_page + $range);

                if ($start > 1) {
                    $first_url = add_query_arg(array_merge($filter_args, ['paged' => 1]), $base_url);
                    echo '<a href="' . esc_url($first_url) . '">1</a>';
                    if ($start > 2) echo '<span>...</span>';
                }

                for ($i = $start; $i <= $end; $i++) {
                    if ($i === $current_page) {
                        echo '<span class="current">' . $i . '</span>';
                    } else {
                        $page_url = add_query_arg(array_merge($filter_args, ['paged' => $i]), $base_url);
                        echo '<a href="' . esc_url($page_url) . '">' . $i . '</a>';
                    }
                }

                if ($end < $total_pages) {
                    if ($end < $total_pages - 1) echo '<span>...</span>';
                    $last_url = add_query_arg(array_merge($filter_args, ['paged' => $total_pages]), $base_url);
                    echo '<a href="' . esc_url($last_url) . '">' . $total_pages . '</a>';
                }

                // Next
                if ($current_page < $total_pages) {
                    $next_url = add_query_arg(array_merge($filter_args, ['paged' => $current_page + 1]), $base_url);
                    echo '<a href="' . esc_url($next_url) . '">Siguiente →</a>';
                } else {
                    echo '<span class="disabled">Siguiente →</span>';
                }
                ?>

                <span class="page-info">
                  Mostrando <?php echo $offset + 1; ?>-<?php echo min($offset + $per_page, $total_items); ?> de <?php echo $total_items; ?>
                </span>
              </div>
              <?php endif; ?>

            </div>
            
<?php include ACPDF_DIR . 'templates/partials/foot.php'; ?>
