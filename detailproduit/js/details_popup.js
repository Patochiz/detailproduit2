/* Copyright (C) 2025 Patrice GOURMELEN <pgourmelen@diamant-industrie.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    js/details_popup.js
 * \ingroup detailproduit
 * \brief   Product details modal - spreadsheet for pieces, dimensions, calculations
 */

if (typeof window.DETAILPRODUIT_POPUP_LOADED === 'undefined') {
window.DETAILPRODUIT_POPUP_LOADED = true;

// --- State ---
var currentCommandedetId = null;
var currentTotalQuantity = 0;
var currentProductName = '';
var rowCounter = 0;
var sortColumn = -1;
var sortDirection = 'asc';
var isLoading = false;
var detailsToken = '';
var ajaxUrl = '';

// --- Initialization ---

document.addEventListener('DOMContentLoaded', function() {
    initializeGlobalVariables();
    createDetailsModal();
    setTimeout(addDetailsButtonsToExistingLines, 500);
});

function initializeGlobalVariables() {
    detailsToken = findTokenInPage();
    var baseUrl = findBaseUrl();
    ajaxUrl = baseUrl + '/custom/detailproduit/ajax/details_handler.php';
}

function findTokenInPage() {
    // Global variables injected by hooks
    if (typeof window.detailproduit_token !== 'undefined' && window.detailproduit_token) return window.detailproduit_token;
    if (typeof window.newtoken !== 'undefined' && window.newtoken) return window.newtoken;
    if (typeof window.token !== 'undefined' && window.token) return window.token;

    // Hidden inputs
    var inputs = document.querySelectorAll('input[name="token"], input[name="newtoken"]');
    for (var i = 0; i < inputs.length; i++) {
        if (inputs[i].value && inputs[i].value.length > 10) return inputs[i].value;
    }

    // Meta tag
    var meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) return meta.getAttribute('content');

    // Links with token
    var links = document.querySelectorAll('a[href*="token="]');
    for (var j = 0; j < links.length; j++) {
        var match = links[j].href.match(/[?&]token=([^&]+)/);
        if (match && match[1].length > 10) return match[1];
    }

    return null;
}

function findBaseUrl() {
    if (typeof window.DOL_URL_ROOT !== 'undefined' && window.DOL_URL_ROOT) return window.DOL_URL_ROOT;

    // Analyze current URL for common patterns
    var segments = window.location.pathname.split('/');
    var base = [];
    for (var i = 0; i < segments.length; i++) {
        base.push(segments[i]);
        if (segments[i] === 'doli' || segments[i] === 'dolibarr' || segments[i] === 'htdocs') break;
    }
    if (base.length > 1) return base.join('/');

    return '';
}

// --- Modal Creation ---

function createDetailsModal() {
    if (document.getElementById('detailsModal')) return;

    var html = '<div id="detailsModal" class="details-modal">' +
        '<div class="details-modal-content">' +
            '<div class="details-modal-header">' +
                '<h3 id="detailsModalTitle">D\u00e9tails du produit</h3>' +
                '<button class="details-modal-close" onclick="closeDetailsModal()">&times;</button>' +
            '</div>' +
            '<div class="details-modal-body">' +
                '<div class="details-summary-info">' +
                    '<div><strong>Produit:</strong> <span id="detailsProductName"></span></div>' +
                    '<div><strong>Quantit\u00e9 totale \u00e0 r\u00e9partir:</strong> <span id="detailsTotalQuantity"></span></div>' +
                '</div>' +
                '<div class="details-toolbar">' +
                    '<button class="details-btn details-btn-success" onclick="addDetailsRow()">+ Ajouter une ligne</button>' +
                    '<button class="details-btn" onclick="clearAllDetails()">\uD83D\uDDD1\uFE0F Vider tout</button>' +
                    '<button class="details-btn details-btn-primary" onclick="updateCommandQuantity()">\uD83D\uDD04 Mettre \u00e0 jour la quantit\u00e9 commande</button>' +
                '</div>' +
                '<div class="details-spreadsheet-container">' +
                    '<table class="details-spreadsheet-table" id="detailsTable">' +
                        '<thead><tr>' +
                            '<th class="details-sortable-header details-col-pieces" onclick="sortDetailsTable(0)">Nb pi\u00e8ces<span class="details-sort-icon"></span></th>' +
                            '<th class="details-sortable-header details-col-longueur" onclick="sortDetailsTable(1)">Longueur (mm)<span class="details-sort-icon"></span></th>' +
                            '<th class="details-sortable-header details-col-largeur" onclick="sortDetailsTable(2)">Largeur (mm)<span class="details-sort-icon"></span></th>' +
                            '<th class="details-col-total">Total <span class="details-unit-label" id="detailsUnitLabel">m\u00b2</span></th>' +
                            '<th class="details-sortable-header details-col-description" onclick="sortDetailsTable(4)">Description<span class="details-sort-icon"></span></th>' +
                            '<th class="details-col-actions">Actions</th>' +
                        '</tr></thead>' +
                        '<tbody id="detailsTableBody"></tbody>' +
                        '<tfoot><tr class="details-total-row">' +
                            '<td><strong>Total:</strong></td>' +
                            '<td colspan="2" class="details-text-center"><strong id="detailsTotalPieces">0</strong> pi\u00e8ces</td>' +
                            '<td><strong id="detailsTotalQuantityDisplay">0</strong> <span id="detailsTotalUnit">m\u00b2</span></td>' +
                            '<td colspan="2"></td>' +
                        '</tr></tfoot>' +
                    '</table>' +
                '</div>' +
                '<div id="detailsValidationMessage" class="details-validation-message"></div>' +
            '</div>' +
            '<div class="details-modal-footer">' +
                '<div class="details-tip">Tip: Tab = navigation horizontale, Entr\u00e9e = navigation verticale</div>' +
                '<div>' +
                    '<button class="details-btn" onclick="closeDetailsModal()">Annuler</button>' +
                    '<button class="details-btn details-btn-success" onclick="saveDetails()">\uD83D\uDCBE Sauvegarder</button>' +
                '</div>' +
            '</div>' +
        '</div>' +
    '</div>';

    document.body.insertAdjacentHTML('beforeend', html);

    window.addEventListener('click', function(e) {
        if (e.target === document.getElementById('detailsModal')) closeDetailsModal();
    });
}

// --- Button Injection ---

function addDetailsButtonsToExistingLines() {
    var selectors = [
        '#tablelines tbody tr[id^="row-"]',
        '#tablelines tbody tr',
        '.liste tbody tr[class*="oddeven"]'
    ];

    var lines = [];
    for (var s = 0; s < selectors.length; s++) {
        lines = document.querySelectorAll(selectors[s]);
        if (lines.length > 0) break;
    }

    for (var i = 0; i < lines.length; i++) {
        var line = lines[i];
        if (line.classList.contains('liste_titre') || line.classList.contains('liste_total') || line.querySelector('th')) continue;
        var lineId = extractLineId(line);
        if (lineId) addDetailsButtonToLine(lineId, line);
    }
}

function extractLineId(el) {
    if (el.id && el.id.indexOf('row-') !== -1) return el.id.replace('row-', '');
    if (el.dataset && el.dataset.lineId) return el.dataset.lineId;

    var inputs = el.querySelectorAll('input[type="hidden"]');
    for (var i = 0; i < inputs.length; i++) {
        if (inputs[i].name && (inputs[i].name.indexOf('lineid') !== -1) && !isNaN(inputs[i].value)) return inputs[i].value;
    }

    var links = el.querySelectorAll('a[href*="action=editline"]');
    for (var j = 0; j < links.length; j++) {
        var m = links[j].href.match(/[?&]lineid=(\d+)/);
        if (m) return m[1];
    }

    return null;
}

function addDetailsButtonToLine(lineId, lineElement) {
    if (!lineElement) lineElement = document.getElementById('row-' + lineId);
    if (!lineElement || lineElement.querySelector('.details-btn-open')) return;

    // Dolibarr 20: linecoledit (draft), linecoldelete, linecolmove
    var targetCell = lineElement.querySelector('.linecoledit') ||
                     lineElement.querySelector('.linecoldelete') ||
                     lineElement.querySelector('.linecolmove') ||
                     lineElement.querySelector('.linecolaction');
    if (!targetCell) {
        // Fallback: last cell containing interactive elements
        var cells = lineElement.querySelectorAll('td');
        for (var i = cells.length - 1; i >= 0; i--) {
            if (cells[i].querySelector('a, button, .pictoedit')) { targetCell = cells[i]; break; }
        }
        // Absolute fallback: first cell with product link (validated orders)
        if (!targetCell) {
            for (var j = 0; j < cells.length; j++) {
                if (cells[j].querySelector('a[href*="product"]')) { targetCell = cells[j]; break; }
            }
        }
    }
    if (!targetCell) return;

    var productId = extractProductId(lineElement);
    var btn = document.createElement('a');
    btn.href = '#';
    btn.className = 'details-btn-open';

    if (productId == 361) {
        // Service séparateur de sections (ID 361) → popup libellé commande
        btn.title = 'Modifier le label du service';
        btn.innerHTML = '&#x1F3F7;';
        btn.style.cssText = 'margin-left:5px;text-decoration:none;font-size:11px;padding:2px 6px;background:#28a745;color:white;border-radius:2px;';
        btn.onclick = function(e) {
            e.preventDefault();
            var name = extractProductName(lineElement);
            var orderId = getOrderIdFromUrl();
            if (orderId && typeof window.openLabelUpdateModal === 'function') {
                getSocidFromOrderId(orderId).then(function(socid) {
                    window.openLabelUpdateModal(lineId, socid, name);
                }).catch(function() {
                    alert('Impossible de récupérer les informations du tiers.');
                });
            }
            return false;
        };
    } else {
        // Tous les autres produits/services → popup détails produit
        btn.title = 'Détails produit';
        btn.innerHTML = '&#x1F4CB;';
        btn.style.cssText = 'margin-left:5px;text-decoration:none;font-size:11px;padding:2px 6px;background:#17a2b8;color:white;border-radius:2px;';
        btn.onclick = function(e) {
            e.preventDefault();
            openDetailsModal(lineId, extractQuantity(lineElement), extractProductName(lineElement));
            return false;
        };
    }

    targetCell.appendChild(btn);
}

function extractProductName(el) {
    var link = el.querySelector('a[href*="product/card.php"]');
    if (link) return link.textContent.trim();
    return 'Produit';
}

function extractQuantity(el) {
    // Draft: qty is in an input
    var input = el.querySelector('input[name*="qty"]');
    if (input) return parseFloat(input.value) || 1;
    // Validated: qty is displayed as text in .linecolqty cell
    var qtyCell = el.querySelector('.linecolqty');
    if (qtyCell) {
        var text = qtyCell.textContent.trim().replace(/\s/g, '').replace(',', '.');
        var val = parseFloat(text);
        if (!isNaN(val) && val > 0) return val;
    }
    return 1;
}

function extractProductType(el) {
    // 1. Hidden input (draft mode only)
    var input = el.querySelector('input[name*="product_type"]');
    if (input && input.value) return parseInt(input.value);

    // 2. Data attribute
    if (el.dataset && el.dataset.productType) return parseInt(el.dataset.productType);

    // 3. Product link URL contains type=1
    var links = el.querySelectorAll('a[href*="product/card.php"]');
    for (var i = 0; i < links.length; i++) {
        if (links[i].href.indexOf('type=1') !== -1) return 1;
    }

    // 4. Service icon in Dolibarr themes (img or span)
    //    Dolibarr uses object_service.png for services, object_product.png for products
    var serviceIndicators = el.querySelectorAll(
        'img[src*="object_service"],' +
        'span[class*="object_service"],' +
        'span.fas.fa-concierge-bell'
    );
    if (serviceIndicators.length > 0) return 1;

    // 5. Known service product: ID 361 = section separator (Diamant Industrie business rule)
    //    On validated orders, product_type is not in the DOM, so we extract from product link
    var productId = extractProductId(el);
    if (productId == 361) return 1;

    return 0;
}

/**
 * Extract product ID from the product link in a table row
 */
function extractProductId(el) {
    var links = el.querySelectorAll('a[href*="product/card.php"]');
    for (var i = 0; i < links.length; i++) {
        var match = links[i].href.match(/[?&]id=(\d+)/);
        if (match) return parseInt(match[1]);
    }
    return null;
}

function getOrderIdFromUrl() {
    var params = new URLSearchParams(window.location.search);
    var id = params.get('id');
    return id ? parseInt(id) : null;
}

function getSocidFromOrderId(orderId) {
    return new Promise(function(resolve, reject) {
        if (!orderId || !ajaxUrl || !detailsToken) { reject(new Error('Missing params')); return; }

        var body = 'action=get_socid_from_order&order_id=' + orderId + '&token=' + encodeURIComponent(detailsToken);
        fetch(ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success && data.socid) resolve(data.socid);
            else reject(new Error('Socid not found'));
        })
        .catch(reject);
    });
}

// --- Modal Open/Close ---

function openDetailsModal(commandedetId, totalQty, productName) {
    if (isLoading) return;

    if (!detailsToken || !ajaxUrl) initializeGlobalVariables();
    if (!detailsToken) {
        alert('Token CSRF non trouvé. Veuillez rafraîchir la page.');
        return;
    }

    currentCommandedetId = commandedetId;
    currentTotalQuantity = totalQty || 1;
    currentProductName = productName || 'Produit';

    document.getElementById('detailsModalTitle').textContent = 'Détails du produit';
    document.getElementById('detailsProductName').textContent = currentProductName;
    document.getElementById('detailsTotalQuantity').textContent = currentTotalQuantity;

    loadExistingDetails();
    document.getElementById('detailsModal').style.display = 'block';
}

function closeDetailsModal() {
    document.getElementById('detailsModal').style.display = 'none';
    clearValidationMessage();
    currentCommandedetId = null;
}

// --- Data Loading ---

function loadExistingDetails() {
    if (!currentCommandedetId || !ajaxUrl || !detailsToken) {
        resetTable();
        return;
    }

    isLoading = true;
    showValidationMessage('Chargement des détails...', 'info');

    var body = 'action=get_details&commandedet_id=' + currentCommandedetId + '&token=' + encodeURIComponent(detailsToken);

    fetch(ajaxUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body
    })
    .then(function(r) {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
    })
    .then(function(data) {
        isLoading = false;
        clearValidationMessage();
        resetTable();

        if (data.success && data.details && data.details.length > 0) {
            data.details.forEach(function(d) { addDetailsRow(d); });
        } else {
            addDetailsRow();
        }
        calculateTotals();
    })
    .catch(function(err) {
        isLoading = false;
        showValidationMessage('Erreur: ' + err.message, 'error');
        resetTable();
    });
}

function resetTable() {
    document.getElementById('detailsTableBody').innerHTML = '';
    rowCounter = 0;
}

// --- Row Management ---

function addDetailsRow(data) {
    var tableBody = document.getElementById('detailsTableBody');
    var row = document.createElement('tr');
    var id = ++rowCounter;

    var rowColor = (data && data.color) ? data.color : '';

    row.innerHTML =
        '<td><input type="number" class="details-cell-input details-cell-number" id="pieces_' + id + '" value="' + (data ? data.pieces : '') + '" min="1" step="1" placeholder="1" onchange="calculateRowTotal(' + id + ')" onkeydown="handleKeyNavigation(event,' + id + ',0)"></td>' +
        '<td><input type="number" class="details-cell-input details-cell-number" id="longueur_' + id + '" value="' + (data && data.longueur ? data.longueur : '') + '" min="0" step="0.1" placeholder="0" onchange="calculateRowTotal(' + id + ')" onkeydown="handleKeyNavigation(event,' + id + ',1)"></td>' +
        '<td><input type="number" class="details-cell-input details-cell-number" id="largeur_' + id + '" value="' + (data && data.largeur ? data.largeur : '') + '" min="0" step="0.1" placeholder="0" onchange="calculateRowTotal(' + id + ')" onkeydown="handleKeyNavigation(event,' + id + ',2)"></td>' +
        '<td class="details-cell-calculated"><span id="total_' + id + '" data-value="0" data-unit="">0 u</span></td>' +
        '<td><input type="text" class="details-cell-input" id="description_' + id + '" value="' + (data && data.description ? data.description : '') + '" placeholder="Description..." onkeydown="handleKeyNavigation(event,' + id + ',4)"></td>' +
        '<td class="details-row-actions"><button class="details-row-color-btn" onclick="showColorPicker(' + id + ', this)" title="Couleur de surlignage">\ud83c\udfa8</button><button class="details-row-delete" onclick="removeDetailsRow(this)" title="Supprimer">\u2716</button></td>';

    tableBody.appendChild(row);
    if (rowColor) { row.style.backgroundColor = rowColor; row.dataset.color = rowColor; }
    if (data) calculateRowTotal(id);

    setTimeout(function() {
        var input = document.getElementById('pieces_' + id);
        if (input) input.focus();
    }, 10);
}

function removeDetailsRow(button) {
    button.closest('tr').remove();
    calculateTotals();
}

// --- Color Picker ---

var DETAIL_COLORS = [
    '#ffff00', '#ffa500', '#00cc00', '#00aaff',
    '#ff69b4', '#ff3333', '#cc66ff', '#aaaaaa'
];

function showColorPicker(rowId, btn) {
    closeColorPicker();

    var palette = document.createElement('div');
    palette.className = 'details-color-palette';
    palette.id = 'detailsColorPalette';

    DETAIL_COLORS.forEach(function(color) {
        var swatch = document.createElement('span');
        swatch.className = 'details-color-swatch';
        swatch.style.backgroundColor = color;
        swatch.title = color;
        swatch.onclick = function(e) { e.stopPropagation(); applyRowColor(rowId, color); };
        palette.appendChild(swatch);
    });

    var clearBtn = document.createElement('button');
    clearBtn.className = 'details-color-clear';
    clearBtn.textContent = 'Effacer';
    clearBtn.onclick = function(e) { e.stopPropagation(); applyRowColor(rowId, ''); };
    palette.appendChild(clearBtn);

    var rect = btn.getBoundingClientRect();
    palette.style.top = rect.bottom + 'px';
    palette.style.left = (rect.right - 130) + 'px';
    document.body.appendChild(palette);

    setTimeout(function() {
        document.addEventListener('click', closeColorPicker, { once: true });
    }, 0);
}

function applyRowColor(rowId, color) {
    var input = document.getElementById('pieces_' + rowId);
    if (!input) return;
    var row = input.closest('tr');
    row.style.backgroundColor = color;
    row.dataset.color = color;
    closeColorPicker();
}

function closeColorPicker() {
    var existing = document.getElementById('detailsColorPalette');
    if (existing) existing.remove();
}

// --- Calculations ---

function calculateRowTotal(rowId) {
    var pieces = parseFloat(document.getElementById('pieces_' + rowId).value) || 0;
    var longueur = parseFloat(document.getElementById('longueur_' + rowId).value) || 0;
    var largeur = parseFloat(document.getElementById('largeur_' + rowId).value) || 0;

    var total = 0, unit = '';

    if (longueur > 0 && largeur > 0) {
        total = pieces * (longueur / 1000) * (largeur / 1000);
        unit = 'm\u00b2';
    } else if (longueur > 0) {
        total = pieces * (longueur / 1000);
        unit = 'ml';
    } else if (largeur > 0) {
        total = pieces * (largeur / 1000);
        unit = 'ml';
    } else if (pieces > 0) {
        total = pieces;
        unit = 'u';
    }

    var el = document.getElementById('total_' + rowId);
    el.textContent = total.toFixed(3) + ' ' + unit;
    el.setAttribute('data-value', total);
    el.setAttribute('data-unit', unit);

    calculateTotals();
}

function calculateTotals() {
    var totalPieces = 0;
    var totals = { 'm\u00b2': 0, 'ml': 0, 'u': 0 };

    var rows = document.querySelectorAll('#detailsTableBody tr');
    rows.forEach(function(row) {
        var pInput = row.querySelector('input[id^="pieces_"]');
        if (pInput) totalPieces += parseFloat(pInput.value) || 0;

        var tCell = row.querySelector('[id^="total_"]');
        if (tCell) {
            var val = parseFloat(tCell.getAttribute('data-value')) || 0;
            var u = tCell.getAttribute('data-unit') || '';
            if (u && totals.hasOwnProperty(u) && val > 0) totals[u] += val;
        }
    });

    document.getElementById('detailsTotalPieces').textContent = totalPieces.toLocaleString();

    // Main unit = highest total value
    var mainUnit = 'u', maxVal = 0;
    for (var u in totals) {
        if (totals[u] > maxVal) { maxVal = totals[u]; mainUnit = u; }
    }

    document.getElementById('detailsTotalQuantityDisplay').textContent = totals[mainUnit].toFixed(3);
    document.getElementById('detailsTotalUnit').textContent = mainUnit;
    document.getElementById('detailsUnitLabel').textContent = mainUnit;
}

// --- Sorting ---

function sortDetailsTable(colIdx) {
    var tbody = document.getElementById('detailsTableBody');
    var rows = Array.from(tbody.querySelectorAll('tr'));

    if (sortColumn === colIdx) { sortDirection = sortDirection === 'asc' ? 'desc' : 'asc'; }
    else { sortDirection = 'asc'; sortColumn = colIdx; }

    document.querySelectorAll('.details-sortable-header').forEach(function(h) {
        h.classList.remove('details-sort-asc', 'details-sort-desc');
    });
    var headers = document.querySelectorAll('.details-sortable-header');
    if (headers[colIdx]) headers[colIdx].classList.add(sortDirection === 'asc' ? 'details-sort-asc' : 'details-sort-desc');

    rows.sort(function(a, b) {
        var aVal, bVal;
        if (colIdx === 4) {
            aVal = (a.querySelector('input[id^="description_"]').value || '').toLowerCase();
            bVal = (b.querySelector('input[id^="description_"]').value || '').toLowerCase();
        } else {
            var aIn = a.querySelectorAll('input[type="number"]')[colIdx];
            var bIn = b.querySelectorAll('input[type="number"]')[colIdx];
            aVal = parseFloat(aIn ? aIn.value : 0) || 0;
            bVal = parseFloat(bIn ? bIn.value : 0) || 0;
        }
        if (aVal < bVal) return sortDirection === 'asc' ? -1 : 1;
        if (aVal > bVal) return sortDirection === 'asc' ? 1 : -1;
        return 0;
    });

    rows.forEach(function(r) { tbody.appendChild(r); });
}

// --- Keyboard Navigation ---

function handleKeyNavigation(event, rowId, colIndex) {
    if (event.key === 'Tab') {
        event.preventDefault();
        var nextRow = rowId, nextCol = colIndex + 1;
        if (nextCol > 4) { nextRow = rowId + 1; nextCol = 0; }
        if (!document.getElementById('pieces_' + nextRow)) { addDetailsRow(); nextRow = rowCounter; }
        focusInput(nextRow, nextCol);
    } else if (event.key === 'Enter') {
        event.preventDefault();
        var nextR = rowId + 1;
        if (!document.getElementById('pieces_' + nextR)) { addDetailsRow(); nextR = rowCounter; }
        focusInput(nextR, colIndex);
    }
}

function focusInput(rowId, colIndex) {
    var ids = ['pieces_' + rowId, 'longueur_' + rowId, 'largeur_' + rowId, '', 'description_' + rowId];
    if (colIndex === 3) colIndex = 4;
    var el = document.getElementById(ids[colIndex]);
    if (el) { el.focus(); el.select(); }
}

// --- Save ---

function saveDetails() {
    if (!currentCommandedetId || !ajaxUrl) {
        showValidationMessage('Erreur: ID de ligne manquant', 'error');
        return;
    }

    var rows = document.querySelectorAll('#detailsTableBody tr');
    var details = [];

    rows.forEach(function(row) {
        var pInput = row.querySelector('input[id^="pieces_"]');
        if (!pInput) return;

        var pieces = parseFloat(pInput.value) || 0;
        if (pieces <= 0) return;

        var longueur = parseFloat(row.querySelector('input[id^="longueur_"]').value) || 0;
        var largeur = parseFloat(row.querySelector('input[id^="largeur_"]').value) || 0;
        var desc = (row.querySelector('input[id^="description_"]').value || '').replace(/[\r\n\t]/g, ' ').replace(/"/g, "'").trim().substring(0, 255);

        var tCell = row.querySelector('[id^="total_"]');
        var totalValue = tCell ? parseFloat(tCell.getAttribute('data-value')) || 0 : 0;
        var unit = tCell ? tCell.getAttribute('data-unit') || 'u' : 'u';
        var color = row.dataset.color || '';

        details.push({
            pieces: pieces,
            longueur: longueur > 0 ? longueur : null,
            largeur: largeur > 0 ? largeur : null,
            total_value: totalValue,
            unit: unit,
            description: desc,
            color: color
        });
    });

    if (details.length === 0) {
        showValidationMessage('Veuillez saisir au moins une ligne.', 'error');
        return;
    }

    isLoading = true;
    showValidationMessage('Sauvegarde en cours...', 'info');

    var formData = new FormData();
    formData.append('action', 'save_details');
    formData.append('commandedet_id', String(currentCommandedetId));
    formData.append('token', detailsToken);

    details.forEach(function(d, i) {
        formData.append('detail[' + i + '][pieces]', String(d.pieces));
        formData.append('detail[' + i + '][longueur]', d.longueur ? String(d.longueur) : '');
        formData.append('detail[' + i + '][largeur]', d.largeur ? String(d.largeur) : '');
        formData.append('detail[' + i + '][total_value]', String(d.total_value));
        formData.append('detail[' + i + '][unit]', d.unit);
        formData.append('detail[' + i + '][description]', d.description);
        formData.append('detail[' + i + '][color]', d.color || '');
    });

    fetch(ajaxUrl, { method: 'POST', body: formData })
    .then(function(r) {
        return r.text().then(function(text) {
            if (!r.ok) {
                try { var err = JSON.parse(text); throw new Error(err.error || r.statusText); }
                catch(e) { throw new Error('HTTP ' + r.status); }
            }
            return text;
        });
    })
    .then(function(text) {
        var data = JSON.parse(text);
        isLoading = false;

        if (data.success) {
            showValidationMessage('Détails sauvegardés ! (' + details.length + ' lignes)', 'success');

            // Auto-update order line quantity
            updateCommandQuantityAutomatic()
            .then(function() {
                closeDetailsModal();
                setTimeout(function() {
                    var params = new URLSearchParams(window.location.search);
                    var orderId = params.get('id');
                    window.location.href = window.location.pathname + (orderId ? '?id=' + encodeURIComponent(orderId) : '');
                }, 1000);
            })
            .catch(function(err) {
                showValidationMessage('Détails sauvegardés. Erreur mise à jour quantité: ' + (err.message || ''), 'error');
                setTimeout(function() {
                    closeDetailsModal();
                    var params = new URLSearchParams(window.location.search);
                    var orderId = params.get('id');
                    window.location.href = window.location.pathname + (orderId ? '?id=' + encodeURIComponent(orderId) : '');
                }, 3000);
            });
        } else {
            showValidationMessage('Erreur: ' + (data.error || 'Erreur inconnue'), 'error');
        }
    })
    .catch(function(err) {
        isLoading = false;
        showValidationMessage('Erreur: ' + err.message, 'error');
    });
}

// --- Quantity Update ---

function getMainUnitAndTotal() {
    var totals = { 'm\u00b2': 0, 'ml': 0, 'u': 0 };
    document.querySelectorAll('#detailsTableBody tr').forEach(function(row) {
        var tCell = row.querySelector('[id^="total_"]');
        if (tCell) {
            var val = parseFloat(tCell.getAttribute('data-value')) || 0;
            var u = tCell.getAttribute('data-unit') || '';
            if (u && totals.hasOwnProperty(u) && val > 0) totals[u] += val;
        }
    });

    var mainUnit = 'u', maxVal = 0;
    for (var u in totals) {
        if (totals[u] > maxVal) { maxVal = totals[u]; mainUnit = u; }
    }
    return { unit: mainUnit, quantity: totals[mainUnit] };
}

function updateCommandQuantityAutomatic() {
    return new Promise(function(resolve, reject) {
        var result = getMainUnitAndTotal();
        if (result.quantity === 0) { reject(new Error('No quantity')); return; }

        var formData = new FormData();
        formData.append('action', 'update_command_quantity');
        formData.append('commandedet_id', currentCommandedetId);
        formData.append('new_quantity', result.quantity.toFixed(2));
        formData.append('unit', result.unit);
        formData.append('token', detailsToken);

        fetch(ajaxUrl, { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) { data.success ? resolve(data) : reject(new Error(data.error)); })
        .catch(reject);
    });
}

function updateCommandQuantity() {
    if (!currentCommandedetId || !ajaxUrl) return;

    var result = getMainUnitAndTotal();
    if (result.quantity === 0) {
        showValidationMessage('Aucune quantité calculée.', 'error');
        return;
    }

    if (!confirm('Mettre à jour la quantité ?\n\nActuelle: ' + currentTotalQuantity + '\nNouvelle: ' + result.quantity.toFixed(2) + ' ' + result.unit)) return;

    var formData = new FormData();
    formData.append('action', 'update_command_quantity');
    formData.append('commandedet_id', currentCommandedetId);
    formData.append('new_quantity', result.quantity.toFixed(2));
    formData.append('unit', result.unit);
    formData.append('token', detailsToken);

    fetch(ajaxUrl, { method: 'POST', body: formData })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            showValidationMessage('Quantité mise à jour !', 'success');
            currentTotalQuantity = result.quantity;
            document.getElementById('detailsTotalQuantity').textContent = result.quantity.toFixed(2);
        } else {
            showValidationMessage('Erreur: ' + (data.error || 'Erreur inconnue'), 'error');
        }
    })
    .catch(function(err) {
        showValidationMessage('Erreur: ' + err.message, 'error');
    });
}

function clearAllDetails() {
    if (confirm('Vider toutes les lignes ?')) {
        resetTable();
        addDetailsRow();
        calculateTotals();
    }
}

// --- Messages ---

function showValidationMessage(msg, type) {
    var el = document.getElementById('detailsValidationMessage');
    if (el) {
        el.className = 'details-validation-message details-validation-' + type;
        el.textContent = msg;
        el.style.display = 'block';
    }
}

function clearValidationMessage() {
    var el = document.getElementById('detailsValidationMessage');
    if (el) { el.style.display = 'none'; el.textContent = ''; }
}

// --- CSV Export ---

function exportToCSV() {
    if (!currentCommandedetId || !ajaxUrl) return;
    var url = ajaxUrl + '?action=export_details_csv&commandedet_id=' + currentCommandedetId + '&token=' + encodeURIComponent(detailsToken);
    var a = document.createElement('a');
    a.href = url;
    a.download = 'details_' + currentCommandedetId + '_' + new Date().toISOString().split('T')[0] + '.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
}

// --- Expose globals ---
window.openDetailsModal = openDetailsModal;
window.closeDetailsModal = closeDetailsModal;
window.addDetailsRow = addDetailsRow;
window.removeDetailsRow = removeDetailsRow;
window.calculateRowTotal = calculateRowTotal;
window.sortDetailsTable = sortDetailsTable;
window.updateCommandQuantity = updateCommandQuantity;
window.clearAllDetails = clearAllDetails;
window.handleKeyNavigation = handleKeyNavigation;
window.saveDetails = saveDetails;
window.exportToCSV = exportToCSV;
window.addDetailsButtonsToExistingLines = addDetailsButtonsToExistingLines;
window.addDetailsButtonToLine = addDetailsButtonToLine;
window.getOrderIdFromUrl = getOrderIdFromUrl;
window.getSocidFromOrderId = getSocidFromOrderId;

} // end double-load protection
