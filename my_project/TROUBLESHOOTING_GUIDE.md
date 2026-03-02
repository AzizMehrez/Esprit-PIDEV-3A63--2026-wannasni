# Guide d'Amélioration du ML - Solutions Spécifiques

## 📍 Diagnostic: Où les erreurs se produisent?

### Si le système détecte MAL les aliments:

#### **Symptôme 1: Faux Positifs (détecte des choses qui n'existent pas)**

**Exemple**: Photo d'une pomme → Détecte: Pomme ✅ + Fraise ❌ + Banane ❌

**Causes**:
- ✗ Seuils CNN trop bas (< 0.45)
- ✗ Fusion mal pondérée (trop CNN, pas assez similarité)
- ✗ Pas de validation contextuelle
- ✗ Candidats secondaires acceptés trop facilement

**Solutions Implémentées**: ✅
1. **Augmented Similarity Weight**: 75% sim vs 25% CNN (était 60/40)
2. **Strict Secondary Threshold**: 0.52 (était 0.35)
3. **Compatibility Check**: Supprime pizza + burger si détectés ensemble
4. **Confidence Penalty**: Réduit confiance si combinaison implausible

**Comment valider la correction**:
```bash
# Test avec une photo d'une seule pomme
python test_ml_improvements_level2.py
# Résultat attendu: Détecte SEULEMENT pomme, pas de faux positifs
```

---

#### **Symptôme 2: Faux Négatifs (ne détecte pas ce qui existe)**

**Exemple**: Photo d'un burger → Détecte: Rien ❌ ou Confiance trop basse

**Causes**:
- ✗ Seuils trop stricts (0.60 trop haut pour similarité)
- ✗ Image de mauvaise qualité
- ✗ Aliment non dans la base de données
- ✗ Angle ou éclairage mauvais

**Solutions Implémentées**: ✅
1. **Context Boost**: Augmente confiance si combinaison plausible
2. **Adaptive Thresholds**: Ajuste selon la source (CNN < Similarity)
3. **Fallback Detection**: Si CNN seul détecte quelque chose de plausible, garder

**Comment améliorer**:
1. **Réentraîner le CNN** sur de meilleures données:
```python
# python/ml/train_food_classifier.py
- Augmenter données d'entraînement
- Utiliser ImageNet + Fine-tuning
- Data augmentation (rotations, zooms, qualité variable)
```

2. **Ajouter aliments manquants** à NUTRITION_DATA:
```python
# nutrition_knowledge.py - ajouter au dictionnaire
"nom_aliment": {
    "calories": XXX,
    "unite": "100g",
    "categorie": "xxx",
    "portion_moyenne": XXX,
    "nutriments": {...}
}
```

3. **Améliorer images de référence** (pour similarité):
```
data/raw/nom_aliment/
  ├── image1.jpg (angle 1)
  ├── image2.jpg (angle 2)
  └── image3.jpg (éclairage différent)
```

---

### Si les CALORIES calculées sont FAUSSES:

#### **Symptôme 3: Calories trop élevées**

**Exemple**: Une pomme → 150 kcal (réalité: ~78 kcal)

**Causes**:
- ✗ Facteur de correction non appliqué
- ✗ Quantité mal estimée (300g au lieu de 150g)
- ✗ Données nutrition incorrectes dans NUTRITION_DATA
- ✗ Mauvaise conversion 100g → portion

**Solutions Implémentées**: ✅
1. **Quantity Validation**: Limite 0.25x à 3x portion moyenne
2. **Cooking Correction**: Applique -10% pour viande cuite
3. **Min/Max Bounds**: Interdit calories < 1 ou aberrantes

**Comment vérifier**:
```python
# Dans full_nutrition_analyzer.py, _calculate_detailed_nutrition()
# Vérifier que:
adjusted_ratio = ratio * correction_factor  # ← Correction appliquée
"calories": max(1, round(base_cals * adjusted_ratio, 1))  # ← Min 1 kcal
```

**Si toujours faux**:
1. Vérifier les données NUTRITION_DATA:
```python
# nutrition_knowledge.py
"pomme": {
    "calories": 52,  # ← Vérifier: 52 kcal/100g
    "portion_moyenne": 150,  # ← Une pomme = 150g
}
# Calcul: 52 * (150/100) * 1.0 = 78 kcal ✓
```

2. Vérifier image détection (vraie portion?):
```python
# Dans _calculate_detailed_nutrition():
qty = self._validate_and_adjust_quantity(name, qty, data)  # ← Ajuste si aberrant
```

---

#### **Symptôme 4: Calories trop basses**

**Exemple**: Burger → 200 kcal (réalité: ~500 kcal)

**Causes**:
- ✗ Portion mal estimée (seulement la viande, pas le pain/sauce)
- ✗ Facteur correction trop agressif (> 1.0)
- ✗ Quantité validée à minimum (0.25x portion)
- ✗ Calories manquantes dans les nutriments

**Solutions Implémentées**: ✅
1. **No Over-Correction**: Facteur max 1.05 (pas -50%)
2. **Reasonable Ranges**: 0.25x à 3x portable
3. **Complete Data**: Inclut sauce, pain, etc.

**Comment corriger**:
1. Vérifier données burger dans NUTRITION_DATA:
```python
"burger_classique": {
    "calories": 540,  # ← Doit inclure pain + sauce
    "unite": "100g",
    "portion_moyenne": 200,  # ← Burger complet
    "nutriments": {
        "proteines": 30,
        "glucides": 40,
        "lipides": 25,  # ← Important: calculer depuis cela
    }
}
```

2. Vérifier quantité estimée correctement:
```python
# Si utilisateur dit "burger" → portion_moyenne = 200g
# Ne pas baisser à 100g (moitié burger)
```

---

### Si DETECTION est LENTE:

#### **Symptôme 5: Long temps de réponse (> 5 secondes)**

**Causes**:
- ✗ Modèle CNN pas optimisé
- ✗ Similarité matcher pas en cache
- ✗ Traitement image inefficace
- ✗ Pas de cache de détections

**Solutions Implémentées**: ✅
1. **Detection Cache**: Mémorise résultats image identiques
2. **Early Exit**: Arrête si confiance suffisante
3. **Optimized CNN**: Top-10 prédictions (au lieu de 15)
4. **Batch Processing**: Peut traiter N images en parallèle

**Comment optimiser davantage**:
```python
# 1. Utiliser GPU si disponible
import tensorflow as tf
gpus = tf.config.list_physical_devices('GPU')
print(f"GPUs detected: {len(gpus)}")

# 2. Réduire taille image entry
# Passer de 224x224 à 160x160
IMG_SIZE = 160  # au lieu de 224

# 3. Lazy load du modèle
self.model = None  # Ne charger que si demandé
```

---

## 🔧 Checklist pour Améliorer le Système

### Priority 1: Validation (Implémenté ✅)
- [x] Filtrer faux positifs par seuils stricts
- [x] Valider combinaisons (pizza + burger = NON)
- [x] Ajuster quantités dans fourchettes raisonnables
- [x] Appliquer facteurs correction cuisson

### Priority 2: Données (Partiellement ✅)
- [ ] **Vérifier les calories NUTRITION_DATA**
  ```bash
  python3 <<EOF
  from python.ml.nutrition_knowledge import NUTRITION_DATA
  for food, data in NUTRITION_DATA.items():
      print(f"{food}: {data['calories']} kcal/100g")
  EOF
  ```
  
- [ ] **Ajouter aliments manquants** détectés par CNN
  
- [ ] **Améliorer images de référence** (angles multiples, éclairages)

### Priority 3: Modèle (Optional 🔮)
- [ ] Réentraîner CNN avec données réelles
- [ ] Data augmentation cible (photo mal éclairée, angles, etc.)
- [ ] Fine-tune sur erreurs courantes

### Priority 4: Monitoring (Future 🔮)
- [ ] Logger toutes les erreurs (faux positifs, faux négatifs)
- [ ] Dashboard de métriques (precision, recall par aliment)
- [ ] Feedback loop utilisateur

---

## 📊 Métriques à suivre

### Correctness Metrics
```python
from python.ml.detection_debugger import DetectionDebugger

debugger = DetectionDebugger()
# ... détection ...
report = debugger.get_report()

# Chercher:
# - "faux_positifs": Devrait être < 2 par image
# - "confiance_moyenne": Devrait être > 0.65
# - "validations_passées": Devrait être = nombre d'aliments
```

### Performance by Food Category
```python
# Tracker par catégorie:
{
    "fruits": {"recall": 0.85, "precision": 0.90},
    "legumes": {"recall": 0.78, "precision": 0.88},
    "proteines": {"recall": 0.92, "precision": 0.95},
    "plats_complets": {"recall": 0.70, "precision": 0.75},
}
```

### Calorie Accuracy
```python
# Compare detected_calories vs actual_calories
mean_absolute_error = mean(|detected - actual|)
# Devrait être < 50 kcal pour plats simples
# Peut être 100-200 kcal pour plats composés
```

---

## 🚀 Tests à Exécuter

```bash
# Test 1: Validation de base
python test_ml_improvements.py

# Test 2: Améliorations Level 2
python test_ml_improvements_level2.py

# Test 3: Scénario réel
python3 <<EOF
from python.ml.full_nutrition_analyzer import FullNutritionAnalyzer

analyzer = FullNutritionAnalyzer()

# Test avec image réelle
image_path = "test_images/apple.jpg"
result = analyzer.detect_only(image_path)

if result['detected']:
    print(f"✓ Détecté: {[f['nom'] for f in result['foods']]}")
    print(f"✓ Calories: {analyzer.calculate_nutrition(result['foods'], 'standard')['total_nutrition']['calories']} kcal")
else:
    print("✗ Aucune détection")
EOF
```

---

## 💡 Résumé des Améliorations Appliquées

| Aspect | Avant | Après | Impact |
|--------|-------|-------|--------|
| **Fusion Sim/CNN** | 60/40 | 75/25 | ↓ Faux positifs |
| **Seuil Primary** | 0.45 | 0.50-0.60 | ↓ Faux positifs |
| **Seuil Secondary** | 0.35 | 0.40-0.52 | ↓ Faux positifs |
| **Validation Combo** | NON | OUI | Logique OK ✓ |
| **Correction Cuisson** | NON | OUI | Calories réalistes |
| **Filtre Outliers** | NON | OUI | Détections propres |

---

## 🎯 Prochain Étape: Amélioration Level 3

Quand vous verrez que faux positifs/négatifs persistent sur un aliment spécifique:

1. **Identifier l'aliment problématique**:
```python
# python/ml/analyze_errors.py
problematic_foods = {
    "pizza": {"false_positives": 12, "false_negatives": 3},
    "burger": {"false_positives": 8, "false_negatives": 5},
    ...
}
```

2. **Réentraîner specificement pour cet aliment**:
- Augmenter images de référence
- Fine-tune CNN sur cet aliment uniquement
- Ajouter au INCOMPATIBLE_PAIRS si souvent faux positif

3. **Valider amélioration**:
```bash
python test_single_food.py --food pizza
# Vérifier: recall > 0.90, precision > 0.90
```

---

## 📞 Quick Reference

Si vous rencontrez un problème, cherchez:

| Problème | Likelihood | Solution |
|----------|-----------|----------|
| Détecte pizza + burger | Très haut | ✅ _validate_food_combinations() |
| Pizza pas détectée | Haut | ↑ Baisser seuil ou CNN training |
| Calories 2x trop hautes | Haut | ✅ Vérifier NUTRITION_DATA/portions |
| Calories 2x trop basses | Moyen | ✅ Vérifier `correction_factor` |
| Détection lente | Moyen | ✅ Activer cache ou GPU |
| Ne détecte que 1 aliment | Haut | ↑ Appliquer multi-food detection |

