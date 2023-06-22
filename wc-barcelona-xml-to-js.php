<?php
$jsonUrl = "https://opendata-ajuntament.barcelona.cat/data/dataset/830265fe-c8c5-44b5-9aae-a80afceb02b6/resource/025707a3-acd0-4c1e-a6e7-daf8faff2703/download";

echo "Downloading wc.json...\n";

$wcJson = file_get_contents($jsonUrl);

echo "Parsing wc.json...\n";

$wcData = json_decode($wcJson, true);

$listOfPublicWcs = [];

foreach ($wcData as $key => $wc) {
    $name = $wc['name'];
    if (str_starts_with($name, 'WC Públic *')) {
        $listOfPublicWcs[$key]['name'] = substr($name, strlen('WC Públic *'));
    } elseif ($wc['is_section_of_data'] !== null) {
        $listOfPublicWcs[$key]['name'] = $wc['is_section_of_data']['name'];
    }
    $coordinates = $wc['geo_epgs_4326'];
    $listOfPublicWcs[$key]['lat'] = $coordinates['x'];
    $listOfPublicWcs[$key]['lon'] = $coordinates['y'];
    $address = $wc['addresses'][0];
    $listOfPublicWcs[$key]['address'] = $address['address_name'] . " " . $address['start_street_number'] . " (" . $address['zip_code'] . ")";
}

usort($listOfPublicWcs, function ($a, $b) {
    return strcmp($a['address'], $b['address']);
});

echo "Found " . count($listOfPublicWcs) . " public WC's in Barcelona";

echo "Saving to wc.js...\n";

$fp = fopen('wc.js', 'w');
fwrite($fp, 'var wc_barcelona_data = ' . json_encode($listOfPublicWcs, JSON_PRETTY_PRINT) . ';');
fclose($fp);

echo "Updating index.html...\n";

$indexHtml = file_get_contents('index.html');
$indexHtml = preg_replace('/var revision = .+?;/s', "var revision = '" . date('d/m/Y') . "';", $indexHtml);
file_put_contents('index.html', $indexHtml);
