<?php
/* Copyright (C) 2025 Patrice GOURMELEN <pgourmelen@diamant-industrie.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    core/hooks/detailproduit.class.php
 * \ingroup detailproduit
 * \brief   Hook handler for detailproduit module
 *
 * Injects CSS/JS assets into the ordercard page via printCommonFooter.
 * The actual per-line buttons are created by details_popup.js and label_update.js.
 *
 * Uses print instead of $this->resprints to avoid interference from other
 * modules (productionfiche, productselector, etc.) that may override
 * resprints via HookManager's aggregation mechanism.
 */

class ActionsDetailproduit
{
	/** @var DoliDB */
	public $db;
	public $error = '';
	public $errors = array();
	public $results = array();
	public $resprints;

	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Hook: required by Dolibarr convention
	 */
	public function getNomUrl($parameters, &$object, &$action, $hookmanager)
	{
		return 0;
	}

	/**
	 * Hook: inject CSS/JS assets in page footer
	 *
	 * Uses print directly to write to output buffer.
	 * This bypasses the HookManager resprints mechanism which can be
	 * overwritten by other modules returning 1 in their own printCommonFooter.
	 *
	 * @param array  $parameters Hook parameters
	 * @param object $object     Current object
	 * @param string $action     Current action
	 * @param object $hookmanager Hook manager instance
	 * @return int 0 = OK (does not interfere with other hooks)
	 */
	public function printCommonFooter($parameters, &$object, &$action, $hookmanager)
	{
		global $conf;

		if (!isModEnabled('detailproduit')) {
			return 0;
		}

		// Runtime flag to avoid double-injection
		if (!empty($conf->detailproduit->assets_loaded)) {
			return 0;
		}
		$conf->detailproduit->assets_loaded = 1;

		// Print directly — immune to resprints override by other modules
		print '<link rel="stylesheet" type="text/css" href="'.dol_buildpath('/detailproduit/css/details_popup.css', 1).'">';

		print '<script type="text/javascript">';
		print 'window.DOL_URL_ROOT = "'.DOL_URL_ROOT.'";';
		print 'window.detailproduit_token = "'.newToken().'";';
		print '</script>';

		print '<script type="text/javascript" src="'.dol_buildpath('/detailproduit/js/label_update.js', 1).'"></script>';
		print '<script type="text/javascript" src="'.dol_buildpath('/detailproduit/js/details_popup.js', 1).'"></script>';

		return 0;
	}
}
