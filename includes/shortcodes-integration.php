<?php
/**
 * Intégration Shortcodes
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Applique une configuration de shortcode.
 *
 * @param array $config_data Données de configuration.
 * @param string $element Element ciblé (non utilisé).
 *
 * @return bool
 */
function up_config_apply_shortcodes($config_data, $element = '') {
    if (empty($config_data)) {
        return false;
    }

    // Les fichiers physiques ont déjà été créés.
    // Optionnellement, on pourrait exécuter du code supplémentaire ici.
    return true;
}

/**
 * Import du shortcode (non implémenté pour le moment).
 */
function up_config_import_shortcodes($element = '') {
    return [];
}
