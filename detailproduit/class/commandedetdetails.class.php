<?php
/* Copyright (C) 2025 Patrice GOURMELEN <pgourmelen@diamant-industrie.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    class/commandedetdetails.class.php
 * \ingroup detailproduit
 * \brief   CRUD class for order line details stored in extrafields
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

class CommandeDetDetails extends CommonObject
{
	public $module = 'detailproduit';
	public $element = 'commandedetdetails';

	public function __construct(DoliDB $db)
	{
		$this->db = $db;
	}

	/**
	 * Get details for an order line from extrafield detailjson
	 *
	 * @param  int       $fk_commandedet  Order line ID
	 * @return array|int                  Array of details or -1 on error
	 */
	public function getDetailsForLine($fk_commandedet)
	{
		$sql = "SELECT detailjson FROM ".MAIN_DB_PREFIX."commandedet_extrafields";
		$sql .= " WHERE fk_object = ".((int) $fk_commandedet);

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->errors[] = 'Error '.$this->db->lasterror();
			dol_syslog(__METHOD__.' '.$this->db->lasterror(), LOG_ERR);
			return -1;
		}

		$details = array();

		if ($this->db->num_rows($resql)) {
			$obj = $this->db->fetch_object($resql);
			if (!empty($obj->detailjson)) {
				$decoded = json_decode($obj->detailjson, true);
				if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
					foreach ($decoded as $index => $detail) {
						$details[] = array(
							'rowid' => $index + 1,
							'fk_commandedet' => $fk_commandedet,
							'pieces' => $detail['pieces'] ?? 0,
							'longueur' => $detail['longueur'] ?? null,
							'largeur' => $detail['largeur'] ?? null,
							'total_value' => $detail['total_value'] ?? 0,
							'unit' => $detail['unit'] ?? 'u',
							'description' => $detail['description'] ?? '',
							'color' => $detail['color'] ?? '',
							'rang' => $index + 1,
						);
					}
				}
			}
		}

		$this->db->free($resql);
		return $details;
	}

	/**
	 * Save details for an order line into extrafields
	 *
	 * @param  int   $fk_commandedet  Order line ID
	 * @param  array $details_array   Details to save
	 * @param  User  $user            Current user
	 * @return int                    <0 on error, >0 on success
	 */
	public function saveDetailsForLine($fk_commandedet, $details_array, User $user)
	{
		$this->db->begin();

		$clean_details = array();
		foreach ($details_array as $detail) {
			$clean_details[] = array(
				'pieces' => (float) $detail['pieces'],
				'longueur' => !empty($detail['longueur']) ? (float) $detail['longueur'] : null,
				'largeur' => !empty($detail['largeur']) ? (float) $detail['largeur'] : null,
				'total_value' => (float) $detail['total_value'],
				'unit' => (string) $detail['unit'],
				'description' => (string) ($detail['description'] ?? ''),
				'color' => (string) ($detail['color'] ?? ''),
			);
		}

		$json_data = json_encode($clean_details, JSON_UNESCAPED_UNICODE);
		if (json_last_error() !== JSON_ERROR_NONE) {
			$this->errors[] = 'JSON encode error: '.json_last_error_msg();
			$this->db->rollback();
			return -1;
		}

		$formatted_detail = $this->generateFormattedDetail($clean_details);

		$result = $this->updateExtrafields($fk_commandedet, $json_data, $formatted_detail);
		if ($result < 0) {
			$this->db->rollback();
			return -1;
		}

		$this->db->commit();
		return 1;
	}

	/**
	 * Generate formatted display string for the "detail" extrafield
	 *
	 * @param  array  $details_array  Cleaned details
	 * @return string                 HTML formatted string
	 */
	public function generateFormattedDetail($details_array)
	{
		$rows = '';
		foreach ($details_array as $detail) {
			$pieces = (int) $detail['pieces'];
			$longueur = !empty($detail['longueur']) ? (int) $detail['longueur'] : null;
			$largeur = !empty($detail['largeur']) ? (int) $detail['largeur'] : null;
			$total = number_format($detail['total_value'], 2, '.', '');
			$unit = $detail['unit'];
			$desc = htmlspecialchars($detail['description'] ?? '', ENT_QUOTES, 'UTF-8');
			$color = !empty($detail['color']) && preg_match('/^#[0-9A-Fa-f]{6}$/', $detail['color']) ? $detail['color'] : '';

			$parts = array($pieces);
			if ($longueur !== null) $parts[] = $longueur;
			if ($largeur !== null) $parts[] = $largeur;

			$cell = implode(' x ', $parts).' ('.$total.' '.$unit.')';
			if (!empty($desc)) $cell .= ' '.$desc;
			$cell = htmlspecialchars($cell, ENT_QUOTES, 'UTF-8');

			$style = $color ? ' style="background-color:'.htmlspecialchars($color, ENT_QUOTES, 'UTF-8').';"' : '';
			$rows .= '<tr><td>'.($color ? '<span'.$style.'>'.$cell.'</span>' : $cell).'</td></tr>';
		}
		return '<table border="0" cellpadding="0" cellspacing="0" style="width:100%"><tbody>'.$rows.'</tbody></table>';
	}

	/**
	 * Insert or update extrafields record
	 *
	 * @param  int    $fk_commandedet    Order line ID
	 * @param  string $json_data         JSON data
	 * @param  string $formatted_detail  Formatted display string
	 * @return int                       <0 on error, >0 on success
	 */
	private function updateExtrafields($fk_commandedet, $json_data, $formatted_detail)
	{
		$sql = "SELECT fk_object FROM ".MAIN_DB_PREFIX."commandedet_extrafields";
		$sql .= " WHERE fk_object = ".((int) $fk_commandedet);

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->errors[] = 'Error: '.$this->db->lasterror();
			return -1;
		}

		$exists = ($this->db->num_rows($resql) > 0);
		$this->db->free($resql);

		if ($exists) {
			$sql = "UPDATE ".MAIN_DB_PREFIX."commandedet_extrafields";
			$sql .= " SET detailjson = '".$this->db->escape($json_data)."'";
			$sql .= ", detail = '".$this->db->escape($formatted_detail)."'";
			$sql .= " WHERE fk_object = ".((int) $fk_commandedet);
		} else {
			$sql = "INSERT INTO ".MAIN_DB_PREFIX."commandedet_extrafields";
			$sql .= " (fk_object, detailjson, detail) VALUES";
			$sql .= " (".((int) $fk_commandedet).", '".$this->db->escape($json_data)."', '".$this->db->escape($formatted_detail)."')";
		}

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->errors[] = 'Error: '.$this->db->lasterror();
			return -1;
		}

		return 1;
	}

	/**
	 * Delete details for an order line
	 *
	 * @param  int $fk_commandedet  Order line ID
	 * @return int                  <0 on error, >0 on success
	 */
	public function deleteDetailsForLine($fk_commandedet)
	{
		$sql = "UPDATE ".MAIN_DB_PREFIX."commandedet_extrafields";
		$sql .= " SET detailjson = NULL, detail = NULL";
		$sql .= " WHERE fk_object = ".((int) $fk_commandedet);

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->errors[] = 'Error: '.$this->db->lasterror();
			return -1;
		}
		return 1;
	}

	/**
	 * Get totals grouped by unit
	 *
	 * @param  int       $fk_commandedet  Order line ID
	 * @return array|int                  Totals array or -1 on error
	 */
	public function getTotalsByUnit($fk_commandedet)
	{
		$details = $this->getDetailsForLine($fk_commandedet);
		if ($details === -1) return -1;

		$totals = array();
		foreach ($details as $detail) {
			$unit = $detail['unit'];
			if (!isset($totals[$unit])) {
				$totals[$unit] = array('total_value' => 0, 'nb_lines' => 0);
			}
			$totals[$unit]['total_value'] += (float) $detail['total_value'];
			$totals[$unit]['nb_lines']++;
		}

		uasort($totals, function ($a, $b) {
			return $b['total_value'] <=> $a['total_value'];
		});

		return $totals;
	}

	/**
	 * Update the order line quantity using Dolibarr's updateline method
	 *
	 * @param  int    $fk_commandedet  Order line ID
	 * @param  float  $new_quantity    New quantity
	 * @param  string $unit            Main unit
	 * @return int                     <0 on error, >0 on success
	 */
	public function updateCommandLineQuantity($fk_commandedet, $new_quantity, $unit)
	{
		$sql = "SELECT cd.fk_commande, cd.description, cd.subprice, cd.qty,";
		$sql .= " cd.tva_tx, cd.localtax1_tx, cd.localtax2_tx, cd.remise_percent,";
		$sql .= " cd.info_bits, cd.product_type, cd.fk_parent_line, cd.label,";
		$sql .= " cd.special_code, cd.rang, cd.fk_unit, cd.date_start, cd.date_end,";
		$sql .= " cd.buy_price_ht, cd.multicurrency_subprice";
		$sql .= " FROM ".MAIN_DB_PREFIX."commandedet as cd";
		$sql .= " WHERE cd.rowid = ".((int) $fk_commandedet);

		$resql = $this->db->query($sql);
		if (!$resql || !$this->db->num_rows($resql)) {
			$this->errors[] = 'Line not found: '.$fk_commandedet;
			return -1;
		}

		$line = $this->db->fetch_object($resql);
		$this->db->free($resql);

		require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
		$commande = new Commande($this->db);
		if ($commande->fetch($line->fk_commande) < 0) {
			$this->errors[] = 'Error loading order';
			return -1;
		}

		$new_quantity = round((float) $new_quantity, 2);

		// Fetch current extrafields to restore them after updateline()
		// (updateline() calls insertExtraFields() which may corrupt HTML content in 'detail')
		$saved_detailjson = null;
		$saved_detail = null;
		$sql_ef = "SELECT detailjson, detail FROM ".MAIN_DB_PREFIX."commandedet_extrafields";
		$sql_ef .= " WHERE fk_object = ".((int) $fk_commandedet);
		$resql_ef = $this->db->query($sql_ef);
		if ($resql_ef && $this->db->num_rows($resql_ef) > 0) {
			$obj_ef = $this->db->fetch_object($resql_ef);
			$saved_detailjson = $obj_ef->detailjson;
			$saved_detail = $obj_ef->detail;
			$this->db->free($resql_ef);
		}

		// Direct SQL: update qty and recalculate line totals.
		// We intentionally bypass $commande->updateline() because it causes line duplication
		// on this Dolibarr setup (confirmed by logs showing a second commandedet row after
		// updateline() runs, regardless of the notrigger flag).
		$pu        = (float) $line->subprice;
		$new_qty_f = (float) $new_quantity;
		$remise    = (float) $line->remise_percent;
		$tva_tx    = (float) $line->tva_tx;
		$ltax1_tx  = (float) $line->localtax1_tx;
		$ltax2_tx  = (float) $line->localtax2_tx;

		$total_ht    = round($pu * $new_qty_f * (1 - $remise / 100), 6);
		$total_tva   = round($total_ht * $tva_tx / 100, 6);
		$total_ltax1 = round($total_ht * $ltax1_tx / 100, 6);
		$total_ltax2 = round($total_ht * $ltax2_tx / 100, 6);
		$total_ttc   = $total_ht + $total_tva + $total_ltax1 + $total_ltax2;

		$sql_qty  = "UPDATE ".MAIN_DB_PREFIX."commandedet";
		$sql_qty .= " SET qty = ".((float) $new_qty_f);
		$sql_qty .= ", total_ht = ".((float) $total_ht);
		$sql_qty .= ", total_tva = ".((float) $total_tva);
		$sql_qty .= ", total_localtax1 = ".((float) $total_ltax1);
		$sql_qty .= ", total_localtax2 = ".((float) $total_ltax2);
		$sql_qty .= ", total_ttc = ".((float) $total_ttc);
		$sql_qty .= " WHERE rowid = ".((int) $fk_commandedet);

		if (!$this->db->query($sql_qty)) {
			$this->errors[] = 'Error updating qty: '.$this->db->lasterror();
			return -1;
		}

		// Refresh order header totals from the updated line totals
		$commande->update_price(1);

		// Restore detailjson and detail via INSERT ... ON DUPLICATE KEY UPDATE
		// This handles both cases: row still exists (UPDATE) or was deleted+recreated by
		// insertExtraFields() with empty values (INSERT), which a plain UPDATE would miss.
		if ($saved_detailjson !== null) {
			// Regenerate detail from detailjson in case $saved_detail is stale/null
			$saved_detail_final = $saved_detail;
			if (empty($saved_detail_final)) {
				$details_arr = json_decode($saved_detailjson, true);
				if (is_array($details_arr)) {
					$saved_detail_final = $this->generateFormattedDetail($details_arr);
				}
			}
			$sql_restore  = "INSERT INTO ".MAIN_DB_PREFIX."commandedet_extrafields";
			$sql_restore .= " (fk_object, detailjson, detail) VALUES";
			$sql_restore .= " (".((int) $fk_commandedet);
			$sql_restore .= ", '".$this->db->escape($saved_detailjson)."'";
			$sql_restore .= ", '".$this->db->escape($saved_detail_final)."')";
			$sql_restore .= " ON DUPLICATE KEY UPDATE";
			$sql_restore .= " detailjson = '".$this->db->escape($saved_detailjson)."'";
			$sql_restore .= ", detail = '".$this->db->escape($saved_detail_final)."'";
			$this->db->query($sql_restore);
		}

		return 1;
	}

	/**
	 * Calculate unit and value from dimensions
	 *
	 * @param  float      $pieces    Number of pieces
	 * @param  float|null $longueur  Length in mm
	 * @param  float|null $largeur   Width in mm
	 * @return array                 ['unit' => string, 'total_value' => float]
	 */
	public static function calculateUnitAndValue($pieces, $longueur, $largeur)
	{
		$pieces = (float) $pieces;
		$longueur = !empty($longueur) ? (float) $longueur : 0;
		$largeur = !empty($largeur) ? (float) $largeur : 0;

		if ($longueur > 0 && $largeur > 0) {
			return array('unit' => 'm²', 'total_value' => $pieces * ($longueur / 1000) * ($largeur / 1000));
		} elseif ($longueur > 0) {
			return array('unit' => 'ml', 'total_value' => $pieces * ($longueur / 1000));
		} elseif ($largeur > 0) {
			return array('unit' => 'ml', 'total_value' => $pieces * ($largeur / 1000));
		}
		return array('unit' => 'u', 'total_value' => $pieces);
	}

	/**
	 * Clean orphaned extrafield data
	 *
	 * @return array Cleanup statistics
	 */
	public function cleanupOrphanedData()
	{
		$stats = array(
			'orphaned_extrafields_found' => 0,
			'orphaned_extrafields_cleaned' => 0,
			'errors' => array(),
		);

		$sql = "SELECT ef.fk_object FROM ".MAIN_DB_PREFIX."commandedet_extrafields ef";
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."commandedet cd ON cd.rowid = ef.fk_object";
		$sql .= " WHERE cd.rowid IS NULL AND (ef.detailjson IS NOT NULL OR ef.detail IS NOT NULL)";

		$resql = $this->db->query($sql);
		if (!$resql) {
			$stats['errors'][] = $this->db->lasterror();
			return $stats;
		}

		$orphaned = array();
		while ($obj = $this->db->fetch_object($resql)) {
			$orphaned[] = (int) $obj->fk_object;
		}
		$this->db->free($resql);

		$stats['orphaned_extrafields_found'] = count($orphaned);

		if (count($orphaned) > 0) {
			$sql = "UPDATE ".MAIN_DB_PREFIX."commandedet_extrafields";
			$sql .= " SET detailjson = NULL, detail = NULL";
			$sql .= " WHERE fk_object IN (".implode(',', $orphaned).")";

			$resql = $this->db->query($sql);
			if ($resql) {
				$stats['orphaned_extrafields_cleaned'] = $this->db->affected_rows($resql);
			} else {
				$stats['errors'][] = $this->db->lasterror();
			}
		}

		return $stats;
	}

	/**
	 * Check data integrity
	 *
	 * @return array Integrity report
	 */
	public function checkDataIntegrity()
	{
		$report = array(
			'total_extrafields_with_detailjson' => 0,
			'total_extrafields_with_detail' => 0,
			'orphaned_extrafields' => array(),
			'invalid_json_extrafields' => array(),
			'integrity_ok' => true,
		);

		// Count extrafields with data
		$sql = "SELECT COUNT(*) as total FROM ".MAIN_DB_PREFIX."commandedet_extrafields WHERE detailjson IS NOT NULL";
		$resql = $this->db->query($sql);
		if ($resql) {
			$report['total_extrafields_with_detailjson'] = $this->db->fetch_object($resql)->total;
			$this->db->free($resql);
		}

		$sql = "SELECT COUNT(*) as total FROM ".MAIN_DB_PREFIX."commandedet_extrafields WHERE detail IS NOT NULL";
		$resql = $this->db->query($sql);
		if ($resql) {
			$report['total_extrafields_with_detail'] = $this->db->fetch_object($resql)->total;
			$this->db->free($resql);
		}

		// Find orphaned extrafields
		$sql = "SELECT ef.fk_object FROM ".MAIN_DB_PREFIX."commandedet_extrafields ef";
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."commandedet cd ON cd.rowid = ef.fk_object";
		$sql .= " WHERE cd.rowid IS NULL AND (ef.detailjson IS NOT NULL OR ef.detail IS NOT NULL)";
		$resql = $this->db->query($sql);
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				$report['orphaned_extrafields'][] = $obj->fk_object;
			}
			$this->db->free($resql);
		}

		// Check for invalid JSON
		$sql = "SELECT fk_object, detailjson FROM ".MAIN_DB_PREFIX."commandedet_extrafields WHERE detailjson IS NOT NULL";
		$resql = $this->db->query($sql);
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				json_decode($obj->detailjson);
				if (json_last_error() !== JSON_ERROR_NONE) {
					$report['invalid_json_extrafields'][] = array(
						'fk_object' => $obj->fk_object,
						'json_error' => json_last_error_msg(),
					);
				}
			}
			$this->db->free($resql);
		}

		$report['integrity_ok'] = (
			count($report['orphaned_extrafields']) == 0 &&
			count($report['invalid_json_extrafields']) == 0
		);

		return $report;
	}
}
