<?php
/* Copyright (C) 2025 Patrice GOURMELEN <pgourmelen@diamant-industrie.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \defgroup   detailproduit     Module Detailproduit
 * \brief      Module descriptor for Détails Produit
 *
 * \file       core/modules/modDetailproduit.class.php
 * \ingroup    detailproduit
 */

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 * Module descriptor for Détails Produit
 *
 * Manages dimensional details (pieces, length, width) for order lines
 * with automatic calculation of m², ml or units.
 * Data is stored in commandedet extrafields (detailjson + detail).
 */
class modDetailproduit extends DolibarrModules
{
	public function __construct($db)
	{
		global $langs, $conf;

		$this->db = $db;

		$this->numero = 500003;
		$this->rights_class = 'detailproduit';
		$this->family = "other";
		$this->module_position = '90';
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = "DetailproduitDescription";
		$this->descriptionlong = "DetailproduitDescription";

		$this->editor_name = 'DIAMANT INDUSTRIE';
		$this->editor_url = 'www.diamant-industrie.com';

		$this->version = '3.1';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'fa-file-o';

		$this->module_parts = array(
			'triggers' => 1,
			'login' => 0,
			'substitutions' => 0,
			'menus' => 0,
			'tpl' => 0,
			'barcode' => 0,
			'models' => 0,
			'printing' => 0,
			'theme' => 0,
			'css' => array(),
			'js' => array(),
			// IMPORTANT: format simple = noms de contexte directement
			// Le format imbriqué array('data'=>..., 'entity'=>...) peut ne pas
			// être correctement interprété par HookManager dans Dolibarr 20
			'hooks' => array('ordercard'),
			'moduleforexternal' => 0,
			'websitetemplates' => 0,
		);

		$this->dirs = array("/detailproduit/temp");
		$this->config_page_url = array("setup.php@detailproduit");

		$this->hidden = getDolGlobalInt('MODULE_DETAILPRODUIT_DISABLED');
		$this->depends = array();
		$this->requiredby = array();
		$this->conflictwith = array();
		$this->langfiles = array("detailproduit@detailproduit");

		$this->phpmin = array(7, 1);
		$this->need_dolibarr_version = array(19, -3);
		$this->need_javascript_ajax = 0;

		$this->warnings_activation = array();
		$this->warnings_activation_ext = array();

		$this->const = array();

		if (!isModEnabled("detailproduit")) {
			$conf->detailproduit = new stdClass();
			$conf->detailproduit->enabled = 0;
		}

		$this->tabs = array();
		$this->dictionaries = array();
		$this->boxes = array();
		$this->cronjobs = array();

		// Permissions
		$this->rights = array();
		$r = 0;

		$this->rights[$r][0] = $this->numero.'0011';
		$this->rights[$r][1] = 'Consulter les détails produits';
		$this->rights[$r][4] = 'details';
		$this->rights[$r][5] = 'read';
		$r++;

		$this->rights[$r][0] = $this->numero.'0012';
		$this->rights[$r][1] = 'Créer/Modifier les détails produits';
		$this->rights[$r][4] = 'details';
		$this->rights[$r][5] = 'write';
		$r++;

		$this->rights[$r][0] = $this->numero.'0013';
		$this->rights[$r][1] = 'Supprimer les détails produits';
		$this->rights[$r][4] = 'details';
		$this->rights[$r][5] = 'delete';
		$r++;

		$this->menu = array();
	}

	/**
	 * @param string $options Options when enabling module
	 * @return int 1 if OK, 0 if KO
	 */
	public function init($options = '')
	{
		$this->remove($options);
		$sql = array();
		return $this->_init($sql, $options);
	}

	/**
	 * @param string $options Options when disabling module
	 * @return int 1 if OK, 0 if KO
	 */
	public function remove($options = '')
	{
		$sql = array();
		return $this->_remove($sql, $options);
	}
}
