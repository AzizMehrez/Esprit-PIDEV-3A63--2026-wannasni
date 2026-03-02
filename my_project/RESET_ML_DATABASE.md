# Guide - Vider la Base de Données ML

## 🧹 Réinitialiser les Données de Test

### Option 1: Commande Symfony (Recommandée)

#### Nettoyer TOUS les repas d'aujourd'hui:
```bash
php bin/console app:clear-today-meals
```

Vous devrez confirmer:
```
⚠️  Ceci supprimera tous les repas/boissons du jour!
Continuer? (yes/no) [no]:
> yes
```

#### Nettoyer pour UN utilisateur spécifique (ID = 5):
```bash
php bin/console app:clear-today-meals --user=5
```

#### Forcer sans confirmation:
```bash
php bin/console app:clear-today-meals --force
```

---

### Option 2: Script Direct

```bash
php bin/clear_today_meals.php
```

---

## 📊 Qu'est-ce qui est Supprimé?

| Table | Description | Suppression |
|-------|-------------|------------|
| `SuiviRepas` | Les repas detéctés | ✓ Du jour |
| `BeverageLog` | Les boissons | ✓ Du jour |
| `NutritionJournal` | Journal nutrition | ✓ Du jour (si existe) |
| `HealthJournal` | Journal santé | ✓ Du jour (si existe) |

---

## ✅ Résultat Après Nettoyage

```
Calories: 0 kcal
Boissons: 0 ml
Repas: Aucun
État: Comme si aucun repas n'avait été pris
```

---

## 🔧 Utilisation Pratique

### Avant de Tester un Nouveau Repas:
```bash
# 1. Vider la BDD
php bin/console app:clear-today-meals --force

# 2. Tester la détection ML
curl -X POST http://localhost:8000/api/detect-meal \
  -F "image=@burger.jpg"

# 3. Vérifier les calories
# Doivent être 0 avant le premier repas, puis augmenter après
```

### Pour Tests Répétés:
```bash
# Loop de test
for i in {1..5}; do
  echo "Test $i - Nettoyage..."
  php bin/console app:clear-today-meals --force
  
  echo "Test $i - Détection..."
  curl -X POST http://localhost:8000/api/detect-meal -F "image=@test_$i.jpg"
  
  sleep 2
done
```

---

## 📊 Vérifier l'État

### Voir repas d'aujourd'hui actuellement en BDD:
```bash
php bin/console doctrine:query:sql \
  "SELECT sr.id, sr.date_repas, sr.calories_calculees FROM suivi_repas WHERE DATE(sr.date_repas) = CURDATE()"
```

### Voir boissons d'aujourd'hui:
```bash
php bin/console doctrine:query:sql \
  "SELECT bl.id, bl.consumed_at, bl.quantity_ml FROM beverage_log WHERE DATE(bl.consumed_at) = CURDATE()"
```

---

## ⚠️ Important

- ✅ Supprime **UNIQUEMENT le jour actuel** (aujourd'hui)
- ✅ Les données des jours précédents restent intactes
- ✅ Idéal pour les tests et réinitialisations
- ❌ **NE supprime PAS les utilisateurs** - ils restent
- ❌ **NE supprime PAS les paramètres régime**

---

## 🔄 Alternative: Soft Reset (Garder les Entrées)

Si vous voulez garder l'historique mais reset les calories:

```bash
# Marquer comme NON conforme
php bin/console doctrine:query:sql \
  "UPDATE suivi_repas SET calories_calculees = 0, est_conforme = false WHERE DATE(date_repas) = CURDATE()"
```

---

## 🐛 Troubleshooting

### "Command not found"
```bash
# Vérifier que le fichier existe
ls src/Command/ClearTodayMealsCommand.php

# Vérifier que la commande est chargée
php bin/console list | grep clear-today
```

### "Permission denied"
```bash
# Donner droits d'exécution
chmod +x bin/clear_today_meals.php
```

### Erreur de Connexion BDD
```bash
# Vérifier la connexion
php bin/console doctrine:query:sql "SELECT 1"

# Vérifier les paramètres .env
cat .env | grep DATABASE
```

---

## 📝 Logs

Les suppressions sont automatiquement loggées:
```bash
# Voir les dernières actions
tail -f var/log/dev.log | grep "clear-today"
```

---

**💡 Tip**: Pour un développement rapide, utilisez:
```bash
alias reset-meals='php bin/console app:clear-today-meals --force'
reset-meals  # Puis tester
```
