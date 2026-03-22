/* Copyright (C) 2025 Patrice GOURMELEN <pgourmelen@diamant-industrie.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    js/label_update.js
 * \ingroup detailproduit
 * \brief   JavaScript for label update modal (product_type = 1)
 * \version 2.0.1 - Protection anti-double-click ajoutée
 */

(function() {
    'use strict';
    
    console.log('📦 label_update.js v2.0.1 - Début du chargement');

    // Variables globales pour le modal de label
    var currentLabelCommandedetId = null;
    var currentLabelSocid = null;
    var currentLabelProductLabel = '';
    var labelAjaxUrl = '';
    var isLabelLoading = false;
    var isSaving = false; // ✅ NOUVELLE VARIABLE pour bloquer double-click

    /**
     * Fonction utilitaire locale pour trouver l'URL de base Dolibarr
     */
    function findBaseUrlLocal() {
        if (typeof window.DOL_URL_ROOT !== 'undefined' && window.DOL_URL_ROOT) {
            return window.DOL_URL_ROOT;
        }
        
        var currentPath = window.location.pathname;
        var segments = currentPath.split('/');
        var baseSegments = [];
        
        for (var i = 0; i < segments.length; i++) {
            baseSegments.push(segments[i]);
            if (segments[i] === 'doli' || segments[i] === 'dolibarr') {
                break;
            }
        }
        
        if (baseSegments.length > 0 && baseSegments[baseSegments.length - 1] === 'doli') {
            return baseSegments.join('/');
        }
        
        var scripts = document.querySelectorAll('script[src*="doli"], link[href*="doli"]');
        for (var j = 0; j < scripts.length; j++) {
            var src = scripts[j].src || scripts[j].href;
            var match = src.match(/^(.*\/doli)/);
            if (match) {
                return match[1].replace(window.location.origin, '');
            }
        }
        
        return '/doli';
    }

    /**
     * Fonction utilitaire locale pour récupérer le token CSRF
     */
    function findTokenInPageLocal() {
        if (typeof window.token !== 'undefined' && window.token) {
            return window.token;
        }
        if (typeof window.newtoken !== 'undefined' && window.newtoken) {
            return window.newtoken;
        }
        
        var tokenInputs = document.querySelectorAll('input[name="token"], input[name="newtoken"]');
        for (var i = 0; i < tokenInputs.length; i++) {
            if (tokenInputs[i].value && tokenInputs[i].value.length > 10) {
                return tokenInputs[i].value;
            }
        }
        
        var forms = document.querySelectorAll('form');
        for (var j = 0; j < forms.length; j++) {
            var formData = new FormData(forms[j]);
            var token = formData.get('token');
            if (token) {
                return token;
            }
            var newtoken = formData.get('newtoken');
            if (newtoken) {
                return newtoken;
            }
        }
        
        var metaToken = document.querySelector('meta[name="csrf-token"]');
        if (metaToken) {
            return metaToken.getAttribute('content');
        }
        
        var linksWithToken = document.querySelectorAll('a[href*="token="]');
        for (var k = 0; k < linksWithToken.length; k++) {
            var match = linksWithToken[k].href.match(/[?&]token=([^&]+)/);
            if (match && match[1].length > 10) {
                return match[1];
            }
        }
        
        return null;
    }

    /**
     * Initialiser labelAjaxUrl immédiatement
     */
    function initializeLabelAjaxUrl() {
        var baseUrl = findBaseUrlLocal();
        labelAjaxUrl = baseUrl + '/custom/detailproduit/ajax/label_handler.php';
        console.log('🔧 labelAjaxUrl initialisé:', labelAjaxUrl);
    }

    /**
     * EXPOSITION IMMÉDIATE DES FONCTIONS
     */
    
    window.openLabelUpdateModal = function(commandedetId, socid, productLabel) {
        console.log('🔄 openLabelUpdateModal appelée avec:', {
            commandedetId: commandedetId,
            socid: socid,
            productLabel: productLabel
        });
        
        if (!labelAjaxUrl) {
            console.log('⚠️ labelAjaxUrl non initialisé, réinitialisation...');
            initializeLabelAjaxUrl();
        }
        
        if (isLabelLoading) {
            console.log('⚠️ Chargement en cours, opération annulée');
            return;
        }

        var modal = document.getElementById('labelUpdateModal');
        if (!modal) {
            console.error('❌ Modal labelUpdateModal non trouvé, création...');
            createLabelUpdateModal();
            modal = document.getElementById('labelUpdateModal');
            
            if (!modal) {
                alert('Erreur: Le modal de mise à jour de label n\'a pas pu être créé.');
                return;
            }
        }

        currentLabelCommandedetId = commandedetId;
        currentLabelSocid = socid;
        currentLabelProductLabel = productLabel || 'Service';
        
        // ✅ Réinitialiser l'état de sauvegarde
        isSaving = false;
        
        console.log('✅ Variables définies:', {
            currentLabelCommandedetId: currentLabelCommandedetId,
            currentLabelSocid: currentLabelSocid,
            labelAjaxUrl: labelAjaxUrl
        });
        
        document.getElementById('labelNCommande').value = '';
        document.getElementById('labelDateCommande').value = '';
        document.getElementById('labelContact').value = '';
        document.getElementById('labelRefChantier').value = '';
        
        // Charger les contacts EN PREMIER, puis les données du formulaire.
        // Cela évite la race condition où loadLabelData restaure la valeur du contact
        // avant que les options du <select> soient construites par loadThirdpartyContacts.
        loadThirdpartyContactsInternal().then(function() {
            loadLabelDataInternal();
        });
        
        console.log('✅ Affichage du modal');
        modal.style.display = 'block';
    };

    window.closeLabelUpdateModal = function() {
        console.log('🔄 Fermeture du modal de label');
        var modal = document.getElementById('labelUpdateModal');
        if (modal) {
            modal.style.display = 'none';
        }
        clearLabelValidationMessage();
        currentLabelCommandedetId = null;
        currentLabelSocid = null;
        // ✅ Réinitialiser l'état de sauvegarde
        isSaving = false;
    };

    window.saveLabelUpdate = function() {
        console.log('💾 Appel saveLabelUpdate...');
        saveLabelUpdateInternal();
    };

    console.log('✅ Fonctions label exposées globalement:', {
        openLabelUpdateModal: typeof window.openLabelUpdateModal,
        closeLabelUpdateModal: typeof window.closeLabelUpdateModal,
        saveLabelUpdate: typeof window.saveLabelUpdate
    });

    /**
     * Initialisation au chargement du DOM
     */
    function initializeLabelModule() {
        console.log('🔧 Initialisation du module de mise à jour de label...');
        
        if (!labelAjaxUrl) {
            initializeLabelAjaxUrl();
        }
        
        createLabelUpdateModal();
        
        console.log('✅ Module label initialisé:', {
            labelAjaxUrl: labelAjaxUrl,
            modalExists: document.getElementById('labelUpdateModal') ? 'OUI' : 'NON'
        });
    }

    /**
     * Créer le modal de mise à jour de label dans le DOM
     */
    function createLabelUpdateModal() {
        if (document.getElementById('labelUpdateModal')) {
            console.log('ℹ️ Modal labelUpdateModal déjà existant');
            return;
        }

        console.log('🏗️ Création du modal labelUpdateModal...');

        var modalHTML = '<div id="labelUpdateModal" class="details-modal">' +
            '<div class="details-modal-content" style="max-width: 600px;">' +
                '<div class="details-modal-header">' +
                    '<h3>Modifier le label du service</h3>' +
                    '<button class="details-modal-close" onclick="closeLabelUpdateModal()">&times;</button>' +
                '</div>' +
                '<div class="details-modal-body">' +
                    '<div class="label-form">' +
                        '<div class="label-form-group">' +
                            '<label for="labelNCommande">N° de commande</label>' +
                            '<input type="text" id="labelNCommande" class="label-form-input" placeholder="Saisir le numéro de commande">' +
                        '</div>' +
                        '<div class="label-form-group">' +
                            '<label for="labelDateCommande">Date de commande</label>' +
                            '<input type="date" id="labelDateCommande" class="label-form-input">' +
                        '</div>' +
                        '<div class="label-form-group">' +
                            '<label for="labelContact">Contact Commande</label>' +
                            '<select id="labelContact" class="label-form-input">' +
                                '<option value="">-- Sélectionner un contact --</option>' +
                            '</select>' +
                        '</div>' +
                        '<div class="label-form-group">' +
                            '<label for="labelRefChantier">Ref Chantier</label>' +
                            '<input type="text" id="labelRefChantier" class="label-form-input" placeholder="Saisir la référence du chantier">' +
                        '</div>' +
                        '<div class="label-preview" id="labelPreview" style="margin-top: 20px; padding: 10px; background: #f0f0f0; border-radius: 4px; color: #666;">' +
                            '<strong>Aperçu du tableau :</strong><br>' +
                            '<div id="labelPreviewTable" style="margin-top: 10px;">' +
                                '<table border="0" cellpadding="1" cellspacing="1" style="width:500px; border: 1px dashed #ccc;">' +
                                    '<tbody>' +
                                        '<tr>' +
                                            '<td style="padding: 5px;">Le tableau sera généré automatiquement</td>' +
                                        '</tr>' +
                                    '</tbody>' +
                                '</table>' +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                    '<div id="labelValidationMessage" class="details-validation-message"></div>' +
                '</div>' +
                '<div class="details-modal-footer">' +
                    '<button class="details-btn" onclick="closeLabelUpdateModal()">Annuler</button>' +
                    '<button class="details-btn details-btn-success" id="labelSaveButton" onclick="saveLabelUpdate()">💾 Valider</button>' +
                '</div>' +
            '</div>' +
        '</div>';

        document.body.insertAdjacentHTML('beforeend', modalHTML);
        console.log('✅ Modal labelUpdateModal créé');

        var modal = document.getElementById('labelUpdateModal');
        modal.onclick = function(event) {
            if (event.target === modal) {
                closeLabelUpdateModal();
            }
        };
        
        var fieldIds = ['labelNCommande', 'labelDateCommande', 'labelContact', 'labelRefChantier'];
        for (var i = 0; i < fieldIds.length; i++) {
            var field = document.getElementById(fieldIds[i]);
            if (field) {
                field.addEventListener('input', updateLabelPreviewInternal);
                field.addEventListener('change', updateLabelPreviewInternal);
            }
        }
    }

    /**
     * Charger les données de label existantes
     */
    function loadLabelDataInternal() {
        if (!currentLabelCommandedetId) {
            console.error('❗ currentLabelCommandedetId manquant pour loadLabelData');
            return;
        }
        
        if (!labelAjaxUrl) {
            console.error('❗ labelAjaxUrl manquant pour loadLabelData');
            initializeLabelAjaxUrl();
            if (!labelAjaxUrl) {
                showLabelValidationMessage('Erreur: URL AJAX non configurée', 'error');
                return;
            }
        }

        var csrfToken = findTokenInPageLocal();
        if (!csrfToken) {
            console.error('❗ Token CSRF introuvable');
            showLabelValidationMessage('Erreur: Token CSRF introuvable', 'error');
            return;
        }

        isLabelLoading = true;
        showLabelValidationMessage('Chargement des données...', 'info');

        var formData = new URLSearchParams();
        formData.append('action', 'get_label_data');
        formData.append('commandedet_id', currentLabelCommandedetId);
        formData.append('token', csrfToken);
        
        console.log('🔄 loadLabelData - Requête AJAX:', {
            url: labelAjaxUrl,
            commandedet_id: currentLabelCommandedetId
        });
        
        fetch(labelAjaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: formData.toString()
        })
        .then(function(response) {
            console.log('📥 loadLabelData - Réponse:', response.status);
            
            if (!response.ok) {
                throw new Error('HTTP ' + response.status + ': ' + response.statusText);
            }
            
            return response.json();
        })
        .then(function(data) {
            isLabelLoading = false;
            clearLabelValidationMessage();

            if (data.success && data.data) {
                console.log('✅ Données chargées:', data.data);

                document.getElementById('labelNCommande').value = data.data.n_commande || '';
                document.getElementById('labelDateCommande').value = data.data.date_commande || '';
                document.getElementById('labelContact').value = data.data.contact || '';
                document.getElementById('labelRefChantier').value = data.data.ref_commande || '';

                updateLabelPreviewInternal();
            } else {
                console.log('ℹ️ Aucune donnée existante');
            }
        })
        .catch(function(error) {
            isLabelLoading = false;
            console.error('❌ Erreur loadLabelData:', error);
            showLabelValidationMessage('Erreur lors du chargement: ' + error.message, 'error');
        });
    }

    /**
     * Charger la liste des contacts du tiers
     */
    function loadThirdpartyContactsInternal() {
        if (!currentLabelSocid) {
            console.error('❗ currentLabelSocid manquant pour loadThirdpartyContacts:', {
                currentLabelSocid: currentLabelSocid,
                currentLabelCommandedetId: currentLabelCommandedetId
            });
            return;
        }
        
        if (!labelAjaxUrl) {
            console.error('❗ labelAjaxUrl manquant pour loadThirdpartyContacts');
            initializeLabelAjaxUrl();
            if (!labelAjaxUrl) {
                showLabelValidationMessage('Erreur: URL AJAX non configurée', 'error');
                return;
            }
        }

        var csrfToken = findTokenInPageLocal();
        if (!csrfToken) {
            console.error('❗ Token CSRF introuvable');
            showLabelValidationMessage('Erreur: Token CSRF introuvable', 'error');
            return;
        }

        var formData = new URLSearchParams();
        formData.append('action', 'get_thirdparty_contacts');
        formData.append('socid', currentLabelSocid);
        formData.append('token', csrfToken);
        
        console.log('🔄 loadThirdpartyContacts - Requête:', {
            url: labelAjaxUrl,
            socid: currentLabelSocid
        });
        
        return fetch(labelAjaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: formData.toString()
        })
        .then(function(response) {
            console.log('📥 loadThirdpartyContacts - Réponse:', response.status);

            if (!response.ok) {
                throw new Error('HTTP ' + response.status + ': ' + response.statusText);
            }

            return response.json();
        })
        .then(function(data) {
            if (data.success && data.contacts) {
                console.log('✅ Contacts chargés:', data.contacts.length);

                var selectContact = document.getElementById('labelContact');
                var firstOption = selectContact.options[0];
                selectContact.innerHTML = '';
                selectContact.appendChild(firstOption);

                for (var i = 0; i < data.contacts.length; i++) {
                    var option = document.createElement('option');
                    option.value = data.contacts[i].id;
                    option.textContent = data.contacts[i].name;
                    selectContact.appendChild(option);
                }
            } else {
                console.log('ℹ️ Aucun contact trouvé');
                showLabelValidationMessage('Aucun contact disponible pour ce tiers', 'warning');
            }
        })
        .catch(function(error) {
            console.error('❌ Erreur loadThirdpartyContacts:', error);
            showLabelValidationMessage('Erreur lors du chargement des contacts: ' + error.message, 'error');
        });
    }

    /**
     * Mettre à jour l'aperçu du tableau en temps réel
     */
    function updateLabelPreviewInternal() {
        var nCommande = document.getElementById('labelNCommande').value.trim();
        var dateCommande = document.getElementById('labelDateCommande').value;
        var contactId = document.getElementById('labelContact').value;
        var refChantier = document.getElementById('labelRefChantier').value.trim();

        var contactName = '';
        if (contactId) {
            var selectContact = document.getElementById('labelContact');
            var selectedOption = selectContact.options[selectContact.selectedIndex];
            contactName = selectedOption ? selectedOption.textContent : '';
        }

        var labelParts = [];

        if (nCommande) {
            labelParts.push("Commande " + nCommande);
        }

        if (dateCommande) {
            var dateParts = dateCommande.split('-');
            if (dateParts.length === 3) {
                var dateFormatted = dateParts[2] + '/' + dateParts[1] + '/' + dateParts[0];
                labelParts.push("du " + dateFormatted);
            } else {
                labelParts.push("du " + dateCommande);
            }
        }

        if (contactName) {
            labelParts.push("de " + contactName);
        }

        if (refChantier) {
            labelParts.push("ref : " + refChantier);
        }

        var labelText = labelParts.length > 0
            ? labelParts.join(' ')
            : 'Le tableau sera généré automatiquement';

        var tableHTML = '<table border="0" cellpadding="1" cellspacing="1" style="width:500px; border: 1px dashed #ccc;">' +
            '<tbody>' +
                '<tr>' +
                    '<td style="padding: 5px;">' + escapeHtml(labelText) + '</td>' +
                '</tr>' +
            '</tbody>' +
        '</table>';

        document.getElementById('labelPreviewTable').innerHTML = tableHTML;
    }

    /**
     * Échapper les caractères HTML
     */
    function escapeHtml(text) {
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    /**
     * Sauvegarder la mise à jour du label
     * ✅ VERSION 2.0.1 - AVEC PROTECTION ANTI-DOUBLE-CLICK
     */
    function saveLabelUpdateInternal() {
        // ✅ PROTECTION ANTI-DOUBLE-CLICK
        if (isSaving) {
            console.log('⚠️ Sauvegarde déjà en cours, requête ignorée (protection anti-double-click)');
            return;
        }
        
        if (!currentLabelCommandedetId) {
            showLabelValidationMessage('Erreur: ID de ligne manquant', 'error');
            return;
        }
        
        if (!labelAjaxUrl) {
            console.error('❗ labelAjaxUrl manquant pour saveLabelUpdate');
            initializeLabelAjaxUrl();
            if (!labelAjaxUrl) {
                showLabelValidationMessage('Erreur: URL AJAX non définie', 'error');
                return;
            }
        }

        var nCommande = document.getElementById('labelNCommande').value.trim();
        var dateCommande = document.getElementById('labelDateCommande').value;
        var contactId = document.getElementById('labelContact').value;
        var refChantier = document.getElementById('labelRefChantier').value.trim();

        if (!nCommande && !dateCommande && !contactId && !refChantier) {
            showLabelValidationMessage('Veuillez remplir au moins un champ', 'error');
            return;
        }

        var csrfToken = findTokenInPageLocal();
        if (!csrfToken) {
            console.error('❗ Token CSRF introuvable');
            showLabelValidationMessage('Erreur: Token CSRF introuvable', 'error');
            return;
        }

        console.log('📤 Sauvegarde label:', {
            commandedet_id: currentLabelCommandedetId,
            n_commande: nCommande,
            date_commande: dateCommande,
            contact: contactId,
            ref_chantier: refChantier
        });

        // ✅ BLOQUER les nouveaux appels
        isSaving = true;
        isLabelLoading = true;
        
        // ✅ DÉSACTIVER le bouton visuellement
        var saveButton = document.getElementById('labelSaveButton');
        if (saveButton) {
            saveButton.disabled = true;
            saveButton.style.opacity = '0.5';
            saveButton.style.cursor = 'not-allowed';
            saveButton.innerHTML = '⏳ Sauvegarde en cours...';
        }
        
        showLabelValidationMessage('Sauvegarde en cours...', 'info');

        var formData = new FormData();
        formData.append('action', 'save_label_update');
        formData.append('commandedet_id', currentLabelCommandedetId);
        formData.append('n_commande', nCommande);
        formData.append('date_commande', dateCommande);
        formData.append('contact', contactId);
        formData.append('ref_commande', refChantier);
        formData.append('token', csrfToken);
        
        fetch(labelAjaxUrl, {
            method: 'POST',
            body: formData
        })
        .then(function(response) {
            console.log('📥 Réponse:', response.status);
            
            return response.text().then(function(text) {
                if (!response.ok) {
                    try {
                        var errorData = JSON.parse(text);
                        throw new Error(errorData.error || response.statusText);
                    } catch (parseError) {
                        throw new Error('HTTP ' + response.status + ': ' + response.statusText);
                    }
                }
                return text;
            });
        })
        .then(function(text) {
            var data;
            try {
                data = JSON.parse(text);
            } catch (parseError) {
                console.error('❌ Erreur parsing:', parseError);
                throw new Error('Réponse serveur non-JSON');
            }
            
            isLabelLoading = false;
            
            if (data.success) {
                showLabelValidationMessage('✅ Label mis à jour avec succès !', 'success');
                console.log('✅ Sauvegarde réussie:', data);
                
                setTimeout(function() {
                    closeLabelUpdateModal();
                    window.location.reload();
                }, 1500);
            } else {
                // ✅ RÉACTIVER en cas d'échec
                isSaving = false;
                if (saveButton) {
                    saveButton.disabled = false;
                    saveButton.style.opacity = '1';
                    saveButton.style.cursor = 'pointer';
                    saveButton.innerHTML = '💾 Valider';
                }
                showLabelValidationMessage('Erreur: ' + (data.error || 'Erreur inconnue'), 'error');
            }
        })
        .catch(function(error) {
            // ✅ RÉACTIVER en cas d'erreur
            isSaving = false;
            isLabelLoading = false;
            
            if (saveButton) {
                saveButton.disabled = false;
                saveButton.style.opacity = '1';
                saveButton.style.cursor = 'pointer';
                saveButton.innerHTML = '💾 Valider';
            }
            
            console.error('❌ Erreur:', error);
            showLabelValidationMessage('Erreur de communication: ' + error.message, 'error');
        });
    }

    /**
     * Afficher un message de validation
     */
    function showLabelValidationMessage(message, type) {
        var messageDiv = document.getElementById('labelValidationMessage');
        if (messageDiv) {
            messageDiv.className = 'details-validation-message details-validation-' + type;
            messageDiv.textContent = message;
            messageDiv.style.display = 'block';
        }
    }

    /**
     * Effacer le message de validation
     */
    function clearLabelValidationMessage() {
        var messageDiv = document.getElementById('labelValidationMessage');
        if (messageDiv) {
            messageDiv.style.display = 'none';
            messageDiv.textContent = '';
        }
    }

    // Initialiser labelAjaxUrl immédiatement
    initializeLabelAjaxUrl();

    // Initialisation au chargement du DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeLabelModule);
    } else {
        initializeLabelModule();
    }

    console.log('📦 label_update.js v2.0.1 - Fin du chargement');
})();
