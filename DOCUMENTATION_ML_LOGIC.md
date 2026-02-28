# 🧠 Logique Complète du Moteur d'Analyse ML (Vision & Nutrition)

Cette documentation détaille le fonctionnement interne du moteur d'Intelligence Artificielle utilisé pour la reconnaissance alimentaire dans le projet WANNASNI.

---

## 🏗️ 1. Architecture du Pipeline d'Analyse

Le moteur suit un flux de traitement séquentiel pour garantir une précision maximale :

1.  **Réception** : Image reçue via FastAPI (`app.py`).
2.  **Classification de Composition** (`_classify_image_type`) : Détermine si l'image contient un seul aliment (**SIMPLE**) ou plusieurs (**MULTI**).
3.  **Extraction de Caractéristiques** : Utilise plusieurs modèles en parallèle :
    *   **Deep Learning** (MobileNetV2) : Caractéristiques sémantiques.
    *   **Histogrammes de Couleurs** : Analyse spectrale (crucial pour distinguer tomate vs pomme).
    *   **Analyse de Texture** : Local Binary Patterns (LBP).
    *   **Descripteurs ORB** : Points d'intérêt géométriques.
4.  **Matching par Similarité** (`SimilarityMatcher`) : Recherche dans l'index de référence (base de données de signatures visuelles).
5.  **Filtrage des Fausses Positives** (`StrictFalsePositiveFilter`) : Élimine le "bruit" visuel par analyse d'écarts de confiance.
6.  **Enrichissement Nutritionnel** (`NutritionKnowledge`) : Traduction des noms d'aliments en calories, nutriments et conseils.

---

## 🔍 2. Stratégies de Détection

### A. Détection Simple (Image Dominante)
Utilisée lorsque l'image contient un focus unique.
*   **Algorithme** : Vote pondéré entre 4 modèles.
*   **Matching** : Si la confiance du meilleur candidat est > 75%, ou si l'écart avec le second est significatif, l'aliment est validé.

### B. Détection Multiple (Plat Composé)
Utilisée pour les plateaux repas ou assiettes garnies.
*   **Segmentation Régionale** : Découpage de l'image en 5 zones (Haut, Bas, Gauche, Droite, Centre).
*   **Analyse Spatiale** : Détection des objets indépendants pour éviter de confondre une garniture avec l'aliment principal.
*   **Correction de Contexte** : Si du riz et du poulet sont détectés proches, ils sont validés comme un repas cohérent.

---

## 🛡️ 3. Le Filtre Anti-Erreurs (Strict Filtering)

C'est la couche de "bon sens" de l'IA. Elle empêche des résultats absurdes :

*   **Analyse du "Gap" (Écart)** : Si l'IA hésite entre 3 aliments avec des scores presque identiques (ex: 71%, 70%, 69%), le filtre rejette la détection multiple comme étant du "bruit" et ne garde que le plus probable.
*   **Incompatibilités Biologiques** : Empêche de détecter du "Chocolat" en plein milieu de "Pâtes bolognaise".
*   **Diversité des Catégories** : Un vrai repas (Plat Complet) doit généralement contenir des catégories différentes (Protéine + Féculent + Légume). Si l'IA détecte 3 types de burgers différents dans une seule assiette, le filtre intervient.

---

## 📊 4. Base de Connaissance Nutritionnelle (`nutrition_knowledge.py`)

Le moteur ne se contente pas de nommer l'aliment, il "comprend" son impact :

*   **Données** : Plus de 800 aliments avec calories, fibres, vitamines, et minéraux.
*   **Portion Intelligent** : Estimation du poids basée sur des "portions types" (ex: 1 pomme moyenne = 150g).
*   **Règles de Régime** : 
    *   **Diabétique** : Alerte sur l'index glycémique.
    *   **Hypertension** : Alerte sur la teneur en sel.
    *   **Standard** : Simple suivi calorique.
*   **Moteur de Recettes** : Suggère des alternatives saines basées sur les aliments restants dans la limite calorique de la journée du senior.

---

## 🛠️ 5. Composants Techniques Principaux

| Fichier | Rôle Technique |
| :--- | :--- |
| `app.py` | Serveur de production (FastAPI / Uvicorn). |
| `full_nutrition_analyzer.py` | Orchestrateur (C'est le "cerveau" qui décide quel modèle appeler). |
| `similarity_matcher.py` | Moteur de recherche visuel vectorisé. |
| `strict_false_positive_filter.py` | Garde-fou logique contre les erreurs de vision. |
| `nutrition_knowledge.py` | Encyclopédie nutritionnelle et calculs diététiques. |

---

## 📈 6. Améliorations Futures
*   **Estimation de volume 3D** : Pour une précision accrue des portions.
*   **Apprentissage Renforcé** : Correction automatique basée sur la validation utilisateur.
*   **Multi-angle** : Possibilité d'analyser plusieurs photos du même repas.
