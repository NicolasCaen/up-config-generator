<?php
/**
 * Plugin Name: UP Config Generator
 * Description: Permet de créer et gérer des configurations pré-établies pour différents plugins WordPress (CF7, Yoast, etc.)
 * Version: 0.1.9.0
 * Author: GEHIN Nicolas
 */

if (!defined('ABSPATH')) {
    exit;
}

define('UP_CONFIG_GENERATOR_VERSION', '0.1.9.0');
define('UP_CONFIG_GENERATOR_PATH', plugin_dir_path(__FILE__));
define('UP_CONFIG_GENERATOR_URL', plugin_dir_url(__FILE__));

// Inclure les fichiers d'intégration
require_once UP_CONFIG_GENERATOR_PATH . 'includes/cf7-integration.php';
require_once UP_CONFIG_GENERATOR_PATH . 'includes/yoast-integration.php';
require_once UP_CONFIG_GENERATOR_PATH . 'includes/shortcodes-integration.php';
require_once UP_CONFIG_GENERATOR_PATH . 'includes/functions-integration.php';
require_once UP_CONFIG_GENERATOR_PATH . 'includes/scss-files-integration.php';
require_once UP_CONFIG_GENERATOR_PATH . 'includes/js-files-integration.php';
require_once UP_CONFIG_GENERATOR_PATH . 'includes/gsap-files-integration.php';
require_once UP_CONFIG_GENERATOR_PATH . 'includes/index-generator-integration.php';

class UP_Config_Generator {
    
    private static $instance = null;
    private $preview_allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    private $preview_allowed_mimes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp'
    ];
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
    }
    
    private function init_hooks() {
        add_action('admin_menu', [$this, 'register_admin_menus']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('admin_post_up_config_save', [$this, 'handle_save_configuration']);
        add_action('admin_post_up_config_delete', [$this, 'handle_delete_configuration']);
        add_action('admin_post_up_config_apply', [$this, 'handle_apply_configuration']);
    }
    
    /**
     * Crée les dossiers de configuration si nécessaire
     */
    private function ensure_config_directories() {
        $config_files = $this->get_config_files();
        
        foreach ($config_files as $slug => $file_path) {
            $config_dir = UP_CONFIG_GENERATOR_PATH . 'config/' . $slug;
            if (!file_exists($config_dir)) {
                wp_mkdir_p($config_dir);
                // Créer un fichier index.php vide pour sécurité
                file_put_contents($config_dir . '/index.php', '<?php // Silence is golden');
            }
        }
    }
    
    /**
     * Récupère la liste des fichiers de configuration
     */
    private function get_config_files() {
        $config_dir = UP_CONFIG_GENERATOR_PATH . 'plugin-config/';
        $files = [];
        
        if (is_dir($config_dir)) {
            $scan = glob($config_dir . '*.json');
            foreach ($scan as $file) {
                $slug = basename($file, '.json');
                $files[$slug] = $file;
            }
        }
        
        return $files;
    }
    
    /**
     * Charge un fichier de configuration JSON
     */
    public function load_config($slug) {
        $file_path = UP_CONFIG_GENERATOR_PATH . 'plugin-config/' . $slug . '.json';
        
        if (!file_exists($file_path)) {
            return false;
        }
        
        $json = file_get_contents($file_path);
        $config = json_decode($json, true);
        
        return $config;
    }
    
    /**
     * Enregistre les menus d'administration pour chaque plugin
     */
    public function register_admin_menus() {
        $this->ensure_config_directories();
        
        // Menu principal
        add_menu_page(
            'Configurations',
            'Configurations',
            'manage_options',
            'up-config-generator',
            [$this, 'render_main_page'],
            'dashicons-admin-settings',
            30
        );
        
        // Sous-menus pour chaque plugin
        $config_files = $this->get_config_files();
        
        foreach ($config_files as $slug => $file_path) {
            $config = $this->load_config($slug);
            if ($config && !empty($config['name'])) {
                add_submenu_page(
                    'up-config-generator',
                    $config['name'],
                    $config['name'],
                    'manage_options',
                    'up-config-' . $slug,
                    function() use ($slug, $config) {
                        $this->render_plugin_page($slug, $config);
                    }
                );
            }
        }
    }
    
    /**
     * Affiche la page principale
     */
    public function render_main_page() {
        echo '<div class="wrap">';
        echo '<h1>Générateur de Configurations</h1>';
        echo '<p>Sélectionnez un plugin dans le menu pour gérer ses configurations.</p>';
        
        $config_files = $this->get_config_files();
        
        if (!empty($config_files)) {
            echo '<ul class="up-config-plugins-list">';
            foreach ($config_files as $slug => $file_path) {
                $config = $this->load_config($slug);
                if ($config && !empty($config['name'])) {
                    $url = admin_url('admin.php?page=up-config-' . $slug);
                    $count = count($this->get_saved_configs($slug));
                    echo '<li><a href="' . esc_url($url) . '">' . esc_html($config['name']) . '</a> <span class="count">(' . $count . ')</span></li>';
                }
            }
            echo '</ul>';
        }
        
        echo '</div>';
    }
    
    /**
     * Affiche la page de configuration pour un plugin spécifique
     */
    public function render_plugin_page($plugin_slug, $plugin_config) {
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        $config_file = isset($_GET['config']) ? sanitize_file_name($_GET['config']) : '';
        
        if ($action === 'edit' && $config_file) {
            $this->render_edit_form($plugin_slug, $plugin_config, $config_file);
        } elseif ($action === 'new') {
            $this->render_edit_form($plugin_slug, $plugin_config, '');
        } else {
            $this->render_config_list($plugin_slug, $plugin_config);
        }
    }
    
    /**
     * Affiche la liste des configurations pour un plugin
     */
    private function render_config_list($plugin_slug, $plugin_config) {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html($plugin_config['name']) . ' - Configurations</h1>';
        
        $add_url = admin_url('admin.php?page=up-config-' . $plugin_slug . '&action=new');
        echo '<a href="' . esc_url($add_url) . '" class="page-title-action">Ajouter</a>';
        
        // Messages de notification
        if (isset($_GET['updated'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Configuration enregistrée avec succès.</p></div>';
        }
        if (isset($_GET['applied'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Configuration appliquée avec succès.</p></div>';
        }
        if (isset($_GET['deleted'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Configuration supprimée.</p></div>';
        }
        
        $configs = $this->get_saved_configs($plugin_slug);
        
        if (!empty($configs)) {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr><th>Aperçu</th><th>Titre</th><th>Description</th><th>Date</th><th>Actions</th></tr></thead>';
            echo '<tbody>';
            
            foreach ($configs as $config_file => $config_data) {
                $edit_url = admin_url('admin.php?page=up-config-' . $plugin_slug . '&action=edit&config=' . urlencode($config_file));
                $delete_url = wp_nonce_url(
                    admin_url('admin-post.php?action=up_config_delete&plugin=' . $plugin_slug . '&config=' . urlencode($config_file)),
                    'up_config_delete_' . $config_file
                );
                $apply_url = wp_nonce_url(
                    admin_url('admin-post.php?action=up_config_apply&plugin=' . $plugin_slug . '&config=' . urlencode($config_file)),
                    'up_config_apply_' . $config_file
                );
                
                $date = isset($config_data['date']) ? date('d/m/Y H:i', strtotime($config_data['date'])) : '-';
                
                $preview = isset($config_data['preview']) ? $config_data['preview'] : null;

                echo '<tr>';
                echo '<td class="up-config-preview-cell">';
                if (!empty($preview['url'])) {
                    echo '<img src="' . esc_url($preview['url']) . '" alt="Aperçu" class="up-config-preview-img" />';
                } else {
                    echo '<span class="up-config-preview-placeholder">—</span>';
                }
                echo '</td>';
                echo '<td><strong>' . esc_html($config_data['title']) . '</strong></td>';
                echo '<td>' . esc_html($config_data['description']) . '</td>';
                echo '<td>' . esc_html($date) . '</td>';
                echo '<td>';
                echo '<a href="' . esc_url($edit_url) . '">Modifier</a> | ';
                echo '<a href="' . esc_url($apply_url) . '" class="button button-small">Appliquer</a> | ';
                echo '<a href="' . esc_url($delete_url) . '" class="submitdelete" onclick="return confirm(\'Supprimer cette configuration ?\')">Supprimer</a>';
                echo '</td>';
                echo '</tr>';
            }
            
            echo '</tbody></table>';
        } else {
            echo '<p>Aucune configuration trouvée.</p>';
        }
        
        echo '</div>';
    }
    
    /**
     * Affiche le formulaire d'édition
     */
    private function render_edit_form($plugin_slug, $plugin_config, $config_file) {
        $saved_data = [];
        $is_new = empty($config_file);
        
        // Gérer l'importation de la configuration courante
        if (isset($_POST['import_current_config']) && wp_verify_nonce($_POST['up_config_import_nonce'], 'up_config_import')) {
            $import_element = isset($_POST['import_element']) ? sanitize_text_field($_POST['import_element']) : '';
            $saved_data = $this->import_current_configuration($plugin_slug, $plugin_config, $import_element);
        }
        // Charger les données si édition
        elseif (!$is_new) {
            $saved_data = $this->load_saved_config($plugin_slug, $config_file);
        }
        
        $current_generated_slug = isset($saved_data['slug']) ? $saved_data['slug'] : '';
        $plugin_uses_slug = in_array($plugin_slug, ['shortcodes', 'functions', 'scss-files', 'js-files', 'gsap-files'], true);

        echo '<div class="wrap">';
        echo '<h1>' . ($is_new ? 'Nouvelle' : 'Modifier') . ' Configuration - ' . esc_html($plugin_config['name']) . '</h1>';

        // Prévisualisation (pour index-generator)
        $preview_data = null;
        if (isset($_POST['preview_config']) && !empty($plugin_config['apply_callback']) && $plugin_config['apply_callback'] === 'up_config_apply_index_generator') {
            $posted_fields = isset($_POST['config_fields']) && is_array($_POST['config_fields']) ? array_map('wp_unslash', $_POST['config_fields']) : [];
            if (function_exists('up_config_preview_index_generator')) {
                $preview_data = up_config_preview_index_generator($posted_fields);
            }
        }

        if ($preview_data) {
            echo '<div class="notice notice-info"><p><strong>Prévisualisation</strong></p>';
            echo '<p><strong>Fichier cible :</strong> <code>' . esc_html($preview_data['target']) . '</code></p>';
            echo '<details open><summary>Bloc généré</summary><pre style="max-height:300px;overflow:auto;">' . esc_html($preview_data['block']) . '</pre></details>';
            echo '<details open><summary>Fichier complet (simulation)</summary><pre style="max-height:400px;overflow:auto;">' . esc_html($preview_data['final']) . '</pre></details>';
            echo '</div>';
        }
        
        // Section d'importation de configuration courante
        if ($is_new) {
            echo '<div class="up-config-import-section">';
            echo '<h2>Importer la configuration courante</h2>';
            echo '<p class="description">Vous pouvez importer la configuration actuellement appliquée pour pré-remplir le formulaire.</p>';
            
            // Si le plugin a un sélecteur d'élément, afficher un sélecteur
            if (!empty($plugin_config['element_selector'])) {
                $selector_config = $plugin_config['element_selector'];
                
                echo '<form method="post" action="" class="up-config-import-form">';
                wp_nonce_field('up_config_import', 'up_config_import_nonce');
                echo '<input type="hidden" name="import_current_config" value="1">';
                
                if (!empty($selector_config['callback']) && is_callable($selector_config['callback'])) {
                    $options = call_user_func($selector_config['callback']);
                    echo '<label for="import_element">' . esc_html($selector_config['label']) . ' : </label>';
                    echo '<select id="import_element" name="import_element">';
                    echo '<option value="">-- Sélectionner --</option>';
                    foreach ($options as $value => $label) {
                        echo '<option value="' . esc_attr($value) . '">' . esc_html($label) . '</option>';
                    }
                    echo '</select> ';
                }
                
                echo '<button type="submit" class="button button-secondary">Importer la configuration</button>';
                echo '</form>';
            } else {
                // Pas de sélecteur, importer directement
                echo '<form method="post" action="" class="up-config-import-form">';
                wp_nonce_field('up_config_import', 'up_config_import_nonce');
                echo '<input type="hidden" name="import_current_config" value="1">';
                echo '<button type="submit" class="button button-secondary">Importer la configuration courante</button>';
                echo '</form>';
            }
            
            echo '</div>';
            echo '<hr>';
        }
        
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" enctype="multipart/form-data">';
        echo '<input type="hidden" name="action" value="up_config_save">';
        wp_nonce_field('up_config_save', 'up_config_nonce');
        echo '<input type="hidden" name="plugin_slug" value="' . esc_attr($plugin_slug) . '">';
        echo '<input type="hidden" name="config_file" value="' . esc_attr($config_file) . '">';
        if ($plugin_uses_slug) {
            echo '<input type="hidden" name="config_generated_slug" value="' . esc_attr($current_generated_slug) . '">';
        }
        
        echo '<table class="form-table">';
        
        // Titre
        echo '<tr>';
        echo '<th><label for="config_title">Titre</label></th>';
        echo '<td><input type="text" id="config_title" name="config_title" class="regular-text" value="' . (isset($saved_data['title']) ? esc_attr($saved_data['title']) : '') . '" required></td>';
        echo '</tr>';
        
        // Description
        echo '<tr>';
        echo '<th><label for="config_description">Description</label></th>';
        echo '<td><textarea id="config_description" name="config_description" rows="3" class="large-text">' . (isset($saved_data['description']) ? esc_textarea($saved_data['description']) : '') . '</textarea></td>';
        echo '</tr>';

        // Aperçu
        $current_preview = isset($saved_data['preview']) ? $saved_data['preview'] : null;
        echo '<tr class="up-config-preview-row">';
        echo '<th><label for="config_preview">Aperçu</label></th>';
        echo '<td>';
        if ($current_preview && !empty($current_preview['url'])) {
            echo '<div class="up-config-current-preview">';
            echo '<img src="' . esc_url($current_preview['url']) . '" alt="Aperçu actuel" class="up-config-preview-img" />';
            echo '<label><input type="checkbox" name="config_preview_remove" value="1"> Supprimer l\'aperçu</label>';
            echo '</div>';
        } else {
            echo '<p class="description">Aucun aperçu n\'est associé à cette configuration.</p>';
        }
        echo '<input type="file" id="config_preview" name="config_preview" accept="image/png,image/jpeg,image/gif,image/webp">';
        echo '<p class="description">Formats acceptés : JPG, PNG, GIF, WebP.</p>';
        echo '</td>';
        echo '</tr>';
        
        // Champs de configuration dynamiques
        if (!empty($plugin_config['fields'])) {
            foreach ($plugin_config['fields'] as $field) {
                $field_value = isset($saved_data['fields'][$field['id']]) ? $saved_data['fields'][$field['id']] : '';
                if ($field_value === '' && isset($field['default'])) {
                    $field_value = $field['default'];
                }
                
                echo '<tr class="up-field-row up-field-row-' . esc_attr($field['id']) . '">';
                echo '<th><label for="field_' . esc_attr($field['id']) . '">' . esc_html($field['label']) . '</label></th>';
                echo '<td>';
                
                switch ($field['type']) {
                    case 'text':
                        echo '<input type="text" id="field_' . esc_attr($field['id']) . '" name="config_fields[' . esc_attr($field['id']) . ']" class="regular-text" value="' . esc_attr($field_value) . '">';
                        break;
                    case 'textarea':
                        echo '<textarea id="field_' . esc_attr($field['id']) . '" name="config_fields[' . esc_attr($field['id']) . ']" rows="5" class="large-text">' . esc_textarea($field_value) . '</textarea>';
                        break;
                    case 'select':
                        echo '<select id="field_' . esc_attr($field['id']) . '" name="config_fields[' . esc_attr($field['id']) . ']">';
                        foreach ($field['options'] as $option_value => $option_label) {
                            $selected = ($field_value == $option_value) ? 'selected' : '';
                            echo '<option value="' . esc_attr($option_value) . '" ' . $selected . '>' . esc_html($option_label) . '</option>';
                        }
                        echo '</select>';
                        break;
                    case 'file':
                        // Champ de type fichier (CSS, SCSS, JS, etc.)
                        $file_type = isset($field['file_type']) ? $field['file_type'] : 'css';
                        $default_file_path = isset($field['file_path']) ? $field['file_path'] : '';
                        $current_file_path = isset($saved_data['file_paths'][$field['id']]) ? $saved_data['file_paths'][$field['id']] : $default_file_path;
                        
                        echo '<div class="up-config-file-field">';
                        echo '<textarea id="field_' . esc_attr($field['id']) . '" name="config_fields[' . esc_attr($field['id']) . ']" class="up-config-code-editor" data-mode="' . esc_attr($file_type) . '" rows="15">' . esc_textarea($field_value) . '</textarea>';
                        
                        if (!empty($current_file_path)) {
                            echo '<p class="description"><strong>Fichier de destination :</strong> <code>' . esc_html($current_file_path) . '</code></p>';
                        }
                        
                        echo '<input type="hidden" name="config_file_paths[' . esc_attr($field['id']) . ']" value="' . esc_attr($current_file_path) . '">';
                        echo '</div>';
                        break;
                }
                
                if (!empty($field['description'])) {
                    echo '<p class="description">' . esc_html($field['description']) . '</p>';
                }

                if ($plugin_uses_slug && in_array($field['id'], ['shortcode_name', 'function_name', 'scss_name', 'js_name', 'gsap_name'], true) && !empty($current_generated_slug)) {
                    echo '<p class="description">Slug généré : <code>' . esc_html($current_generated_slug) . '</code></p>';
                }
                
                echo '</td>';
                echo '</tr>';
            }
        }
        
        // Sélecteur d'élément si défini
        if (!empty($plugin_config['element_selector'])) {
            $selector_config = $plugin_config['element_selector'];
            $saved_element = isset($saved_data['element']) ? $saved_data['element'] : '';
            
            echo '<tr>';
            echo '<th><label for="config_element">' . esc_html($selector_config['label']) . '</label></th>';
            echo '<td>';
            
            // Appel de la fonction callback pour générer les options
            if (!empty($selector_config['callback']) && is_callable($selector_config['callback'])) {
                $options = call_user_func($selector_config['callback']);
                echo '<select id="config_element" name="config_element">';
                echo '<option value="">-- Sélectionner --</option>';
                foreach ($options as $value => $label) {
                    $selected = ($saved_element == $value) ? 'selected' : '';
                    echo '<option value="' . esc_attr($value) . '" ' . $selected . '>' . esc_html($label) . '</option>';
                }
                echo '</select>';
            }
            
            if (!empty($selector_config['description'])) {
                echo '<p class="description">' . esc_html($selector_config['description']) . '</p>';
            }
            
            echo '</td>';
            echo '</tr>';
        }
        
        // Case à cocher "Appliquer"
        echo '<tr>';
        echo '<th><label for="apply_config">Appliquer</label></th>';
        echo '<td>';
        echo '<label><input type="checkbox" id="apply_config" name="apply_config" value="1"> Appliquer à la configuration</label>';
        echo '<p class="description">Si cochée, la configuration sera appliquée immédiatement après l\'enregistrement.</p>';
        echo '</td>';
        echo '</tr>';
        
        echo '</table>';

        // Boutons: Prévisualiser + Enregistrer
        if (!empty($plugin_config['apply_callback']) && $plugin_config['apply_callback'] === 'up_config_apply_index_generator') {
            $current_url = admin_url('admin.php?page=up-config-' . $plugin_slug . ($is_new ? '&action=new' : '&action=edit&config=' . urlencode($config_file)));
            echo '<p class="submit">';
            echo '<button type="submit" name="preview_config" value="1" class="button" formaction="' . esc_url($current_url) . '" formmethod="post">Prévisualiser</button> ';
            echo get_submit_button($is_new ? 'Créer' : 'Mettre à jour', 'primary', '', false);
            echo '</p>';
        } else {
            submit_button($is_new ? 'Créer' : 'Mettre à jour');
        }
        
        echo '</form>';

        // Script de visibilité dynamique pour index-generator
        if ($plugin_slug === 'index-generator') {
            echo '<script>(function(){
                function q(id){return document.getElementById(id);} 
                function row(id){return document.querySelector("tr.up-field-row-"+id);} 
                function show(id, vis){var el=row(id); if(el){el.style.display = vis ? "" : "none";}}
                function onChange(){
                    var type = q("field_content_type") ? q("field_content_type").value : "";
                    var mode = q("field_generation_mode") ? q("field_generation_mode").value : "";

                    // Champs communs visibles
                    show("target_relative_path", true);
                    show("scan_folder_relative", true);
                    show("scan_glob", true);
                    show("manual_entries", true);

                    // Délimiteurs et update selon mode
                    var inject = (mode === "inject_between_delimiters");
                    show("delimiter_start", inject);
                    show("delimiter_end", inject);
                    show("update_mode", inject);

                    // Header/Footer toujours visibles (utiles si création)
                    show("file_header", true);
                    show("file_footer", true);

                    // Spécifique SCSS
                    var isScss = (type === "import-scss");
                    show("scss_selection_mode", isScss);

                    // Handles/URL/Dépendances pour register/enqueue
                    var isScript = (type === "register-script" || type === "enqueue-script");
                    var isStyle = (type === "register-style" || type === "enqueue-style");
                    var isRegister = (type === "register-script" || type === "register-style");
                    var isEnqueue = (type === "enqueue-script" || type === "enqueue-style");

                    show("wp_handle_prefix", !isScss);
                    show("deps", !isScss);
                    show("base_url", !isScss); // utilisé pour register et enqueue avec URL
                    show("enqueue_has_url", isEnqueue && !isScss);
                    show("enqueue_in_footer", isScript && !isRegister);
                    show("enqueue_media", isStyle);
                }
                document.addEventListener("change", function(e){
                    if(e.target && (e.target.id==="field_content_type" || e.target.id==="field_generation_mode")){
                        onChange();
                    }
                });
                document.addEventListener("DOMContentLoaded", onChange);
                onChange();
            })();</script>';
        }
        echo '</div>';
    }
    
    /**
     * Récupère toutes les configurations sauvegardées pour un plugin
     */
    private function get_saved_configs($plugin_slug) {
        $config_dir = UP_CONFIG_GENERATOR_PATH . 'config/' . $plugin_slug;
        $configs = [];
        
        if (!is_dir($config_dir)) {
            return $configs;
        }
        
        $files = glob($config_dir . '/*.xml');
        
        foreach ($files as $file) {
            $filename = basename($file);
            $data = $this->load_saved_config($plugin_slug, $filename);
            if ($data) {
                $configs[$filename] = $data;
            }
        }
        
        return $configs;
    }
    
    /**
     * Charge une configuration depuis un fichier XML
     */
    private function load_saved_config($plugin_slug, $config_file) {
        $file_path = UP_CONFIG_GENERATOR_PATH . 'config/' . $plugin_slug . '/' . $config_file;
        
        if (!file_exists($file_path)) {
            return false;
        }
        
        $xml = simplexml_load_file($file_path);
        if (!$xml) {
            return false;
        }
        
        $data = [
            'title' => (string) $xml->title,
            'description' => (string) $xml->description,
            'date' => (string) $xml->date,
            'element' => (string) $xml->element,
            'fields' => [],
            'file_paths' => [],
            'preview' => $this->get_config_preview($plugin_slug, $config_file),
            'slug' => isset($xml->slug) ? (string) $xml->slug : ''
        ];
        
        if (isset($xml->fields->field)) {
            foreach ($xml->fields->field as $field) {
                $field_id = (string) $field['id'];

                if ($field_id === '_dynamic_slug') {
                    $data['slug'] = (string) $field;
                    continue;
                }

                if ($field_id === '_shortcode_slug' && empty($data['slug'])) {
                    $data['slug'] = (string) $field;
                    continue;
                }

                $data['fields'][$field_id] = (string) $field;
                
                // Charger le chemin de fichier si présent
                if (isset($field['file_path'])) {
                    $data['file_paths'][$field_id] = (string) $field['file_path'];
                }
            }
        }
        
        return $data;
    }

    /**
     * Retourne les informations d'aperçu (url, path, extension) si disponible
     */
    private function get_config_preview($plugin_slug, $config_file) {
        return $this->find_preview_file($plugin_slug, $config_file);
    }

    /**
     * Recherche un fichier d'aperçu correspondant au fichier XML
     */
    private function find_preview_file($plugin_slug, $config_file) {
        $config_dir = UP_CONFIG_GENERATOR_PATH . 'config/' . $plugin_slug . '/';
        if (!is_dir($config_dir)) {
            return null;
        }

        $base = pathinfo($config_file, PATHINFO_FILENAME);

        foreach ($this->preview_allowed_extensions as $extension) {
            $absolute = $config_dir . $base . '.' . $extension;
            if (file_exists($absolute)) {
                return [
                    'path' => $absolute,
                    'url' => UP_CONFIG_GENERATOR_URL . 'config/' . $plugin_slug . '/' . $base . '.' . $extension,
                    'extension' => $extension,
                    'filename' => $base . '.' . $extension
                ];
            }
        }

        return null;
    }

    /**
     * Supprime l'aperçu associé à une configuration
     */
    private function delete_config_preview($plugin_slug, $config_file) {
        $config_dir = UP_CONFIG_GENERATOR_PATH . 'config/' . $plugin_slug . '/';
        if (!is_dir($config_dir)) {
            return;
        }

        $base = pathinfo($config_file, PATHINFO_FILENAME);

        foreach ($this->preview_allowed_extensions as $extension) {
            $absolute = $config_dir . $base . '.' . $extension;
            if (file_exists($absolute)) {
                unlink($absolute);
            }
        }
    }

    /**
     * Enregistre un nouveau fichier d'aperçu
     */
    private function save_config_preview($plugin_slug, $config_file, $file) {
        if (empty($file) || !isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return;
        }

        if (!empty($file['error'])) {
            return;
        }

        $check = wp_check_filetype_and_ext($file['tmp_name'], $file['name'], $this->preview_allowed_mimes);
        $extension = '';

        if (!empty($check['ext']) && in_array($check['ext'], $this->preview_allowed_extensions, true)) {
            $extension = $check['ext'];
        } else {
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($extension, $this->preview_allowed_extensions, true)) {
                return;
            }
        }

        $config_dir = UP_CONFIG_GENERATOR_PATH . 'config/' . $plugin_slug . '/';
        wp_mkdir_p($config_dir);

        $base = pathinfo($config_file, PATHINFO_FILENAME);
        $destination = $config_dir . $base . '.' . $extension;

        // Supprimer les aperçus existants avant de sauvegarder le nouveau
        $this->delete_config_preview($plugin_slug, $config_file);

        if (move_uploaded_file($file['tmp_name'], $destination)) {
            @chmod($destination, 0644);
        }
    }

    /**
     * Génère un slug propre pour une configuration dynamique
     */
    private function generate_config_slug($name, $fallback_prefix = 'config') {
        $name = trim((string) $name);
        if (function_exists('remove_accents')) {
            $name = remove_accents($name);
        }
        $slug = sanitize_title($name);
        if (empty($slug)) {
            $fallback_prefix = sanitize_title($fallback_prefix);
            if (empty($fallback_prefix)) {
                $fallback_prefix = 'config';
            }
            $slug = $fallback_prefix . '-' . uniqid();
        }
        return $slug;
    }

    /**
     * Convertit un slug en CamelCase pour les fonctions JS
     */
    private function slug_to_camel($slug) {
        $parts = preg_split('/[^a-z0-9]+/i', (string) $slug);
        $parts = array_filter($parts);
        $camel = '';
        foreach ($parts as $part) {
            $camel .= ucfirst(strtolower($part));
        }
        return $camel ?: 'Shortcode';
    }

    /**
     * Retourne les chemins de fichiers pour une configuration de shortcode
     */
    private function get_shortcode_file_paths($slug, $theme) {
        $base_dir = 'themes/' . $theme . '/shortcodes/' . $slug;

        return [
            'shortcode_php' => $base_dir . '/' . $slug . '.php',
            'shortcode_css' => $base_dir . '/style.css',
            'shortcode_scss' => $base_dir . '/assets/scss/style.scss',
            'shortcode_js' => $base_dir . '/assets/js/' . $slug . '.js',
            'shortcode_gsap' => $base_dir . '/assets/js/gsap/gsap-' . $slug . '.js'
        ];
    }

    /**
     * Retourne le chemin du fichier pour une configuration de fonction
     */
    private function get_function_file_paths($slug, $theme) {
        $base_dir = 'themes/' . $theme . '/functions';

        return [
            'function_php' => $base_dir . '/' . $slug . '.php'
        ];
    }

    /**
     * Remplace les placeholders %slug%, %theme%, %SLUG_CAMEL% dans une chaîne
     */
    private function replace_placeholders($content, $slug, $slug_camel, $theme) {
        $slug_underscore = preg_replace('/[^a-z0-9_]/i', '_', str_replace('-', '_', $slug));
        $slug_underscore = preg_replace('/_+/', '_', $slug_underscore);
        $slug_underscore = trim($slug_underscore, '_');

        $replacements = [
            '%slug%' => $slug,
            '%theme%' => $theme,
            '%SLUG_CAMEL%' => $slug_camel,
            '%SLUG_UNDERSCORE%' => strtolower($slug_underscore)
        ];

        return strtr($content, $replacements);
    }

    /**
     * Retourne le chemin du fichier SCSS pour un thème donné
     */
    private function get_scss_file_paths($theme, $relative_path) {
        $paths = [];

        if (!empty($relative_path)) {
            $paths['scss_content'] = 'themes/' . $theme . '/' . ltrim($relative_path, '/');
        }

        return $paths;
    }

    /**
     * Nettoie un chemin relatif fourni par l'utilisateur pour le dossier du thème
     */
    private function sanitize_theme_relative_path($path) {
        $path = (string) $path;
        $path = str_replace('\\', '/', $path);
        $path = trim($path);
        $path = ltrim($path, '/');

        if ($path === '') {
            return '';
        }

        $segments = explode('/', $path);
        $clean_segments = [];

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($clean_segments);
                continue;
            }

            $sanitized_segment = sanitize_file_name($segment);

            if ($sanitized_segment !== '') {
                $clean_segments[] = $sanitized_segment;
            }
        }

        return implode('/', $clean_segments);
    }
    
    /**
     * Gère la sauvegarde d'une configuration
     */
    public function handle_save_configuration() {
        if (!isset($_POST['up_config_nonce']) || !wp_verify_nonce($_POST['up_config_nonce'], 'up_config_save')) {
            wp_die('Erreur de sécurité');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Permissions insuffisantes');
        }
        
        $plugin_slug = sanitize_text_field($_POST['plugin_slug']);
        $config_file = isset($_POST['config_file']) && !empty($_POST['config_file']) ? sanitize_file_name($_POST['config_file']) : '';
        // Retirer les slashes ajoutés par WP avant tout traitement
        $title = sanitize_text_field(wp_unslash($_POST['config_title']));
        $description = sanitize_textarea_field(wp_unslash($_POST['config_description']));

        $config_fields = [];
        if (isset($_POST['config_fields']) && is_array($_POST['config_fields'])) {
            foreach ($_POST['config_fields'] as $field_id => $field_value) {
                $config_fields[$field_id] = wp_unslash($field_value);
            }
        }

        $config_file_paths = [];
        if (isset($_POST['config_file_paths']) && is_array($_POST['config_file_paths'])) {
            foreach ($_POST['config_file_paths'] as $field_id => $file_path) {
                $config_file_paths[$field_id] = wp_unslash($file_path);
            }
        }

        $plugin_uses_slug = in_array($plugin_slug, ['shortcodes', 'functions', 'scss-files'], true);
        $generated_slug = '';
        $generated_slug_camel = '';
        $generated_slug_theme = '';
        $shortcode_slug_for_xml = '';

        if ($plugin_slug === 'shortcodes') {
            $generated_slug_theme = get_stylesheet();
            $shortcode_name = isset($config_fields['shortcode_name']) ? $config_fields['shortcode_name'] : '';
            $existing_slug = isset($_POST['config_generated_slug']) ? sanitize_title(wp_unslash($_POST['config_generated_slug'])) : '';
            $generated_slug = !empty($shortcode_name) ? $this->generate_config_slug($shortcode_name, 'shortcode') : '';

            if (empty($generated_slug)) {
                $generated_slug = $existing_slug;
            }

            if (empty($generated_slug)) {
                $generated_slug = $this->generate_config_slug($title, 'shortcode');
            }

            if (!empty($generated_slug)) {
                $generated_slug_camel = $this->slug_to_camel($generated_slug);

                $paths = $this->get_shortcode_file_paths($generated_slug, $generated_slug_theme);

                $relative_fields_map = [
                    'shortcode_php_relative_path' => 'shortcode_php',
                    'shortcode_css_relative_path' => 'shortcode_css',
                    'shortcode_scss_relative_path' => 'shortcode_scss',
                    'shortcode_js_relative_path' => 'shortcode_js',
                    'shortcode_gsap_relative_path' => 'shortcode_gsap'
                ];

                foreach ($relative_fields_map as $relative_field => $target_field) {
                    if (isset($config_fields[$relative_field])) {
                        $relative_path = $this->sanitize_theme_relative_path($config_fields[$relative_field]);

                        if (empty($relative_path)) {
                            $config_fields[$relative_field] = '';
                        } else {
                            $config_fields[$relative_field] = $relative_path;
                            $paths[$target_field] = 'themes/' . $generated_slug_theme . '/' . $relative_path;
                        }
                    }
                }

                foreach ($paths as $field_id => $path) {
                    $config_file_paths[$field_id] = $path;
                }

                $relative_placeholder_fields = array_keys($relative_fields_map);

                foreach ($config_fields as $field_id => $field_value) {
                    if (in_array($field_id, $relative_placeholder_fields, true)) {
                        continue;
                    }

                    if (is_string($field_value)) {
                        $config_fields[$field_id] = $this->replace_placeholders($field_value, $generated_slug, $generated_slug_camel, $generated_slug_theme);
                    }
                }

                foreach ($config_file_paths as $field_id => $path) {
                    if (is_string($path)) {
                        $config_file_paths[$field_id] = $this->replace_placeholders($path, $generated_slug, $generated_slug_camel, $generated_slug_theme);
                    }
                }

                $config_fields['_dynamic_slug'] = $generated_slug;
                $config_fields['_shortcode_slug'] = $generated_slug;
                $shortcode_slug_for_xml = $generated_slug;
            } else {
                foreach (['shortcode_php', 'shortcode_css', 'shortcode_scss', 'shortcode_js', 'shortcode_gsap'] as $field_id) {
                    if (isset($config_file_paths[$field_id])) {
                        $config_file_paths[$field_id] = '';
                    }
                }
            }
        }

        if ($plugin_slug === 'functions') {
            $generated_slug_theme = get_stylesheet();
            $function_name = isset($config_fields['function_name']) ? $config_fields['function_name'] : '';
            $existing_slug = isset($_POST['config_generated_slug']) ? sanitize_title(wp_unslash($_POST['config_generated_slug'])) : '';
            $generated_slug = !empty($function_name) ? $this->generate_config_slug($function_name, 'function') : '';

            if (empty($generated_slug)) {
                $generated_slug = $existing_slug;
            }

            if (empty($generated_slug)) {
                $generated_slug = $this->generate_config_slug($title, 'function');
            }

            if (!empty($generated_slug)) {
                $generated_slug_camel = $this->slug_to_camel($generated_slug);

                $relative_path = isset($config_fields['function_relative_path']) ? $this->sanitize_theme_relative_path($config_fields['function_relative_path']) : '';

                if (empty($relative_path)) {
                    $relative_path = 'functions/' . $generated_slug . '.php';
                }

                $config_fields['function_relative_path'] = $relative_path;

                $paths = $this->get_function_file_paths($generated_slug, $generated_slug_theme);
                $paths['function_php'] = 'themes/' . $generated_slug_theme . '/' . $relative_path;

                foreach ($paths as $field_id => $path) {
                    $config_file_paths[$field_id] = $path;
                }

                foreach ($config_fields as $field_id => $field_value) {
                    if (is_string($field_value)) {
                        $config_fields[$field_id] = $this->replace_placeholders($field_value, $generated_slug, $generated_slug_camel, $generated_slug_theme);
                    }
                }

                foreach ($config_file_paths as $field_id => $path) {
                    if (is_string($path)) {
                        $config_file_paths[$field_id] = $this->replace_placeholders($path, $generated_slug, $generated_slug_camel, $generated_slug_theme);
                    }
                }

                $config_fields['_dynamic_slug'] = $generated_slug;
            } else {
                if (isset($config_file_paths['function_php'])) {
                    $config_file_paths['function_php'] = '';
                }
            }
        }

        if ($plugin_slug === 'scss-files') {
            $generated_slug_theme = get_stylesheet();
            $scss_name = isset($config_fields['scss_name']) ? $config_fields['scss_name'] : '';
            $existing_slug = isset($_POST['config_generated_slug']) ? sanitize_title(wp_unslash($_POST['config_generated_slug'])) : '';
            $generated_slug = !empty($scss_name) ? $this->generate_config_slug($scss_name, 'scss') : '';

            if (empty($generated_slug)) {
                $generated_slug = $existing_slug;
            }

            if (empty($generated_slug)) {
                $generated_slug = $this->generate_config_slug($title, 'scss');
            }

            $relative_path = isset($config_fields['scss_relative_path']) ? $config_fields['scss_relative_path'] : '';
            $relative_path = $this->sanitize_theme_relative_path($relative_path);

            if (empty($relative_path) && !empty($generated_slug)) {
                $relative_path = 'assets/scss/' . $generated_slug . '.scss';
            }

            $config_fields['scss_relative_path'] = $relative_path;

            if (!empty($generated_slug)) {
                $generated_slug_camel = $this->slug_to_camel($generated_slug);
            }

            if (!empty($relative_path)) {
                $paths = $this->get_scss_file_paths($generated_slug_theme, $relative_path);
                foreach ($paths as $field_id => $path) {
                    $config_file_paths[$field_id] = $path;
                }
            } else {
                $config_file_paths['scss_content'] = '';
            }

            if (!empty($generated_slug) && isset($config_fields['scss_content'])) {
                $config_fields['scss_content'] = $this->replace_placeholders(
                    $config_fields['scss_content'],
                    $generated_slug,
                    !empty($generated_slug_camel) ? $generated_slug_camel : $generated_slug,
                    $generated_slug_theme
                );
            }

            if (!empty($generated_slug)) {
                $config_fields['_dynamic_slug'] = $generated_slug;
            }
        }
        
        // Générer un nom de fichier si nouveau
        if (empty($config_file)) {
            $config_file = sanitize_file_name(strtolower(str_replace(' ', '-', $title))) . '-' . time() . '.xml';
        }
        
        // Créer le XML avec CDATA pour préserver le contenu brut
        $xml = new SimpleXMLElement('<configuration></configuration>');
        
        // Utiliser CDATA pour les champs texte
        $title_node = $xml->addChild('title');
        $title_cdata = dom_import_simplexml($title_node);
        $title_cdata->appendChild($title_cdata->ownerDocument->createCDATASection($title));
        
        $desc_node = $xml->addChild('description');
        $desc_cdata = dom_import_simplexml($desc_node);
        $desc_cdata->appendChild($desc_cdata->ownerDocument->createCDATASection($description));
        
        $xml->addChild('date', date('Y-m-d H:i:s'));
        
        // Ajouter l'élément sélectionné
        $element = isset($_POST['config_element']) ? sanitize_text_field(wp_unslash($_POST['config_element'])) : '';
        $xml->addChild('element', $element);

        if ($plugin_slug === 'shortcodes' && !empty($shortcode_slug_for_xml)) {
            $slug_node = $xml->addChild('slug');
            $slug_dom = dom_import_simplexml($slug_node);
            $slug_dom->appendChild($slug_dom->ownerDocument->createCDATASection($shortcode_slug_for_xml));
        }
        
        // Ajouter les champs
        $fields = $xml->addChild('fields');
        if (!empty($config_fields)) {
            foreach ($config_fields as $field_id => $field_value) {
                // Ne pas filtrer le contenu, utiliser CDATA
                $field = $fields->addChild('field');
                $field->addAttribute('id', $field_id);
                
                // Utiliser CDATA pour préserver le contenu exact
                $field_dom = dom_import_simplexml($field);
                $field_dom->appendChild($field_dom->ownerDocument->createCDATASection($field_value));
                
                // Ajouter le chemin de fichier si présent
                if (isset($config_file_paths[$field_id]) && !empty($config_file_paths[$field_id])) {
                    $field->addAttribute('file_path', $config_file_paths[$field_id]);
                }
            }
        }
        
        // Sauvegarder le fichier XML
        $config_dir = UP_CONFIG_GENERATOR_PATH . 'config/' . $plugin_slug;
        wp_mkdir_p($config_dir);
        
        $file_path = $config_dir . '/' . $config_file;
        $xml->asXML($file_path);

        // Gérer l'aperçu (suppression + upload)
        if (isset($_POST['config_preview_remove']) && $_POST['config_preview_remove'] == '1') {
            $this->delete_config_preview($plugin_slug, $config_file);
        }

        if (isset($_FILES['config_preview']) && is_array($_FILES['config_preview'])) {
            $preview_file = $_FILES['config_preview'];
            if (isset($preview_file['error']) && $preview_file['error'] !== UPLOAD_ERR_NO_FILE && $preview_file['error'] === UPLOAD_ERR_OK) {
                $this->save_config_preview($plugin_slug, $config_file, $preview_file);
            }
        }
        
        // Appliquer si demandé
        if (isset($_POST['apply_config']) && $_POST['apply_config'] == '1') {
            $this->apply_configuration_from_file($plugin_slug, $config_file);
        }
        
        // Redirection
        $redirect_url = admin_url('admin.php?page=up-config-' . $plugin_slug . '&updated=1');
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * Gère la suppression d'une configuration
     */
    public function handle_delete_configuration() {
        $plugin_slug = sanitize_text_field($_GET['plugin']);
        $config_file = sanitize_file_name($_GET['config']);
        
        if (!wp_verify_nonce($_GET['_wpnonce'], 'up_config_delete_' . $config_file)) {
            wp_die('Erreur de sécurité');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Permissions insuffisantes');
        }
        
        $this->delete_config_preview($plugin_slug, $config_file);

        $file_path = UP_CONFIG_GENERATOR_PATH . 'config/' . $plugin_slug . '/' . $config_file;
        
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        
        $redirect_url = admin_url('admin.php?page=up-config-' . $plugin_slug . '&deleted=1');
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * Gère l'application d'une configuration
     */
    public function handle_apply_configuration() {
        $plugin_slug = sanitize_text_field($_GET['plugin']);
        $config_file = sanitize_file_name($_GET['config']);
        
        if (!wp_verify_nonce($_GET['_wpnonce'], 'up_config_apply_' . $config_file)) {
            wp_die('Erreur de sécurité');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Permissions insuffisantes');
        }
        
        $this->apply_configuration_from_file($plugin_slug, $config_file);
        
        $redirect_url = admin_url('admin.php?page=up-config-' . $plugin_slug . '&applied=1');
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * Applique une configuration depuis un fichier XML
     */
    private function apply_configuration_from_file($plugin_slug, $config_file) {
        $plugin_config = $this->load_config($plugin_slug);
        
        if (!$plugin_config) {
            return false;
        }
        
        $saved_data = $this->load_saved_config($plugin_slug, $config_file);
        
        if (!$saved_data) {
            return false;
        }
        
        // Créer les fichiers physiques (SCSS, CSS, JS, etc.)
        $this->create_physical_files($plugin_slug, $saved_data);
        
        // Appeler la fonction callback pour appliquer la configuration
        if (!empty($plugin_config['apply_callback']) && is_callable($plugin_config['apply_callback'])) {
            call_user_func($plugin_config['apply_callback'], $saved_data['fields'], $saved_data['element']);
            return true;
        }
        
        return false;
    }
    
    /**
     * Importe la configuration courante d'un plugin
     */
    private function import_current_configuration($plugin_slug, $plugin_config, $element_id = '') {
        $imported_data = [
            'title' => '',
            'description' => 'Configuration importée le ' . date('d/m/Y à H:i'),
            'element' => $element_id,
            'fields' => [],
            'file_paths' => [],
            'preview' => null,
            'slug' => ''
        ];
        
        // Appeler le callback d'importation si défini
        if (!empty($plugin_config['import_callback']) && is_callable($plugin_config['import_callback'])) {
            $config_data = call_user_func($plugin_config['import_callback'], $element_id);
            
            if ($config_data && is_array($config_data)) {
                $imported_data['fields'] = $config_data;
            }
        }
        
        // Importer les fichiers physiques si définis
        if (!empty($plugin_config['fields'])) {
            foreach ($plugin_config['fields'] as $field) {
                if ($field['type'] === 'file' && !empty($field['file_path'])) {
                    $file_content = $this->read_physical_file($field['file_path']);
                    if ($file_content !== false) {
                        $imported_data['fields'][$field['id']] = $file_content;
                        $imported_data['file_paths'][$field['id']] = $field['file_path'];
                    }
                }
            }
        }
        
        return $imported_data;
    }
    
    /**
     * Lit le contenu d'un fichier physique
     */
    private function read_physical_file($file_path) {
        // Résoudre le chemin absolu
        if (strpos($file_path, '/') === 0) {
            $absolute_path = ABSPATH . ltrim($file_path, '/');
        } else {
            $absolute_path = WP_CONTENT_DIR . '/' . $file_path;
        }
        
        if (file_exists($absolute_path)) {
            return file_get_contents($absolute_path);
        }
        
        return false;
    }
    
    /**
     * Crée les fichiers physiques (SCSS, CSS, JS, etc.) depuis la configuration
     */
    private function create_physical_files($plugin_slug, $saved_data) {
        if (empty($saved_data['file_paths'])) {
            return;
        }
        
        foreach ($saved_data['file_paths'] as $field_id => $file_path) {
            if (!isset($saved_data['fields'][$field_id])) {
                continue;
            }
            
            $content = $saved_data['fields'][$field_id];

            if (trim((string) $content) === '') {
                continue;
            }

            $target_path = $file_path;

            if (empty($target_path) && isset($saved_data['file_paths'][$field_id])) {
                $target_path = $saved_data['file_paths'][$field_id];
            }

            if (empty($target_path)) {
                continue;
            }

            // Résoudre le chemin absolu
            // Si le chemin commence par /, c'est un chemin absolu depuis ABSPATH
            // Sinon, c'est relatif au dossier wp-content
            if (strpos($target_path, '/') === 0) {
                $absolute_path = ABSPATH . ltrim($target_path, '/');
            } else {
                $absolute_path = WP_CONTENT_DIR . '/' . $target_path;
            }
            
            // Créer les dossiers parents si nécessaire
            $dir = dirname($absolute_path);
            if (!file_exists($dir)) {
                wp_mkdir_p($dir);
            }
            
            // Écrire le fichier
            file_put_contents($absolute_path, $content);
        }
    }
    
    /**
     * Enregistre les scripts admin
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'up-config') === false) {
            return;
        }
        
        wp_enqueue_media();
        
        // CodeMirror pour l'édition de code
        wp_enqueue_code_editor(['type' => 'text/css']);
        wp_enqueue_script('wp-theme-plugin-editor');
        wp_enqueue_style('wp-codemirror');
        
        wp_enqueue_style('up-config-generator-admin', UP_CONFIG_GENERATOR_URL . 'assets/admin.css', [], UP_CONFIG_GENERATOR_VERSION);
        wp_enqueue_script('up-config-generator-admin', UP_CONFIG_GENERATOR_URL . 'assets/admin.js', ['jquery', 'wp-codemirror'], UP_CONFIG_GENERATOR_VERSION, true);
    }
}

// Initialisation
UP_Config_Generator::get_instance();
