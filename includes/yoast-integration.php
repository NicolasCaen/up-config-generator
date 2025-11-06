<?php
/**
 * Intégration Yoast SEO
 * Fonctions callback pour le plugin Yoast SEO
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Importe la configuration courante de Yoast SEO
 * 
 * @param int $element_id Non utilisé pour Yoast (configuration globale)
 * @return array Données de configuration
 */
function up_config_import_yoast($element_id = null) {
    // Vérifier si Yoast est actif
    if (!defined('WPSEO_VERSION')) {
        return [];
    }
    
    // Récupérer les options actuelles
    $options = get_option('wpseo_titles');
    $social_options = get_option('wpseo_social');
    
    $config_data = [];
    
    // Extraire les paramètres généraux
    if (is_array($options)) {
        $config_data['site_name'] = isset($options['website_name']) ? $options['website_name'] : '';
        $config_data['separator'] = isset($options['separator']) ? $options['separator'] : '';
        $config_data['homepage_title'] = isset($options['title-home-wpseo']) ? $options['title-home-wpseo'] : '';
        $config_data['homepage_description'] = isset($options['metadesc-home-wpseo']) ? $options['metadesc-home-wpseo'] : '';
        $config_data['company_or_person'] = isset($options['company_or_person']) ? $options['company_or_person'] : '';
        $config_data['company_name'] = isset($options['company_name']) ? $options['company_name'] : '';
        $config_data['company_logo'] = isset($options['company_logo']) ? $options['company_logo'] : '';
    }
    
    // Extraire les paramètres sociaux
    if (is_array($social_options)) {
        $config_data['social_facebook'] = isset($social_options['facebook_site']) ? $social_options['facebook_site'] : '';
        $config_data['social_twitter'] = isset($social_options['twitter_site']) ? $social_options['twitter_site'] : '';
        $config_data['social_instagram'] = isset($social_options['instagram_url']) ? $social_options['instagram_url'] : '';
        $config_data['social_linkedin'] = isset($social_options['linkedin_url']) ? $social_options['linkedin_url'] : '';
    }
    
    return $config_data;
}

/**
 * Applique une configuration Yoast SEO
 * 
 * @param array $config_data Données de configuration
 * @param int $element_id Non utilisé pour Yoast (configuration globale)
 * @return bool Succès ou échec
 */
function up_config_apply_yoast($config_data, $element_id = null) {
    // Vérifier si Yoast est actif
    if (!defined('WPSEO_VERSION')) {
        return false;
    }
    
    // Récupérer les options actuelles
    $options = get_option('wpseo_titles');
    $social_options = get_option('wpseo_social');
    
    if (!is_array($options)) {
        $options = [];
    }
    
    if (!is_array($social_options)) {
        $social_options = [];
    }
    
    // Mettre à jour les paramètres généraux
    if (isset($config_data['site_name'])) {
        $options['website_name'] = sanitize_text_field($config_data['site_name']);
    }
    
    if (isset($config_data['separator'])) {
        $options['separator'] = sanitize_text_field($config_data['separator']);
    }
    
    if (isset($config_data['homepage_title'])) {
        $options['title-home-wpseo'] = sanitize_text_field($config_data['homepage_title']);
    }
    
    if (isset($config_data['homepage_description'])) {
        $options['metadesc-home-wpseo'] = sanitize_textarea_field($config_data['homepage_description']);
    }
    
    if (isset($config_data['company_or_person'])) {
        $options['company_or_person'] = sanitize_text_field($config_data['company_or_person']);
    }
    
    if (isset($config_data['company_name'])) {
        $options['company_name'] = sanitize_text_field($config_data['company_name']);
    }
    
    if (isset($config_data['company_logo'])) {
        $options['company_logo'] = esc_url_raw($config_data['company_logo']);
    }
    
    // Mettre à jour les paramètres sociaux
    if (isset($config_data['social_facebook'])) {
        $social_options['facebook_site'] = esc_url_raw($config_data['social_facebook']);
    }
    
    if (isset($config_data['social_twitter'])) {
        $social_options['twitter_site'] = sanitize_text_field($config_data['social_twitter']);
    }
    
    if (isset($config_data['social_instagram'])) {
        $social_options['instagram_url'] = esc_url_raw($config_data['social_instagram']);
    }
    
    if (isset($config_data['social_linkedin'])) {
        $social_options['linkedin_url'] = esc_url_raw($config_data['social_linkedin']);
    }
    
    // Sauvegarder les options
    update_option('wpseo_titles', $options);
    update_option('wpseo_social', $social_options);
    
    return true;
}
