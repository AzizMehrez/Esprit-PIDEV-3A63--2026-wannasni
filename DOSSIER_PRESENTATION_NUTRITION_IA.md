# 📄 DOSSIER DE PRÉSENTATION TECHNIQUE : MODULE NUTRITION & I.A.
**PROJET : WANNASNI - Solution e-Santé pour le Suivi des Seniors**

---

## 🏛️ 1. CONTEXTE ET OBJECTIFS DU PROJET
Le projet **WANNASNI** répond à une problématique majeure de santé publique : la dénutrition et le suivi métabolique des seniors. Le module Nutrition a été conçu pour transformer une contrainte (noter tout ce que l'on mange) en un acte simple et automatisé.

*   **Problématique** : Les seniors oublient souvent de noter leurs repas ou ont des difficultés avec la saisie textuelle.
*   **Solution** : Utiliser la vision par ordinateur pour "voir" le repas et en déduire l'apport calorique et la conformité médicale en temps réel.

---

## 📋 2. SPRINT BACKLOG : ANALYSE DES USER STORIES (US)

### A. USER STORY 1 : La Gestion Administrative et Médicale (Fonctionnalité CRUD)
> **Thème** : Digitalisation du dossier nutritionnel.

*   **En tant qu’utilisateur (Senior)**,
*   **Je veux** créer et mettre à jour mon profil patient (Poids, Taille, Maladies chroniques, Contact d'urgence),
*   **Afin que** le nutritionniste dispose de toutes les métriques nécessaires pour me prescrire un régime adapté et sécurisé.

**Détails de l'implémentation (Back-office)** :
*   **Contrôle de Saisie Avancé** : Le formulaire utilise des contraintes Symfony pour valider les formats (Numéro international E.164) et interdire les données absurdes (ex: taille négative).
*   **Calcul de l'IMC** : Implémentation d'un service métier qui calcule l'Indice de Masse Corporelle dès la validation, permettant une classification immédiate (Dénutrition, Obésité, Poids normal).
*   **Workflow de Validation** : La demande possède des états (`STATUT_NOUVEAU`, `STATUT_TRAITE`). Une fois traitée par le professionnel, la demande est archivée en lecture seule pour garantir l'historique médical.

---

### B. USER STORY 2 : L'Analyse de Repas par Intelligence Artificielle (Fonctionnalité Avancée)
> **Thème** : Innovation technologique et Vision par ordinateur.

*   **En tant qu’utilisateur (Senior)**,
*   **Je veux** prendre une photo de mon repas et recevoir instantanément une analyse nutritionnelle,
*   **Afin de** savoir si mon repas respecte mon régime (ex: sans sel, sans sucre) sans avoir à chercher les informations nutritionnelles manuellement.

**Détails du fonctionnement métier (Le "Moteur IA")** :
*   **Pipeline de Détection** : L'image subit trois analyses parallèles :
    1.  **Sémantique** : Identification de l'aliment via Deep Learning (MobileNetV2).
    2.  **Spectrale** : Analyse de la couleur pour distinguer des aliments proches (ex: viande rouge vs blanche).
    3.  **Texture** : Analyse LBP pour reconnaître les textures spécifiques (riz, féculents).
*   **Gestion de l'Incertitude** : Le système applique un **Seuil de Confiance de 70%**. Si l'IA n'est pas certaine, elle propose une liste de choix probables à l'utilisateur au lieu de valider une erreur.
*   **Communication Inter-Services** : Utilisation d'une architecture découplée où Symfony agit comme orchestrateur et FastAPI (Python) comme moteur de calcul intensif.

---

## 🔄 3. DIAGRAMME DE SÉQUENCE OBJET (MODÈLE 3-COUCHES)
Ce diagramme illustre le flux d'interactions en respectant l'architecture logicielle en 3 couches : **IHM (Présentation)**, **Métier (Contrôle)** et **Données (Persistance)**.

```mermaid
sequenceDiagram
    autonumber
    
    box "Couche Présentation (IHM)" #f9f9f9
        participant U as 👴 Utilisateur (Senior)
        participant V as 🖥️ Vue Twig / JS
    box end

    box "Couche Métier (Logic & IA)" #edf2ff
        participant C as 🌐 Contrôleur Symfony
        participant S as ⚙️ Service ML (FastAPI)
    box end

    box "Couche Données (Persistance)" #f0fff4
        participant D as 💾 Base de Données (MySQL)
    box end

    U->>V: Sélectionne la photo du repas
    V->>C: Envoie la requête (POST /analyze)
    
    Note over C, S: Phase d'Analyse Intelligente
    C->>S: Transmet l'image pour reconnaissance
    S->>S: Analyse (Deep Learning + Nutrition)
    S-->>C: Retourne les résultats ( Calories, Risques)

    Note over C, D: Phase de Persistance
    C->>D: Persist(SuiviRepas)
    D-->>C: Confirmation de sauvegarde
    
    C-->>V: Renvoie les données traitées (JSON)
    V-->>U: Affiche le bilan nutritionnel & Conformité
```

**Description du modèle 3-couches :**
1.  **IHM (Présentation)** : L'interface utilisateur gère la capture d'image et l'affichage dynamique des résultats via JavaScript.
2.  **Métier (Contrôle)** : Le contrôleur Symfony orchestre la demande. Il délègue l'intelligence au service Python (FastAPI) qui exécute les algorithmes de reconnaissance alimentaire et de calcul diététique.
3.  **Données (Persistance)** : Une fois l'analyse validée, les informations sont stockées de manière permanente en base de données pour permettre l'historique de suivi.

---

## 📉 4. MÉTHODOLOGIE DE TRAVAIL ET SUIVI

### A. Évolution du Sprint (Burn Down Chart)
Le développement a duré deux semaines intensives. La courbe de progression montre :
*   **Phase 1** : Mise en place du socle PHP/Symfony et des formulaires CRUD (Travail régulier).
*   **Phase 2** : Tunnel de développement IA. On note une baisse rapide de l'effort restant après la validation de la communication FastAPI/Symfony au 8ème jour.

### B. Organisation Trello (Kanban du Sprint Nutrition)
*   **Done** : Création des Entités `DemandeRegime` et `SuiviRepas`, Scripts Python de détection, Système d'alertes SMS (Twilio).
*   **In Progress** : Optimisation des temps de réponse de l'IA.
*   **Backlog** : Génération de rapports nutritionnels hebdomadaires en format PDF.

---

## 🛠️ 5. SPÉCIFICATIONS TECHNIQUES
*   **Langages** : PHP 8.2 (Métier) & Python 3.10 (Science des données).
*   **Frameworks** : Symfony 6.4 & FastAPI.
*   **Intelligence Artificielle** : TensorFlow, Keras, OpenCV.
*   **APIs Tierces** : Twilio (Alerte SMS), OpenFoodFacts (Base de données produits).
*   **Stockage** : MySQL (Données structurées) & Système de fichiers local (Images).
