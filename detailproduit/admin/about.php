<?php
/* Copyright (C) 2025 Patrice GOURMELEN <pgourmelen@diamant-industrie.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    admin/about.php
 * \ingroup detailproduit
 * \brief   About page for Détails Produit module
 */

$res = 0;
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
dol_include_once('/detailproduit/lib/detailproduit.lib.php');

$langs->loadLangs(array("admin", "detailproduit@detailproduit"));

if (!$user->admin) accessforbidden();

/*
 * View
 */

llxHeader('', $langs->trans("DetailproduitSetup"));

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans("DetailproduitSetup"), $linkback, 'title_setup');

$head = detailproduitAdminPrepareHead();
print dol_get_fiche_head($head, 'about', $langs->trans("DetailproduitSetup"), -1, "detailproduit");

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td class="titlefield">'.$langs->trans("Parameter").'</td>';
print '<td>'.$langs->trans("Value").'</td>';
print '</tr>';

print '<tr class="oddeven"><td>Version</td><td>3.0</td></tr>';
print '<tr class="oddeven"><td>Auteur</td><td>Patrice GOURMELEN - DIAMANT INDUSTRIE</td></tr>';
print '<tr class="oddeven"><td>Contact</td><td>pgourmelen@diamant-industrie.com</td></tr>';
print '<tr class="oddeven"><td>Licence</td><td>GPL v3+</td></tr>';
print '<tr class="oddeven"><td>Description</td><td>'.$langs->trans("ModuleDetailproduitDesc").'</td></tr>';

print '</table>';
print '</div>';

print dol_get_fiche_end();

llxFooter();
$db->close();
