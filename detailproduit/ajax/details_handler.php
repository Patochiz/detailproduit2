<?php
/* Copyright (C) 2025 Patrice GOURMELEN <pgourmelen@diamant-industrie.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    ajax/details_handler.php
 * \ingroup detailproduit
 * \brief   AJAX handler for product details management
 */

// Prevent main.inc.php from rotating CSRF tokens on AJAX calls
// This avoids invalidating the token used in the parent page's forms
if (!defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', 1);
// Disable CSRF check for this AJAX endpoint (we handle it ourselves below)
if (!defined('NOCSRFCHECK')) define('NOCSRFCHECK', 1);

if (!headers_sent()) {
	header('Content-Type: application/json; charset=utf-8');
	header('Cache-Control: no-cache, must-revalidate');
}

// Locate and include main.inc.php
$res = 0;
if (!$res && file_exists(__DIR__."/../../../main.inc.php")) $res = @include __DIR__."/../../../main.inc.php";
if (!$res && file_exists(__DIR__."/../../../../main.inc.php")) $res = @include __DIR__."/../../../../main.inc.php";
if (!$res && file_exists(__DIR__."/../../main.inc.php")) $res = @include __DIR__."/../../main.inc.php";
if (!$res) {
	http_response_code(500);
	echo json_encode(array('success' => false, 'error' => 'Cannot locate main.inc.php'));
	exit;
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
dol_include_once('/detailproduit/class/commandedetdetails.class.php');

// Authentication check
if (!$user || !$user->id) {
	http_response_code(403);
	echo json_encode(array('success' => false, 'error' => 'Authentication required'));
	exit;
}

// Module check
if (!isModEnabled('detailproduit')) {
	http_response_code(403);
	echo json_encode(array('success' => false, 'error' => 'Module not enabled'));
	exit;
}

$action = GETPOST('action', 'alpha');
if (empty($action)) {
	http_response_code(400);
	echo json_encode(array('success' => false, 'error' => 'Missing action parameter'));
	exit;
}

// CSRF token validation for write actions
if (in_array($action, array('save_details', 'update_command_quantity'))) {
	$token = GETPOST('token', 'alpha');
	$token_valid = false;
	if ($token) {
		if (isset($_SESSION['newtoken']) && $token === $_SESSION['newtoken']) $token_valid = true;
		elseif (isset($_SESSION['token']) && $token === $_SESSION['token']) $token_valid = true;
	}
	if (!$token_valid) {
		http_response_code(403);
		echo json_encode(array('success' => false, 'error' => 'Invalid CSRF token'));
		exit;
	}
}

// ---------- GET DETAILS ----------
if ($action == 'get_details') {
	$commandedet_id = GETPOST('commandedet_id', 'int');
	if (!$commandedet_id) {
		http_response_code(400);
		echo json_encode(array('success' => false, 'error' => 'Missing commandedet_id'));
		exit;
	}

	if (!$user->hasRight('commande', 'lire')) {
		http_response_code(403);
		echo json_encode(array('success' => false, 'error' => 'No read permission'));
		exit;
	}

	$details_obj = new CommandeDetDetails($db);
	$details = $details_obj->getDetailsForLine($commandedet_id);

	if ($details === -1) {
		http_response_code(500);
		echo json_encode(array('success' => false, 'error' => 'Database error', 'details' => $details_obj->errors));
		exit;
	}

	echo json_encode(array('success' => true, 'details' => $details));
	exit;
}

// ---------- SAVE DETAILS ----------
elseif ($action == 'save_details') {
	$commandedet_id = GETPOST('commandedet_id', 'int');
	if (!$commandedet_id) {
		http_response_code(400);
		echo json_encode(array('success' => false, 'error' => 'Missing commandedet_id'));
		exit;
	}

	if (!$user->hasRight('commande', 'creer')) {
		http_response_code(403);
		echo json_encode(array('success' => false, 'error' => 'No write permission'));
		exit;
	}

	// Verify order line exists
	$sql = "SELECT c.rowid FROM ".MAIN_DB_PREFIX."commande c";
	$sql .= " INNER JOIN ".MAIN_DB_PREFIX."commandedet cd ON cd.fk_commande = c.rowid";
	$sql .= " WHERE cd.rowid = ".((int) $commandedet_id);
	$resql = $db->query($sql);
	if (!$resql || !$db->num_rows($resql)) {
		http_response_code(404);
		echo json_encode(array('success' => false, 'error' => 'Command line not found'));
		exit;
	}

	$validated_details = array();

	// Method 1: FormData native (detail[] array)
	if (isset($_POST['detail']) && is_array($_POST['detail'])) {
		foreach ($_POST['detail'] as $detail_data) {
			if (!is_array($detail_data)) continue;

			$pieces = isset($detail_data['pieces']) ? (float) $detail_data['pieces'] : 0;
			if ($pieces <= 0) continue;

			$longueur = !empty($detail_data['longueur']) ? (float) $detail_data['longueur'] : null;
			$largeur = !empty($detail_data['largeur']) ? (float) $detail_data['largeur'] : null;
			$calc = CommandeDetDetails::calculateUnitAndValue($pieces, $longueur, $largeur);

			$validated_details[] = array(
				'pieces' => $pieces,
				'longueur' => $longueur,
				'largeur' => $largeur,
				'total_value' => $calc['total_value'],
				'unit' => $calc['unit'],
				'description' => substr(trim($detail_data['description'] ?? ''), 0, 255),
			);
		}
	}
	// Method 2: JSON fallback
	elseif (!empty($_POST['details_json'])) {
		$details_array = json_decode(GETPOST('details_json', 'alpha'), true);
		if (json_last_error() === JSON_ERROR_NONE && is_array($details_array)) {
			foreach ($details_array as $detail) {
				if (!is_array($detail) || !isset($detail['pieces']) || $detail['pieces'] <= 0) continue;

				$pieces = (float) $detail['pieces'];
				$longueur = (!empty($detail['longueur']) && is_numeric($detail['longueur'])) ? (float) $detail['longueur'] : null;
				$largeur = (!empty($detail['largeur']) && is_numeric($detail['largeur'])) ? (float) $detail['largeur'] : null;
				$calc = CommandeDetDetails::calculateUnitAndValue($pieces, $longueur, $largeur);

				$validated_details[] = array(
					'pieces' => $pieces,
					'longueur' => $longueur,
					'largeur' => $largeur,
					'total_value' => $calc['total_value'],
					'unit' => $calc['unit'],
					'description' => substr(trim($detail['description'] ?? ''), 0, 255),
				);
			}
		}
	}

	if (empty($validated_details)) {
		http_response_code(400);
		echo json_encode(array('success' => false, 'error' => 'No valid details provided'));
		exit;
	}

	$details_obj = new CommandeDetDetails($db);
	$result = $details_obj->saveDetailsForLine($commandedet_id, $validated_details, $user);

	if ($result < 0) {
		http_response_code(500);
		echo json_encode(array('success' => false, 'error' => 'Save failed', 'details' => $details_obj->errors));
		exit;
	}

	echo json_encode(array(
		'success' => true,
		'message' => 'Details saved successfully',
		'nb_details' => count($validated_details),
	));
	exit;
}

// ---------- UPDATE COMMAND QUANTITY ----------
elseif ($action == 'update_command_quantity') {
	$commandedet_id = GETPOST('commandedet_id', 'int');
	$new_quantity = GETPOST('new_quantity', 'alpha');
	$unit = GETPOST('unit', 'alpha');

	if (!$commandedet_id || !$new_quantity || !$unit) {
		http_response_code(400);
		echo json_encode(array('success' => false, 'error' => 'Missing parameters'));
		exit;
	}

	if (!$user->hasRight('commande', 'creer')) {
		http_response_code(403);
		echo json_encode(array('success' => false, 'error' => 'No write permission'));
		exit;
	}

	$details_obj = new CommandeDetDetails($db);
	$result = $details_obj->updateCommandLineQuantity($commandedet_id, round((float) $new_quantity, 2), $unit);

	if ($result < 0) {
		http_response_code(500);
		echo json_encode(array('success' => false, 'error' => 'Update failed', 'details' => $details_obj->errors));
		exit;
	}

	echo json_encode(array(
		'success' => true,
		'message' => 'Quantity updated',
		'new_quantity' => $new_quantity,
		'unit' => $unit,
	));
	exit;
}

// ---------- EXPORT CSV ----------
elseif ($action == 'export_details_csv') {
	$commandedet_id = GETPOST('commandedet_id', 'int');
	if (!$commandedet_id) {
		http_response_code(400);
		echo json_encode(array('success' => false, 'error' => 'Missing commandedet_id'));
		exit;
	}

	if (!$user->hasRight('commande', 'lire')) {
		http_response_code(403);
		echo json_encode(array('success' => false, 'error' => 'No read permission'));
		exit;
	}

	$details_obj = new CommandeDetDetails($db);
	$details = $details_obj->getDetailsForLine($commandedet_id);

	if ($details === -1) {
		http_response_code(500);
		echo json_encode(array('success' => false, 'error' => 'Database error'));
		exit;
	}

	header('Content-Type: text/csv; charset=utf-8');
	header('Content-Disposition: attachment; filename="details_'.$commandedet_id.'_'.date('Y-m-d').'.csv"');

	$csv = "Pieces,Longueur,Largeur,Total,Unite,Description\n";
	foreach ($details as $d) {
		$csv .= '"'.$d['pieces'].'","'.($d['longueur'] ?: '').'","'.($d['largeur'] ?: '').'",';
		$csv .= '"'.$d['total_value'].'","'.$d['unit'].'","'.str_replace('"', '""', $d['description']).'"'."\n";
	}
	echo $csv;
	exit;
}

// ---------- GET SOCID FROM ORDER ----------
elseif ($action == 'get_socid_from_order') {
	$order_id = GETPOST('order_id', 'int');
	if (!$order_id) {
		http_response_code(400);
		echo json_encode(array('success' => false, 'error' => 'Missing order_id'));
		exit;
	}

	if (!$user->hasRight('commande', 'lire')) {
		http_response_code(403);
		echo json_encode(array('success' => false, 'error' => 'No read permission'));
		exit;
	}

	$sql = "SELECT fk_soc, ref FROM ".MAIN_DB_PREFIX."commande WHERE rowid = ".((int) $order_id);
	$resql = $db->query($sql);
	if (!$resql || !$db->num_rows($resql)) {
		http_response_code(404);
		echo json_encode(array('success' => false, 'error' => 'Order not found'));
		exit;
	}

	$obj = $db->fetch_object($resql);
	echo json_encode(array('success' => true, 'socid' => $obj->fk_soc, 'order_ref' => $obj->ref));
	exit;
}

// ---------- UNKNOWN ACTION ----------
else {
	http_response_code(400);
	echo json_encode(array('success' => false, 'error' => 'Unknown action: '.$action));
	exit;
}
