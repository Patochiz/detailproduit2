<?php
/* Copyright (C) 2025 Patrice GOURMELEN <pgourmelen@diamant-industrie.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    lib/detailproduit.lib.php
 * \ingroup detailproduit
 * \brief   Library functions for detailproduit module
 */

/**
 * Prepare admin pages header tabs
 *
 * @return array
 */
function detailproduitAdminPrepareHead()
{
	global $langs, $conf;

	$langs->load("detailproduit@detailproduit");

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath("/detailproduit/admin/setup.php", 1);
	$head[$h][1] = $langs->trans("Settings");
	$head[$h][2] = 'settings';
	$h++;

	$head[$h][0] = dol_buildpath("/detailproduit/admin/cleanup.php", 1);
	$head[$h][1] = $langs->trans("DataIntegrity");
	$head[$h][2] = 'cleanup';
	$h++;

	$head[$h][0] = dol_buildpath("/detailproduit/admin/about.php", 1);
	$head[$h][1] = $langs->trans("About");
	$head[$h][2] = 'about';
	$h++;

	complete_head_from_modules($conf, $langs, null, $head, $h, 'detailproduit@detailproduit');
	complete_head_from_modules($conf, $langs, null, $head, $h, 'detailproduit@detailproduit', 'remove');

	return $head;
}
