<?php
require __DIR__ . '/vendor/autoload.php';

use ExtPHP\XmlToJson\XmlToJsonConverter;

$converter = new XmlToJsonConverter(simplexml_load_file('wc.xml'));
$originalList = $converter->toArray()['response']['body']['resultat']['equipaments']['equipament'];

$listOfPublicWcs = [];
foreach ($originalList as $key => $wc) {
    $listOfPublicWcs[$key]['name'] = $wc['nom']['_value'];
    $listOfPublicWcs[$key]['lat'] = $wc['adreca_simple']['coordenades']['googleMaps']['_attributes']['lat'];
    $listOfPublicWcs[$key]['lon'] = $wc['adreca_simple']['coordenades']['googleMaps']['_attributes']['lon'];
    $listOfPublicWcs[$key]['address'] = $wc['adreca_simple']['carrer']['_value']
        . " " .
        $wc['adreca_simple']['numero']['_attributes']['enter']
        . " (" .
        $wc['adreca_simple']['codi_postal']['_value']
        . ") ";
}

$fp = fopen('wc.js', 'w');
fwrite($fp, 'var wc_barcelona_data = ' . json_encode($listOfPublicWcs) . ';');
fclose($fp);
