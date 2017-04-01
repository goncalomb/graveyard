<?php

// php -d allow_url_include=1 avrmega.php

require('http://vorboss.dl.sourceforge.net/project/simplehtmldom/simple_html_dom.php');

function read_ic_page($url) {
	$url = "http://www.atmel.com{$url}?tab=parameters";
	$html = file_get_html($url);
	$keys = array_map(function($v) { return rtrim($v->plaintext, ':'); }, $html->find('.section-parametric1'));
	$values = array_map(function($v) { return $v->plaintext; }, $html->find('.section-parametric2'));
	if (count($keys) != count($values)) {
		trigger_error('count($keys) != count($values)', E_USER_ERROR);
	}
	return array_combine($keys, $values);
}

$ic_all_data = array();

$html = file_get_html('http://www.atmel.com/products/microcontrollers/avr/megaavr.aspx');
foreach ($html->find('#mpCore_CoreContentSpan_DevicesSeriesControl_lblDevicesList .section-devices') as $elem) {
	$ic_name = $elem->plaintext;
	echo $elem->plaintext, "\n";
	$ic_all_data[$ic_name] = read_ic_page($elem->find('a')[0]->href);
}

$all_keys = array();
foreach ($ic_all_data as $ic_name => $data) {
	$all_keys = array_merge($all_keys, array_keys($data));
}
$all_keys = array_unique($all_keys);

$fp = fopen('avrmega.csv', 'wb');
fputcsv($fp, array_merge(array('IC NAME'), $all_keys));

foreach ($ic_all_data as $ic_name => $ic_data) {
	$fields = array($ic_name);
	foreach ($all_keys as $key) {
		$fields[] = (isset($ic_data[$key]) ? $ic_data[$key] : '');
	}
	fputcsv($fp, $fields);
}

fclose($fp);

?>
