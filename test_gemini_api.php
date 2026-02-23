<?php
// Test de l'API Gemini
$apiKey = 'AIzaSyChhpFVipv0xr_rdH6n6te1hvB2XJi5YJ4';
$url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . $apiKey;

echo "🔍 TEST API GEMINI\n";
echo "══════════════════════════════════════════\n\n";

echo "📌 API Key: " . substr($apiKey, 0, 20) . "...\n";
echo "📌 URL: " . str_replace($apiKey, 'XXXX', $url) . "\n\n";

// Test payload simple
$payload = [
    'contents' => [
        [
            'parts' => [
                ['text' => 'Bonjour, dis-moi une blague courte.']
            ]
        ]
    ]
];

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json'
    ],
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_VERBOSE => false,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "🔐 STATUS CODE: $httpCode\n\n";

if ($curlError) {
    echo "❌ CURL ERROR: $curlError\n";
} elseif ($httpCode === 404) {
    echo "❌ ERREUR 404 - LA CLÉ API OU LE MODÈLE N'EXISTE PAS\n";
    echo "   Possible causes:\n";
    echo "   1. La clé API est invalide\n";
    echo "   2. Le projet Google Cloud n'a pas activé l'API\n";
    echo "   3. Le modèle gemini-1.5-flash n'existe pas pour cette clé\n";
} elseif ($httpCode === 401) {
    echo "❌ ERREUR 401 - AUTHENTIFICATION ÉCHOUÉE\n";
    echo "   La clé API est invalide ou a expiré\n";
} elseif ($httpCode === 400) {
    echo "⚠️  ERREUR 400 - REQUÊTE INVALIDE\n";
} elseif ($httpCode === 200) {
    echo "✅ SUCCÈS 200 - L'API fonctionne!\n";
    $data = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "   Réponse: " . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    }
} else {
    echo "⚠️  STATUS CODE: $httpCode\n";
}

echo "\n📋 RÉPONSE COMPLÈTE:\n";
echo $response . "\n";
?>
