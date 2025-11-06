<?php
/**
 * Plugin Name: UP Config Generator
 * Description: Permet de créer et gérer des configurations pré-établies pour différents plugins WordPress (CF7, Yoast, etc.)
 * Version: 0.1.0
 * Author: GEHIN Nicolas
 */

if (!defined('ABSPATH')) {
    exit;
}

define('UP_CONFIG_GENERATOR_VERSION', '0.1.0');
define('UP_CONFIG_GENERATOR_PATH', plugin_dir_path(__FILE__));
define('UP_CONFIG_GENERATOR_URL', plugin_dir_url(__FILE__));

// Inclure les fichiers d'intégration
require_once UP_CONFIG_GENERATOR_PATH . 'includes/cf7-integration.php';
require_once UP_CONFIG_GENERATOR_PATH . 'includes/yoast-integration.php';

class UP_Config_Generator {
    
    private static $instance = null;
    
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
            echo '<thead><tr><th>Titre</th><th>Description</th><th>Date</th><th>Actions</th></tr></thead>';
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
                
                echo '<tr>';
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
        
        
        // Charger les données si édition
        if (!$is_new) {
            $saved_data = $this->load_saved_config($plugin_slug, $config_file);
        }
        
        echo '<div class="wrap">';
        echo '<h1>' . ($is_new ? 'Nouvelle' : 'Modifier') . ' Configuration - ' . esc_html($plugin_config['name']) . '</h1>';
        
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="up_config_save">';
        wp_nonce_field('up_config_save', 'up_config_nonce');
        echo '<input type="hidden" name="plugin_slug" value="' . esc_attr($plugin_slug) . '">';
        echo '<input type="hidden" name="config_file" value="' . esc_attr($config_file) . '">';
        
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
        
        // Champs de configuration dynamiques
        if (!empty($plugin_config['fields'])) {
            foreach ($plugin_config['fields'] as $field) {
                $field_value = isset($saved_data['fields'][$field['id']]) ? $saved_data['fields'][$field['id']] : '';
                
                echo '<tr>';
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
                        $file_path = isset($field['file_path']) ? $field['file_path'] : '';
                        
                        echo '<div class="up-config-file-field">';
                        echo '<textarea id="field_' . esc_attr($field['id']) . '" name="config_fields[' . esc_attr($field['id']) . ']" class="up-config-code-editor" data-mode="' . esc_attr($file_type) . '" rows="15">' . esc_textarea($field_value) . '</textarea>';
                        
                        if (!empty($file_path)) {
                            echo '<p class="description"><strong>Fichier de destination :</strong> <code>' . esc_html($file_path) . '</code></p>';
                        }
                        
                        echo '<input type="hidden" name="config_file_paths[' . esc_attr($field['id']) . ']" value="' . esc_attr($file_path) . '">';
                        echo '</div>';
                        break;
                }
                
                if (!empty($field['description'])) {
                    echo '<p class="description">' . esc_html($field['description']) . '</p>';
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
        
        submit_button($is_new ? 'Créer' : 'Mettre à jour');
        
        echo '</form>';
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
            'file_paths' => []
        ];
        
        if (isset($xml->fields->field)) {
            foreach ($xml->fields->field as $field) {
                $field_id = (string) $field['id'];
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
        $title = sanitize_text_field($_POST['config_title']);
        $description = sanitize_textarea_field($_POST['config_description']);
        
        // Générer un nom de fichier si nouveau
        if (empty($config_file)) {
            $config_file = sanitize_file_name(strtolower(str_replace(' ', '-', $title))) . '-' . time() . '.xml';
        }
        
        // Créer le XML
        $xml = new SimpleXMLElement('<configuration></configuration>');
        $xml->addChild('title', htmlspecialchars($title));
        $xml->addChild('description', htmlspecialchars($description));
        $xml->addChild('date', date('Y-m-d H:i:s'));
        
        // Ajouter l'élément sélectionné
        $element = isset($_POST['config_element']) ? sanitize_text_field($_POST['config_element']) : '';
        $xml->addChild('element', htmlspecialchars($element));
        
        // Ajouter les champs
        $fields = $xml->addChild('fields');
        if (isset($_POST['config_fields']) && is_array($_POST['config_fields'])) {
            foreach ($_POST['config_fields'] as $field_id => $field_value) {
                $field = $fields->addChild('field', htmlspecialchars($field_value));
                $field->addAttribute('id', $field_id);
                
                // Ajouter le chemin de fichier si présent
                if (isset($_POST['config_file_paths'][$field_id]) && !empty($_POST['config_file_paths'][$field_id])) {
                    $field->addAttribute('file_path', $_POST['config_file_paths'][$field_id]);
                }
            }
        }
        
        // Sauvegarder le fichier XML
        $config_dir = UP_CONFIG_GENERATOR_PATH . 'config/' . $plugin_slug;
        wp_mkdir_p($config_dir);
        
        $file_path = $config_dir . '/' . $config_file;
        $xml->asXML($file_path);
        
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
     * Crée les fichiers physiques (SCSS, CSS, JS, etc.) depuis la configuration
     */
    private function create_physical_files($plugin_slug, $saved_data) {
        if (empty($saved_data['file_paths'])) {
            return;
        }
        
        foreach ($saved_data['file_paths'] as $field_id => $file_path) {
            if (empty($file_path) || !isset($saved_data['fields'][$field_id])) {
                continue;
            }
            
            $content = $saved_data['fields'][$field_id];
            
            // Résoudre le chemin absolu
            // Si le chemin commence par /, c'est un chemin absolu depuis ABSPATH
            // Sinon, c'est relatif au dossier wp-content
            if (strpos($file_path, '/') === 0) {
                $absolute_path = ABSPATH . ltrim($file_path, '/');
            } else {
                $absolute_path = WP_CONTENT_DIR . '/' . $file_path;
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
