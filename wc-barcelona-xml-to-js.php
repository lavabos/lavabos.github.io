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

if (isset($wcData['equipaments']['equipament']) && !empty($wcData['equipaments']['equipament'])) {
    foreach ($wcData['equipaments']['equipament'] as $key => $wc) {
        $name = isset($wc['nom']) ? $wc['nom'] : null;

        // Get the WC name with multiple fallbacks
        if ($name && str_starts_with($name, 'WC Públic *')) {
            $listOfPublicWcs[$key]['name'] = substr($name, strlen('WC Públic *'));
        } elseif (!empty($wc['is_section_of_data']['name'])) {
            $listOfPublicWcs[$key]['name'] = $wc['is_section_of_data']['name'];
        } elseif (!empty($wc['nom'])) {
            $listOfPublicWcs[$key]['name'] = $wc['nom']; // Fallback to 'nom' tag
        } elseif (!empty($wc['seccio'])) {
            $listOfPublicWcs[$key]['name'] = $wc['seccio']; // Fallback to 'seccio' tag
        } else {
            $listOfPublicWcs[$key]['name'] = 'Lavabo públic'; // Final fallback in case no name is found
        }

        // Extract and prioritize using googleMaps coordinates if available
        $googleMaps = isset($wc['adreca_simple']['coordenades']['googleMaps']) ? $wc['adreca_simple']['coordenades']['googleMaps'] : null;
        $coordinates = isset($wc['adreca_simple']['coordenades']['geocodificacio']) ? $wc['adreca_simple']['coordenades']['geocodificacio'] : null;

        if (!empty($googleMaps) && isset($googleMaps['lat'], $googleMaps['lon'])) {
            $listOfPublicWcs[$key]['lat'] = $googleMaps['lat'];
            $listOfPublicWcs[$key]['lon'] = $googleMaps['lon'];
        } elseif (!empty($coordinates) && isset($coordinates['x'], $coordinates['y'])) {
            // Fallback to geocodificacio if googleMaps is not available
            $listOfPublicWcs[$key]['lat'] = $coordinates['x'];
            $listOfPublicWcs[$key]['lon'] = $coordinates['y'];
        } else {
            continue;
        }

        // Extract and Get the WC address
        $addressSimple = isset($wc['adreca_simple']) ? $wc['adreca_simple'] : null;
        if (!empty($addressSimple)) {
            $streetName = isset($addressSimple['carrer']) ? $addressSimple['carrer'] : 'No s\'ha pogut trobar el carrer. ';
            $streetNumber = isset($addressSimple['numero']) ? $addressSimple['numero'] : 'No s\'ha pogut trobar el número. ';
            $zipCode = isset($addressSimple['codi_postal']) ? $addressSimple['codi_postal'] : 'No s\'ha pogut trobar el codi postal. ';

            if (is_array($streetName) && isset($streetName['@'])) {
                $streetName = $streetName['@'];
            }
            if (is_array($streetNumber) && isset($streetNumber['@'])) {
                $streetNumber = $streetNumber['@'];
            }
            if (is_array($zipCode) && isset($zipCode['@'])) {
                $zipCode = $zipCode['@'];
            }

            $listOfPublicWcs[$key]['address'] = $streetName . ' ' . $streetNumber . ' (' . $zipCode . ')';
        } else {
            $listOfPublicWcs[$key]['address'] = 'Adreça desconeguda';
        }
    }
} else {
    echo "No equipaments found in wc.json\n";
    exit;
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
