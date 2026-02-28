# 📊 GESTION DE PROJET ET SUIVI DU SPRINT
**PROJET : MODULE NUTRITION INTELLIGENTE (WANNASNI)**

Ce document détaille la méthodologie Agile appliquée et l'organisation temporelle du développement du module nutrition.

---

## 📉 1. BURN DOWN CHART DU SPRINT
Le Burn Down Chart illustre l'effort restant par rapport au temps disponible. Ce graphique a permis de piloter l'avancement réel du projet.

### 🔍 Détails Techniques du Graphique
*   **Axe Horizontal (X)** : Temps (Sprint de 5 jours de développement intensif).
*   **Axe Vertical (Y)** : Effort restant en **Story Points (SP)**.
*   **Capacité Totale** : 45 Story Points.

### 📈 Analyse de l'Avancement Réel
| Jour | Effort Idéal (SP) | Effort Réel (SP) | État d'Avancement |
| :--- | :--- | :--- | :--- |
| **J1** | 45 | 43 | Mise en place de l'environnement et de la base de données. |
| **J2** | 36 | 35 | Développement du CRUD (Demandes de régime). |
| **J3** | 27 | 15 | **Chute majeure** : Réussite de l'intégration du moteur IA Python avec Symfony. |
| **J4** | 18 | 8 | Logique métier nutritionnelle et filtres de conformité. |
| **J5** | 9 | 0 | Tests unitaires, correction de bugs et intégration Twilio Terminé. |

**Observation** : On remarque une pente très raide au **Jour 3**. Cela correspond à l'étape clé où le serveur FastAPI a commencé à communiquer avec le backend Symfony, libérant ainsi la majorité des fonctionnalités dépendantes de l'IA.

---

## 📋 2. TABLEAU BLANC (ORGANISATION TRELLO)
Le tableau Trello ci-dessous présente la répartition claire des tâches sur une semaine complète de travail (5 jours).

### 📅 Organisation de la Semaine

#### **LUNDI : FONDATIONS & ARCHITECTURE (8h)**
*   [X] Modélisation de la base de données (Entités `SuiviRepas`, `DemandeRegime`).
*   [X] Génération des migrations SQL et configuration de Doctrine.
*   [X] Mise en place du Service de calcul d'IMC automatique.
*   [X] Développement du contrôleur CRUD pour les demandes de profil senior.

#### **MARDI : INTERFACE & EXPÉRIENCE UTILISATEUR (8h)**
*   [X] Intégration du Design System (Dashboard Nutrition) avec Twig & CSS.
*   [X] Développement du formulaire d'Upload de photo avec prévisualisation JS.
*   [X] Création du service de stockage sécurisé des images (`PhotoUploadService`).
*   [X] Design de la barre de progression calorique quotidienne.

#### **MERCREDI : INTELLIGENCE ARTIFICIELLE & VISION (10h)**
*   [X] Configuration du serveur **FastAPI** et des points de terminaison (Endpoints).
*   [X] Intégration du modèle **MobileNetV2** pour la classification d'aliments.
*   [X] Développement du `PythonMLService` côté Symfony pour faire le lien API.
*   [X] Test de performance : Analyse d'image en moins de 2 secondes.

#### **JEUDI : LOGIQUE MÉTIER & NUTRITION (8h)**
*   [X] Implémentation de la base de données nutritionnelle (800+ aliments).
*   [X] Développement des filtres de conformité (Vérification Sel/Sucre selon le régime).
*   [X] Création du service de suggestions de recettes automatiques.
*   [X] Logique de détection des excès caloriques (Seuils crititques).

#### **VENDREDI : SÉCURITÉ, ALERTES & FINALISATION (6h)**
*   [X] Intégration de l'API **Twilio** pour l'envoi de SMS en cas d'alerte.
*   [X] Tests de robustesse (Cas limites : image floue, aliment inconnu).
*   [X] Nettoyage du code et optimisation des requêtes SQL.
*   [X] Rédaction de la documentation technique et de la documentation utilisateur.

---

### 🏛️ État Final du Tableau (Vendredi Soir)
*   **To Do** : 0 tâches.
*   **Doing** : 0 tâches.
*   **Done** : 20 tâches.

**Conclusion** : Le sprint a été clôturé à 100% avec une répartition équilibrée entre le Backend (Symfony), le Frontend (Twig) et l'I.A. (Python).
