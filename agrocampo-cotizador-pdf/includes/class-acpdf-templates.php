<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Loads maintenance templates ("pautas") from JSON.
 * Structure: { "brands": { "<brand_key>": { "label": "...", "templates": { "<template_key>": { ... }}}}}
 */
class ACPDF_Templates {

  private static $catalog = null;

  public static function catalog() : array {
    if (self::$catalog !== null) {
      return self::$catalog;
    }

    $file = ACPDF_DIR . 'includes/data/pautas.json';
    if (!file_exists($file)) {
      self::$catalog = ['brands' => []];
      return self::$catalog;
    }

    $raw = file_get_contents($file);
    $data = json_decode($raw, true);

    if (!is_array($data)) {
      self::$catalog = ['brands' => []];
      return self::$catalog;
    }

    // Normalize minimal expected keys
    if (!isset($data['brands']) || !is_array($data['brands'])) {
      $data['brands'] = [];
    }

    self::$catalog = $data;
    return self::$catalog;
  }

  public static function brands_for_select() : array {
    $cat = self::catalog();
    $out = [];
    foreach (($cat['brands'] ?? []) as $k => $b) {
      $label = is_array($b) ? ($b['label'] ?? $k) : $k;
      $out[$k] = $label;
    }
    return $out;
  }

  public static function templates_for_brand(string $brand_key) : array {
    $cat = self::catalog();
    $b = $cat['brands'][$brand_key] ?? null;
    if (!is_array($b) || !isset($b['templates']) || !is_array($b['templates'])) {
      return [];
    }
    $out = [];
    foreach ($b['templates'] as $tk => $tpl) {
      $out[$tk] = is_array($tpl) ? ($tpl['label'] ?? $tk) : $tk;
    }
    return $out;
  }
}
