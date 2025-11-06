<?php
/**
 * Intégration pour la génération de fichiers GSAP
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Applique une configuration de fichier GSAP
 *
 * @param array $config_data Données de configuration
 * @param string $element Élément ciblé (non utilisé)
 *
 * @return bool
 */
function up_config_apply_gsap_files($config_data, $element = '') {
    if (empty($config_data)) {
        return false;
    }

    // Les fichiers physiques sont gérés par le coeur du plugin.
    return true;
}
