from fastapi import FastAPI, HTTPException, File, UploadFile
import pandas as pd
from sklearn.neighbors import NearestNeighbors
from sklearn.preprocessing import LabelEncoder
import numpy as np
from PIL import Image
import io
import re
import os

app = FastAPI()

# Initialisation du moteur OCR (EasyOCR - aucun binaire externe requis)
# Le modèle est téléchargé automatiquement au premier démarrage (~85 MB)
try:
    import easyocr
    _ocr_reader = easyocr.Reader(['fr', 'en'], gpu=False, verbose=False)
except Exception:
    _ocr_reader = None

# Base de données de médicaments pour le modèle KNN
data = [
    # Analgésiques / Antipyrétiques (Paracétamol)
    {"nom": "Doliprane", "classe": "Analgésique", "molecule": "Paracétamol", "prix": 3.500, "senior_friendly": 1, "indication": "Douleurs et fièvre"},
    {"nom": "Efferalgan", "classe": "Analgésique", "molecule": "Paracétamol", "prix": 3.200, "senior_friendly": 1, "indication": "Douleurs et fièvre"},
    {"nom": "Dafalgan", "classe": "Analgésique", "molecule": "Paracétamol", "prix": 3.800, "senior_friendly": 1, "indication": "Douleurs et fièvre"},
    {"nom": "Adol", "classe": "Analgésique", "molecule": "Paracétamol", "prix": 2.900, "senior_friendly": 1, "indication": "Douleurs et fièvre"},
    {"nom": "Panadol", "classe": "Analgésique", "molecule": "Paracétamol", "prix": 4.500, "senior_friendly": 1, "indication": "Douleurs et fièvre"},
    # Anti-inflammatoires (AINS)
    {"nom": "Advil", "classe": "AINS", "molecule": "Ibuprofène", "prix": 4.500, "senior_friendly": 0, "indication": "Douleurs, fièvre et inflammation"},
    {"nom": "Nurofen", "classe": "AINS", "molecule": "Ibuprofène", "prix": 4.200, "senior_friendly": 0, "indication": "Douleurs, fièvre et inflammation"},
    {"nom": "Upfen", "classe": "AINS", "molecule": "Ibuprofène", "prix": 3.800, "senior_friendly": 0, "indication": "Douleurs, fièvre et inflammation"},
    {"nom": "Antiflam", "classe": "AINS", "molecule": "Ibuprofène", "prix": 3.500, "senior_friendly": 0, "indication": "Douleurs, fièvre et inflammation"},
    {"nom": "Voltarène", "classe": "AINS", "molecule": "Diclofenac", "prix": 8.500, "senior_friendly": 0, "indication": "Rhumatisme et douleurs intenses"},
    {"nom": "Cataflam", "classe": "AINS", "molecule": "Diclofenac", "prix": 7.800, "senior_friendly": 0, "indication": "Rhumatisme et douleurs intenses"},
    # Antibiotiques
    {"nom": "Amoxicilline", "classe": "Antibiotique", "molecule": "Amoxicilline", "prix": 6.800, "senior_friendly": 1, "indication": "Infections bactériennes"},
    {"nom": "Augmentin", "classe": "Antibiotique", "molecule": "Amoxicilline/Acide Clavulanique", "prix": 18.500, "senior_friendly": 1, "indication": "Infections bactériennes sévères"},
    {"nom": "Clamoxyl", "classe": "Antibiotique", "molecule": "Amoxicilline", "prix": 7.500, "senior_friendly": 1, "indication": "Infections bactériennes"},
    {"nom": "Curam", "classe": "Antibiotique", "molecule": "Amoxicilline/Acide Clavulanique", "prix": 17.200, "senior_friendly": 1, "indication": "Infections bactériennes sévères"},
    {"nom": "Zinnat", "classe": "Antibiotique", "molecule": "Céfuroxime", "prix": 22.000, "senior_friendly": 1, "indication": "Infections ORL et respiratoires"},
    {"nom": "Oroken", "classe": "Antibiotique", "molecule": "Céfixime", "prix": 24.500, "senior_friendly": 1, "indication": "Infections urinaires et ORL"},
    # Antispasmodiques
    {"nom": "Spasfon", "classe": "Antispasmodique", "molecule": "Phloroglucinol", "prix": 4.800, "senior_friendly": 1, "indication": "Douleurs abdominales et contractions"},
    {"nom": "Meteospasmyl", "classe": "Antispasmodique", "molecule": "Phloroglucinol/Siméticone", "prix": 6.500, "senior_friendly": 1, "indication": "Ballonnements et douleurs digestives"},
    {"nom": "Duspatalin", "classe": "Antispasmodique", "molecule": "Mébévérine", "prix": 12.800, "senior_friendly": 1, "indication": "Troubles du transit et colopathie"},
    # Gastro-intestinaux
    {"nom": "Mopral", "classe": "Gastro-entérologie", "molecule": "Oméprazole", "prix": 14.500, "senior_friendly": 1, "indication": "Gastrite et reflux gastrique"},
    {"nom": "Zoltum", "classe": "Gastro-entérologie", "molecule": "Oméprazole", "prix": 13.800, "senior_friendly": 1, "indication": "Gastrite et reflux gastrique"},
    {"nom": "Inexium", "classe": "Gastro-entérologie", "molecule": "Ésoméprazole", "prix": 28.500, "senior_friendly": 1, "indication": "Ulcères et reflux sévère"},
    {"nom": "Gaviscon", "classe": "Gastro-entérologie", "molecule": "Alginate de sodium", "prix": 7.200, "senior_friendly": 1, "indication": "Aigreurs d'estomac"},
    # Cardiovasculaires / Hypertension
    {"nom": "Amlor", "classe": "Cardiologie", "molecule": "Amlodipine", "prix": 15.500, "senior_friendly": 1, "indication": "Hypertension et angine de poitrine"},
    {"nom": "Tenormin", "classe": "Cardiologie", "molecule": "Aténolol", "prix": 9.800, "senior_friendly": 1, "indication": "Hypertension et troubles cardiaques"},
    {"nom": "Tritace", "classe": "Cardiologie", "molecule": "Ramipril", "prix": 18.200, "senior_friendly": 1, "indication": "Hypertension et insuffisance cardiaque"},
    {"nom": "Co-Tritace", "classe": "Cardiologie", "molecule": "Ramipril/Hydrochlorothiazide", "prix": 22.500, "senior_friendly": 1, "indication": "Hypertension (traitement combiné)"},
    {"nom": "Lasilix", "classe": "Cardiologie", "molecule": "Furosémide", "prix": 4.500, "senior_friendly": 1, "indication": "Œdèmes et hypertension"},
    # Diabète
    {"nom": "Glucophage", "classe": "Diabétologie", "molecule": "Metformine", "prix": 8.500, "senior_friendly": 1, "indication": "Diabète de type 2"},
    {"nom": "Stagid", "classe": "Diabétologie", "molecule": "Metformine", "prix": 7.800, "senior_friendly": 1, "indication": "Diabète de type 2"},
    {"nom": "Diamicron", "classe": "Diabétologie", "molecule": "Gliclazide", "prix": 14.200, "senior_friendly": 1, "indication": "Diabète (stimule l'insuline)"},
    {"nom": "Amaryl", "classe": "Diabétologie", "molecule": "Glimépiride", "prix": 19.500, "senior_friendly": 1, "indication": "Diabète de type 2"},
    # Allergies / Antihistaminiques
    {"nom": "Aerius", "classe": "Antiallergique", "molecule": "Desloratadine", "prix": 11.500, "senior_friendly": 1, "indication": "Rhinite allergique et urticaire"},
    {"nom": "Deslor", "classe": "Antiallergique", "molecule": "Desloratadine", "prix": 8.900, "senior_friendly": 1, "indication": "Rhinite allergique et urticaire"},
    {"nom": "Xyzall", "classe": "Antiallergique", "molecule": "Lévocétirizine", "prix": 12.200, "senior_friendly": 1, "indication": "Rhinite allergique et conjonctivite"},
    {"nom": "Zyrtec", "classe": "Antiallergique", "molecule": "Cétirizine", "prix": 10.500, "senior_friendly": 1, "indication": "Rhinite allergique"},
    # Respiration / Asthme
    {"nom": "Ventoline", "classe": "Pneumologie", "molecule": "Salbutamol", "prix": 6.500, "senior_friendly": 1, "indication": "Asthme et gêne respiratoire"},
    {"nom": "Seretide", "classe": "Pneumologie", "molecule": "Salmétérol/Fluticasone", "prix": 45.000, "senior_friendly": 1, "indication": "Traitement de fond de l'asthme"},
    {"nom": "Symbicort", "classe": "Pneumologie", "molecule": "Budésonide/Formotérol", "prix": 48.500, "senior_friendly": 1, "indication": "Asthme et BPCO"},
]

df = pd.DataFrame(data)

le_classe = LabelEncoder()
df['classe_encoded'] = le_classe.fit_transform(df['classe'])

X = df[['classe_encoded', 'senior_friendly']].values

knn = NearestNeighbors(n_neighbors=3, metric='euclidean')
knn.fit(X)


@app.get("/alternatives")
def get_alternatives(nom: str):
    nom = nom.strip()
    idx_list = df.index[df['nom'].str.lower() == nom.lower()].tolist()

    if not idx_list:
        raise HTTPException(status_code=404, detail="Médicament non trouvé dans la base")

    idx = idx_list[0]
    distances, indices = knn.kneighbors([X[idx]])

    recommendations = []
    for i in indices[0]:
        if df.iloc[i]['nom'].lower() != nom.lower():
            recommendations.append(df.iloc[i].to_dict())

    return {
        "original": df.iloc[idx]['nom'],
        "alternatives": recommendations
    }


@app.post("/analyze-image")
async def analyze_image(file: UploadFile = File(...)):
    try:
        contents = await file.read()

        if _ocr_reader is None:
            raise HTTPException(status_code=500, detail="Moteur OCR non disponible. Redémarrez le service Python.")

        try:
            # easyocr accepts raw bytes directly
            results = _ocr_reader.readtext(contents, detail=0)
            text = ' '.join(results)
        except Exception as e:
            raise HTTPException(status_code=500, detail=f"Erreur OCR : {str(e)}")

        text = text.lower()
        found_nom = None

        for nom in df['nom'].tolist():
            if nom.lower() in text:
                found_nom = nom
                break

        if not found_nom:
            words = re.findall(r'\b\w+\b', text)
            for word in words:
                if len(word) > 3:
                    matches = df[df['nom'].str.lower().str.contains(word)]
                    if not matches.empty:
                        found_nom = matches.iloc[0]['nom']
                        break

        if not found_nom:
            raise HTTPException(status_code=404, detail="Aucun médicament reconnu sur l'image")

        return get_alternatives(found_nom)

    except Exception as e:
        if isinstance(e, HTTPException):
            raise e
        raise HTTPException(status_code=500, detail=str(e))


# ─────────────────────────────────────────────────────────────────────────────
# Chat Routing Endpoint
# Detects language & navigation intent from a chat message.
# Used by Symfony proxy as a first-pass classifier before calling OpenRouter.
# ─────────────────────────────────────────────────────────────────────────────
from pydantic import BaseModel
from typing import Optional

class ChatRouteRequest(BaseModel):
    message: str
    locale: Optional[str] = "fr"


def _detect_lang(text: str) -> str:
    """Detect language: en / fr / ar / tn-ar / tn-latn"""
    tun_latin = ['chnowa','kifech','nheb','wqtesh','bahi','barcha','moch','3andi',
                 'ya kho','roh l','hedha','famma','yezzi','chwaya','n7eb','inscri-ni',
                 'beh t3addi','wadini','chno famma']
    tl = text.lower()
    if any(w in tl for w in tun_latin):
        return 'tn-latn'
    # Arabic script
    if any('\u0600' <= c <= '\u06FF' for c in text):
        tn_markers = ['\u0634\u0646\u0648\u0627','\u0643\u064a\u0641\u0627\u0634','\u0646\u062d\u0628','\u0628\u0627\u0647\u064a','\u0628\u0631\u0634\u0627',
                      '\u0645\u0648\u0634','\u064a\u0632\u064a','\u062e\u0630\u0646\u064a','\u0631\u0648\u062d','\u0641\u0627\u0645\u0629','\u0634\u0648\u064a\u0629',
                      '\u062a\u0627\u0648','\u0647\u0630\u0627\u0643\u0627','\u0627\u062f\u064a']
        if any(m in text for m in tn_markers):
            return 'tn-ar'
        return 'ar'
    fr_words = ['je','le','la','les','des','mon','mes','bonjour','salut','comment','quoi','voudrais','activit','sant','merci']
    en_words = ['i','the','my','me','you','what','how','can','show','help','take me','go to','open','navigate','hello','thank']
    fr_score = sum(1 for w in fr_words if w in tl)
    en_score = sum(1 for w in en_words if w in tl)
    return 'fr' if fr_score >= en_score else 'en'


def _detect_navigation(text: str, locale: str) -> Optional[str]:
    """If message is a navigation command, return the target relative URL."""
    tl = text.lower()
    nav_triggers = ['take me to','go to','navigate to','open page','emmène','aller à','\u062e\u0630\u0646\u064a','\u0631\u0648\u062d',
                    '\u0641\u062a\u062d\u0644\u064a','\u0627\u0645\u0634\u064a','roh l','khothni','fta7li','emchi','beh t3addi','wadini']
    if not any(t in tl for t in nav_triggers):
        return None

    page_map = [
        (['dashboard','main page','page principale','\u0644\u0648\u062d\u0629','\u0627\u0644\u0631\u0626\u064a\u0633\u064a\u0629','dash','\u062f\u0627\u0634\u0628\u0648\u0631\u062f'], 'dashboard'),
        (['profile','profil','\u0645\u0644\u0641','\u0628\u0631\u0648\u0641\u064a\u0644'], 'profile'),
        (['activit','\u0646\u0634\u0627\u0637'], 'my-activities'),
        (['health','sant','\u0635\u062d','journal'], 'health/journal'),
        (['nutrition','\u062a\u063a\u0630\u064a\u0629','r\u00e9gime'], 'nutrition'),
        (['service','\u062e\u062f\u0645'], 'my-services'),
        (['treatment','traitement','\u0639\u0644\u0627\u062c','\u062f\u0648\u0627\u0621'], 'treatment'),
        (['loyalty','fid\u00e9lit\u00e9','\u0648\u0641\u0627\u0621'], 'loyalty'),
        (['message','\u0631\u0633\u0627\u0626\u0644'], 'networking/messages'),
        (['networking','\u062a\u0648\u0627\u0635\u0644','\u0631\u0633\u0627\u0626\u0644'], 'networking'),
    ]
    for keywords, path in page_map:
        if any(k in tl for k in keywords):
            return f'/{locale}/{path}'
    return f'/{locale}/dashboard'  # fallback to dashboard


@app.post("/chat-route")
def chat_route(req: ChatRouteRequest):
    """
    First-pass classifier for chat messages.
    Returns:
      - handled: whether ML handled it (True = navigation, language hint forced)
      - language: detected language code
      - intent: 'navigate' | 'chat'
      - navigate_url: relative URL if intent is navigate
      - confidence: 0.0-1.0
    """
    lang = _detect_lang(req.message)
    nav_url = _detect_navigation(req.message, req.locale)

    if nav_url:
        return {
            "handled": True,
            "language": lang,
            "intent": "navigate",
            "navigate_url": nav_url,
            "confidence": 0.92
        }

    return {
        "handled": False,
        "language": lang,
        "intent": "chat",
        "navigate_url": None,
        "confidence": 0.0
    }


if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=8090)
