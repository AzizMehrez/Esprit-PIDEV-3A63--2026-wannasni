from fastapi import FastAPI, HTTPException, File, UploadFile
import pandas as pd
from sklearn.neighbors import NearestNeighbors
from sklearn.preprocessing import LabelEncoder
import numpy as np
import pytesseract
from PIL import Image
import io
import re
import os

app = FastAPI()

# Configuration de Tesseract pour Windows
# On cherche tesseract dans les dossiers d'installation par défaut
tesseract_paths = [
    r'C:\Program Files\Tesseract-OCR\tesseract.exe',
    r'C:\Program Files (x86)\Tesseract-OCR\tesseract.exe',
    os.path.join(os.environ.get('USERPROFILE', ''), r'AppData\Local\Tesseract-OCR\tesseract.exe'),
    os.path.join(os.environ.get('USERPROFILE', ''), r'AppData\Local\Programs\Tesseract-OCR\tesseract.exe'),
    r'C:\Tesseract-OCR\tesseract.exe',
    r'C:\Users\ayadi\anaconda3\Library\bin\tesseract.exe'
]

for path in tesseract_paths:
    if os.path.exists(path):
        pytesseract.pytesseract.tesseract_cmd = path
        break


# Simulation d'une base de données de médicaments
# Caractéristiques : classe_therapeutique, molecule, prix, restriction_age
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
    
    # Gastro-intestinaux (Estomac / RGO)
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

# Préparation des données pour KNN
le_classe = LabelEncoder()
df['classe_encoded'] = le_classe.fit_transform(df['classe'])

# On utilise la classe thérapeutique et senior_friendly pour trouver des alternatives
X = df[['classe_encoded', 'senior_friendly']].values

# Entraînement du modèle KNN
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
    
    # On exclut le médicament lui-même de la recommandation
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
        # Lecture de l'image
        contents = await file.read()
        image = Image.open(io.BytesIO(contents))
        
        # Extraction du texte via OCR
        try:
            text = pytesseract.image_to_string(image)
        except Exception as e:
            # Si le binaire tesseract n'est pas configuré
            if "tesseract_cmd" in str(e) or "not found" in str(e).lower():
                raise HTTPException(status_code=500, detail="Moteur OCR (Tesseract) non configuré sur le serveur.")
            raise HTTPException(status_code=500, detail=f"Erreur OCR : {str(e)}")

        # Nettoyage et recherche du nom du médicament dans le texte extrait
        text = text.lower()
        found_nom = None
        
        for nom in df['nom'].tolist():
            if nom.lower() in text:
                found_nom = nom
                break
        
        if not found_nom:
            # Recherche par mot-clé simple si le nom exact n'est pas trouvé
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

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=8080)
