# UP Config Generator

## Description

Plugin WordPress permettant de créer et gérer des configurations pré-établies pour différents plugins WordPress (Contact Form 7, Yoast SEO, etc.). Ces configurations peuvent être sauvegardées et appliquées à la demande.

### Fonctionnalités principales

- **Sauvegarde en fichiers XML** : chaque configuration est stockée dans un fichier XML dans `config/[plugin-slug]/`
- **Fichiers JSON de configuration** définissant les champs et comportements pour chaque plugin
- **Interface d'administration dynamique** avec sous-menus par plugin
- **Tableau de gestion** : liste toutes les configurations avec actions (Modifier, Appliquer, Supprimer)
- **Application sélective** : possibilité d'enregistrer sans appliquer ou d'appliquer immédiatement
- **Réédition complète** : toutes les données XML sont rechargées lors de l'édition
- **Sélecteur d'élément** optionnel pour cibler un élément spécifique (ex: formulaire CF7)
- **Gestion de fichiers** : création automatique de fichiers SCSS, CSS, JS, PHP avec éditeur CodeMirror intégré
- **Templates CF7** : champ dédié pour stocker et réappliquer le formulaire Contact Form 7
- **Aperçus visuels** : upload d'une image pour chaque configuration avec affichage dans le listing
- **Shortcodes** : génération complète d'un dossier de shortcode (PHP, CSS, SCSS, JS, GSAP) avec slug automatisé
- **Fonctions PHP** : création automatisée de fichiers PHP dans `functions/` avec slug normalisé
- **Fichiers SCSS** : création de feuilles SCSS à l'emplacement souhaité du thème (chemin relatif configurable)
- **Fichiers JS** : création de fichiers JS à l'emplacement souhaité du thème (chemin relatif configurable, fallback `assets/js/{slug}.js`)
- **Fichiers GSAP** : création de fichiers GSAP à l'emplacement souhaité du thème (chemin relatif configurable, fallback `assets/js/gsap/gsap-{slug}.js`)
- **Générateur d'index** : génère un fichier complet ou injecte un bloc entre délimiteurs (imports SCSS, register/enqueue scripts/styles) avec prévisualisation et mode mise à jour sans doublons

## Architecture

### Structure des fichiers

```
up-config-generator/
├── up-config-generator.php    # Fichier principal
├── README.md                   # Documentation
├── plugin-config/              # Configurations JSON des plugins
│   ├── contact-form-7.json    # Exemple pour CF7
│   └── yoast-seo.json         # Exemple pour Yoast
│   └── shortcodes.json        # Exemple pour générer un shortcode complet
│   └── functions.json         # Exemple pour créer une fonction PHP dédiée
│   └── scss-files.json        # Exemple pour générer un fichier SCSS à un chemin personnalisé
│   └── js-files.json          # Exemple pour générer un fichier JS à un chemin personnalisé
│   └── gsap-files.json        # Exemple pour générer un fichier GSAP à un chemin personnalisé
│   └── index-generator.json   # Générateur d'index (scan automatique + injection entre délimiteurs)
├── config/                     # Configurations sauvegardées (XML)
│   ├── contact-form-7/        # Configs CF7
│   │   ├── ma-config-1.xml
│   │   └── ma-config-2.xml
│   └── yoast-seo/             # Configs Yoast
│       └── config-seo.xml
├── includes/                   # Intégrations
│   ├── cf7-integration.php
│   ├── yoast-integration.php
│   ├── shortcodes-integration.php
│   ├── functions-integration.php
│   ├── scss-files-integration.php
│   ├── js-files-integration.php
│   ├── gsap-files-integration.php
│   └── index-generator-integration.php
└── assets/                     # Ressources CSS/JS
    ├── admin.css
    └── admin.js
```

### Format des fichiers JSON

Chaque fichier JSON dans `plugin-config/` définit :

```json
{
  "name": "Nom du Plugin",
  "fields": [
    {
      "id": "field_id",
      "label": "Label du champ",
      "type": "text|textarea|select|file",
      "description": "Description optionnelle",
      "options": {}
    }
  ],
  "element_selector": {
    "label": "Sélectionner un élément",
    "callback": "function_name",
    "description": "Description"
  },
  "apply_callback": "function_name"
}
```

### Types de champs disponibles

- **text** : Champ texte simple
- **textarea** : Zone de texte multi-lignes
- **select** : Liste déroulante (nécessite `options`)
- **file** : Éditeur de code avec CodeMirror (nécessite `file_type` et `file_path`)

#### Champ de type "file"

```json
{
  "id": "form_scss",
  "label": "Styles SCSS du formulaire",
  "type": "file",
  "file_type": "scss",
  "file_path": "themes/twentytwentyfive/assets/scss/_form.scss",
  "description": "Code SCSS pour styliser le formulaire"
}
```

**Paramètres** :
- `file_type` : Type de fichier (`scss`, `css`, `js`, `javascript`, `json`, `html`, `php`)
- `file_path` : Chemin de destination relatif à `wp-content/` (ou absolu depuis la racine WordPress si commence par `/`)

## Installation

1. Télécharger le plugin
2. Le déposer dans `wp-content/plugins/`
3. L'activer dans l'administration WordPress
4. Créer des fichiers JSON dans `plugin-config/` pour chaque plugin à configurer
5. Les sous-menus apparaîtront automatiquement dans "Configurations"

## Utilisation

### Créer une configuration

1. Aller dans **Configurations** > **[Nom du Plugin]**
2. Cliquer sur **Ajouter**
3. Remplir les champs de configuration
4. Sélectionner un élément cible si nécessaire
5. Cocher **"Appliquer à la configuration"** pour appliquer immédiatement
6. Cliquer sur **Créer**

Un fichier XML est créé dans `config/[plugin-slug]/[titre-timestamp].xml`

### Gérer les configurations

Dans le tableau des configurations, vous pouvez :
- **Modifier** : éditer une configuration existante (toutes les données XML sont rechargées)
- **Appliquer** : appliquer la configuration au plugin cible
- **Supprimer** : supprimer le fichier XML de configuration

### Format XML d'une configuration

```xml
<configuration>
  <title>Ma Configuration</title>
  <description>Description de la config</description>
  <date>2025-11-06 10:00:00</date>
  <element>123</element>
  <fields>
    <field id="mail_to">contact@example.com</field>
    <field id="mail_subject">Nouveau message</field>
    <field id="mail_body">Contenu du message</field>
    <field id="form_scss" file_path="themes/twentytwentyfive/assets/scss/_form.scss">
      .wpcf7 {
        input[type="text"] {
          border: 1px solid #ccc;
        }
      }
    </field>
  </fields>
</configuration>
```

**Note** : Les champs de type "file" incluent l'attribut `file_path` qui indique où le fichier sera créé lors de l'application de la configuration.

## Exemple : Contact Form 7

Fichier `plugin-config/contact-form-7.json` :

```json
{
  "name": "Contact Form 7",
  "fields": [
    {
      "id": "mail_to",
      "label": "Email destinataire",
      "type": "text",
      "description": "Adresse email qui recevra les messages"
    },
    {
      "id": "mail_subject",
      "label": "Sujet de l'email",
      "type": "text"
    },
    {
      "id": "mail_body",
      "label": "Corps de l'email",
      "type": "textarea"
    }
  ],
  "element_selector": {
    "label": "Formulaire cible",
    "callback": "up_config_get_cf7_forms",
    "description": "Sélectionnez le formulaire à configurer"
  },
  "apply_callback": "up_config_apply_cf7"
}
```

## Développement

### Ajouter un nouveau plugin

1. Créer un fichier JSON dans `plugin-config/`
2. Définir les champs et callbacks
3. Implémenter les fonctions callback pour :
   - Récupérer la liste des éléments (si sélecteur nécessaire)
   - Appliquer la configuration

### Callbacks requis

**Sélecteur d'élément** : Doit retourner un tableau associatif `[id => label]`

```php
function up_config_get_cf7_forms() {
    $forms = WPCF7_ContactForm::find();
    $options = [];
    foreach ($forms as $form) {
        $options[$form->id()] = $form->title();
    }
    return $options;
}
```

**Application de configuration** : Reçoit les données et l'ID de l'élément

```php
function up_config_apply_cf7($config_data, $form_id) {
    $form = WPCF7_ContactForm::get_instance($form_id);
    if (!$form) return;
    
    // Appliquer la configuration
    $properties = $form->get_properties();
    $properties['mail']['recipient'] = $config_data['mail_to'];
    $properties['mail']['subject'] = $config_data['mail_subject'];
    $properties['mail']['body'] = $config_data['mail_body'];
    $form->set_properties($properties);
    $form->save();
}
```

## Fonctionnalité : Gestion de fichiers

Lors de l'application d'une configuration, les champs de type "file" créent automatiquement les fichiers physiques :

1. Le contenu du champ est écrit dans le fichier spécifié par `file_path`
2. Les dossiers parents sont créés automatiquement si nécessaire
3. Les fichiers existants sont écrasés

**Exemple d'utilisation** :
- Créer un fichier SCSS pour styliser un formulaire CF7
- Créer un fichier JS pour ajouter des interactions
- Créer un fichier PHP (`functions/wpautop.php`) pour désactiver `wpautop`
- Créer plusieurs fichiers en une seule configuration

### Aperçus de configuration

- Téléversement d'une image (JPG, PNG, GIF, WebP) pour chaque configuration
- L'aperçu est stocké dans `config/[plugin-slug]/` avec le même nom que le XML (`ma-config-1.png`)
- Affichage automatique dans la liste des configurations
- Gestion depuis le formulaire : upload d'un nouvel aperçu ou suppression de l'image actuelle

## Fonctionnalité : Générateur de shortcodes

- **Slug automatique** : le nom du shortcode est nettoyé (accents, espaces) pour produire un slug cohérent (`hero-banner`)
- **Fichiers générés** :
  - PHP : `themes/{theme}/shortcodes/{slug}/{slug}.php`
  - CSS : `themes/{theme}/shortcodes/{slug}/style.css`
  - SCSS : `themes/{theme}/shortcodes/{slug}/assets/scss/style.scss`
  - JS : `themes/{theme}/shortcodes/{slug}/assets/js/{slug}.js`
  - GSAP : `themes/{theme}/shortcodes/{slug}/assets/js/gsap/gsap-{slug}.js`
- **Placeholders** : Le contenu par défaut des fichiers remplace automatiquement `%slug%`, `%theme%` et `%SLUG_CAMEL%`
- **Edition** : lors de la modification, le slug existant est rappelé et les chemins de fichiers sont conservés

## Fonctionnalité : Générateur de fonctions PHP

- **Slug automatique** : le nom de la fonction est transformé en slug (`hero-cta` → fichier `hero-cta.php`)
- **Fichier généré** : `themes/{theme}/functions/{slug}.php`
- **Placeholders** : le contenu remplace `%slug%`, `%SLUG_CAMEL%`, `%SLUG_UNDERSCORE%` et `%theme%`
- **Edition** : le slug existant est affiché et préserve le chemin du fichier PHP

## Fonctionnalité : Générateur de fichiers SCSS

- **Chemin libre** : saisissez un chemin relatif (ex: `assets/scss/components/_cta.scss`) qui sera résolu dans `wp-content/themes/{theme}`
- **Nettoyage automatique** : les segments invalides ou les `..` sont nettoyés pour éviter toute sortie du thème
- **Fallback** : si le champ est vide, un chemin par défaut `assets/scss/{slug}.scss` est proposé
- **Placeholders** : le contenu peut utiliser `%slug%`, `%SLUG_CAMEL%`, `%SLUG_UNDERSCORE%`, `%theme%`
- **Edition** : le chemin enregistré est réaffiché et le fichier n'est régénéré que si du contenu est fourni

## Fonctionnalité : Générateur d'index

- **Modes** :
  - Fichier complet: écrit tout le fichier (optionnellement avec `file_header`/`file_footer`).
  - Injection entre délimiteurs: remplace le bloc entre `delimiter_start` et `delimiter_end`. Si le fichier n'existe pas, il est créé avec header/footer + délimiteurs + bloc.
- **Types** :
  - `import-scss` → `@import 'chemin/partiel';`
  - `register-script` / `enqueue-script`
  - `register-style` / `enqueue-style`
- **Scan automatique** : `scan_folder_relative` + `scan_glob`
  - Sélection SCSS: `scss_selection_mode = partials_only | no_partials | all`
  - Tri optionnel et source manuelle possible (`manual_entries`)
- **Enqueue/Register complets** :
  - Dépendances via `deps` (une par ligne)
  - `enqueue_has_url`, `base_url`, `enqueue_in_footer` (scripts), `enqueue_media` (styles)
- **Mise à jour sans doublons** : `update_mode=1` fusionne les lignes nouvelles sans dupliquer et préserve l'ordre existant
- **Prévisualisation** : bouton "Prévisualiser" affiche le bloc généré et le fichier final simulé

## Fonctionnalité : Générateur de fichiers JS

- **Chemin libre** : `js_relative_path` (ex: `assets/js/cta.js`) résolu dans `wp-content/themes/{theme}`
- **Fallback** : si vide, `assets/js/{slug}.js`
- **Placeholders** : `%slug%`, `%SLUG_CAMEL%`, `%SLUG_UNDERSCORE%`, `%theme%` remplacés dans le contenu uniquement
- **Edition** : le chemin enregistré est rappelé et le fichier n'est régénéré que si du contenu est fourni

## Fonctionnalité : Générateur de fichiers GSAP

- **Chemin libre** : `gsap_relative_path` (ex: `assets/js/gsap/gsap-cta.js`) résolu dans `wp-content/themes/{theme}`
- **Fallback** : si vide, `assets/js/gsap/gsap-{slug}.js`
- **Placeholders** : `%slug%`, `%SLUG_CAMEL%`, `%SLUG_UNDERSCORE%`, `%theme%` remplacés dans le contenu uniquement
- **Edition** : le chemin enregistré est rappelé et le fichier n'est régénéré que si du contenu est fourni

## Fonctionnalité : Importation de configuration courante

Lors de la création d'une nouvelle configuration, vous pouvez importer la configuration actuellement appliquée pour pré-remplir le formulaire.

### Comment ça fonctionne

1. **Section d'importation** : Affichée en haut du formulaire de création
2. **Sélection d'élément** : Pour les plugins avec sélecteur (ex: CF7), choisir l'élément à importer
3. **Import automatique** : 
   - Récupération des données via le callback `import_callback`
   - Lecture des fichiers physiques existants (SCSS, CSS, JS)
   - Pré-remplissage de tous les champs du formulaire

### Configuration JSON

Ajouter le callback d'importation dans le fichier JSON :

```json
{
  "name": "Contact Form 7",
  "fields": [...],
  "element_selector": {...},
  "import_callback": "up_config_import_cf7",
  "apply_callback": "up_config_apply_cf7"
}
```

### Callback d'importation

Le callback doit retourner un tableau associatif avec les valeurs des champs :

```php
function up_config_import_cf7($form_id) {
    // Récupérer les données du plugin
    $form = WPCF7_ContactForm::get_instance($form_id);
    $properties = $form->get_properties();
    
    // Retourner les données
    return [
        'mail_to' => $properties['mail']['recipient'],
        'mail_subject' => $properties['mail']['subject'],
        // ...
    ];
}
```

Les fichiers physiques (SCSS, CSS, JS) sont automatiquement lus depuis leurs chemins définis dans le JSON.

## Changelog

- **2025-11-08 · v0.1.9.0** · Ajout du module « Générateur d'index » avec modes Fichier complet et Injection entre délimiteurs, prévisualisation sans écriture, support des types `import-scss`, `register/enqueue` scripts/styles (dépendances, URL, media/footer), sélection SCSS (`partials_only`, `no_partials`, `all`), header/footer facultatifs, et mode mise à jour sans doublons préservant l'ordre existant.
- **2025-11-06 · v0.1.8.0** · Ajout des modules "Fichiers JS" et "Fichiers GSAP" avec chemins relatifs configurables, fallbacks automatiques (`assets/js/{slug}.js`, `assets/js/gsap/gsap-{slug}.js`), et remplacement de placeholders dans le contenu uniquement. Affichage du slug généré dans le formulaire.
- **2025-11-06 · v0.1.7.0** · Ajout du générateur de fichiers SCSS avec chemin relatif configurable, nettoyage automatique du chemin et support des placeholders. Détection des slugs mutualisée pour les trois modules (shortcodes, fonctions, SCSS).

- **2025-11-06 · v0.1.6.0** · Ajout du générateur de fonctions PHP (nouveau JSON `functions.json`, slug automatique, création du fichier dans `functions/`). Mutualisation de la logique de slug et support du placeholder `%SLUG_UNDERSCORE%`.

- **2025-11-06 · v0.1.5.0** · Ajout d'un module de génération de shortcodes : nouveau JSON `shortcodes.json`, création automatique des fichiers PHP/CSS/SCSS/JS/GSAP avec placeholders, slug nettoyé et sauvegardé dans la configuration. Support des aperçus existant pour les shortcodes.

- **2025-11-06 · v0.1.4.0** · Ajout d'un champ d'aperçu pour chaque configuration (upload, suppression). Affichage des aperçus dans la liste des configurations et dans le formulaire d'édition. Stockage des images aux côtés des fichiers XML.

- **2025-11-06 · v0.1.3.0** · Ajout du champ "Template du formulaire" pour Contact Form 7 avec valeurs par défaut. Génération automatique du fichier `functions/wpautop.php` pour désactiver `wpautop`. Support des fichiers PHP dans le système de sauvegarde. Pré-remplissage des nouveaux champs lors de l'importation.

- **2025-11-06 · v0.1.2.1** · Correction du bug d'échappement des caractères lors de l'enregistrement XML. Remplacement de `htmlspecialchars` par des sections CDATA pour préserver le contenu brut des champs (shortcodes WordPress, code SCSS/CSS/JS, caractères spéciaux). Les données sont maintenant sauvegardées et restaurées sans altération.

- **2025-11-06 · v0.1.2.0** · Ajout de l'importation de configuration courante : nouvelle section dans le formulaire permettant d'importer la configuration actuellement appliquée pour pré-remplir les champs. Support du sélecteur d'élément pour CF7. Lecture automatique des fichiers physiques existants (SCSS, CSS, JS). Callbacks d'importation ajoutés pour CF7 et Yoast SEO.

- **2025-11-06 · v0.1.1.0** · Ajout de la gestion de fichiers : nouveau type de champ "file" permettant de créer automatiquement des fichiers SCSS, CSS, JS, etc. lors de l'application d'une configuration. Intégration de l'éditeur CodeMirror avec coloration syntaxique. Les chemins de fichiers sont stockés dans le XML et les fichiers sont créés automatiquement avec leurs dossiers parents.

- **2025-11-06 · v0.1.0** · Création du plugin avec architecture de base, système de sauvegarde en fichiers XML, lecture des configurations JSON, interface d'administration dynamique avec tableau de gestion, réédition complète des configurations et logique d'application. Intégrations Contact Form 7 et Yoast SEO incluses.
