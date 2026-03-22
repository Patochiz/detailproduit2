<?php
/* Copyright (C) 2025 Patrice GOURMELEN <pgourmelen@diamant-industrie.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    admin/setup.php
 * \ingroup detailproduit
 * \brief   Setup page for Détails Produit module
 */

$res = 0;
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
dol_include_once('/detailproduit/lib/detailproduit.lib.php');

$langs->loadLangs(array("admin", "detailproduit@detailproduit"));

if (!$user->admin) accessforbidden();

$action = GETPOST('action', 'aZ09');

/*
 * View
 */

$form = new Form($db);

llxHeader('', $langs->trans("DetailproduitSetup"));

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans("DetailproduitSetup"), $linkback, 'title_setup');

$head = detailproduitAdminPrepareHead();
print dol_get_fiche_head($head, 'settings', $langs->trans("DetailproduitSetup"), -1, "detailproduit");

print '<div class="info">';
print $langs->trans("DetailproduitSetupPage");
print '<br><br>';
print '<strong>Extrafields requis :</strong><br>';
print 'Ce module nécessite deux extrafields sur les lignes de commande (commandedet) :<br>';
print '- <code>detailjson</code> (type: text long, invisible) - stockage JSON des détails<br>';
print '- <code>detail</code> (type: text/HTML, visible) - affichage formaté<br>';
print '<br>';
print 'Créez-les dans Configuration > Dictionnaires > Champs supplémentaires > Lignes de commande.';
print '</div>';

print '<br>';

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Parameter").'</td>';
print '<td>'.$langs->trans("Value").'</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>Version du module</td>';
print '<td>3.0</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>Stockage des données</td>';
print '<td>Extrafields (commandedet_extrafields.detailjson)</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>Contextes hooks</td>';
print '<td>ordercard</td>';
print '</tr>';

print '</table>';
print '</div>';

print dol_get_fiche_end();

llxFooter();
$db->close();
