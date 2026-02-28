# 🥗 Documentation Complète du Système Nutrition & IA

Cette documentation est un guide exhaustif de tous les composants liés à la nutrition dans l'application. Elle couvre les interfaces utilisateur, le backend Symfony, la base de données, le moteur d'Intelligence Artificielle, et le service expert Sommelier.

---

## 🏗️ 1. Architecture des Données (Entités & Base de Données)

Les données nutritionnelles sont structurées pour une traçabilité totale :

- **`src/Entity/RegimePrescrit.php`** : Définit le contrat nutritionnel (calories max, sel, sucre, hydratation cible).
- **`src/Entity/SuiviRepas.php`** : Capture chaque repas (photo, analyse IA, calories, conformité).
- **`src/Entity/DemandeRegime.php`** : Recueil initial des besoins du senior (allergies, contact d'urgence).
- **`src/Entity/Beverage.php`** : Catalogue des boissons autorisées et recommandées par le Sommelier.
- **`src/Entity/BeverageLog.php`** : Historique précis de l'hydratation quotidienne.
- **`src/Entity/NutritionJournal.php`** & **`NutritionPlan.php`** : Outils de planification et journal de bord quotidien.
- **`src/Entity/RapportHebdomadaire.php`** : Synthèse automatique générée pour le médecin chaque semaine.

---

## 📱 2. Interface Utilisateur (Frontend Senior)

### Contrôleurs Front
- **`MealTrackingController.php`** : Pilote le parcours d'analyse de repas par IA (4 étapes).
- **`NutritionController.php`** : Gère le dashboard, l'historique et le coach IA interactif.
- **`SommelierController.php`** : Expert en hydratation, propose des boissons adaptées aux repas et au régime.

### Templates Twig (`templates/front/nutrition/`)
- **`track.html.twig`** : Interface d'upload photo avec analyseur temps réel.
- **`_meal_results.html.twig`** : Affichage dynamique des calories et alertes de conformité.
- **`index.html.twig`** : Dashboard avec anneaux de progression calorique.
- **`regime.html.twig`** : Visualisation détaillée du régime actuel et des conseils médicaux.
- **`sommelier/`** : Pages dédiées à la dégustation virtuelle de thés, cafés et infusions.

---

## 👩‍⚕️ 3. Interface Administration (Nutritionnistes)

### Contrôleur Admin
- **`NutritionAdminController.php`** : Outil de gestion des patients, création de régimes complexes et monitoring des alertes.

### Templates Admin (`templates/admin/regime_prescrit/`)
- **`demandesatraiter.html.twig`** : Gestionnaire de formulaires pour les nouvelles demandes.
- **`new.html.twig` / `edit.html.twig`** : Configuration fine des interdictions alimentaires.
- **`show.html.twig`** : Vue 360° du senior (repas récents, courbes caloriques, statut hydratation).
- **`pdf_template.html.twig`** : Exportation du programme nutritionnel pour impression.

---

## ⚙️ 4. Services Experts & Logique Métier

### `src/Service/PythonMLService.php`
- **Rôle** : Coeur de l'IA de vision par ordinateur. Détecte les aliments et estime les portions via le serveur Python dédié.

### `src/Service/SommelierService.php`
- **Rôle** : Spécialiste de l'hydratation et des accords mets-boissons.
- **Fonctions** : `seedCatalogIfEmpty` (catalogue thés/cafés), `suggestForMeal` (accords intelligents), `getPersonalizedHydrationAdvice` (conseils sur mesure).

### `src/Service/MealDbService.php` & `MealAnalysisService.php`
- **Rôle** : Calculs mathématiques sur les apports nutritifs et analyse des tendances alimentaires.

---

## 🧠 5. Moteur d'Intelligence Artificielle (Python)

Situé dans `python/ml/`.

- **`full_nutrition_analyzer.py`** : Classifier intelligent distinguant les plats simples des plats composés.
- **`similarity_matcher.py`** : Reconnaissance visuelle par comparaison avec MobileNetV2.
- **`nutrition_knowledge.py`** : Base de données scientifique des nutriments et règles de diététique.
- **`food_detection_corrector.py`** : Algorithme de correction des erreurs de détection visuelle.

---

## 🚀 6. Marketplace Nutrition (Options avancées)

- **`BeverageProduct.php`** & **`BeverageOrder.php`** : Système de commande de box de boissons santé (thés, infusions bio) directement depuis l'application.
- **`BeverageMarketplaceService.php`** : Gère les abonnements aux "Box Hydratation" pour les seniors.

---

## 🚨 7. Sécurité & Alertes Senior

- **SMS Twilio** : Système critique d'alerte en cas de danger nutritionnel (consommation excessive ou aliments dangereux pour le régime).
- **Logique** : Contrôle automatique du seuil journalier et notifications instantanées aux aidants familiaux.
