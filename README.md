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
- **Gestion de fichiers** : création automatique de fichiers SCSS, CSS, JS avec éditeur CodeMirror intégré

## Architecture

### Structure des fichiers

```
up-config-generator/
├── up-config-generator.php    # Fichier principal
├── README.md                   # Documentation
├── plugin-config/              # Configurations JSON des plugins
│   ├── contact-form-7.json    # Exemple pour CF7
│   └── yoast-seo.json         # Exemple pour Yoast
├── config/                     # Configurations sauvegardées (XML)
│   ├── contact-form-7/        # Configs CF7
│   │   ├── ma-config-1.xml
│   │   └── ma-config-2.xml
│   └── yoast-seo/             # Configs Yoast
│       └── config-seo.xml
├── includes/                   # Intégrations
│   ├── cf7-integration.php
│   └── yoast-integration.php
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
- Créer plusieurs fichiers en une seule configuration

## Changelog

- **2025-11-06 · v0.1.1.0** · Ajout de la gestion de fichiers : nouveau type de champ "file" permettant de créer automatiquement des fichiers SCSS, CSS, JS, etc. lors de l'application d'une configuration. Intégration de l'éditeur CodeMirror avec coloration syntaxique. Les chemins de fichiers sont stockés dans le XML et les fichiers sont créés automatiquement avec leurs dossiers parents.

- **2025-11-06 · v0.1.0** · Création du plugin avec architecture de base, système de sauvegarde en fichiers XML, lecture des configurations JSON, interface d'administration dynamique avec tableau de gestion, réédition complète des configurations et logique d'application. Intégrations Contact Form 7 et Yoast SEO incluses.
