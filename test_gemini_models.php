<?php
// Test les modèles disponibles pour cette clé API
$apiKey = 'AIzaSyChhpFVipv0xr_rdH6n6te1hvB2XJi5YJ4';
$url = 'https://generativelanguage.googleapis.com/v1beta/models?key=' . $apiKey;

echo "🔍 VÉRIFIER LES MODÈLES DISPONIBLES\n";
echo "══════════════════════════════════════════\n\n";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json'
    ],
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "📌 STATUS CODE: $httpCode\n\n";

if ($httpCode === 200) {
    $data = json_decode($response, true);
    
    if (isset($data['models'])) {
        echo "✅ MODÈLES DISPONIBLES:\n";
        foreach ($data['models'] as $model) {
            echo "  - " . $model['name'] . "\n";
            if (isset($model['supportedGenerationMethods'])) {
                echo "    Methods: " . implode(', ', $model['supportedGenerationMethods']) . "\n";
            }
        }
    } else {
        echo "⚠️  Pas de modèles trouvés ou structure différente.\n";
        echo "Réponse: " . substr($response, 0, 500) . "\n";
    }
} else {
    echo "❌ Erreur: HTTP $httpCode\n";
    echo "Réponse:\n" . $response . "\n";
}
?>
