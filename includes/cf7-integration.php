<?php
/**
 * Intégration Contact Form 7
 * Fonctions callback pour le plugin Contact Form 7
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Récupère la liste des formulaires Contact Form 7
 * 
 * @return array Tableau associatif [id => titre]
 */
function up_config_get_cf7_forms() {
    $options = [];
    
    // Vérifier si CF7 est actif
    if (!class_exists('WPCF7_ContactForm')) {
        return $options;
    }
    
    // Récupérer tous les formulaires
    $args = [
        'post_type' => 'wpcf7_contact_form',
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC'
    ];
    
    $forms = get_posts($args);
    
    foreach ($forms as $form) {
        $options[$form->ID] = $form->post_title;
    }
    
    return $options;
}

/**
 * Applique une configuration à un formulaire Contact Form 7
 * 
 * @param array $config_data Données de configuration
 * @param int $form_id ID du formulaire cible
 * @return bool Succès ou échec
 */
function up_config_apply_cf7($config_data, $form_id) {
    // Vérifier si CF7 est actif
    if (!class_exists('WPCF7_ContactForm')) {
        return false;
    }
    
    if (empty($form_id)) {
        return false;
    }
    
    // Récupérer le formulaire
    $form = WPCF7_ContactForm::get_instance($form_id);
    
    if (!$form) {
        return false;
    }
    
    // Récupérer les propriétés actuelles
    $properties = $form->get_properties();
    
    // Mettre à jour les paramètres de mail
    if (isset($config_data['mail_to'])) {
        $properties['mail']['recipient'] = sanitize_text_field($config_data['mail_to']);
    }
    
    if (isset($config_data['mail_from'])) {
        $properties['mail']['sender'] = sanitize_text_field($config_data['mail_from']);
    }
    
    if (isset($config_data['mail_subject'])) {
        $properties['mail']['subject'] = sanitize_text_field($config_data['mail_subject']);
    }
    
    if (isset($config_data['mail_body'])) {
        $properties['mail']['body'] = wp_kses_post($config_data['mail_body']);
    }
    
    // Mettre à jour les paramètres de mail 2 (email de confirmation)
    if (isset($config_data['mail2_active'])) {
        $properties['mail_2']['active'] = (bool) $config_data['mail2_active'];
    }
    
    if (isset($config_data['mail2_to'])) {
        $properties['mail_2']['recipient'] = sanitize_text_field($config_data['mail2_to']);
    }
    
    if (isset($config_data['mail2_subject'])) {
        $properties['mail_2']['subject'] = sanitize_text_field($config_data['mail2_subject']);
    }
    
    if (isset($config_data['mail2_body'])) {
        $properties['mail_2']['body'] = wp_kses_post($config_data['mail2_body']);
    }
    
    // Mettre à jour les messages
    if (!isset($properties['messages'])) {
        $properties['messages'] = [];
    }
    
    if (isset($config_data['messages_success'])) {
        $properties['messages']['mail_sent_ok'] = sanitize_text_field($config_data['messages_success']);
    }
    
    if (isset($config_data['messages_error'])) {
        $properties['messages']['mail_sent_ng'] = sanitize_text_field($config_data['messages_error']);
    }
    
    // Appliquer les propriétés
    $form->set_properties($properties);
    
    // Sauvegarder
    $form->save();
    
    return true;
}
