<?php
/* Copyright (C) 2025 Patrice GOURMELEN <pgourmelen@diamant-industrie.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    core/triggers/interface_99_modDetailproduit_Detailproduittrigger.class.php
 * \ingroup detailproduit
 * \brief   Trigger to cleanup detail data when order lines/orders are deleted
 */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';

class InterfaceDetailproduitTrigger extends DolibarrTriggers
{
	public $name = 'InterfaceDetailproduitTrigger';
	public $description = 'Auto-cleanup orphaned detailproduit extrafield data';
	public $version = '2.0.0';
	public $picto = 'technic';

	public function __construct($db)
	{
		$this->db = $db;
	}

	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
	{
		if (!isModEnabled('detailproduit')) return 0;

		switch ($action) {
			case 'LINEORDER_DELETE':
			case 'ORDERLINE_DELETE':
				if (is_object($object) && isset($object->rowid)) {
					$this->cleanupForLine($object->rowid);
				}
				break;

			case 'ORDER_DELETE':
				if (is_object($object) && isset($object->id)) {
					$this->cleanupForOrder($object->id);
				}
				break;
		}

		return 0;
	}

	/**
	 * Clear detailjson and detail extrafields for a deleted order line
	 */
	private function cleanupForLine($commandedet_id)
	{
		$sql = "UPDATE ".MAIN_DB_PREFIX."commandedet_extrafields";
		$sql .= " SET detailjson = NULL, detail = NULL";
		$sql .= " WHERE fk_object = ".((int) $commandedet_id);

		$resql = $this->db->query($sql);
		if ($resql) {
			$nb = $this->db->affected_rows($resql);
			if ($nb > 0) {
				dol_syslog(__METHOD__." cleaned extrafields for line ".$commandedet_id, LOG_INFO);
			}
		}
	}

	/**
	 * Clear extrafields for all lines of a deleted order
	 */
	private function cleanupForOrder($order_id)
	{
		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."commandedet WHERE fk_commande = ".((int) $order_id);
		$resql = $this->db->query($sql);
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				$this->cleanupForLine($obj->rowid);
			}
			$this->db->free($resql);
		}
	}
}
