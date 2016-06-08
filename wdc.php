<?php
/**
 * A system to pull EIA Data into a WDC Tableau Connector.
 */

require_once('libs/api.php');

if (empty($_GET['wdc_ids'])) {
	die('Error: No WDC Ids Specified.');
}

function cleanField($str)
{
	$str = preg_replace("/[^a-zA-Z0-9]/", "_", $str);
	$str = strtolower($str);
	$str = str_replace('__', '_', $str);
	$str = trim($str, '_');
	
	return $str;
}

$return = array();
foreach (json_decode($_GET['wdc_ids'], true) as $vals) {
	$id = $vals['id'];
	$friendlyname = $vals['title'];
	
	$api = api::factory();
	$results = $api->series(array(
		'series_id' => $id
	));
	
	if (!empty($_GET['debug'])) {
		dar($results);
	}
	
	$fields[] = $friendlyname;
	foreach ($results->series[0]->data as $el) {
		// Date magic
		$date = '01-01-1990';
		if (strpos($el[0], 'Q') !== false) {
			$date = ((substr($el[0], -1) * 3) - 2) . '-01-' . substr($el[0], 0, 4);
		} elseif(strlen($el[0]) > 6) {
			$date = substr($el[0], 4, 2) . '-' . substr($el[0], -2) . '-' . substr($el[0], 0, 4);
		} elseif(strlen($el[0]) > 4) {
			$date =substr($el[0], -2) . '-01-' . substr($el[0], 0, 4);
		} else {
			$date = '01-01-' . $el[0];
		}
		
		$key = str_replace('-', '_', $date);
		if (array_key_exists($key, $return)) {
			$return[$key][$friendlyname] = floatval($el[1]);
		} else {
			$return[$key] = array(
				'date'        => $date,
				$friendlyname => floatval($el[1]),
			);
		}
	}
}

$data = array();
$cols = array(array('id' => 'Date', 'alias' => 'Date', 'dataType' => 'date'));

// Fill in missing results.
foreach ($return as $date => $el) {
	foreach ($fields as $id) {
		if (empty($return[$date][$id])) {
			$return[$date][$id] = null;
		}
	}
}

// Register Fields
foreach ($fields as $el) {
	$cols[] = array(
		'id'       => cleanField($el),
		'alias'    => $el,
		'dataType' => 'float',
	);
}

// Populate rows
foreach ($return as $el) {
	$add = array('Date' => $el['date']);
	foreach ($fields as $f) {
		$id = cleanField($f);
		$add[$id] = @floatval($el[$f]);
	}
	$data[] = $add;
}

header('Content-Type: application/json');
echo json_encode(array(
	'dataToReturn' => $data,
	'cols'         => $cols,
));
