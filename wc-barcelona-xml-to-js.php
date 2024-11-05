<?php
$jsonUrl = "https://opendata-ajuntament.barcelona.cat/data/dataset/830265fe-c8c5-44b5-9aae-a80afceb02b6/resource/025707a3-acd0-4c1e-a6e7-daf8faff2703/download";

echo "Downloading wc.json...\n";

$wcJson = file_get_contents($jsonUrl);
if ($wcJson === false) {
    die("Error downloading wc.json\n");
}

echo "Parsing wc.json...\n";

$wcData = json_decode($wcJson, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    die("Error parsing wc.json: " . json_last_error_msg() . "\n");
}

$listOfPublicWcs = [];

if (empty($wcData)) {
    echo "No entries found in wc.json\n";
    exit;
}

foreach ($wcData as $key => $wc) {
    $name = isset($wc['name']) ? $wc['name'] : null;

    // Get the WC name with multiple fallbacks
    if (!empty($wc['is_section_of_data']['name'])) {
        $listOfPublicWcs[$key]['name'] = $wc['is_section_of_data']['name'];
    } elseif ($name && str_starts_with($name, 'WC Públic *')) {
        $listOfPublicWcs[$key]['name'] = substr($name, strlen('WC Públic *'));
    } elseif (!empty($wc['name'])) {
        $listOfPublicWcs[$key]['name'] = $wc['name']; // Fallback to 'nom' tag
    } elseif (!empty($wc['seccio'])) {
        $listOfPublicWcs[$key]['name'] = $wc['seccio']; // Fallback to 'seccio' tag
    } else {
        $listOfPublicWcs[$key]['name'] = 'Lavabo públic'; // Final fallback in case no name is found
    }

    // Extract and prioritize using googleMaps coordinates if available
    $coordinates = isset($wc['geo_epgs_4326_latlon']) ? $wc['geo_epgs_4326_latlon'] : null;

    if (!empty($coordinates) && !isset($coordinates['lat'], $coordinates['lon'])) {
        continue;
    }

    $listOfPublicWcs[$key]['lat'] = $coordinates['lat'];
    $listOfPublicWcs[$key]['lon'] = $coordinates['lon'];

    $body = isset($wc['body']) ? trim(strip_tags($wc['body'])) : null;
    if (!empty($body)) {
        $listOfPublicWcs[$key]['body'] = $body;
    }

    $warnings = isset($wc['warnings']) ? $wc['warnings'] : [];
    foreach ($warnings as $warning) {
        $warning = trim(strip_tags($warning['text']));

        if (!empty($warning)) {
            $listOfPublicWcs[$key]['warnings'][] = $warning;
        }
    }

    // Extract and Get the WC address
    $addresses = isset($wc['addresses']) ? $wc['addresses'] : null;
    $address = isset($addresses[0]) ? $addresses[0] : null;

    if (!empty($address)) {
        $streetName = isset($address['address_name']) ? $address['address_name'] : 'No s\'ha pogut trobar el carrer. ';

        $startStreetNumber = isset($address['start_street_number']) ? $address['start_street_number'] : null;
        $endStreetNumber = isset($address['end_street_number']) ? $address['end_street_number'] : null;

        if ($startStreetNumber !== null && $endStreetNumber !== null && $startStreetNumber != $endStreetNumber) {
            $streetNumber = $startStreetNumber . '-' . $endStreetNumber;
        } elseif ($startStreetNumber !== null) {
            $streetNumber = $startStreetNumber;
        } elseif ($endStreetNumber !== null) {
            $streetNumber = $endStreetNumber;
        } else {
            $streetNumber = 'No s\'ha pogut trobar el número. ';
        }

        $zipCode = isset($address['zip_code']) ? $address['zip_code'] : 'No s\'ha pogut trobar el codi postal. ';

        $listOfPublicWcs[$key]['address'] = $streetName . ' ' . $streetNumber . ' (' . $zipCode . ')';
    } else {
        $listOfPublicWcs[$key]['address'] = 'Adreça desconeguda';
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
if ($fp === false) {
    die("Error opening wc.js for writing\n");
}
fwrite($fp, 'var wc_barcelona_data = ' . json_encode($listOfPublicWcs, JSON_PRETTY_PRINT) . ';');
fclose($fp);

echo "Updating index.html...\n";

// Update the revision date in index.html
$indexHtml = file_get_contents('index.html');
if ($indexHtml === false) {
    die("Error reading index.html\n");
}
$indexHtml = preg_replace('/var revision = .+?;/s', "var revision = '" . date('d/m/Y') . "';", $indexHtml);
file_put_contents('index.html', $indexHtml);

echo "Process completed.\n";
