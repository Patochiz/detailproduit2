<?php
/* Diagnostic detailproduit v2 - à supprimer après usage */

$res = 0;
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res) die("Include of main fails");

if (!$user->admin) {
	die('Admin access required');
}

echo '<html><head><title>Diagnostic detailproduit v2</title>';
echo '<style>body{font-family:monospace;padding:20px;max-width:900px;margin:0 auto}';
echo '.ok{color:green;font-weight:bold}.ko{color:red;font-weight:bold}.warn{color:orange;font-weight:bold}';
echo 'h2{border-bottom:2px solid #333;padding-bottom:5px}pre{background:#f5f5f5;padding:10px;overflow-x:auto}</style>';
echo '</head><body>';
echo '<h1>Diagnostic module detailproduit v2</h1>';

// ============================================================
echo '<h2>1. Module activé ?</h2>';
$enabled = isModEnabled('detailproduit');
echo '<p>isModEnabled("detailproduit"): '.($enabled ? '<span class="ok">OUI</span>' : '<span class="ko">NON</span>').'</p>';

if (!empty($conf->detailproduit)) {
	echo '<p>$conf->detailproduit->enabled = '.(isset($conf->detailproduit->enabled) ? $conf->detailproduit->enabled : 'non défini').'</p>';
} else {
	echo '<p class="ko">$conf->detailproduit n\'existe pas</p>';
}

// Constantes
$sql = "SELECT name, value FROM ".MAIN_DB_PREFIX."const WHERE name LIKE '%DETAILPRODUIT%'";
$resql = $db->query($sql);
echo '<p>Constantes en base :</p><pre>';
if ($resql) {
	$found = false;
	while ($obj = $db->fetch_object($resql)) {
		echo htmlspecialchars($obj->name).' = '.htmlspecialchars(substr($obj->value, 0, 200))."\n";
		$found = true;
	}
	if (!$found) echo '(aucune)';
}
echo '</pre>';

// ============================================================
echo '<h2>2. Hooks enregistrés ?</h2>';
if (!empty($conf->modules_parts['hooks'])) {
	$found_dp = false;
	foreach ($conf->modules_parts['hooks'] as $module => $contexts) {
		if (stripos($module, 'detailproduit') !== false) {
			echo '<p class="ok">detailproduit trouvé dans modules_parts[hooks]</p>';
			echo '<pre>'.print_r($contexts, true).'</pre>';
			$found_dp = true;
		}
	}
	if (!$found_dp) {
		echo '<p class="ko">detailproduit NON TROUVÉ dans modules_parts[hooks]</p>';
	}
} else {
	echo '<p class="ko">$conf->modules_parts[\'hooks\'] est vide</p>';
}

// ============================================================
echo '<h2>3. Fichiers du module</h2>';
$base_path = dol_buildpath('/detailproduit', 0);
echo '<p>Chemin: <code>'.$base_path.'</code></p>';

$files = array(
	'class/actions_detailproduit.class.php',
	'core/hooks/detailproduit.class.php',
	'core/modules/modDetailproduit.class.php',
	'js/details_popup.js',
	'js/label_update.js',
	'css/details_popup.css',
	'ajax/details_handler.php',
	'ajax/label_handler.php',
);

echo '<table border="1" cellpadding="4" cellspacing="0"><tr><th>Fichier</th><th>Existe</th><th>Taille</th></tr>';
foreach ($files as $f) {
	$full = $base_path.'/'.$f;
	$ex = file_exists($full);
	echo '<tr><td><code>'.$f.'</code></td>';
	echo '<td>'.($ex ? '<span class="ok">OUI</span>' : '<span class="ko">NON</span>').'</td>';
	echo '<td>'.($ex ? number_format(filesize($full)).' o' : '-').'</td></tr>';
}
echo '</table>';

// ============================================================
echo '<h2>4. Classe hook</h2>';

$hook_file = $base_path.'/core/hooks/detailproduit.class.php';
if (file_exists($hook_file)) {
	$content = file_get_contents($hook_file);

	// Vérifier nom de classe
	if (preg_match('/class\s+(\w+)/', $content, $m)) {
		echo '<p>Classe: <code>'.$m[1].'</code> ';
		echo ($m[1] === 'ActionsDetailproduit') ? '<span class="ok">OK</span>' : '<span class="ko">MAUVAIS NOM</span>';
		echo '</p>';
	}

	// Vérifier méthodes
	echo '<p>printCommonFooter: '.(strpos($content, 'function printCommonFooter') !== false ? '<span class="ok">présente</span>' : '<span class="ko">absente</span>').'</p>';

	// Vérifier s'il y a un check de context redondant (ancien bug)
	if (strpos($content, 'isOrderCardContext') !== false) {
		echo '<p class="ko">⚠ Contient encore isOrderCardContext() - vérification redondante qui bloque la sortie !</p>';
	} else {
		echo '<p class="ok">Pas de vérification de contexte redondante</p>';
	}

	// Vérifier le type de garde anti-double
	if (strpos($content, 'static $assets_included') !== false || strpos($content, 'self::$assets_included') !== false) {
		echo '<p class="warn">⚠ Utilise static $assets_included - peut poser problème entre instances</p>';
	}
	if (strpos($content, '$conf->detailproduit->assets_loaded') !== false) {
		echo '<p class="ok">Utilise $conf->detailproduit->assets_loaded (runtime flag)</p>';
	}
} else {
	echo '<p class="ko">Fichier hook NON TROUVÉ</p>';
}

// ============================================================
echo '<h2>5. Test HookManager</h2>';

$hookmanager2 = new HookManager($db);
$hookmanager2->initHooks(array('ordercard'));

echo '<p>Hooks chargés pour ordercard :</p><pre>';
if (!empty($hookmanager2->hooks)) {
	foreach ($hookmanager2->hooks as $context => $hooks) {
		echo "Context: ".$context."\n";
		foreach ($hooks as $classname => $obj) {
			echo "  ".$classname." (".get_class($obj).")\n";
		}
	}
} else {
	echo '<span class="ko">Aucun hook chargé</span>';
}
echo '</pre>';

// ============================================================
echo '<h2>6. Test executeHooks printCommonFooter</h2>';

// Reset the runtime flag if it exists
if (isset($conf->detailproduit->assets_loaded)) {
	unset($conf->detailproduit->assets_loaded);
}

$test_params = array();
$test_object = new stdClass();
$test_object->element = 'commande';
$test_action = '';

$result = $hookmanager2->executeHooks('printCommonFooter', $test_params, $test_object, $test_action);

echo '<p>Résultat: '.$result.'</p>';
echo '<p>resprints:</p><pre>'.htmlspecialchars($hookmanager2->resprints ?: '(vide)').'</pre>';

if (!empty($hookmanager2->resprints)) {
	$rp = $hookmanager2->resprints;
	echo '<p>Vérifications :</p>';
	echo '<p>- details_popup.css: '.(strpos($rp, 'details_popup.css') !== false ? '<span class="ok">OUI</span>' : '<span class="ko">NON</span>').'</p>';
	echo '<p>- details_popup.js: '.(strpos($rp, 'details_popup.js') !== false ? '<span class="ok">OUI</span>' : '<span class="ko">NON</span>').'</p>';
	echo '<p>- label_update.js: '.(strpos($rp, 'label_update.js') !== false ? '<span class="ok">OUI</span>' : '<span class="ko">NON</span>').'</p>';
	echo '<p>- DOL_URL_ROOT: '.(strpos($rp, 'DOL_URL_ROOT') !== false ? '<span class="ok">OUI</span>' : '<span class="ko">NON</span>').'</p>';
	echo '<p>- detailproduit_token: '.(strpos($rp, 'detailproduit_token') !== false ? '<span class="ok">OUI</span>' : '<span class="ko">NON</span>').'</p>';
} else {
	echo '<p class="ko">Le hook ne produit rien via executeHooks !</p>';

	// Test direct sur l'instance
	echo '<h3>6b. Test DIRECT sur l\'instance (bypass HookManager)</h3>';
	$direct_instance = null;
	if (!empty($hookmanager2->hooks)) {
		foreach ($hookmanager2->hooks as $context => $hooks) {
			foreach ($hooks as $classname => $obj) {
				if ($classname === 'detailproduit' || get_class($obj) === 'ActionsDetailproduit') {
					$direct_instance = $obj;
					break 2;
				}
			}
		}
	}

	if ($direct_instance) {
		// Reset runtime flag
		if (isset($conf->detailproduit->assets_loaded)) {
			unset($conf->detailproduit->assets_loaded);
		}
		$direct_instance->resprints = '';

		$direct_result = $direct_instance->printCommonFooter($test_params, $test_object, $test_action, $hookmanager2);
		echo '<p>Résultat direct: '.$direct_result.'</p>';
		echo '<p>resprints direct:</p><pre>'.htmlspecialchars($direct_instance->resprints ?: '(vide)').'</pre>';

		if (!empty($direct_instance->resprints)) {
			echo '<p class="ok">L\'appel DIRECT fonctionne ! Le problème vient du HookManager.</p>';
			echo '<p class="warn">Cela peut signifier qu\'un autre module intercepte/bloque la sortie dans executeHooks.</p>';
		} else {
			echo '<p class="ko">L\'appel direct ne produit rien non plus.</p>';
			echo '<p>Debug isModEnabled: '.(isModEnabled('detailproduit') ? 'true' : 'false').'</p>';
			echo '<p>Debug $conf->detailproduit: </p><pre>'.print_r($conf->detailproduit, true).'</pre>';
		}
	} else {
		echo '<p class="ko">Instance ActionsDetailproduit non trouvée dans les hooks chargés</p>';
	}
}

// ============================================================
echo '<h2>7. URLs des assets</h2>';
echo '<p>CSS: <code>'.dol_buildpath('/detailproduit/css/details_popup.css', 1).'</code></p>';
echo '<p>JS popup: <code>'.dol_buildpath('/detailproduit/js/details_popup.js', 1).'</code></p>';
echo '<p>JS label: <code>'.dol_buildpath('/detailproduit/js/label_update.js', 1).'</code></p>';
echo '<p>DOL_URL_ROOT: <code>'.DOL_URL_ROOT.'</code></p>';

// ============================================================
echo '<h2>8. Extrafields commandedet</h2>';
$sql = "SHOW COLUMNS FROM ".MAIN_DB_PREFIX."commandedet_extrafields WHERE Field IN ('detailjson','detail','ref_chantier')";
$resql = $db->query($sql);
echo '<pre>';
if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		echo $obj->Field.' ('.$obj->Type.') Null='.$obj->Null."\n";
	}
} else {
	echo 'Erreur: '.$db->lasterror();
}
echo '</pre>';

// ============================================================
echo '<h2>9. Config hooks dans descripteur</h2>';
$desc_file = $base_path.'/core/modules/modDetailproduit.class.php';
if (file_exists($desc_file)) {
	$desc_content = file_get_contents($desc_file);
	if (preg_match("/'hooks'\s*=>\s*array\s*\((.+?)\),/s", $desc_content, $m)) {
		$hooks_config = trim($m[1]);
		echo '<pre>\'hooks\' => array('.$hooks_config.'),</pre>';

		if (strpos($hooks_config, "'data'") !== false) {
			echo '<p class="ko">⚠ Format imbriqué détecté (data => ...) - doit être un tableau simple !</p>';
		} else {
			echo '<p class="ok">Format simple (tableau de noms de contexte)</p>';
		}
	}
}

echo '<hr><p><em>Diagnostic v2 - '.date('Y-m-d H:i:s').'</em></p>';
echo '</body></html>';
