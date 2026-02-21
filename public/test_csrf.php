<?php
// Test CSRF token generation via browser

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test CSRF Token</title>
</head>
<body>
    <h1>Test CSRF Token</h1>
    
    <script>
        // Try to access the token from page
        console.log('Testing CSRF token access...');
        
        // Get from inline script if available
        const tokenEl = document.querySelector('[data-csrf]');
        console.log('Token element:', tokenEl);
        
        // Check what's in window
        console.log('Window csrf:', window.csrfToken || 'Not found');
        
        // Try a simple fetch to /fr/nutrition/sommelier/marketplace
        fetch('/fr/nutrition/sommelier/marketplace', {
            method: 'GET',
            credentials: 'include'
        })
        .then(r => r.text())
        .then(html => {
            // Extract csrf token from HTML
            const match = html.match(/csrf_token\("marketplace"\)\s*}}\s*;\s*const\s+csrfToken\s*=\s*'([^']+)'/);
            if (match) {
                console.log('✅ Found CSRF token in HTML:', match[1].substring(0, 20) + '...');
            } else {
                console.log('❌ Could not find CSRF token in HTML');
                console.log('First 500 chars:', html.substring(0, 500));
            }
        })
        .catch(e => console.error('Fetch error:', e));
    </script>
    
    <p>Ouvrez la console (F12) pour voir les résultats.</p>
</body>
</html>
?>
