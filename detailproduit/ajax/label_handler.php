<?php
/* Copyright (C) 2025 Patrice GOURMELEN <pgourmelen@diamant-industrie.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    ajax/label_handler.php
 * \ingroup detailproduit
 * \brief   AJAX handler for service label management
 */

// Prevent main.inc.php from rotating CSRF tokens on AJAX calls
if (!defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', 1);
if (!defined('NOCSRFCHECK')) define('NOCSRFCHECK', 1);

// Mode debug
$debug_mode = false;

// Function pour log debug
function debug_log($message) {
    global $debug_mode;
    if ($debug_mode) {
        error_log("[DetailProduit Label AJAX] " . $message);
    }
}

// Headers
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');
}

try {
    debug_log("=== DEBUT LABEL HANDLER AJAX ===");

    // Trouver main.inc.php
    $main_found = false;
    $main_path = '';

    $standard_paths = array(
        __DIR__ . "/../../../main.inc.php",
        __DIR__ . "/../../../../main.inc.php",
        __DIR__ . "/../../main.inc.php",
    );

    foreach ($standard_paths as $path) {
        $real_path = realpath($path);
        if ($real_path && file_exists($real_path) && is_readable($real_path)) {
            $main_path = $real_path;
            $main_found = true;
            break;
        }
    }

    if (!$main_found) {
        $current_dir = __DIR__;
        for ($i = 0; $i < 10; $i++) {
            $test_path = $current_dir . "/main.inc.php";
            if (file_exists($test_path) && is_readable($test_path)) {
                $main_path = realpath($test_path);
                $main_found = true;
                break;
            }
            $parent_dir = dirname($current_dir);
            if ($parent_dir === $current_dir) break;
            $current_dir = $parent_dir;
        }
    }

    if (!$main_found) {
        http_response_code(500);
        echo json_encode(array('success' => false, 'error' => 'Cannot locate main.inc.php'));
        exit;
    }

    $res = @include_once $main_path;

    if (!$res || !isset($db) || !isset($user)) {
        http_response_code(500);
        echo json_encode(array('success' => false, 'error' => 'Failed to include main.inc.php'));
        exit;
    }

    // Vérifier authentification
    if (!$user || !$user->id) {
        http_response_code(403);
        echo json_encode(array('success' => false, 'error' => 'Authentication required'));
        exit;
    }

    // Vérifier module activé
    if (!isModEnabled('detailproduit')) {
        http_response_code(403);
        echo json_encode(array('success' => false, 'error' => 'Module detailproduit not enabled'));
        exit;
    }

    // Récupérer l'action
    $action = GETPOST('action', 'alpha');
    debug_log("Action: " . $action);

    if (empty($action)) {
        http_response_code(400);
        echo json_encode(array('success' => false, 'error' => 'Missing action parameter'));
        exit;
    }

    // Vérification CSRF pour les actions de modification
    if (in_array($action, array('save_label_update'))) {
        $token = GETPOST('token', 'alpha');
        $token_valid = false;

        if ($token) {
            if (isset($_SESSION['newtoken']) && $token === $_SESSION['newtoken']) {
                $token_valid = true;
            } elseif (isset($_SESSION['token']) && $token === $_SESSION['token']) {
                $token_valid = true;
            } elseif ($debug_mode && strlen($token) > 10) {
                $token_valid = true;
            }
        }

        if (!$token_valid) {
            http_response_code(403);
            echo json_encode(array('success' => false, 'error' => 'Invalid CSRF token'));
            exit;
        }
    }

    /**
     * Récupérer les contacts du tiers (hors civilité "Adresse")
     */
    if ($action == 'get_thirdparty_contacts') {
        debug_log("=== ACTION: get_thirdparty_contacts ===");

        $socid = GETPOST('socid', 'int');
        debug_log("Socid: " . $socid);

        if (!$socid) {
            http_response_code(400);
            echo json_encode(array('success' => false, 'error' => 'Missing socid parameter'));
            exit;
        }

        // Vérifier permissions
        if (!$user->hasRight('societe', 'lire')) {
            http_response_code(403);
            echo json_encode(array('success' => false, 'error' => 'No read permission for thirdparties'));
            exit;
        }

        // Récupérer les contacts du tiers en excluant ceux avec civilité "ADR" (Adresse)
        $sql = "SELECT c.rowid, c.lastname, c.firstname, c.civility";
        $sql .= " FROM ".MAIN_DB_PREFIX."socpeople c";
        $sql .= " WHERE c.fk_soc = ".((int) $socid);
        $sql .= " AND c.statut = 1";  // Actif
        $sql .= " AND (c.civility IS NULL OR c.civility != 'ADR')";  // Exclure civilité Adresse
        $sql .= " ORDER BY c.lastname, c.firstname";

        debug_log("SQL contacts: " . $sql);

        $resql = $db->query($sql);
        if (!$resql) {
            http_response_code(500);
            echo json_encode(array('success' => false, 'error' => 'Database error: ' . $db->lasterror()));
            exit;
        }

        $contacts = array();
        while ($obj = $db->fetch_object($resql)) {
            $name = trim(($obj->firstname ? $obj->firstname . ' ' : '') . $obj->lastname);
            if (empty($name)) $name = 'Contact #' . $obj->rowid;

            $contacts[] = array(
                'id' => $obj->rowid,
                'name' => $name
            );
        }

        debug_log("Contacts trouvés: " . count($contacts));
        echo json_encode(array('success' => true, 'contacts' => $contacts));
        exit;
    }

    /**
     * Récupérer les données de label existantes
     */
    elseif ($action == 'get_label_data') {
        debug_log("=== ACTION: get_label_data ===");

        $commandedet_id = GETPOST('commandedet_id', 'int');
        debug_log("Commandedet ID: " . $commandedet_id);

        if (!$commandedet_id) {
            http_response_code(400);
            echo json_encode(array('success' => false, 'error' => 'Missing commandedet_id parameter'));
            exit;
        }

        // Vérifier permissions
        if (!$user->hasRight('commande', 'lire')) {
            http_response_code(403);
            echo json_encode(array('success' => false, 'error' => 'No read permission for orders'));
            exit;
        }

        // Récupérer les données stockées dans les extrafields de la ligne
        $sql = "SELECT ref_commande, detailjson FROM ".MAIN_DB_PREFIX."commandedet_extrafields";
        $sql .= " WHERE fk_object = ".((int) $commandedet_id);

        debug_log("SQL get label data: " . $sql);

        $resql = $db->query($sql);
        if (!$resql) {
            http_response_code(500);
            echo json_encode(array('success' => false, 'error' => 'Database error: ' . $db->lasterror()));
            exit;
        }

        $data = array(
            'n_commande' => '',
            'date_commande' => '',
            'contact' => '',
            'ref_commande' => ''
        );

        if ($db->num_rows($resql) > 0) {
            $obj = $db->fetch_object($resql);
            $data['ref_commande'] = $obj->ref_commande ?: '';
            // Les champs du popup service sont stockés en JSON dans detailjson
            if (!empty($obj->detailjson)) {
                $json = json_decode($obj->detailjson, true);
                if (is_array($json)) {
                    $data['n_commande']    = isset($json['n_commande'])    ? $json['n_commande']    : '';
                    $data['date_commande'] = isset($json['date_commande']) ? $json['date_commande'] : '';
                    $data['contact']       = isset($json['contact_id'])    ? $json['contact_id']    : '';
                }
            }
            debug_log("Ref commande trouvée: " . $data['ref_commande']);
            debug_log("N commande trouvée: " . $data['n_commande']);
            debug_log("Date commande trouvée: " . $data['date_commande']);
            debug_log("Contact ID trouvé: " . $data['contact']);
        }

        echo json_encode(array('success' => true, 'data' => $data));
        exit;
    }

    /**
     * Sauvegarder la mise à jour du label
     */
    elseif ($action == 'save_label_update') {
        debug_log("=== ACTION: save_label_update ===");

        $commandedet_id = GETPOST('commandedet_id', 'int');
        $n_commande = GETPOST('n_commande', 'alpha');
        $date_commande = GETPOST('date_commande', 'alpha');
        $contact_id = GETPOST('contact', 'int');
        $ref_commande = GETPOST('ref_commande', 'alpha');

        debug_log("Commandedet ID: " . $commandedet_id);
        debug_log("N Commande: " . $n_commande);
        debug_log("Date Commande: " . $date_commande);
        debug_log("Contact ID: " . $contact_id);
        debug_log("Ref Commande: " . $ref_commande);

        if (!$commandedet_id) {
            http_response_code(400);
            echo json_encode(array('success' => false, 'error' => 'Missing commandedet_id parameter'));
            exit;
        }

        // Vérifier permissions
        if (!$user->hasRight('commande', 'creer')) {
            http_response_code(403);
            echo json_encode(array('success' => false, 'error' => 'No write permission for orders'));
            exit;
        }

        // Charger la commande pour utiliser l'API Dolibarr
        require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
        
        // Récupérer l'ID de la commande depuis la ligne
        $sql = "SELECT fk_commande FROM ".MAIN_DB_PREFIX."commandedet WHERE rowid = ".((int) $commandedet_id);
        $resql = $db->query($sql);
        if (!$resql || $db->num_rows($resql) == 0) {
            http_response_code(404);
            echo json_encode(array('success' => false, 'error' => 'Order line not found'));
            exit;
        }
        
        $obj = $db->fetch_object($resql);
        $order_id = $obj->fk_commande;
        
        debug_log("Order ID: " . $order_id);
        
        // Charger la commande
        $order = new Commande($db);
        $result = $order->fetch($order_id);
        
        if ($result <= 0) {
            http_response_code(404);
            echo json_encode(array('success' => false, 'error' => 'Failed to load order'));
            exit;
        }

        // Récupérer le nom du contact si ID fourni
        $contact_name = '';
        if ($contact_id) {
            $sql = "SELECT firstname, lastname FROM ".MAIN_DB_PREFIX."socpeople";
            $sql .= " WHERE rowid = ".((int) $contact_id);

            $resql = $db->query($sql);
            if ($resql && $db->num_rows($resql) > 0) {
                $obj = $db->fetch_object($resql);
                $contact_name = trim(($obj->firstname ? $obj->firstname . ' ' : '') . $obj->lastname);
                debug_log("Contact name: " . $contact_name);
            }
        }

        // Construire le texte du label
        // Format: "Commande [N° Commande] du [Date Commande] de [Contact Commande] ref : [Ref Chantier]"
        $label_parts = array();

        if (!empty($n_commande)) {
            $label_parts[] = "Commande " . $n_commande;
        }

        if (!empty($date_commande)) {
            // Formater la date au format français (JJ/MM/AAAA)
            $date_obj = DateTime::createFromFormat('Y-m-d', $date_commande);
            if ($date_obj) {
                $label_parts[] = "du " . $date_obj->format('d/m/Y');
            }
        }

        if (!empty($contact_name)) {
            $label_parts[] = "de " . $contact_name;
        }

        if (!empty($ref_commande)) {
            $label_parts[] = "ref : " . $ref_commande;
        }

        $label_text = implode(' ', $label_parts);
        debug_log("Texte du label: " . $label_text);

        if (empty($label_text)) {
            http_response_code(400);
            echo json_encode(array('success' => false, 'error' => 'Label cannot be empty'));
            exit;
        }

        // Construire le tableau HTML
        // Format demandé par l'utilisateur
        $new_label_html = '<table border="0" cellpadding="1" cellspacing="1" style="width:500px">' . "\n";
        $new_label_html .= "\t" . '<tbody>' . "\n";
        $new_label_html .= "\t\t" . '<tr>' . "\n";
        $new_label_html .= "\t\t\t" . '<td>' . $label_text . '</td>' . "\n";
        $new_label_html .= "\t\t" . '</tr>' . "\n";
        $new_label_html .= "\t" . '</tbody>' . "\n";
        $new_label_html .= '</table>';

        debug_log("Label HTML (avant): " . $new_label_html);

        // Utiliser l'API Dolibarr pour mettre à jour la ligne
        // Cela garantit que le HTML est bien traité
        // Charger aussi les lignes
$order->fetch_lines();

// Trouver la ligne à modifier
$line = null;
foreach ($order->lines as $l) {
    if ($l->id == $commandedet_id) {
        $line = $l;
        break;
    }
}

if (!$line) {
    http_response_code(404);
    echo json_encode(array('success' => false, 'error' => 'Ligne non trouvée dans la commande'));
    exit;
}

// Mise à jour : uniquement la description HTML
$result = $order->updateline(
    $line->rowid,                      // 1. rowid
    $label_text,               // 2. description (HTML)
    $line->subprice,               // 3. pu
    $line->qty,                    // 4. qty
    $line->remise_percent,         // 5. remise %
    $line->tva_tx,                 // 6. TVA
    $line->localtax1_tx,           // 7. taxe locale 1
    $line->localtax2_tx,           // 8. taxe locale 2
    'HT',                          // 9. base de prix
    $line->info_bits,              // 10
    $line->date_start,             // 11
    $line->date_end,               // 12
    $line->product_type,           // 13. type produit/service
    $line->fk_parent_line,         // 14
    0,                             // 15. skip update total
    $line->fk_fournprice,          // 16
    $line->pa_ht,                  // 17
    $line->label,                  // 18. label texte simple
    $line->special_code,           // 19
    $line->array_options,          // 20
    $line->fk_unit,                // 21
    $line->multicurrency_subprice, // 22
    0,                             // 23. notrigger
    $line->ref_ext,                // 24
    $line->rang                    // 25
);

if ($result < 0) {
    debug_log("ERREUR updateline: " . $order->error);
    debug_log("Erreurs: " . implode(', ', $order->errors));

    http_response_code(500);
    echo json_encode(array(
        'success' => false,
        'error' => 'Failed to update line: ' . $order->error,
        'details' => $order->errors
    ));
    exit;
}


        if ($result < 0) {
            debug_log("ERREUR updateline: " . $order->error);
            debug_log("Erreurs: " . implode(', ', $order->errors));
            
            http_response_code(500);
            echo json_encode(array(
                'success' => false, 
                'error' => 'Failed to update line: ' . $order->error,
                'details' => $order->errors
            ));
            exit;
        }

        debug_log("Ligne mise à jour via API Dolibarr");

        // Vérifier que la mise à jour a bien fonctionné
        $sql = "SELECT description FROM ".MAIN_DB_PREFIX."commandedet WHERE rowid = ".((int) $commandedet_id);
        $resql = $db->query($sql);
        if ($resql && $db->num_rows($resql) > 0) {
            $obj = $db->fetch_object($resql);
            debug_log("Description après update: " . substr($obj->description, 0, 200) . "...");
        }

        // Mise à jour des extrafields
        debug_log("=== MISE A JOUR DES EXTRAFIELDS ===");
        
        // Vérifier si l'entrée existe déjà dans les extrafields
        $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."commandedet_extrafields";
        $sql .= " WHERE fk_object = ".((int) $commandedet_id);

        $resql = $db->query($sql);
        $exists = ($resql && $db->num_rows($resql) > 0);

        // Lire le detailjson existant pour ne pas écraser d'autres clés éventuelles
        $existing_json = array();
        $sql_json = "SELECT detailjson FROM ".MAIN_DB_PREFIX."commandedet_extrafields";
        $sql_json .= " WHERE fk_object = ".((int) $commandedet_id);
        $resql_json = $db->query($sql_json);
        if ($resql_json && $db->num_rows($resql_json) > 0) {
            $obj_json = $db->fetch_object($resql_json);
            if (!empty($obj_json->detailjson)) {
                $decoded = json_decode($obj_json->detailjson, true);
                if (is_array($decoded)) {
                    $existing_json = $decoded;
                }
            }
        }

        // Fusionner les champs du popup service dans le JSON
        $existing_json['n_commande']    = $n_commande;
        $existing_json['date_commande'] = $date_commande;
        $existing_json['contact_id']    = $contact_id ? (int)$contact_id : null;
        $new_detailjson = json_encode($existing_json);

        if ($exists) {
            // Mise à jour
            $sql = "UPDATE ".MAIN_DB_PREFIX."commandedet_extrafields";
            $sql .= " SET ref_commande = '".$db->escape($ref_commande)."'";
            $sql .= ", ref_chantier = '".$db->escape($label_text)."'";
            $sql .= ", detailjson = '".$db->escape($new_detailjson)."'";
            $sql .= " WHERE fk_object = ".((int) $commandedet_id);
        } else {
            // Insertion
            $sql = "INSERT INTO ".MAIN_DB_PREFIX."commandedet_extrafields";
            $sql .= " (fk_object, ref_commande, ref_chantier, detailjson)";
            $sql .= " VALUES (";
            $sql .= ((int) $commandedet_id).", ";
            $sql .= "'".$db->escape($ref_commande)."', ";
            $sql .= "'".$db->escape($label_text)."', ";
            $sql .= "'".$db->escape($new_detailjson)."'";
            $sql .= ")";
        }

        debug_log("SQL update extrafields: " . $sql);

        $resql = $db->query($sql);
        if (!$resql) {
            debug_log("WARNING: Failed to update extrafields: " . $db->lasterror());
            // Ne pas faire échouer toute la sauvegarde pour autant
        } else {
            debug_log("Extrafields mis à jour avec succès");
            debug_log("- ref_commande: " . $ref_commande);
            debug_log("- ref_chantier (texte brut): " . $label_text);
            debug_log("- detailjson: " . $new_detailjson);
        }

        // Mise à jour de l'extrafield designations_service_361 sur la commande
        // Collecte toutes les désignations (description) des lignes du service ID 361
        debug_log("=== MISE A JOUR EXTRAFIELD designations_service_361 ===");
        $sql_desig = "SELECT cd.label, cd.description FROM ".MAIN_DB_PREFIX."commandedet as cd";
        $sql_desig .= " WHERE cd.fk_commande = ".((int) $order_id);
        $sql_desig .= " AND cd.fk_product = 361";
        $sql_desig .= " ORDER BY cd.rang ASC";

        $resql_desig = $db->query($sql_desig);
        if ($resql_desig) {
            $designations = array();
            while ($obj_desig = $db->fetch_object($resql_desig)) {
                $desig_text = !empty($obj_desig->label) ? $obj_desig->label : $obj_desig->description;
                if (!empty($desig_text)) {
                    $designations[] = $desig_text;
                }
            }
            $db->free($resql_desig);

            $desig_html = !empty($designations) ? '<ul><li>' . implode('</li><li>', $designations) . '</li></ul>' : '';
            $desig_value = $db->escape($desig_html);

            // Vérifier si une entrée extrafield existe déjà pour cette commande
            $sql_check = "SELECT rowid FROM ".MAIN_DB_PREFIX."commande_extrafields WHERE fk_object = ".((int) $order_id);
            $resql_check = $db->query($sql_check);
            if ($resql_check) {
                $obj_check = $db->fetch_object($resql_check);
                $db->free($resql_check);
                if ($obj_check) {
                    $sql_write = "UPDATE ".MAIN_DB_PREFIX."commande_extrafields";
                    $sql_write .= " SET designations_service_361 = '".$desig_value."'";
                    $sql_write .= " WHERE fk_object = ".((int) $order_id);
                } else {
                    $sql_write = "INSERT INTO ".MAIN_DB_PREFIX."commande_extrafields (fk_object, designations_service_361)";
                    $sql_write .= " VALUES (".((int) $order_id).", '".$desig_value."')";
                }
                $db->query($sql_write);
                debug_log("designations_service_361 mis à jour (".count($designations)." ligne(s))");
            }
        }

        debug_log("Label sauvegardé avec succès");
        echo json_encode(array(
            'success' => true,
            'message' => 'Label updated successfully',
            'new_label' => $new_label_html,
            'new_label_text' => $label_text  // Renvoyer aussi le texte pour l'aperçu JS
        ));
        exit;
    }

    /**
     * Action non reconnue
     */
    else {
        debug_log("ERREUR: Action non reconnue: " . $action);
        http_response_code(400);
        echo json_encode(array('success' => false, 'error' => 'Unknown action: '.$action));
        exit;
    }

} catch (Exception $e) {
    debug_log("EXCEPTION: " . $e->getMessage());
    debug_log("Stack trace: " . $e->getTraceAsString());

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }

    echo json_encode(array(
        'success' => false,
        'error' => 'Internal server error: ' . $e->getMessage()
    ));
    exit;
}

debug_log("=== FIN LABEL HANDLER AJAX ===");
exit;
