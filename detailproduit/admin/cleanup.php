<?php
/* Copyright (C) 2025 Patrice GOURMELEN <pgourmelen@diamant-industrie.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    admin/cleanup.php
 * \ingroup detailproduit
 * \brief   Data integrity and cleanup page
 */

$res = 0;
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
dol_include_once('/detailproduit/lib/detailproduit.lib.php');
dol_include_once('/detailproduit/class/commandedetdetails.class.php');

$langs->loadLangs(array("admin", "detailproduit@detailproduit"));

if (!$user->admin) accessforbidden();

$action = GETPOST('action', 'aZ09');
$details_obj = new CommandeDetDetails($db);

// Actions
if ($action == 'cleanup') {
	$stats = $details_obj->cleanupOrphanedData();
	$msg = $stats['orphaned_extrafields_cleaned'].' extrafields nettoyés';
	if (count($stats['errors']) > 0) {
		setEventMessages($msg, $stats['errors'], 'warnings');
	} else {
		setEventMessages($msg, null, 'mesgs');
	}
}

/*
 * View
 */

llxHeader('', $langs->trans("DetailproduitSetup"));

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans("DetailproduitSetup"), $linkback, 'title_setup');

$head = detailproduitAdminPrepareHead();
print dol_get_fiche_head($head, 'cleanup', $langs->trans("DetailproduitSetup"), -1, "detailproduit");

$report = $details_obj->checkDataIntegrity();

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Parameter").'</td>';
print '<td align="center" width="20">'.$langs->trans("Status").'</td>';
print '<td align="center" width="100">'.$langs->trans("Action").'</td>';
print '</tr>';

// Integrity check
print '<tr class="oddeven"><td>';
print '<strong>'.$langs->trans("DataIntegrity").'</strong><br>';
print '<span class="opacitymedium">Extrafields avec detailjson: '.$report['total_extrafields_with_detailjson'].'</span><br>';
print '<span class="opacitymedium">Extrafields avec detail: '.$report['total_extrafields_with_detail'].'</span><br>';
print '<span class="opacitymedium">Extrafields orphelins: '.count($report['orphaned_extrafields']).'</span><br>';
print '<span class="opacitymedium">JSON invalides: '.count($report['invalid_json_extrafields']).'</span>';
print '</td>';
print '<td align="center">';
if ($report['integrity_ok']) {
	print '<span class="ok">OK</span>';
} else {
	$nb = count($report['orphaned_extrafields']) + count($report['invalid_json_extrafields']);
	print '<span class="warning">'.$nb.' problème(s)</span>';
}
print '</td>';
print '<td align="center">';
if (!$report['integrity_ok']) {
	print '<a href="'.$_SERVER["PHP_SELF"].'?action=cleanup&token='.newToken().'" class="button"';
	print ' onclick="return confirm(\''.$langs->trans("CleanupConfirm").'\');">';
	print $langs->trans("Cleanup");
	print '</a>';
} else {
	print '<span class="opacitymedium">-</span>';
}
print '</td></tr>';

// Trigger status
$trigger_file = dol_buildpath('/detailproduit/core/triggers/interface_99_modDetailproduit_Detailproduittrigger.class.php');
print '<tr class="oddeven"><td>';
print '<strong>Trigger de nettoyage automatique</strong><br>';
print '<span class="opacitymedium">Nettoyage automatique lors de la suppression de lignes de commande</span>';
print '</td>';
print '<td align="center">';
print file_exists($trigger_file) ? '<span class="ok">Installé</span>' : '<span class="error">Manquant</span>';
print '</td>';
print '<td align="center">-</td></tr>';

print '</table>';
print '</div>';

print dol_get_fiche_end();

llxFooter();
$db->close();
