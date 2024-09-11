<?php
$jsonUrl = "https://opendata-ajuntament.barcelona.cat/data/dataset/830265fe-c8c5-44b5-9aae-a80afceb02b6/resource/025707a3-acd0-4c1e-a6e7-daf8faff2703/download";

echo "Downloading wc.json...\n";

$wcJson = file_get_contents($jsonUrl);

echo "Parsing wc.json...\n";

$wcData = json_decode($wcJson, true);

$listOfPublicWcs = [];

foreach ($wcData as $key => $wc) {
    $name = $wc['name'];
    
    // Get the WC name
    if (str_starts_with($name, 'WC Públic *')) {
        $listOfPublicWcs[$key]['name'] = substr($name, strlen('WC Públic *'));
    } elseif ($wc['is_section_of_data'] !== null) {
        $listOfPublicWcs[$key]['name'] = $wc['is_section_of_data']['name'];
    }

    // Prioritize using googleMaps coordinates if available
    $coordinates = $wc['geo_epgs_4326'];
    $googleMaps = $wc['googleMaps'] ?? null;

    if ($googleMaps !== null && isset($googleMaps['lat'], $googleMaps['lon'])) {
        $listOfPublicWcs[$key]['lat'] = $googleMaps['lat'];
        $listOfPublicWcs[$key]['lon'] = $googleMaps['lon'];
    } elseif (isset($coordinates['x'], $coordinates['y'])) {
        // Fallback to geo_epgs_4326 if googleMaps is not available
        $listOfPublicWcs[$key]['lat'] = $coordinates['x'];
        $listOfPublicWcs[$key]['lon'] = $coordinates['y'];
    } else {
        // Skip this entry if neither googleMaps nor geo_epgs_4326 coordinates are available. geo_epgs_4326 was the working key, and it worked for months. https://x.com/Angela_OrFe/status/1795121198700855640 warned us that the map wasn't working anymore, so we saw that Ajuntament de Barcelona changed the XML structure. Just in case they revert back to the previous way of doing things, we keep the logic here as a fallback. 
        continue;
    }

    // Get the WC address
    $address = $wc['addresses'][0] ?? null;
    if ($address !== null) {
        $listOfPublicWcs[$key]['address'] = $address['address_name'] . " " . $address['start_street_number'] . " (" . $address['zip_code'] . ")";
    } else {
        $listOfPublicWcs[$key]['address'] = 'Unknown Address';
    }
}

// Sort the list of WCs by address
usort($listOfPublicWcs, function ($a, $b) {
    return strcmp($a['address'], $b['address']);
});

echo "Found " . count($listOfPublicWcs) . " public WC's in Barcelona\n";

echo "Saving to wc.js...\n";

// Write the result to wc.js
$fp = fopen('wc.js', 'w');
fwrite($fp, 'var wc_barcelona_data = ' . json_encode($listOfPublicWcs, JSON_PRETTY_PRINT) . ';');
fclose($fp);

echo "Updating index.html...\n";

// Update the revision date in index.html
$indexHtml = file_get_contents('index.html');
$indexHtml = preg_replace('/var revision = .+?;/s', "var revision = '" . date('d/m/Y') . "';", $indexHtml);
file_put_contents('index.html', $indexHtml);

echo "Process completed.\n";
