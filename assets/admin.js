/**
 * Scripts pour l'interface d'administration du plugin UP Config Generator
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // Gestion de la case "Appliquer à la configuration"
        const $applyCheckbox = $('#apply_config');
        
        if ($applyCheckbox.length) {
            // Ajouter un style visuel à la section
            $applyCheckbox.closest('tr').addClass('up-config-apply-row');
            
            // Confirmation avant application
            $('form').on('submit', function(e) {
                if ($applyCheckbox.is(':checked')) {
                    const confirmed = confirm(
                        'Vous êtes sur le point d\'appliquer cette configuration. ' +
                        'Les paramètres actuels seront remplacés. Continuer ?'
                    );
                    
                    if (!confirmed) {
                        e.preventDefault();
                        return false;
                    }
                }
            });
        }
        
        // Gestion de l'affichage conditionnel des champs mail 2
        const $mail2Active = $('select[name="config_fields[mail2_active]"]');
        
        if ($mail2Active.length) {
            const $mail2Fields = [
                $('input[name="config_fields[mail2_to]"]').closest('tr'),
                $('input[name="config_fields[mail2_subject]"]').closest('tr'),
                $('textarea[name="config_fields[mail2_body]"]').closest('tr')
            ];
            
            function toggleMail2Fields() {
                const isActive = $mail2Active.val() === '1';
                $mail2Fields.forEach($field => {
                    if (isActive) {
                        $field.show();
                    } else {
                        $field.hide();
                    }
                });
            }
            
            $mail2Active.on('change', toggleMail2Fields);
            toggleMail2Fields(); // Initial state
        }
        
        // Validation des emails
        $('input[name="config_fields[mail_to]"], input[name="config_fields[mail2_to]"]').on('blur', function() {
            const $input = $(this);
            const value = $input.val().trim();
            
            if (value && !isValidEmail(value)) {
                $input.css('border-color', '#dc3232');
                
                if (!$input.next('.email-error').length) {
                    $input.after('<span class="email-error" style="color: #dc3232; display: block; margin-top: 5px;">Format d\'email invalide</span>');
                }
            } else {
                $input.css('border-color', '');
                $input.next('.email-error').remove();
            }
        });
        
        // Validation des URLs
        $('input[name="config_fields[company_logo]"], ' +
          'input[name="config_fields[social_facebook]"], ' +
          'input[name="config_fields[social_instagram]"], ' +
          'input[name="config_fields[social_linkedin]"]').on('blur', function() {
            const $input = $(this);
            const value = $input.val().trim();
            
            if (value && !isValidUrl(value)) {
                $input.css('border-color', '#dc3232');
                
                if (!$input.next('.url-error').length) {
                    $input.after('<span class="url-error" style="color: #dc3232; display: block; margin-top: 5px;">Format d\'URL invalide</span>');
                }
            } else {
                $input.css('border-color', '');
                $input.next('.url-error').remove();
            }
        });
        
        // Message de confirmation après sauvegarde
        if (window.location.search.indexOf('updated=1') > -1) {
            const $message = $('<div class="notice notice-success is-dismissible"><p>Configuration enregistrée avec succès.</p></div>');
            $('.wrap h1').after($message);
            
            // Auto-dismiss après 3 secondes
            setTimeout(function() {
                $message.fadeOut(function() {
                    $(this).remove();
                });
            }, 3000);
        }
        
        // Compteur de caractères pour les champs textarea
        $('textarea[name^="config_fields"]').each(function() {
            const $textarea = $(this);
            const maxLength = $textarea.attr('maxlength');
            
            if (maxLength) {
                const $counter = $('<div class="character-counter" style="text-align: right; margin-top: 5px; color: #646970; font-size: 12px;"></div>');
                $textarea.after($counter);
                
                function updateCounter() {
                    const length = $textarea.val().length;
                    $counter.text(length + ' / ' + maxLength + ' caractères');
                    
                    if (length > maxLength * 0.9) {
                        $counter.css('color', '#dc3232');
                    } else {
                        $counter.css('color', '#646970');
                    }
                }
                
                $textarea.on('input', updateCounter);
                updateCounter();
            }
        });
        
    });
    
    /**
     * Valide un format d'email
     */
    function isValidEmail(email) {
        // Accepte aussi les tags CF7 comme [your-email]
        if (email.match(/^\[[\w-]+\]$/)) {
            return true;
        }
        
        const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return regex.test(email);
    }
    
    /**
     * Valide un format d'URL
     */
    function isValidUrl(url) {
        try {
            new URL(url);
            return true;
        } catch (e) {
            return false;
        }
    }
    
})(jQuery);
