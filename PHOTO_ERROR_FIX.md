═══════════════════════════════════════════════════════════════
✅ ERREUR CORRIGÉE: "Cannot access 'currentPhotoFile' before initialization"
═══════════════════════════════════════════════════════════════

📝 PROBLÈME:
La variable `currentPhotoFile` était déclarée à la ligne 1864, mais utilisée 
via onchange à la ligne 837 (HTML). Cela causait une ReferenceError car la 
variable n'était pas initialisée avant l'utilisation.

🔧 SOLUTION APPLIQUÉE:
1. Déplacé `let currentPhotoFile = null;` et `let currentPhotoAnalysis = null;` 
   au DÉBUT du bloc JavaScript (juste après le token CSRF)
   
2. Déplacé la fonction `handleBeveragePhoto()` immédiatement après les 
   déclarations des variables

3. Supprimé les déclarations et fonction en double plus bas dans le fichier

📄 FICHIER MODIFIÉ:
- templates/front/nutrition/sommelier/index.html.twig
  - Lignes 1119-1145: Ajouté initialisation variables et fonction
  - Lignes 1889-1907: Supprimé duplicatifs

═══════════════════════════════════════════════════════════════

✅ ÉTAPES MAINTENANT DISPONIBLES:

1. `currentPhotoFile` - Initialisée à null au démarrage
2. `currentPhotoAnalysis` - Initialisée à null au démarrage
3. `handleBeveragePhoto(input)` - Disponible pour onchange
4. `analyzeBeveragePhoto()` - Appelable via onclick
5. `savePhotoAnalysis()` - Hoisted et disponible
6. `resetPhotoUpload()` - Hoisted et disponible

═══════════════════════════════════════════════════════════════

🧪 POUR TESTER:

1. Vérifiez la Console (F12) en chargeant le page
   ✅ Devrait être VIDE (pas d'erreurs rouges)

2. Dans la Console, tapez:
   > typeof currentPhotoFile
   ✅ Doit répondre: "object" (null est un object en JS)
   
3. Téléchargez une photo en cliquant sur "Enregistrer par photo"
   ✅ Aucune erreur ne devrait apparaître

═══════════════════════════════════════════════════════════════
