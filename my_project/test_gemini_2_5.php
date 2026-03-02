<?php
// Test de l'API Gemini avec le nouveau modèle
$apiKey = 'AIzaSyChhpFVipv0xr_rdH6n6te1hvB2XJi5YJ4';
$url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $apiKey;

echo "🔍 TEST API GEMINI 2.5-FLASH\n";
echo "══════════════════════════════════════════\n\n";

$payload = [
    'contents' => [
        [
            'parts' => [
                ['text' => 'Dis-moi trois bénéfices de boire de l\'eau en JSON: {benefits: [...]}']
            ]
        ]
    ]
];

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json'
    ],
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "📌 STATUS CODE: $httpCode\n\n";

if ($httpCode === 200) {
    $data = json_decode($response, true);
    if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
        echo "✅ SUCCÈS!\n\n";
        echo "Réponse:\n";
        echo $data['candidates'][0]['content']['parts'][0]['text'] . "\n";
    } else {
        echo "⚠️ Réponse reçue mais structure inattendue\n";
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    }
} elseif ($httpCode === 404) {
    echo "❌ ERREUR 404 - LE MODÈLE N'EXISTE TOUJOURS PAS\n";
    $data = json_decode($response, true);
    echo "Message: " . $data['error']['message'] . "\n";
} else {
    echo "❌ ERREUR: HTTP $httpCode\n";
    echo "Réponse:\n" . $response . "\n";
}
?>
