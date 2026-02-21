"""
Nutrition Knowledge Base - WANNASNI AI
======================================
Contains comprehensive nutritional data for all foods, diet rules,
and recipe suggestions.
"""

# ============================================================================
# NUTRITION DATA - Complete food database
# ============================================================================

NUTRITION_DATA = {
    # ===== FRUITS =====
    "pomme": {
        "calories": 52, "unite": "100g", "categorie": "fruit",
        "description": "Pomme fraîche (Golden, Gala, Granny Smith ou Fuji). Fruit croquant à la peau lisse, chair juteuse sucrée ou acidulée selon la variété. Se consomme crue, en compote, en tarte ou cuite au four.",
        "bienfaits": "Riche en fibres solubles (pectine, 2.4g/100g) : régule le cholestérol et le transit. Source de quercetine (antioxydant puissant). Favorise la satiété grâce à son index glycémique bas (38). Une pomme par jour est associée à une réduction du risque de maladies cardiovasculaires.",
        "portion_moyenne": 150, "saison": ["automne", "hiver"],
        "portions": {"petite": 100, "moyenne": 150, "grande": 200, "genereuse": 250},
        "composition_detaillee": "Eau 85.6g, glucides 13.8g, protéines 0.3g, lipides 0.2g",
        "nutriments": {"fibres": 2.4, "vitamine_c": 4.6, "potassium": 107, "quercetine": 4.4, "sucres": 10.4, "calcium": 6, "magnesium": 5, "phosphore": 11, "proteines": 0.3, "glucides": 13.8, "lipides": 0.2}
    },
    "banane": {
        "calories": 89, "unite": "100g", "categorie": "fruit",
        "description": "Banane mûre (jaune), fruit tropical énergétique à la chair crémeuse et sucrée. Se consomme nature, en smoothie, en pâtisserie ou flambée.",
        "bienfaits": "Champion du potassium (358mg/100g) : essentiel pour le cœur et les muscles. Source d'énergie rapide grâce aux sucres naturels (glucose, fructose, saccharose). Riche en vitamine B6 et tryptophane (précurseur de la sérotonine, améliore l'humeur). Snack idéal avant le sport.",
        "portion_moyenne": 120, "saison": ["toute l'année"],
        "portions": {"petite": 80, "moyenne": 120, "grande": 150, "genereuse": 200},
        "nutriments": {"fibres": 2.6, "potassium": 358, "magnesium": 27, "vitamine_b6": 0.4, "vitamine_c": 8.7, "sucres": 12.2, "proteines": 1.1, "glucides": 22.8, "lipides": 0.3}
    },
    "orange": {
        "calories": 47, "unite": "100g", "categorie": "fruit",
        "description": "Orange fraîche (Navel, Valencia ou sanguine). Agrume juteux à la peau épaisse orangée, segments sucrés-acidulés. Se consomme en quartiers, en jus pressé, en salade ou en zéste.",
        "bienfaits": "Excellente source de vitamine C (53mg/100g, soit 66% des besoins quotidiens). Riche en antioxydants (hespéridine, naringenine), folate et thiamine. Renforce l'immunité, favorise l'absorption du fer végétal et protège contre le stress oxydatif.",
        "portion_moyenne": 130, "saison": ["hiver"],
        "portions": {"petite": 80, "moyenne": 130, "grande": 180, "genereuse": 250},
        "nutriments": {"fibres": 2.4, "vitamine_c": 53, "potassium": 181, "folate": 30, "thiamine": 0.1, "calcium": 40, "sucres": 9.4, "proteines": 0.9, "glucides": 11.8, "lipides": 0.1}
    },
    "fraise": {
        "calories": 32, "unite": "100g", "categorie": "fruit",
        "description": "Fraises fraîches, petits fruits rouges juteux et parfumés. Se consomment nature, en salade de fruits, en smoothie ou en dessert (tarte, charlotte).",
        "bienfaits": "Faible en calories (32 kcal) mais riche en vitamine C (58mg, plus que l'orange par calorie !). Source de manganèse, folate et polyphénols (ellagitanins anti-cancer). Excellente pour les régimes minceur.",
        "portion_moyenne": 150, "saison": ["printemps", "été"],
        "portions": {"petite": 80, "moyenne": 150, "grande": 250, "genereuse": 400},
        "composition_detaillee": "Eau 91g, glucides 7.7g, protéines 0.7g, lipides 0.3g",
        "nutriments": {"fibres": 2, "vitamine_c": 58, "sucres": 4.9, "folate": 24, "manganese": 0.4, "potassium": 153, "proteines": 0.7, "glucides": 7.7, "lipides": 0.3}
    },
    "raisin": {
        "calories": 69, "unite": "100g", "categorie": "fruit",
        "description": "Raisin frais (blanc ou noir), en grappes. Grains sucrés et juteux. Le raisin noir est plus riche en antioxydants (resvératrol dans la peau).",
        "bienfaits": "Riche en antioxydants puissants (resvératrol, quercétine) qui protègent le cœur. Source de potassium et vitamines B. Attention : c'est l'un des fruits les plus sucrés (16g/100g). À consommer avec modération en cas de diabète.",
        "portion_moyenne": 120, "saison": ["automne"],
        "portions": {"petite": 60, "moyenne": 120, "grande": 200, "genereuse": 300},
        "composition_detaillee": "Eau 81g, glucides 18g, protéines 0.7g, lipides 0.2g",
        "nutriments": {"fibres": 0.9, "sucres": 16, "potassium": 191, "vitamine_c": 3.2, "vitamine_k": 14.6, "manganese": 0.07, "proteines": 0.7, "glucides": 18, "lipides": 0.2}
    },
    "poire": {
        "calories": 57, "unite": "100g", "categorie": "fruit",
        "description": "Poire fraîche (Williams, Conférence ou Comice). Chair juteuse, fondante et sucrée. Se consomme crue, pochée au vin, en compote ou en tarte.",
        "bienfaits": "Champion des fibres parmi les fruits (3.1g/100g dont pectine soluble). Excellente pour le transit intestinal et la satiété. Faible index glycémique malgré son goût sucré. Hypoallergénique (souvent le premier fruit pour bébé).",
        "portion_moyenne": 170, "saison": ["automne", "hiver"],
        "portions": {"petite": 100, "moyenne": 170, "grande": 250, "genereuse": 350},
        "composition_detaillee": "Eau 84g, glucides 15g, protéines 0.4g, lipides 0.1g",
        "nutriments": {"fibres": 3.1, "vitamine_c": 4.3, "potassium": 116, "vitamine_k": 4.4, "cuivre": 0.08, "proteines": 0.4, "glucides": 15.2, "lipides": 0.1, "sucres": 9.8}
    },
    "kiwi": {
        "calories": 61, "unite": "100g", "categorie": "fruit",
        "description": "Kiwi vert (ou gold), petit fruit ovale à peau velue brune et chair vert vif avec petites graines noires. Saveur acidulée et rafraîchissante.",
        "bienfaits": "Record de vitamine C : 93mg/100g (plus que l'orange !). Riche en vitamine K, folate et actinidine (enzyme qui aide à digérer les protéines). Manger 2 kiwis le soir améliore le sommeil (études cliniques). Laxatif naturel doux.",
        "portion_moyenne": 80, "saison": ["hiver"],
        "portions": {"petite": 60, "moyenne": 80, "grande": 120, "genereuse": 180},
        "composition_detaillee": "Eau 83g, glucides 14.7g, protéines 1.1g, lipides 0.5g",
        "nutriments": {"fibres": 3, "vitamine_c": 92.7, "potassium": 312, "vitamine_k": 40.3, "folate": 25, "vitamine_e": 1.5, "proteines": 1.1, "glucides": 14.7, "lipides": 0.5, "sucres": 9}
    },
    "melon": {
        "calories": 34, "unite": "100g", "categorie": "fruit",
        "description": "Melon charentais (ou cantaloup), fruit rond à peau réticulée et chair orange parfumée. Très juteux et sucré en été.",
        "bienfaits": "Extrêmement hydratant (90% d'eau) et peu calorique. Très riche en bêta-carotène (vitamine A : 169µg) et vitamine C. Les variétés orange sont les plus nutritives. Parfait pour les régimes hydratants d'été.",
        "portion_moyenne": 200, "saison": ["été"],
        "portions": {"petite": 100, "moyenne": 200, "grande": 350, "genereuse": 500},
        "composition_detaillee": "Eau 90g, glucides 8.2g, protéines 0.8g, lipides 0.2g",
        "nutriments": {"vitamine_c": 36.7, "potassium": 267, "sucres": 8, "vitamine_a": 169, "folate": 21, "proteines": 0.8, "glucides": 8.2, "lipides": 0.2, "fibres": 0.9}
    },
    "pastèque": {
        "calories": 30, "unite": "100g", "categorie": "fruit",
        "description": "Pastèque (watermelon), gros fruit à peau verte rayée et chair rouge vif, juteuse et sucrée. Rafraîchissante en été.",
        "bienfaits": "Très hydratante : 92% d'eau ! Ultra-faible en calories. Riche en lycopène (plus que la tomate !) et citrulline (améliore la circulation sanguine et la récupération sportive). Idéale pour la récupération après l'effort.",
        "portion_moyenne": 300, "saison": ["été"],
        "portions": {"petite": 150, "moyenne": 300, "grande": 500, "genereuse": 700},
        "composition_detaillee": "Eau 91.5g, glucides 7.6g, protéines 0.6g, lipides 0.2g",
        "nutriments": {"vitamine_c": 8.1, "potassium": 112, "sucres": 6.2, "vitamine_a": 28, "lycopene": 4532, "citrulline": 250, "proteines": 0.6, "glucides": 7.6, "lipides": 0.2, "fibres": 0.4}
    },
    "abricot": {
        "calories": 48, "unite": "100g", "categorie": "fruit",
        "description": "Abricot frais, petit fruit orange velouté à noyau. Chair douce, sucrée et parfumée. Se consomme frais, en compote, confiture ou séché.",
        "bienfaits": "Exceptionnel en bêta-carotène (vitamine A : 96µg) pour la peau et la vision. Riche en potassium (259mg), fibres et antioxydants. L'abricot séché est 5x plus concentré en nutriments mais aussi en sucres.",
        "portion_moyenne": 100, "saison": ["été"],
        "portions": {"petite": 50, "moyenne": 100, "grande": 180, "genereuse": 250},
        "composition_detaillee": "Eau 86g, glucides 11.1g, protéines 1.4g, lipides 0.4g",
        "nutriments": {"fibres": 2, "vitamine_a": 96, "potassium": 259, "vitamine_c": 10, "vitamine_e": 0.9, "fer": 0.4, "proteines": 1.4, "glucides": 11.1, "lipides": 0.4, "sucres": 9.2}
    },
    "cerise": {
        "calories": 63, "unite": "100g", "categorie": "fruit",
        "description": "Cerises fraîches, petits fruits rouges à noyau, sucrées et juteuses. Variétés : burlat (précoce, rouge foncé), bigarreau (croquante). Parfaites nature ou en clafoutis.",
        "bienfaits": "Puissants anti-inflammatoires naturels (anthocyanines). Favorisent le sommeil (mélatonine naturelle). Réduisent les douleurs musculaires après le sport. Riche en vitamine C et potassium. Article de luxe au printemps !",
        "portion_moyenne": 120, "saison": ["été"],
        "portions": {"petite": 60, "moyenne": 120, "grande": 200, "genereuse": 300},
        "composition_detaillee": "Eau 82g, glucides 16g, protéines 1g, lipides 0.2g",
        "nutriments": {"fibres": 2.1, "vitamine_c": 7, "sucres": 12.8, "potassium": 222, "vitamine_a": 3, "proteines": 1, "glucides": 16, "lipides": 0.2}
    },
    "pamplemousse": {
        "calories": 42, "unite": "100g", "categorie": "fruit",
        "description": "Pamplemousse (ou pomelo), gros agrume rose ou jaune à saveur amère-acidulée. Se consomme en segments, en jus pressé ou en salade.",
        "bienfaits": "Puissant brûle-graisse naturel : la naringine stimule le métabolisme des graisses. Riche en vitamine C (31mg), fibres et antioxydants. ⚠️ ATTENTION : interactions médicamenteuses avec statines, immunosuppresseurs et autres médicaments.",
        "portion_moyenne": 250, "saison": ["hiver"],
        "portions": {"petite": 120, "moyenne": 250, "grande": 400, "genereuse": 500},
        "composition_detaillee": "Eau 88.1g, glucides 10.7g, protéines 0.8g, lipides 0.1g",
        "nutriments": {"fibres": 1.6, "vitamine_c": 31.2, "potassium": 135, "vitamine_a": 23, "folate": 13, "proteines": 0.8, "glucides": 10.7, "lipides": 0.1, "sucres": 6.9}
    },
    
    # ===== LÉGUMES =====
    "salade_verte": {
        "calories": 15, "unite": "100g", "categorie": "legume",
        "description": "Salade verte fraîche (laitue, batavia ou romaine). Feuilles croquantes et hydratantes composées à 95% d'eau. Accompagnement classique riche en fibres douces et en micronutriments essentiels.",
        "bienfaits": "Très peu calorique (15 kcal/100g), riche en eau et fibres douces. Source importante de vitamine K (coagulation), d'acide folique (B9) et de bêta-carotène. Favorise le transit intestinal et la satiété.",
        "portion_moyenne": 80, "saison": ["toute l'année"],
        "portions": {"petite": 40, "moyenne": 80, "grande": 150, "genereuse": 200},
        "composition_detaillee": "Eau 95.6g, glucides 1.5g, protéines 1.3g, lipides 0.2g",
        "nutriments": {"fibres": 1.3, "vitamine_k": 126, "vitamine_a": 166, "vitamine_c": 3.7, "folate": 73, "potassium": 194, "calcium": 35, "fer": 0.9, "magnesium": 13, "proteines": 1.3, "glucides": 1.5, "lipides": 0.2}
    },
    "tomate": {
        "calories": 18, "unite": "100g", "categorie": "legume",
        "description": "Tomate rouge fraîche, fruit-légume polyvalent. Chair juteuse et acidulée, riche en lycopène qui lui donne sa couleur rouge vif.",
        "bienfaits": "Excellente source de lycopène (puissant antioxydant qui protège contre les maladies cardiovasculaires et certains cancers). Riche en vitamine C, potassium et vitamine A. L'absorption du lycopène est augmentée par la cuisson et l'huile d'olive.",
        "portion_moyenne": 120, "saison": ["été"],
        "portions": {"petite": 60, "moyenne": 120, "grande": 200, "genereuse": 300},
        "composition_detaillee": "Eau 94.5g, glucides 3.9g, protéines 0.9g, lipides 0.2g",
        "nutriments": {"fibres": 1.2, "vitamine_c": 14, "potassium": 237, "vitamine_a": 42, "lycopene": 2573, "folate": 15, "proteines": 0.9, "glucides": 3.9, "lipides": 0.2, "sucres": 2.6}
    },
    "carotte": {
        "calories": 41, "unite": "100g", "categorie": "legume",
        "description": "Carotte orange fraîche, racine sucrée et croquante. Se consomme crue (râpée, en bâtonnets) ou cuite (vapeur, rôtie, en soupe).",
        "bienfaits": "Champion de la vitamine A (bêta-carotène) : une carotte moyenne couvre 200% des besoins quotidiens. Excellente pour la vision, la peau et l'immunité. Riche en fibres solubles qui régulent le cholestérol.",
        "portion_moyenne": 100, "saison": ["toute l'année"],
        "portions": {"petite": 50, "moyenne": 100, "grande": 180, "genereuse": 250},
        "composition_detaillee": "Eau 88.3g, glucides 9.6g, protéines 0.9g, lipides 0.2g",
        "nutriments": {"fibres": 2.8, "vitamine_a": 835, "potassium": 320, "vitamine_c": 5.9, "vitamine_k": 13.2, "calcium": 33, "magnesium": 12, "phosphore": 35, "proteines": 0.9, "glucides": 9.6, "lipides": 0.2, "sucres": 4.7}
    },
    "brocoli": {
        "calories": 34, "unite": "100g", "categorie": "legume",
        "description": "Brocoli vert en fleurettes, légume crucifère à la texture ferme et au goût légèrement amer. Se consomme cuit vapeur (idéal), sauté, ou en gratin.",
        "bienfaits": "Super-aliment : très riche en vitamine C (89mg/100g, plus que l'orange !), vitamine K et sulforaphane (puissant anti-cancer). Source de calcium végétal, de folate et de chrome. La cuisson vapeur courte (5 min) préserve au mieux les nutriments.",
        "portion_moyenne": 150, "saison": ["automne", "hiver"],
        "portions": {"petite": 80, "moyenne": 150, "grande": 250, "genereuse": 350},
        "composition_detaillee": "Eau 89.3g, glucides 6.6g, protéines 2.8g, lipides 0.4g",
        "nutriments": {"fibres": 2.6, "vitamine_c": 89.2, "vitamine_k": 101.6, "vitamine_a": 31, "folate": 63, "potassium": 316, "calcium": 47, "magnesium": 21, "fer": 0.7, "phosphore": 66, "proteines": 2.8, "glucides": 6.6, "lipides": 0.4}
    },
    "courgette": {
        "calories": 17, "unite": "100g", "categorie": "legume",
        "description": "Courgette verte, cucurbitacée à chair tendre et goût délicat. Se cuisine grillée, en ratatouille, en spirales (courgetti), farcie ou en soupe.",
        "bienfaits": "Très peu calorique (17 kcal/100g) grâce à sa haute teneur en eau (95%). Source de vitamine C, potassium et manganèse. Excellente pour les régimes hypocaloriques et le contrôle glycémique.",
        "portion_moyenne": 150, "saison": ["été"],
        "portions": {"petite": 80, "moyenne": 150, "grande": 250, "genereuse": 350},
        "composition_detaillee": "Eau 95g, glucides 3.1g, protéines 1.2g, lipides 0.3g",
        "nutriments": {"fibres": 1, "vitamine_c": 17.9, "potassium": 261, "vitamine_a": 10, "magnesium": 18, "manganese": 0.2, "folate": 24, "proteines": 1.2, "glucides": 3.1, "lipides": 0.3}
    },
    "aubergine": {
        "calories": 25, "unite": "100g", "categorie": "legume",
        "description": "Aubergine violette, chair spongieuse qui absorbe les saveurs. Se cuisine grillée, en moussaka, en ratatouille, ou en caviar d'aubergine.",
        "bienfaits": "Riche en fibres (3g/100g) et en antioxydants (nasunine dans la peau violette). Attention : sa texture spongieuse absorbe beaucoup d'huile à la cuisson. Préférer la cuisson au four ou vapeur.",
        "portion_moyenne": 150, "saison": ["été"],
        "portions": {"petite": 80, "moyenne": 150, "grande": 250, "genereuse": 350},
        "composition_detaillee": "Eau 92g, glucides 5.9g, protéines 1g, lipides 0.2g",
        "nutriments": {"fibres": 3, "potassium": 229, "magnesium": 14, "vitamine_c": 2.2, "folate": 22, "manganese": 0.2, "proteines": 1, "glucides": 5.9, "lipides": 0.2}
    },
    "poivron": {
        "calories": 26, "unite": "100g", "categorie": "legume",
        "description": "Poivron (rouge, vert ou jaune), croquant et sucré. Le poivron rouge est le plus riche en vitamines car le plus mûr. Se mange cru en salade ou cuit en ratatouille, farci, grillé.",
        "bienfaits": "Record de vitamine C : 128mg/100g (le rouge), plus que l'orange ! Riche en vitamine A et antioxydants (capsaicine douce). Le poivron rouge contient 2x plus de vitamine C que le vert.",
        "portion_moyenne": 100, "saison": ["été"],
        "portions": {"petite": 50, "moyenne": 100, "grande": 180, "genereuse": 250},
        "nutriments": {"fibres": 1.8, "vitamine_c": 127.7, "vitamine_a": 157, "folate": 46, "potassium": 211, "proteines": 1, "glucides": 6, "lipides": 0.3}
    },
    "haricots_verts": {
        "calories": 31, "unite": "100g", "categorie": "legume",
        "description": "Haricots verts fins, légume vert allongé à texture croquante. Se consomment cuits vapeur, sautés à l'ail, en salade tiède ou en accompagnement.",
        "bienfaits": "Excellente source de fibres (2.7g), vitamine K et folate (B9). Faible index glycémique, parfait pour les diabétiques. Riche en silicium, bon pour les os et les articulations.",
        "portion_moyenne": 150, "saison": ["été"],
        "portions": {"petite": 80, "moyenne": 150, "grande": 250, "genereuse": 350},
        "nutriments": {"fibres": 2.7, "vitamine_c": 16.3, "vitamine_k": 43, "folate": 33, "potassium": 211, "calcium": 37, "magnesium": 25, "fer": 1, "proteines": 1.8, "glucides": 7, "lipides": 0.1}
    },
    "épinard": {
        "calories": 23, "unite": "100g", "categorie": "legume",
        "description": "Épinards frais en feuilles, légume vert foncé au goût caractéristique. Se consomment crus en salade (jeunes pousses), cuits à la crème, en soupe ou en quiche.",
        "bienfaits": "Super-aliment riche en fer (2.7mg), magnésium (79mg), vitamine K, folate et lutéine (protection des yeux). Le fer végétal est mieux absorbé avec de la vitamine C (citron). Riche en nitrates naturels qui améliorent la performance physique.",
        "portion_moyenne": 100, "saison": ["printemps", "automne"],
        "portions": {"petite": 50, "moyenne": 100, "grande": 180, "genereuse": 250},
        "nutriments": {"fibres": 2.2, "fer": 2.7, "magnesium": 79, "vitamine_k": 483, "vitamine_a": 469, "vitamine_c": 28, "folate": 194, "calcium": 99, "potassium": 558, "proteines": 2.9, "glucides": 3.6, "lipides": 0.4}
    },
    "chou_fleur": {
        "calories": 25, "unite": "100g", "categorie": "legume",
        "description": "Chou-fleur blanc en bouquets, crucifère à la texture ferme et au goût doux. Se cuisine en gratin, en purée, rôti au four, en couscous de chou-fleur (alternative low-carb).",
        "bienfaits": "Riche en vitamine C (48mg), vitamine K et choline (santé du cerveau). Légume anti-cancer (glucosinolates). Alternative populaire aux féculents dans les régimes low-carb (riz de chou-fleur, purée).",
        "portion_moyenne": 150, "saison": ["automne", "hiver"],
        "portions": {"petite": 80, "moyenne": 150, "grande": 250, "genereuse": 350},
        "nutriments": {"fibres": 2, "vitamine_c": 48.2, "vitamine_k": 15.5, "folate": 57, "potassium": 299, "choline": 44, "proteines": 1.9, "glucides": 5, "lipides": 0.3}
    },
    "oignon": {
        "calories": 40, "unite": "100g", "categorie": "legume",
        "description": "Oignon (jaune, rouge ou blanc), aromate et légume de base de la cuisine. Utilisé cru en salade, caramélisé, en soupe à l'oignon ou comme base aromatique.",
        "bienfaits": "Riche en quercétine (puissant antioxydant anti-inflammatoire), prébiotiques (nourrissent la flore intestinale) et composés soufrés protecteurs. L'oignon rouge est le plus riche en antioxydants.",
        "portion_moyenne": 50, "saison": ["toute l'année"],
        "portions": {"petite": 20, "moyenne": 50, "grande": 100, "genereuse": 150},
        "nutriments": {"fibres": 1.7, "vitamine_c": 7.4, "sucres": 4.2, "quercetine": 20, "potassium": 146, "proteines": 1.1, "glucides": 9.3, "lipides": 0.1}
    },
    "champignon_cuit": {
        "calories": 28, "unite": "100g", "categorie": "legume",
        "description": "Champignons de Paris cuits (ou champignons blancs). Chair tendre et savoureuse, souvent sautés au beurre ou à l'huile d'olive. Accompagnement classique des viandes et omelettes.",
        "bienfaits": "Très peu calorique (28 kcal/100g). Excellente source de sélénium (antioxydant), de vitamines B (B2, B3, B5) et de cuivre. Seul végétal source naturelle de vitamine D (exposition UV). Riche en bêta-glucanes qui renforcent l'immunité.",
        "portion_moyenne": 100, "saison": ["toute l'année"],
        "portions": {"petite": 50, "moyenne": 100, "grande": 150, "genereuse": 200},
        "composition_detaillee": "Eau 91.1g, glucides 4.3g, protéines 2.2g, lipides 0.3g",
        "nutriments": {"fibres": 1.4, "selenium": 11.7, "vitamine_d": 0.2, "potassium": 356, "cuivre": 0.5, "phosphore": 108, "niacine": 4.5, "riboflavine": 0.5, "proteines": 2.2, "glucides": 4.3, "lipides": 0.3}
    },
    "champignon_cru": {
        "calories": 22, "unite": "100g", "categorie": "legume",
        "description": "Champignons de Paris crus, en tranches. Croquants et savoureux en salade ou en garniture.",
        "bienfaits": "Très faible en calories. Source de sélénium, potassium et vitamines B. Contient des antioxydants uniques comme l'ergothionéine.",
        "portion_moyenne": 80, "saison": ["toute l'année"],
        "portions": {"petite": 40, "moyenne": 80, "grande": 120, "genereuse": 160},
        "nutriments": {"fibres": 1, "selenium": 9.3, "potassium": 318, "phosphore": 86, "niacine": 3.6, "proteines": 3.1, "glucides": 3.3, "lipides": 0.3}
    },
    "concombre": {
        "calories": 15, "unite": "100g", "categorie": "legume",
        "description": "Concombre frais, long légume vert à chair croquante et rafraîchissante. Se consomme cru en salade, en tzatziki, en eau aromatisée ou en gaspacho.",
        "bienfaits": "Champion de l'hydratation : 96% d'eau ! Ultra-faible en calories (15 kcal). Source de vitamine K et potassium. Contient des cucurbitacines aux propriétés anti-inflammatoires.",
        "portion_moyenne": 100, "saison": ["été"],
        "portions": {"petite": 50, "moyenne": 100, "grande": 200, "genereuse": 300},
        "nutriments": {"vitamine_k": 16.4, "potassium": 147, "vitamine_c": 2.8, "magnesium": 13, "proteines": 0.7, "glucides": 3.6, "lipides": 0.1, "fibres": 0.5}
    },

    # ===== FAST FOOD & RESTAURATION RAPIDE =====
    "burger_classique": {
        "calories": 650, "unite": "pièce", "categorie": "fast_food",
        "description": "Burger classique : pain buns, steak de bœuf haché, cheddar fondu, salade, tomate, oignon, ketchup et moutarde. Sandwich emblématique de la restauration rapide.",
        "fourchette_calorique": [500, 800],
        "bienfaits": "Apport protéique intéressant (25g) grâce au steak, mais riche en gras saturés, sel et calories. À consommer avec modération (1x/semaine max). Préférer version maison avec viande maigre.",
        "portion_moyenne": 220,
        "portions": {"petite": 150, "moyenne": 220, "grande": 300, "genereuse": 400},
        "nutriments": {"proteines": 25, "glucides": 40, "lipides": 35, "sel": 1.5, "fer": 3, "calcium": 180, "fibres": 2}
    },
    "burger_double": {
        "calories": 950, "unite": "pièce", "categorie": "fast_food",
        "description": "Double burger : deux steaks de bœuf, double fromage, sauce spéciale. Version XXL très calorique.",
        "fourchette_calorique": [800, 1100],
        "bienfaits": "Très calorique, double portion de protéines mais aussi de gras saturés et sel. Equivalent à presque la moitié des besoins caloriques quotidiens.",
        "portion_moyenne": 350,
        "portions": {"petite": 250, "moyenne": 350, "grande": 450, "genereuse": 550},
        "nutriments": {"proteines": 45, "glucides": 45, "lipides": 55, "sel": 2.2, "fer": 5, "calcium": 300}
    },
    "burger_poulet": {
        "calories": 550, "unite": "pièce", "categorie": "fast_food",
        "description": "Burger au poulet : filet de poulet pané ou grillé, salade, sauce mayo légère, dans un pain buns.",
        "fourchette_calorique": [450, 650],
        "bienfaits": "Alternative plus légère au bœuf, riche en protéines. La version grillée est préférable à la version panée/frite (-30% calories).",
        "portion_moyenne": 200,
        "portions": {"petite": 150, "moyenne": 200, "grande": 280, "genereuse": 350},
        "nutriments": {"proteines": 28, "glucides": 40, "lipides": 28, "sel": 1.8, "fibres": 2}
    },
    "frites_moyenne": {
        "calories": 375, "unite": "portion", "categorie": "fast_food",
        "description": "Frites de pomme de terre dorées, croustillantes à l'extérieur et moelleuses à l'intérieur. Cuites en bain de friture.",
        "fourchette_calorique": [300, 450],
        "bienfaits": "Source de potassium et glucides énergiques, mais la friture ajoute beaucoup de lipides. Riche en acrylamide (cancerigène potentiel). Les frites au four sont 30% moins caloriques.",
        "portion_moyenne": 150,
        "portions": {"petite": 80, "moyenne": 150, "grande": 200, "genereuse": 300},
        "nutriments": {"glucides": 45, "lipides": 18, "sel": 0.8, "potassium": 400, "proteines": 4, "fibres": 3}
    },
    "frites_grande": {
        "calories": 550, "unite": "portion", "categorie": "fast_food",
        "description": "Grande portion de frites croustillantes. Quantité généreuse, équivalent à environ 200g de frites.",
        "fourchette_calorique": [500, 600],
        "bienfaits": "Très calorique, à partager idéalement. Forte teneur en lipides et sel.",
        "portion_moyenne": 200,
        "portions": {"petite": 150, "moyenne": 200, "grande": 280, "genereuse": 350},
        "nutriments": {"glucides": 65, "lipides": 26, "sel": 1.2, "potassium": 550, "proteines": 6}
    },
    "nuggets_poulet_6pcs": {
        "calories": 280, "unite": "portion", "categorie": "fast_food",
        "description": "6 nuggets de poulet panés et frits. Morceaux de poulet enrobés de pâte à beignet croustillante.",
        "fourchette_calorique": [250, 320],
        "bienfaits": "Source de protéines (18g) mais la panure frite augmente les lipides. Contient souvent des additifs. Préférer des nuggets maison au four.",
        "portion_moyenne": 120,
        "portions": {"petite": 80, "moyenne": 120, "grande": 180, "genereuse": 240},
        "nutriments": {"proteines": 18, "lipides": 16, "glucides": 15, "sel": 1.1, "fer": 0.8}
    },
    "pizza": {
        "calories": 800, "unite": "portion (2 parts)", "categorie": "fast_food",
        "description": "Pizza : pâte levée garnie de sauce tomate, mozzarella et divers ingrédients (jambon, légumes, chorizo). Plat d'origine italienne très populaire.",
        "fourchette_calorique": [600, 1000],
        "bienfaits": "Source de glucides complexes (pâte), calcium (fromage) et lycopène (sauce tomate). Calorique selon les garnitures — préférer la version légume/thon et pâte fine. À consommer avec modération (1x/semaine).",
        "portion_moyenne": 300,
        "portions": {"petite": 200, "moyenne": 300, "grande": 420, "genereuse": 550},
        "nutriments": {"proteines": 30, "glucides": 80, "lipides": 28, "sel": 2.0, "calcium": 350, "fibres": 3}
    },
    "wrap_poulet": {
        "calories": 450, "unite": "pièce", "categorie": "fast_food",
        "description": "Wrap au poulet : tortilla de blé garnie de poulet grillé ou pané, salade, tomate, sauce et fromage. Option plus légère que le burger.",
        "fourchette_calorique": [380, 520],
        "bienfaits": "Option plus équilibrée avec légumes, bonne source de protéines et fibres. Attention aux sauces caloriques.",
        "portion_moyenne": 250,
        "portions": {"petite": 180, "moyenne": 250, "grande": 320, "genereuse": 400},
        "nutriments": {"proteines": 25, "glucides": 35, "lipides": 22, "fibres": 3, "calcium": 100}
    },
    "salade_fast_food": {
        "calories": 320, "unite": "bol", "categorie": "fast_food",
        "fourchette_calorique": [250, 450],
        "bienfaits": "Option saine en apparence, attention à la sauce.",
        "portion_moyenne": 300,
        "nutriments": {"fibres": 4, "proteines": 15, "lipides": 18, "glucides": 20}
    },
    "milkshake_fraise": {
        "calories": 580, "unite": "moyen", "categorie": "boisson_sucree",
        "fourchette_calorique": [400, 700],
        "bienfaits": "Très sucré, équivalent à un dessert complet.",
        "portion_moyenne": 350,
        "nutriments": {"sucres": 65, "lipides": 15, "calcium": 250}
    },
    "coca_cola_33cl": {
        "calories": 139, "unite": "canette", "categorie": "boisson_sucree",
        "bienfaits": "Calories vides, sucre rapide, à éviter.",
        "portion_moyenne": 330,
        "nutriments": {"sucres": 35}
    },
    "coca_zero": {
        "calories": 0, "unite": "canette", "categorie": "boisson",
        "bienfaits": "Zéro calorie, édulcorants artificiels.",
        "portion_moyenne": 330,
        "nutriments": {"sucres": 0}
    },

    # ===== PANCAKES & CRÊPES =====
    "pancakes_classiques": {
        "calories": 320, "unite": "2 pièces", "categorie": "dessert",
        "fourchette_calorique": [280, 400],
        "bienfaits": "Source d'énergie rapide, riche en glucides.",
        "portion_moyenne": 2,
        "nutriments": {"glucides": 45, "proteines": 8, "lipides": 12, "sucres": 15}
    },
    "pancakes_ble": {
        "calories": 280, "unite": "2 pièces", "categorie": "dessert",
        "bienfaits": "Plus de fibres que les pancakes classiques.",
        "portion_moyenne": 2,
        "nutriments": {"fibres": 6, "glucides": 40, "proteines": 9, "lipides": 8}
    },
    "crepe_sucre": {
        "calories": 180, "unite": "pièce", "categorie": "dessert",
        "bienfaits": "Plaisir simple, modération sur le sucre ajouté.",
        "portion_moyenne": 1,
        "nutriments": {"glucides": 28, "lipides": 6, "sucres": 10}
    },
    "crepe_complete": {
        "calories": 450, "unite": "pièce", "categorie": "plat",
        "bienfaits": "Repas complet avec protéines (œuf, jambon, fromage).",
        "portion_moyenne": 1,
        "nutriments": {"proteines": 22, "lipides": 25, "glucides": 30}
    },
    "crepe_nutella": {
        "calories": 380, "unite": "pièce", "categorie": "dessert",
        "bienfaits": "Riche en sucre et en gras, plaisir occasionnel.",
        "portion_moyenne": 1,
        "nutriments": {"sucres": 30, "lipides": 18, "glucides": 42}
    },
    "gaufre_sucre": {
        "calories": 350, "unite": "pièce", "categorie": "dessert",
        "bienfaits": "Densité calorique élevée, pâte riche en beurre.",
        "portion_moyenne": 1,
        "nutriments": {"glucides": 45, "lipides": 16, "sucres": 20}
    },
    "gaufre_chocolat": {
        "calories": 520, "unite": "pièce", "categorie": "dessert",
        "fourchette_calorique": [450, 600],
        "bienfaits": "Dessert très calorique, à partager idéalement.",
        "portion_moyenne": 1,
        "nutriments": {"glucides": 58, "lipides": 28, "sucres": 35}
    },

    # ===== SUCRERIES & CONFISERIES =====
    "chocolat_noir_70": {
        "calories": 600, "unite": "100g", "categorie": "confiserie",
        "bienfaits": "Antioxydants, magnésium, moins sucré que le chocolat au lait.",
        "portion_moyenne": 20,
        "nutriments": {"fibres": 10, "magnesium": 200, "fer": 10, "sucres": 25}
    },
    "chocolat_lait": {
        "calories": 550, "unite": "100g", "categorie": "confiserie",
        "bienfaits": "Riche en calcium, mais aussi en sucre et gras.",
        "portion_moyenne": 20,
        "nutriments": {"calcium": 200, "sucres": 50, "lipides": 30}
    },
    "barre_chocolat": {
        "calories": 250, "unite": "barre (50g)", "categorie": "confiserie",
        "bienfaits": "Snack rapide, attention au sucre ajouté.",
        "portion_moyenne": 50,
        "nutriments": {"sucres": 25, "lipides": 13, "proteines": 3}
    },
    "bonbons_gelifies": {
        "calories": 340, "unite": "100g", "categorie": "confiserie",
        "bienfaits": "Uniquement du sucre et des colorants, calories vides.",
        "portion_moyenne": 30,
        "nutriments": {"sucres": 75}
    },
    "guimauve": {
        "calories": 320, "unite": "100g", "categorie": "confiserie",
        "bienfaits": "Pauvre en nutriments, sucre presque pur.",
        "portion_moyenne": 20,
        "nutriments": {"sucres": 80}
    },
    "nougat": {
        "calories": 450, "unite": "100g", "categorie": "confiserie",
        "bienfaits": "Protéines des amandes, mais très sucré.",
        "portion_moyenne": 30,
        "nutriments": {"proteines": 8, "sucres": 60, "lipides": 15}
    },

    # ===== CAFÉ & BOISSONS CHAUDES =====
    "cafe_noir": {
        "calories": 2, "unite": "tasse", "categorie": "boisson",
        "bienfaits": "Antioxydants, stimulant, zéro calorie sans sucre.",
        "portion_moyenne": 200,
        "nutriments": {"cafeine": 95}
    },
    "cafe_lait": {
        "calories": 50, "unite": "tasse", "categorie": "boisson",
        "bienfaits": "Calcium du lait, moins fort en caféine.",
        "portion_moyenne": 200,
        "nutriments": {"calcium": 120, "cafeine": 65}
    },
    "cappuccino": {
        "calories": 120, "unite": "tasse", "categorie": "boisson",
        "bienfaits": "Plaisir, mousse de lait, modération sur le sucre ajouté.",
        "portion_moyenne": 200,
        "nutriments": {"calcium": 150, "cafeine": 80}
    },
    "latte_macchiato": {
        "calories": 180, "unite": "grand", "categorie": "boisson",
        "bienfaits": "Riche en lait, source de calcium mais calorique.",
        "portion_moyenne": 300,
        "nutriments": {"calcium": 250, "cafeine": 75, "lipides": 7}
    },
    "chocolat_chaud": {
        "calories": 220, "unite": "tasse", "categorie": "boisson",
        "bienfaits": "Réconfortant, riche en calcium si fait avec du lait.",
        "portion_moyenne": 250,
        "nutriments": {"calcium": 200, "sucres": 20}
    },
    "the_vert": {
        "calories": 1, "unite": "tasse", "categorie": "boisson",
        "bienfaits": "Antioxydants puissants (catéchines), zéro calorie.",
        "portion_moyenne": 200,
        "nutriments": {"antioxydants": "élevé"}
    },

    # ===== CRUNCHY & SNACKS CROUSTILLANTS =====
    "chips_classiques": {
        "calories": 540, "unite": "100g", "categorie": "snack",
        "bienfaits": "Très gras et salé, à consommer occasionnellement.",
        "portion_moyenne": 30,
        "nutriments": {"lipides": 35, "sel": 1.5, "glucides": 50}
    },
    "chips_cuisson_vapeur": {
        "calories": 380, "unite": "100g", "categorie": "snack",
        "bienfaits": "Alternative moins grasse, mais reste salée.",
        "portion_moyenne": 30,
        "nutriments": {"lipides": 15, "sel": 1.2, "fibres": 6}
    },
    "biscuits_apero": {
        "calories": 480, "unite": "100g", "categorie": "snack",
        "bienfaits": "Gras et sel, attention aux quantités.",
        "portion_moyenne": 30,
        "nutriments": {"lipides": 22, "sel": 2, "glucides": 60}
    },
    "cacahuetes_grillees": {
        "calories": 600, "unite": "100g", "categorie": "snack",
        "bienfaits": "Protéines et bonnes graisses, mais très calorique.",
        "portion_moyenne": 30,
        "nutriments": {"proteines": 25, "lipides": 50, "fibres": 8}
    },
    "popcorn_nature": {
        "calories": 380, "unite": "100g", "categorie": "snack",
        "bienfaits": "Riche en fibres si non beurré, option saine.",
        "portion_moyenne": 30,
        "nutriments": {"fibres": 15, "glucides": 70}
    },
    "popcorn_cinema": {
        "calories": 550, "unite": "moyen", "categorie": "snack",
        "bienfaits": "Très calorique à cause du beurre et du sel.",
        "portion_moyenne": 150,
        "nutriments": {"lipides": 30, "sel": 2, "glucides": 60}
    },

    # ===== ESCALOPES & VIANDES PANÉES =====
    "escalope_poulet_pane": {
        "calories": 290, "unite": "100g", "categorie": "proteine_grasse",
        "bienfaits": "Protéines mais friture, panure absorbante.",
        "portion_moyenne": 150,
        "nutriments": {"proteines": 20, "lipides": 18, "glucides": 15}
    },
    "escalope_veau_pane": {
        "calories": 280, "unite": "100g", "categorie": "proteine_grasse",
        "bienfaits": "Traditionnelle, à accompagner de légumes.",
        "portion_moyenne": 150,
        "nutriments": {"proteines": 22, "lipides": 16, "glucides": 12}
    },
    "cordons_bleus": {
        "calories": 320, "unite": "100g", "categorie": "proteine_grasse",
        "bienfaits": "Fromage fondu ajouté, encore plus gras.",
        "portion_moyenne": 150,
        "nutriments": {"proteines": 18, "lipides": 22, "glucides": 14, "calcium": 150}
    },
    "schnitzel": {
        "calories": 280, "unite": "100g", "categorie": "proteine_grasse",
        "bienfaits": "Spécialité autrichienne, traditionnellement frite.",
        "portion_moyenne": 150,
        "nutriments": {"proteines": 21, "lipides": 17, "glucides": 13}
    },
    "poisson_pane": {
        "calories": 230, "unite": "100g", "categorie": "proteine",
        "bienfaits": "Option poisson, moins grasse que la viande panée.",
        "portion_moyenne": 150,
        "nutriments": {"proteines": 16, "lipides": 12, "glucides": 16}
    },

    # ===== MAYONNAISE & SAUCES =====
    "mayonnaise_classique": {
        "calories": 700, "unite": "100g", "categorie": "sauce",
        "bienfaits": "Très calorique, à utiliser avec parcimonie.",
        "portion_moyenne": 15,
        "nutriments": {"lipides": 75, "sel": 1.5}
    },
    "mayonnaise_legere": {
        "calories": 350, "unite": "100g", "categorie": "sauce",
        "bienfaits": "Moins grasse mais contient additifs.",
        "portion_moyenne": 15,
        "nutriments": {"lipides": 35, "sel": 1.8}
    },
    "ketchup": {
        "calories": 100, "unite": "100g", "categorie": "sauce",
        "bienfaits": "Pauvre en calories mais riche en sucre et sel.",
        "portion_moyenne": 15,
        "nutriments": {"sucres": 22, "sel": 2.5}
    },
    "moutarde": {
        "calories": 70, "unite": "100g", "categorie": "sauce",
        "bienfaits": "Peu calorique, stimule le métabolisme.",
        "portion_moyenne": 10,
        "nutriments": {"sel": 6, "fibres": 4}
    },
    "sauce_bbq": {
        "calories": 180, "unite": "100g", "categorie": "sauce",
        "bienfaits": "Sucrée et fumée, modération.",
        "portion_moyenne": 15,
        "nutriments": {"sucres": 35, "sel": 2}
    },
    "sauce_blanche": {
        "calories": 520, "unite": "100g", "categorie": "sauce",
        "bienfaits": "Base béchamel riche en beurre et farine.",
        "portion_moyenne": 15,
        "nutriments": {"lipides": 45, "glucides": 15}
    },
    "sauce_soja": {
        "calories": 60, "unite": "100g", "categorie": "sauce",
        "bienfaits": "Très salée, à utiliser avec modération.",
        "portion_moyenne": 10,
        "nutriments": {"sel": 15, "proteines": 8}
    },
    "sauce_curry": {
        "calories": 350, "unite": "100g", "categorie": "sauce",
        "bienfaits": "Épices anti-inflammatoires, mais crème calorique.",
        "portion_moyenne": 15,
        "nutriments": {"lipides": 28, "proteines": 5}
    },

    # ===== PROTÉINES (COMPLÉMENT) =====
    "proteine_poudre_whey": {
        "calories": 110, "unite": "dose (30g)", "categorie": "proteine",
        "bienfaits": "Protéines rapides pour la récupération musculaire.",
        "portion_moyenne": 30,
        "nutriments": {"proteines": 24, "glucides": 2, "lipides": 1.5}
    },
    "proteine_vegetale": {
        "calories": 120, "unite": "dose (30g)", "categorie": "proteine",
        "bienfaits": "Alternative végétale, riche en fibres.",
        "portion_moyenne": 30,
        "nutriments": {"proteines": 22, "fibres": 3, "glucides": 3}
    },
    "tofu": {
        "calories": 144, "unite": "100g", "categorie": "proteine_vegetale",
        "bienfaits": "Protéines végétales, fer, calcium.",
        "portion_moyenne": 150,
        "nutriments": {"proteines": 15, "calcium": 350, "fer": 5}
    },
    "seitan": {
        "calories": 140, "unite": "100g", "categorie": "proteine_vegetale",
        "bienfaits": "Très riche en protéines (gluten), pauvre en lipides.",
        "portion_moyenne": 150,
        "nutriments": {"proteines": 25, "glucides": 8}
    },
    "lentilles": {
        "calories": 116, "unite": "100g cuit", "categorie": "legumineuse",
        "bienfaits": "Protéines végétales, fibres, fer.",
        "portion_moyenne": 200,
        "nutriments": {"proteines": 9, "fibres": 8, "fer": 3}
    },
    "pois_chiches": {
        "calories": 139, "unite": "100g cuit", "categorie": "legumineuse",
        "bienfaits": "Protéines, fibres, base du houmous.",
        "portion_moyenne": 200,
        "nutriments": {"proteines": 7, "fibres": 6, "glucides": 22}
    },
    "oeufs_brouilles": {
        "calories": 200, "unite": "2 oeufs", "categorie": "proteine",
        "bienfaits": "Protéines complètes, préparation sans matière grasse si bien cuite.",
        "portion_moyenne": 120,
        "nutriments": {"proteines": 13, "lipides": 15, "vitamine_d": 2}
    },
    "oeufs_coque": {
        "calories": 140, "unite": "2 oeufs", "categorie": "proteine",
        "bienfaits": "Cuisson douce, préserve les nutriments.",
        "portion_moyenne": 100,
        "nutriments": {"proteines": 12, "lipides": 9, "vitamine_d": 2}
    },

    # ===== CRUDITÉS =====
    "carottes_rapees": {
        "calories": 35, "unite": "100g", "categorie": "legume",
        "bienfaits": "Riches en bêta-carotène, fibres.",
        "portion_moyenne": 100,
        "nutriments": {"fibres": 3, "vitamine_a": 800}
    },
    "celeri_remedes": {
        "calories": 16, "unite": "100g", "categorie": "legume",
        "bienfaits": "Peu calorique, effet diurétique.",
        "portion_moyenne": 100,
        "nutriments": {"potassium": 260, "fibres": 1.6}
    },
    "radis": {
        "calories": 16, "unite": "100g", "categorie": "legume",
        "bienfaits": "Rafraîchissant, peu calorique, croquant.",
        "portion_moyenne": 50,
        "nutriments": {"vitamine_c": 14, "fibres": 1.6}
    },
    "concombre_creme": {
        "calories": 15, "unite": "100g", "categorie": "legume",
        "bienfaits": "Très hydratant, parfait en salade.",
        "portion_moyenne": 100,
        "nutriments": {"eau": 96}
    },

    # ===== PLATS COMPOSÉS =====
    "spaghetti_bolognaise": {
        "calories": 650, "unite": "assiette", "categorie": "plat_compose",
        "description": "Spaghetti sauce bolognaise : pâtes de blé dur longues nappées d'une sauce tomate à la viande hachée de bœuf, oignons, carottes, céleri, ail, herbes de Provence et concentré de tomates. Plat italien classique, généreux et rassasiant.",
        "fourchette_calorique": [550, 750],
        "bienfaits": "Repas complet : glucides lents (pâtes), protéines (viande), lycopène (tomates cuites, mieux absorbé avec les lipides). Source de fer, zinc et vitamines B. Énergie progressive pour les activités quotidiennes.",
        "portion_moyenne": 400, "saison": ["toute l'année"],
        "portions": {"petite": 250, "moyenne": 400, "grande": 550, "genereuse": 700},
        "composition_detaillee": "Pâtes ~180g cuites, sauce bolognaise ~220g (viande 80g, sauce tomate 100g, légumes 40g)",
        "nutriments": {"proteines": 28, "glucides": 75, "lipides": 22, "fibres": 5, "fer": 4.5, "vitamine_c": 12, "calcium": 85, "sel": 1.8, "potassium": 520}
    },
    "lasagnes": {
        "calories": 750, "unite": "assiette", "categorie": "plat_compose",
        "description": "Lasagnes à la bolognaise : couches alternées de feuilles de pâte fraîche, sauce bolognaise (bœuf haché, tomates, oignons, carottes, céleri), béchamel onctueuse (lait, beurre, farine, muscade) et fromage râpé gratiné (parmesan/emmental). Plat familial cuit au four, doré et croustillant sur le dessus.",
        "fourchette_calorique": [600, 900],
        "bienfaits": "Plat très complet nutritionnellement : protéines animales (bœuf, fromage), glucides complexes (pâtes), calcium (béchamel, fromage), fer (viande), lycopène (tomates). Attention aux lipides (béchamel, fromage) : à limiter si régime hypocalorique. Version allégée possible : béchamel légère, viande maigre 5%.",
        "portion_moyenne": 350, "saison": ["toute l'année"],
        "portions": {"petite": 200, "moyenne": 350, "grande": 500, "genereuse": 650},
        "composition_detaillee": "Par part : pâtes ~100g, sauce bolognaise ~120g (viande 50g, tomate 50g, légumes 20g), béchamel ~80g, fromage ~30g",
        "nutriments": {"proteines": 32, "glucides": 55, "lipides": 38, "fibres": 3.5, "calcium": 320, "fer": 3.8, "vitamine_a": 180, "vitamine_b12": 2.5, "sel": 2.2, "sucres": 8, "phosphore": 290, "zinc": 4.5, "potassium": 480}
    },
    "lasagnes_legumes": {
        "calories": 480, "unite": "assiette", "categorie": "plat_compose",
        "description": "Lasagnes végétariennes : couches de pâte avec légumes grillés (courgettes, aubergines, poivrons, épinards), sauce tomate, béchamel légère et fromage gratiné. Version plus légère des lasagnes classiques.",
        "fourchette_calorique": [380, 580],
        "bienfaits": "Alternative végétarienne riche en fibres, vitamines et minéraux. Moins calorique que les lasagnes viande (-30%). Riche en antioxydants des légumes colorés.",
        "portion_moyenne": 350, "saison": ["toute l'année"],
        "portions": {"petite": 200, "moyenne": 350, "grande": 500, "genereuse": 650},
        "nutriments": {"proteines": 18, "glucides": 48, "lipides": 22, "fibres": 6, "calcium": 250, "vitamine_c": 25, "vitamine_a": 220, "sel": 1.5}
    },
    "boulettes_viande": {
        "calories": 400, "unite": "portion", "categorie": "plat_compose",
        "description": "Boulettes de viande hachée (bœuf ou bœuf-porc) assaisonnées aux herbes et épices, cuites en sauce tomate, au four ou poêlées. Servies avec pâtes, riz ou purée.",
        "fourchette_calorique": [300, 500],
        "bienfaits": "Riche en protéines (30g/portion) et en fer héminique (bien absorbé). Source de zinc et vitamines B. Les boulettes maison permettent de contrôler la teneur en gras.",
        "portion_moyenne": 200, "saison": ["toute l'année"],
        "portions": {"petite": 120, "moyenne": 200, "grande": 300, "genereuse": 400},
        "nutriments": {"proteines": 30, "lipides": 25, "glucides": 10, "fer": 3.5, "zinc": 5, "vitamine_b12": 2.8, "sel": 1.5}
    },

    # ===== BOISSONS =====
    "eau": {
        "calories": 0, "unite": "100ml", "categorie": "boisson",
        "bienfaits": "Essentielle, zéro calorie.",
        "portion_moyenne": 250
    },
    "soda": {
        "calories": 41, "unite": "100ml", "categorie": "boisson_sucree",
        "bienfaits": "Calories vides, à éviter.",
        "portion_moyenne": 330,
        "nutriments": {"sucres": 10.6}
    },
    "jus_orange": {
        "calories": 45, "unite": "100ml", "categorie": "boisson",
        "bienfaits": "Vitamine C.",
        "portion_moyenne": 200,
        "nutriments": {"vitamine_c": 30, "sucres": 9}
    },

    # ===== VIANDES & POISSONS (compléments) =====
    "poulet_grille": {
        "calories": 165, "unite": "100g", "categorie": "proteine",
        "description": "Blanc de poulet grillé, viande blanche maigre dorée au grill ou à la plancha. Texture ferme, légères marques de grillade. Assaisonné d'herbes, épices ou marinade.",
        "bienfaits": "Champion des protéines maigres : 31g de protéines pour seulement 3.6g de lipides/100g. Excellente source de niacine (B3), phosphore et sélénium. Idéal pour la construction musculaire et les régimes hypocaloriques.",
        "portion_moyenne": 150, "saison": ["toute l'année"],
        "portions": {"petite": 80, "moyenne": 150, "grande": 220, "genereuse": 300},
        "nutriments": {"proteines": 31, "lipides": 3.6, "fer": 1, "zinc": 1, "vitamine_b3": 13.7, "vitamine_b6": 0.6, "phosphore": 228, "selenium": 27.6, "glucides": 0}
    },
    "poulet_frit": {
        "calories": 260, "unite": "100g", "categorie": "proteine_grasse",
        "description": "Poulet frit (aile ou cuisse panée et frite à l'huile). Croûte croustillante dorée, chair juteuse à l'intérieur. Style KFC ou poulet du sud américain.",
        "bienfaits": "Riche en protéines (26g) mais beaucoup de gras ajouté par la friture (14g lipides). La panure absorbe l'huile. Préférer le poulet grillé ou au four pour réduire de 40% les calories.",
        "portion_moyenne": 150, "saison": ["toute l'année"],
        "portions": {"petite": 80, "moyenne": 150, "grande": 220, "genereuse": 300},
        "nutriments": {"proteines": 26, "lipides": 14, "glucides": 8, "sel": 1.2, "fer": 1.2, "zinc": 1.8, "vitamine_b3": 8}
    },
    "steak_boeuf": {
        "calories": 250, "unite": "100g", "categorie": "proteine",
        "description": "Steak de bœuf grillé (entrecôte, faux-filet ou bavette). Viande rouge saisie à haute température, croutée à l'extérieur et juteuse à l'intérieur.",
        "bienfaits": "Excellente source de fer héminique (2.6mg, bien absorbé), zinc (4.8mg), vitamine B12 et protéines complètes (26g). Privilégiez les morceaux maigres (5% MG). Limiter à 2-3 portions/semaine selon les recommandations santé.",
        "portion_moyenne": 150, "saison": ["toute l'année"],
        "portions": {"petite": 100, "moyenne": 150, "grande": 200, "genereuse": 300},
        "nutriments": {"proteines": 26, "lipides": 15, "fer": 2.6, "zinc": 4.8, "vitamine_b12": 2.5, "vitamine_b6": 0.4, "phosphore": 198, "selenium": 26, "glucides": 0}
    },
    "steak_hache": {
        "calories": 230, "unite": "100g", "categorie": "proteine",
        "description": "Steak haché de bœuf (15% MG standard). Galette de viande hachée cuite à la poêle, au grill ou au four. Base du burger, des boulettes et de la sauce bolognaise.",
        "bienfaits": "Protéines complètes (24g) et fer héminique (2mg). Choisir 5% MG pour limiter les graisses (150 kcal au lieu de 230). Pratique et rapide à cuisiner. Source de zinc et vitamines B.",
        "portion_moyenne": 125, "saison": ["toute l'année"],
        "portions": {"petite": 80, "moyenne": 125, "grande": 180, "genereuse": 250},
        "nutriments": {"proteines": 24, "lipides": 14, "fer": 2, "sel": 0.8, "zinc": 4.2, "vitamine_b12": 2.2, "phosphore": 175, "glucides": 0}
    },
    "poisson_blanc": {
        "calories": 82, "unite": "100g", "categorie": "proteine",
        "description": "Poisson blanc (cabillaud, merlan, colin ou sole). Chair blanche, floconneuse et délicate. Le filet sans arêtes se cuisine poché, vapeur, grillé ou en papillote.",
        "bienfaits": "Ultra-maigre (0.8g lipides), très riche en protéines (18g) et iode (essentiel pour la thyroïde). Source de sélénium et phosphore. Idéal pour les régimes protéinés et hypocaloriques. Recommandé 2-3x/semaine.",
        "portion_moyenne": 150, "saison": ["toute l'année"],
        "portions": {"petite": 80, "moyenne": 150, "grande": 200, "genereuse": 250},
        "nutriments": {"proteines": 18, "lipides": 0.8, "iode": 50, "selenium": 30, "phosphore": 200, "vitamine_b12": 1, "vitamine_d": 1, "potassium": 340, "glucides": 0}
    },
    "saumon": {
        "calories": 208, "unite": "100g", "categorie": "proteine",
        "description": "Filet ou pavé de saumon (atlantique ou pacifique). Chair rose-orangée, grasse et fondante. Se cuisine grillé, au four, en tartare, fumé ou en sushi.",
        "bienfaits": "Roi des oméga-3 (2.3g/100g) : protège le cœur, le cerveau et réduit l'inflammation. Excellente source de vitamine D (difficile à trouver dans l'alimentation), B12 et astaxanthine (antioxydant puissant qui donne la couleur rose). Recommandé 2x/semaine.",
        "portion_moyenne": 150, "saison": ["toute l'année"],
        "portions": {"petite": 80, "moyenne": 150, "grande": 200, "genereuse": 250},
        "nutriments": {"proteines": 20, "lipides": 13, "omega_3": 2.3, "vitamine_d": 11, "vitamine_b12": 3.2, "selenium": 36, "phosphore": 240, "potassium": 363, "glucides": 0}
    },
    "oeuf": {
        "calories": 155, "unite": "100g", "categorie": "proteine",
        "description": "Œuf de poule entier (environ 50-60g pièce). Le blanc est riche en protéines pures, le jaune concentre les vitamines, minéraux et lipides. Se décline en brouillés, à la coque, au plat, dur, en omelette.",
        "bienfaits": "Protéine de référence (score 100) : contient tous les acides aminés essentiels. Riche en choline (santé du cerveau), vitamine D, B12 et lutéine (santé des yeux). Le cholestérol alimentaire de l'œuf a peu d'impact sur le cholestérol sanguin chez la majorité des personnes.",
        "portion_moyenne": 60, "saison": ["toute l'année"],
        "portions": {"petite": 50, "moyenne": 60, "grande": 120, "genereuse": 180},
        "nutriments": {"proteines": 13, "lipides": 11, "vitamine_d": 2, "vitamine_b12": 1.1, "choline": 294, "selenium": 30, "phosphore": 198, "vitamine_a": 160, "fer": 1.8, "zinc": 1.3, "glucides": 1.1}
    },

    # ===== FÉCULENTS (compléments) =====
    "pates_completes": {
        "calories": 131, "unite": "100g cuit", "categorie": "feculent",
        "description": "Pâtes complètes (blé complet) cuites al dente. Couleur brune, texture plus ferme et goût plus prononcé que les pâtes blanches. Se déclinent en spaghetti, penne, fusilli.",
        "bienfaits": "Énergie lente (IG bas ~42 vs 55 pour les pâtes blanches). Riche en fibres (4g/100g) : meilleur transit et satiété prolongée. Source de magnésium, phosphore et vitamines B. Idéales pour les diabétiques et sportifs.",
        "portion_moyenne": 200, "saison": ["toute l'année"],
        "portions": {"petite": 100, "moyenne": 200, "grande": 300, "genereuse": 400},
        "composition_detaillee": "Eau 62g, glucides 25g, protéines 5g, lipides 0.9g, fibres 4g",
        "nutriments": {"glucides": 25, "fibres": 4, "proteines": 5, "magnesium": 42, "phosphore": 89, "fer": 1.4, "zinc": 0.9, "vitamine_b1": 0.15, "lipides": 0.9}
    },
    "riz_blanc": {
        "calories": 130, "unite": "100g cuit", "categorie": "feculent",
        "description": "Riz blanc cuit (long grain, basmati ou thaï). Grains séparés et moelleux, base neutre qui accompagne viandes, poissons, légumes et sauces du monde entier.",
        "bienfaits": "Source d'énergie rapide et facilement digestible. Aliment de base pour 3.5 milliards de personnes. Convient aux estomacs sensibles (anti-diarrhéique). Préférer le riz basmati (IG plus bas ~58) au riz à cuisson rapide (IG élevé ~87).",
        "portion_moyenne": 200, "saison": ["toute l'année"],
        "portions": {"petite": 100, "moyenne": 200, "grande": 300, "genereuse": 400},
        "composition_detaillee": "Eau 68g, glucides 28g, protéines 2.7g, lipides 0.3g",
        "nutriments": {"glucides": 28, "proteines": 2.7, "fibres": 0.4, "lipides": 0.3, "phosphore": 43, "magnesium": 12, "selenium": 7.5, "fer": 0.2}
    },
    "riz_complet": {
        "calories": 111, "unite": "100g cuit", "categorie": "feculent",
        "description": "Riz complet (brun) cuit. Grains bruns avec leur enveloppe de son, texture légèrement ferme et goût de noisette. Cuisson plus longue (35-40 min vs 15 min pour le blanc).",
        "bienfaits": "2x plus de fibres et 5x plus de magnésium que le riz blanc. IG plus bas (~50). Riche en manganèse, sélénium et vitamines B. Meilleur pour le contrôle du poids et la glycémie.",
        "portion_moyenne": 200, "saison": ["toute l'année"],
        "portions": {"petite": 100, "moyenne": 200, "grande": 300, "genereuse": 400},
        "nutriments": {"glucides": 23, "proteines": 2.6, "fibres": 1.8, "lipides": 0.9, "magnesium": 43, "manganese": 0.9, "phosphore": 83, "selenium": 10, "vitamine_b3": 1.5}
    },
    "pomme_de_terre_vapeur": {
        "calories": 80, "unite": "100g", "categorie": "feculent",
        "description": "Pomme de terre cuite à la vapeur, chair fondante et peau fine. Cuisson la plus saine : sans matières grasses, préserve le potassium et la vitamine C.",
        "bienfaits": "Excellent rapport satiété/calories. Très riche en potassium (421mg/100g), vitamine C (20mg quand fraîchement cuite) et vitamine B6. L'amidon résistant (après refroidissement) nourrit les bonnes bactéries intestinales.",
        "portion_moyenne": 200, "saison": ["toute l'année"],
        "portions": {"petite": 100, "moyenne": 200, "grande": 300, "genereuse": 400},
        "composition_detaillee": "Eau 77g, glucides 17g, protéines 2g, lipides 0.1g",
        "nutriments": {"glucides": 17, "fibres": 2, "potassium": 421, "vitamine_c": 20, "vitamine_b6": 0.3, "magnesium": 23, "phosphore": 44, "fer": 0.3, "proteines": 2, "lipides": 0.1}
    },
    "couscous": {
        "calories": 112, "unite": "100g cuit", "categorie": "feculent",
        "description": "Semoule de blé dur cuite à la vapeur, grains fins et légers. Base du plat nord-africain traditionnel servi avec légumes, viande et bouillon épicé.",
        "bienfaits": "Source d'énergie progressive, bonne teneur en protéines végétales et sélénium. Faible en lipides. Le couscous complet est encore plus nutritif.",
        "portion_moyenne": 200, "saison": ["toute l'année"],
        "portions": {"petite": 100, "moyenne": 200, "grande": 300, "genereuse": 400},
        "nutriments": {"glucides": 23, "proteines": 3.8, "lipides": 0.2, "fibres": 1.4, "selenium": 27, "phosphore": 22}
    },

    # ===== DESSERTS (compléments) =====
    "chocolat": {
        "calories": 175, "unite": "30g", "categorie": "dessert",
        "bienfaits": "Riche en antioxydants, plaisir en modération.",
        "portion_moyenne": 30,
        "nutriments": {"sucres": 16, "lipides": 11, "calcium": 15, "proteines": 2}
    },
    "glace": {
        "calories": 207, "unite": "100g", "categorie": "dessert",
        "bienfaits": "Plaisir mais riche en sucres et lipides.",
        "portion_moyenne": 100,
        "nutriments": {"sucres": 24, "lipides": 11, "calcium": 128}
    },

    # ===== AUTRES =====
    "yaourt_nature": {
        "calories": 59, "unite": "100g", "categorie": "laitage",
        "bienfaits": "Calcium, probiotiques.",
        "portion_moyenne": 125,
        "nutriments": {"calcium": 150, "proteines": 5, "lipides": 3.5}
    },
    "aliment_non_identifie": {
        "calories": 0, "unite": "portion", "categorie": "inconnu",
        "bienfaits": "Veuillez essayer de reprendre la photo de plus près ou avec un meilleur éclairage."
    }
}

# ============================================================================
# DIET RULES - Dietary restrictions per regime type
# ============================================================================

DIET_RULES = {
    "Sans Sucre": {
        "nom": "Régime sans sucre",
        "interdits": ["boisson_sucree", "confiserie", "dessert", "laitage_sucre"],
        "a_limiter": ["fruit_tres_sucre", "feculent"],
        "recommandes": ["legume", "proteine_maigre", "laitage"]
    },
    "Sans Sel": {
        "nom": "Régime hyposodé",
        "interdits": ["charcuterie", "fromage", "plat_prepare", "snack", "sauce"],
        "a_limiter": ["pain"],
        "recommandes": ["legume", "fruit", "proteine_maigre"]
    },
    "Diabétique": {
        "nom": "Régime pour diabétique",
        "interdits": ["boisson_sucree", "confiserie", "dessert", "fruit_tres_sucre"],
        "a_limiter": ["feculent", "fruit", "sauce"],
        "recommandes": ["legume", "proteine_maigre", "legumineuse"]
    },
    "Hypocalorique": {
        "nom": "Régime minceur",
        "interdits": ["friture", "fast_food", "confiserie", "sauce"],
        "a_limiter": ["feculent", "proteine_grasse"],
        "recommandes": ["legume", "fruit", "proteine_maigre"]
    },
    "Hyperprotéiné": {
        "nom": "Régime riche en protéines",
        "interdits": ["feculent", "sucre", "confiserie"],
        "a_limiter": ["fruit"],
        "recommandes": ["proteine", "proteine_maigre", "laitage", "legumineuse"]
    },
    "Standard": {
        "nom": "Alimentation équilibrée",
        "interdits": [],
        "a_limiter": ["fast_food", "confiserie"],
        "recommandes": ["legume", "fruit", "proteine", "feculent"]
    }
}

# ============================================================================
# DIET RECIPE SUGGESTIONS - Predefined recipes per diet type
# ============================================================================

DIET_RECIPE_SUGGESTIONS = {
    "Sans Sucre": [
        {
            "nom": "Omelette aux champignons",
            "ingredients": ["oeufs", "champignons", "oignon", "persil"],
            "calories": 250,
            "difficulte": "Facile",
            "temps": "15 min"
        },
        {
            "nom": "Salade de poulet grillé",
            "ingredients": ["poulet", "salade_verte", "concombre", "tomate"],
            "calories": 320,
            "difficulte": "Facile",
            "temps": "20 min"
        }
    ],
    "Sans Sel": [
        {
            "nom": "Filet de poisson vapeur",
            "ingredients": ["poisson_blanc", "courgette", "carotte", "citron"],
            "calories": 280,
            "difficulte": "Moyenne",
            "temps": "25 min"
        },
        {
            "nom": "Riz aux légumes",
            "ingredients": ["riz_complet", "brocoli", "poivron", "oignon"],
            "calories": 350,
            "difficulte": "Facile",
            "temps": "30 min"
        }
    ],
    "Diabétique": [
        {
            "nom": "Quinoa aux légumes",
            "ingredients": ["quinoa", "courgette", "aubergine", "poivron"],
            "calories": 320,
            "difficulte": "Moyenne",
            "temps": "25 min"
        },
        {
            "nom": "Blanc de poulet aux herbes",
            "ingredients": ["poulet", "thym", "romarin", "ail"],
            "calories": 220,
            "difficulte": "Facile",
            "temps": "20 min"
        }
    ],
    "Hypocalorique": [
        {
            "nom": "Soupe de légumes",
            "ingredients": ["carotte", "poireau", "courgette", "céleri"],
            "calories": 120,
            "difficulte": "Facile",
            "temps": "30 min"
        },
        {
            "nom": "Salade de thon",
            "ingredients": ["thon", "salade_verte", "tomate", "concombre", "oignon"],
            "calories": 220,
            "difficulte": "Facile",
            "temps": "15 min"
        }
    ],
    "Hyperprotéiné": [
        {
            "nom": "Steak haché et blancs d'oeufs",
            "ingredients": ["steak_hache", "blancs_oeufs", "épinards"],
            "calories": 380,
            "difficulte": "Facile",
            "temps": "15 min"
        },
        {
            "nom": "Dos de cabillaud et lentilles",
            "ingredients": ["poisson_blanc", "lentilles", "oignon"],
            "calories": 420,
            "difficulte": "Moyenne",
            "temps": "30 min"
        }
    ],
    "Standard": [
        {
            "nom": "Salade de poulet au quinoa",
            "ingredients": ["poulet", "quinoa", "concombre", "tomate"],
            "calories": 350,
            "difficulte": "Facile",
            "temps": "25 min"
        },
        {
            "nom": "Pâtes complètes à la sauce tomate",
            "ingredients": ["pates_completes", "tomate", "oignon", "ail", "basilic"],
            "calories": 380,
            "difficulte": "Facile",
            "temps": "20 min"
        }
    ]
}

# ============================================================================
# DIET SEARCH TERMS - For API recipe searches
# ============================================================================

DIET_SEARCH_TERMS = {
    "proteine": ["chicken", "beef", "fish", "turkey", "egg"],
    "proteine_maigre": ["chicken breast", "white fish", "turkey"],
    "proteine_grasse": ["salmon", "duck", "lamb"],
    "legume": ["vegetable", "broccoli", "spinach", "carrot", "tomato"],
    "fruit": ["fruit", "apple", "banana", "orange", "berry"],
    "feculent": ["rice", "pasta", "potato", "quinoa"],
    "legumineuse": ["lentil", "chickpea", "bean"],
    "fast_food": ["burger", "fries", "pizza"],
    "confiserie": ["chocolate", "candy", "sweet"],
    "sauce": ["sauce", "dressing"],
    "laitage": ["yogurt", "cheese", "milk"],
    "boisson": ["drink", "water", "juice"],
    "boisson_sucree": ["soda", "cola", "lemonade"]
}

# ============================================================================
# RECIPE SUGGESTIONS - General recipes (for backward compatibility)
# ============================================================================

RECIPE_SUGGESTIONS = [
    {
        "nom": "Salade de poulet au quinoa",
        "ingredients": ["poulet", "quinoa", "concombre", "tomate"],
        "calories": 350,
        "difficulte": "Facile",
        "image": "https://www.themealdb.com/images/category/quinoa.png"
    },
    {
        "nom": "Omelette aux fines herbes",
        "ingredients": ["oeufs", "persil", "ciboulette", "sel"],
        "calories": 250,
        "difficulte": "Très Facile",
        "image": "https://www.themealdb.com/images/category/omelette.png"
    },
    {
        "nom": "Soupe de légumes maison",
        "ingredients": ["carotte", "poireau", "courgette", "pomme_de_terre"],
        "calories": 180,
        "difficulte": "Facile",
        "image": "https://www.themealdb.com/images/category/soup.png"
    }
]

# ============================================================================
# UTILITY FUNCTIONS
# ============================================================================

def analyze_dish(dish_name):
    """Analyse spéciale pour les plats composés"""
    if dish_name not in NUTRITION_DATA:
        return None
    
    dish = NUTRITION_DATA[dish_name]
    if dish.get("categorie") == "plat_compose":
        return {
            "type": "plat_compose",
            "nom": dish_name,
            "description": dish.get("description", ""),
            "fourchette_calorique": dish.get("fourchette_calorique", [300, 500]),
            "bienfaits": dish.get("bienfaits", ""),
            "message": f"🍽️ {dish.get('description', dish_name)} - {dish.get('bienfaits', '')}"
        }
    return None