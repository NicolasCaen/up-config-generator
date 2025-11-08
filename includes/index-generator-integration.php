<?php
/**
 * Intégration pour le Générateur d'index (fichiers et injections)
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Prévisualise le rendu sans écrire: retourne un tableau avec le bloc et le fichier final simulé
 * @return array{mode:string, target:string, block:string, final:string}
 */
function up_config_preview_index_generator($config_data) {
    $theme = get_stylesheet();
    $theme_dir = WP_CONTENT_DIR . '/themes/' . $theme;

    $mode = isset($config_data['generation_mode']) ? (string)$config_data['generation_mode'] : 'inject_between_delimiters';
    $type = isset($config_data['content_type']) ? (string)$config_data['content_type'] : 'import-scss';

    $target_rel = isset($config_data['target_relative_path']) ? ltrim(trim((string)$config_data['target_relative_path']), '/') : '';
    $target_abs = $target_rel !== '' ? trailingslashit($theme_dir) . $target_rel : '';

    $recursive = !empty($config_data['recursive']) && (string)$config_data['recursive'] !== '0';
    $sort = !empty($config_data['sort']) && (string)$config_data['sort'] !== '0';

    $lines = [];
    $manual_entries_raw = isset($config_data['manual_entries']) ? (string)$config_data['manual_entries'] : '';
    $has_manual = trim($manual_entries_raw) !== '';
    if ($has_manual) {
        $lines = up_idx_generate_lines_from_manual($type, $manual_entries_raw, $config_data, $theme);
    } else {
        $scan_folder_rel = isset($config_data['scan_folder_relative']) ? trim((string)$config_data['scan_folder_relative']) : '';
        $scan_glob = isset($config_data['scan_glob']) ? trim((string)$config_data['scan_glob']) : '**/*.*';
        $scss_mode = isset($config_data['scss_selection_mode']) ? (string)$config_data['scss_selection_mode'] : 'partials_only';
        if ($scan_folder_rel !== '') {
            $files = up_idx_scan_files($theme_dir, $scan_folder_rel, $scan_glob, $recursive);
            if ($type === 'import-scss') {
                $files = up_idx_filter_scss_by_mode($files, $scss_mode);
            }
            $lines = up_idx_generate_lines_from_files($type, $files, $config_data, $theme);
        }
    }
    if ($sort) {
        natcasesort($lines);
        $lines = array_values($lines);
    }

    $content_block = implode("\n", $lines) . "\n";
    $file_header = isset($config_data['file_header']) ? (string)$config_data['file_header'] : '';
    $file_footer = isset($config_data['file_footer']) ? (string)$config_data['file_footer'] : '';

    $preview_final = '';
    if ($mode === 'full_file') {
        $preview_final = ($file_header !== '' ? rtrim($file_header, "\n") . "\n" : '')
                       . $content_block
                       . ($file_footer !== '' ? rtrim($file_footer, "\n") . "\n" : '');
    } else {
        $start = isset($config_data['delimiter_start']) ? (string)$config_data['delimiter_start'] : '//-- DEBUT INDEX --';
        $end   = isset($config_data['delimiter_end']) ? (string)$config_data['delimiter_end'] : '//-- FIN INDEX --';
        $existing = ($target_abs && file_exists($target_abs)) ? file_get_contents($target_abs) : '';
        if ($existing === '') {
            $skeleton = up_idx_inject_between_delimiters('', $start, $end, $content_block);
            $preview_final = ($file_header !== '' ? rtrim($file_header, "\n") . "\n" : '')
                           . $skeleton
                           . ($file_footer !== '' ? rtrim($file_footer, "\n") . "\n" : '');
        } else {
            $ensure_update_only = !empty($config_data['update_mode']) && (string)$config_data['update_mode'] !== '0';
            if ($ensure_update_only) {
                $preview_final = up_idx_merge_into_delimited_block($existing, $start, $end, $lines);
            } else {
                $preview_final = up_idx_inject_between_delimiters($existing, $start, $end, $content_block);
            }
        }
    }

    return [
        'mode' => $mode,
        'target' => $target_abs,
        'block' => $content_block,
        'final' => $preview_final,
    ];
}

/**
 * Applique une configuration d'index (génération complète ou injection entre délimiteurs)
 *
 * @param array $config_data Champs de configuration (depuis le XML)
 * @param string $element Élément ciblé (non utilisé ici)
 * @return bool
 */
function up_config_apply_index_generator($config_data, $element = '') {
    $theme = get_stylesheet();
    $theme_dir = WP_CONTENT_DIR . '/themes/' . $theme;

    $mode = isset($config_data['generation_mode']) ? (string)$config_data['generation_mode'] : 'inject_between_delimiters';
    $type = isset($config_data['content_type']) ? (string)$config_data['content_type'] : 'import-scss';

    $target_rel = isset($config_data['target_relative_path']) ? trim((string)$config_data['target_relative_path']) : '';
    if ($target_rel === '') {
        return false;
    }
    $target_rel = ltrim($target_rel, '/');
    $target_abs = trailingslashit($theme_dir) . $target_rel;

    // Options communes
    $recursive = !empty($config_data['recursive']) && (string)$config_data['recursive'] !== '0';
    $sort = !empty($config_data['sort']) && (string)$config_data['sort'] !== '0';

    $lines = [];

    // Source auto (scan) ou manuel
    $manual_entries_raw = isset($config_data['manual_entries']) ? (string)$config_data['manual_entries'] : '';
    $has_manual = trim($manual_entries_raw) !== '';

    if ($has_manual) {
        $lines = up_idx_generate_lines_from_manual($type, $manual_entries_raw, $config_data, $theme);
    } else {
        $scan_folder_rel = isset($config_data['scan_folder_relative']) ? trim((string)$config_data['scan_folder_relative']) : '';
        $scan_glob = isset($config_data['scan_glob']) ? trim((string)$config_data['scan_glob']) : '**/*.*';
        $scss_mode = isset($config_data['scss_selection_mode']) ? (string)$config_data['scss_selection_mode'] : 'partials_only';

        if ($scan_folder_rel !== '') {
            $files = up_idx_scan_files($theme_dir, $scan_folder_rel, $scan_glob, $recursive);
            if ($type === 'import-scss') {
                $files = up_idx_filter_scss_by_mode($files, $scss_mode);
            }
            $lines = up_idx_generate_lines_from_files($type, $files, $config_data, $theme);
        }
    }

    if ($sort) {
        natcasesort($lines);
        $lines = array_values($lines);
    }

    $content_block = implode("\n", $lines) . "\n";
    $file_header = isset($config_data['file_header']) ? (string)$config_data['file_header'] : '';
    $file_footer = isset($config_data['file_footer']) ? (string)$config_data['file_footer'] : '';

    if ($mode === 'full_file') {
        $final = ($file_header !== '' ? rtrim($file_header, "\n") . "\n" : '')
               . $content_block
               . ($file_footer !== '' ? rtrim($file_footer, "\n") . "\n" : '');
        return up_idx_write_file($target_abs, $final);
    }

    // Injection entre délimiteurs
    $start = isset($config_data['delimiter_start']) ? (string)$config_data['delimiter_start'] : '//-- DEBUT INDEX --';
    $end   = isset($config_data['delimiter_end']) ? (string)$config_data['delimiter_end'] : '//-- FIN INDEX --';

    $ensure_update_only = !empty($config_data['update_mode']) && (string)$config_data['update_mode'] !== '0';

    $existing = file_exists($target_abs) ? file_get_contents($target_abs) : '';

    if ($ensure_update_only && $existing !== '') {
        $final = up_idx_merge_into_delimited_block($existing, $start, $end, $lines);
    } else {
        // Si le fichier n'existe pas ou pas de update_only, créer bloc avec header/footer si nécessaire
        $skeleton = up_idx_inject_between_delimiters($existing, $start, $end, $content_block);
        if ($existing === '') {
            $final = ($file_header !== '' ? rtrim($file_header, "\n") . "\n" : '')
                  . $skeleton
                  . ($file_footer !== '' ? rtrim($file_footer, "\n") . "\n" : '');
        } else {
            $final = $skeleton;
        }
    }

    return up_idx_write_file($target_abs, $final);
}

/**
 * Scan de fichiers à partir d'un dossier et d'un glob.
 */
function up_idx_scan_files($theme_dir, $folder_rel, $glob_pattern, $recursive) {
    $base = trailingslashit($theme_dir) . ltrim($folder_rel, '/');
    if (!is_dir($base)) return [];

    $files = [];

    if ($recursive) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
        $regex = up_idx_glob_to_regex($glob_pattern);
        foreach ($it as $file) {
            if ($file->isFile()) {
                $rel = ltrim(str_replace($theme_dir . '/', '', $file->getPathname()), '/');
                if (preg_match($regex, $rel)) {
                    $files[] = $rel;
                }
            }
        }
    } else {
        $pattern = up_idx_simple_glob_to_glob($base, $glob_pattern);
        foreach (glob($pattern) as $path) {
            if (is_file($path)) {
                $files[] = ltrim(str_replace($theme_dir . '/', '', $path), '/');
            }
        }
    }

    return $files;
}

// Convertit un pattern glob simple (ex: ** puis / puis *.ext) en regex
function up_idx_glob_to_regex($glob) {
    $escaped = preg_quote($glob, '#');
    $escaped = str_replace(['\*\*\/','\*\*','\*','\?'], ['(?:.+\/)?','.+','[^\/]*','[^\/]'], $escaped);
    return '#^' . $escaped . '$#i';
}

/** Crée un pattern glob non récursif depuis base + glob simple */
function up_idx_simple_glob_to_glob($base, $glob) {
    if ($glob === '' || $glob === '**/*.*') {
        return rtrim($base, '/') . '/*.*';
    }
    return rtrim($base, '/') . '/' . ltrim($glob, '/');
}

/** Filtre la liste scss selon le mode */
function up_idx_filter_scss_by_mode(array $files, $mode) {
    $out = [];
    foreach ($files as $rel) {
        $is_scss = (substr($rel, -5) === '.scss');
        if (!$is_scss) continue;
        $base = basename($rel);
        $is_partial = (strlen($base) > 0 && $base[0] === '_');
        if ($mode === 'partials_only' && $is_partial) $out[] = $rel;
        elseif ($mode === 'no_partials' && !$is_partial) $out[] = $rel;
        elseif ($mode === 'all') $out[] = $rel;
    }
    return $out;
}

/** Génère lignes à partir d'une liste de fichiers */
function up_idx_generate_lines_from_files($type, array $files, array $cfg, $theme) {
    $lines = [];
    foreach ($files as $rel) {
        $lines[] = up_idx_generate_line($type, $rel, $cfg, $theme);
    }
    return array_values(array_filter($lines));
}

/** Génère lignes à partir d'entrées manuelles (une par ligne) */
function up_idx_generate_lines_from_manual($type, $manual_raw, array $cfg, $theme) {
    $lines = [];
    $rows = preg_split('/\r?\n/', (string)$manual_raw);
    foreach ($rows as $row) {
        $rel = trim($row);
        if ($rel === '') continue;
        $lines[] = up_idx_generate_line($type, $rel, $cfg, $theme);
    }
    return array_values(array_filter($lines));
}

/** Génère une ligne en fonction du type */
function up_idx_generate_line($type, $rel_path, array $cfg, $theme) {
    $rel_path = ltrim($rel_path, '/');
    $handle_prefix = isset($cfg['wp_handle_prefix']) ? (string)$cfg['wp_handle_prefix'] : 'theme-';
    $deps_raw = isset($cfg['deps']) ? (string)$cfg['deps'] : '';
    $deps = up_idx_parse_deps($deps_raw);

    switch ($type) {
        case 'import-scss':
            $p = preg_replace('#^#', '', $rel_path);
            $p = preg_replace('#^(.*/)_#', '$1', $p); // retirer underscore leader si présent
            $p = preg_replace('#\.scss$#i', '', $p);
            return "@import '" . $p . "';";

        case 'register-script': {
            $handle = up_idx_build_handle($rel_path, $handle_prefix);
            $url = up_idx_build_url($rel_path, $cfg);
            return "wp_register_script('{$handle}', '{$url}', " . up_idx_php_array($deps) . ", null, true);";
        }
        case 'enqueue-script': {
            $handle = up_idx_build_handle($rel_path, $handle_prefix);
            $has_url = empty($cfg['enqueue_has_url']) ? false : ((string)$cfg['enqueue_has_url'] !== '0');
            if ($has_url) {
                $url = up_idx_build_url($rel_path, $cfg);
                $in_footer = empty($cfg['enqueue_in_footer']) ? true : ((string)$cfg['enqueue_in_footer'] !== '0');
                return "wp_enqueue_script('{$handle}', '{$url}', " . up_idx_php_array($deps) . ", null, " . ($in_footer ? 'true' : 'false') . ");";
            }
            return "wp_enqueue_script('{$handle}');";
        }
        case 'register-style': {
            $handle = up_idx_build_handle($rel_path, $handle_prefix);
            $url = up_idx_build_url($rel_path, $cfg);
            $media = isset($cfg['enqueue_media']) ? (string)$cfg['enqueue_media'] : 'all';
            return "wp_register_style('{$handle}', '{$url}', " . up_idx_php_array($deps) . ", null, '{$media}');";
        }
        case 'enqueue-style': {
            $handle = up_idx_build_handle($rel_path, $handle_prefix);
            $has_url = empty($cfg['enqueue_has_url']) ? false : ((string)$cfg['enqueue_has_url'] !== '0');
            if ($has_url) {
                $url = up_idx_build_url($rel_path, $cfg);
                $media = isset($cfg['enqueue_media']) ? (string)$cfg['enqueue_media'] : 'all';
                return "wp_enqueue_style('{$handle}', '{$url}', " . up_idx_php_array($deps) . ", null, '{$media}');";
            }
            return "wp_enqueue_style('{$handle}');";
        }
    }

    return '';
}

function up_idx_parse_deps($raw) {
    $deps = [];
    foreach (preg_split('/\r?\n/', (string)$raw) as $line) {
        $v = trim($line);
        if ($v !== '') $deps[] = $v;
    }
    return $deps;
}

function up_idx_build_handle($rel_path, $prefix) {
    $base = preg_replace('#\.[a-z0-9]+$#i', '', basename($rel_path));
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $base));
    $slug = trim(preg_replace('/-+/', '-', $slug), '-');
    return $prefix . $slug;
}

function up_idx_build_url($rel_path, array $cfg) {
    $rel_path = ltrim($rel_path, '/');
    $base_url = isset($cfg['base_url']) ? trim((string)$cfg['base_url']) : '';
    if ($base_url !== '') {
        return rtrim($base_url, '/') . '/' . $rel_path;
    }
    return "<?php echo get_stylesheet_directory_uri(); ?>/" . $rel_path;
}

function up_idx_php_array(array $arr) {
    if (empty($arr)) return '[]';
    $escaped = array_map(function($v) { return "'" . str_replace("'", "\\'", $v) . "'"; }, $arr);
    return '[' . implode(',', $escaped) . ']';
}

/** Injection: remplace ou ajoute le bloc entre délimiteurs */
function up_idx_inject_between_delimiters($full_content, $start, $end, $new_block) {
    $start_pos = strpos($full_content, $start);
    $end_pos = ($start_pos !== false) ? strpos($full_content, $end, $start_pos) : false;

    if ($start_pos === false || $end_pos === false) {
        // Créer squelette avec délimiteurs
        $nl = "\n";
        return $start . $nl . $new_block . $end . $nl;
    }

    $before = substr($full_content, 0, $start_pos + strlen($start)) . "\n";
    $after = substr($full_content, $end_pos);
    return $before . $new_block . $after;
}

/** Update mode: fusionne sans doublons dans le bloc, conserve l'ordre existant */
function up_idx_merge_into_delimited_block($full_content, $start, $end, array $new_lines) {
    $start_pos = strpos($full_content, $start);
    $end_pos = ($start_pos !== false) ? strpos($full_content, $end, $start_pos) : false;

    if ($start_pos === false || $end_pos === false) {
        // Pas de délimiteurs: créer bloc
        $nl = "\n";
        return $start . $nl . implode($nl, $new_lines) . $nl . $end . $nl;
    }

    $before = substr($full_content, 0, $start_pos + strlen($start)) . "\n";
    $inside = substr($full_content, $start_pos + strlen($start), $end_pos - ($start_pos + strlen($start)));
    $after = substr($full_content, $end_pos);

    $existing_lines = [];
    foreach (preg_split('/\r?\n/', $inside) as $ln) {
        $t = trim($ln);
        if ($t !== '' && $t !== $end) $existing_lines[] = $t;
    }

    $norm = function($s) { return preg_replace('/\s+/', ' ', trim($s)); };
    $set = array_map($norm, $existing_lines);
    $add = [];
    foreach ($new_lines as $ln) {
        $n = $norm($ln);
        if ($n !== '' && !in_array($n, $set, true)) {
            $add[] = $ln;
        }
    }

    $merged = array_merge($existing_lines, $add);
    return $before . implode("\n", $merged) . "\n" . $after;
}

/** Écrit un fichier (crée les dossiers) */
function up_idx_write_file($abs, $content) {
    $dir = dirname($abs);
    if (!is_dir($dir)) {
        wp_mkdir_p($dir);
    }
    $ok = file_put_contents($abs, $content);
    if ($ok !== false) {
        @chmod($abs, 0644);
        return true;
    }
    return false;
}
