<?php
/**
 * Intégration pour les fonctions PHP
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Applique une configuration de fonction PHP.
 *
 * @param array $config_data Données de configuration.
 * @param string $element Élément ciblé (inutilisé).
 *
 * @return bool
 */
function up_config_apply_functions($config_data, $element = '') {
    if (empty($config_data)) {
        return false;
    }

    // Les fichiers sont déjà gérés par le coeur du plugin.
    return true;
}
