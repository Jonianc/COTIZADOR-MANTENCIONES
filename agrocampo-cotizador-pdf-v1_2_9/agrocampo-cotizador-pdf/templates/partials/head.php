<?php
if (!defined('ABSPATH')) { exit; }

nocache_headers();
header('Content-Type: text/html; charset=utf-8');

$css_url = isset($css_url) ? $css_url : (ACPDF_URL . 'assets/cotizador.css?ver=' . ACPDF_VER);
$title   = isset($title) ? $title : 'Agrocampo – Cotizador PDF';
$menu_links = isset($menu_links) ? $menu_links : [];
?><!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title><?php echo esc_html($title); ?></title>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="<?php echo esc_url($css_url); ?>" />
</head>
<body>
<div class="wrap">
<?php if (!empty($menu_links)) : ?>
  <nav class="acpdf-menu" aria-label="Navegación">
    <?php foreach ($menu_links as $link) :
      if (empty($link['url']) || empty($link['label'])) { continue; }
      ?>
      <a href="<?php echo esc_url($link['url']); ?>"><?php echo esc_html($link['label']); ?></a>
    <?php endforeach; ?>
  </nav>
<?php endif; ?>
