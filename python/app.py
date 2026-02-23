"""
WANNASNI Nutrition ML API
=========================
FastAPI service for food detection, nutritional analysis, diet compliance,
and recipe suggestions.

Endpoints:
- POST /analyze/step1-detect     : Detect foods in an image
- POST /analyze/step2-nutrition   : Calculate nutrition for detected foods
- POST /analyze/step3-recipes     : Get diet-based recipe suggestions
- POST /analyze/step4-alerts      : Generate final report with alerts
- POST /analyze/full-analysis     : Complete analysis in one call
"""

import sys
import os
import io

# Ensure UTF-8 encoding for Windows console compatibility
# This prevents 'charmap' codec errors when printing Unicode characters
if sys.platform == 'win32':
    # Set encoding for stdout/stderr to UTF-8
    sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')
    sys.stderr = io.TextIOWrapper(sys.stderr.buffer, encoding='utf-8', errors='replace')

# Add project path to PYTHONPATH
project_path = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
if project_path not in sys.path:
    sys.path.append(project_path)

from fastapi import FastAPI, UploadFile, File, Form
import shutil
from ml.full_nutrition_analyzer import FullNutritionAnalyzer

app = FastAPI(title="WANNASNI Nutrition ML", version="2.0")
analyzer = FullNutritionAnalyzer()
import logging
logger = logging.getLogger("app")

# Temporary upload directory
UPLOAD_DIR = "temp_uploads"
os.makedirs(UPLOAD_DIR, exist_ok=True)


@app.get("/")
def read_root():
    return {"status": "online", "model": "WANNASNI Nutrition ML v2.0"}


@app.post("/analyze/step1-detect")
async def step1_detect(file: UploadFile = File(...)):
    """
    Step 1: Detect foods in an uploaded image.
    STRATEGY: 100% local ML (similarity + CNN). Gemini désactivé.
    """
    import uuid, traceback
    safe_name = file.filename if file.filename else f"upload_{uuid.uuid4().hex}.jpg"
    # Sanitize: keep only safe filename chars
    safe_name = os.path.basename(safe_name) or f"upload_{uuid.uuid4().hex}.jpg"
    file_path = os.path.join(UPLOAD_DIR, safe_name)
    try:
        # Save uploaded file
        with open(file_path, "wb") as buffer:
            shutil.copyfileobj(file.file, buffer)

        # Détection 100% locale (similarité + CNN)
        result = analyzer.detect_only(file_path)

        if not result.get('detected', False):
            return {
                "status": "not_detected",
                "message": result.get(
                    "message",
                    "Aucun aliment detecte. Reprenez la photo avec un meilleur eclairage."
                ),
                "foods": [],
                "detection_source": "local_ml"
            }

        return {
            "status": "success",
            "foods": result.get("foods", []),
            "detection_source": "local_ml"
        }

    except Exception as e:
        error_details = traceback.format_exc()
        # Don't print to stdout with potentially problematic encodings
        # logger.error(f"Step 1 Detection Error: {error_details}")
        return {
            "status": "error",
            "message": str(e),
            "traceback": error_details if logger.isEnabledFor(logging.DEBUG) else ""
        }
    finally:
        if os.path.exists(file_path):
            os.remove(file_path)


@app.post("/analyze/step2-nutrition")
async def step2_nutrition(foods: list = Form(...), regime: str = Form(...)):
    """Step 2: Calculate nutrition for detected foods."""
    import json
    try:
        foods_list = json.loads(foods[0] if isinstance(foods, list) else foods)

        # If no foods detected (empty list), return empty result
        if not foods_list:
            return {
                "status": "success",
                "aliments": [],
                "total_nutrition": {"calories": 0},
                "total_calories": 0,
                "compliance": {"conforme": True, "raisons": []},
                "message": "Aucun aliment a analyser"
            }

        results = analyzer.calculate_nutrition(foods_list, regime)

        # Backward compatibility for frontend
        if 'total_nutrition' in results:
            results['total_calories'] = results['total_nutrition'].get('calories', 0)

        return {"status": "success", **results}
    except Exception as e:
        import traceback
        return {
            "status": "error",
            "message": f"Step 2 Error: {str(e)}",
            "traceback": traceback.format_exc()
        }


@app.post("/analyze/step3-recipes")
async def step3_recipes(
    total_calories: float = Form(...),
    daily_limit: int = Form(...),
    consumed_today: int = Form(...),
    regime: str = Form("Standard")
):
    """
    Step 3: Get diet-based recipe suggestions.
    Recipes are filtered by:
    - Remaining calories
    - Recommended food categories from the prescribed diet
    - Avoiding forbidden food categories
    """
    try:
        total_nutrition = {"calories": total_calories}
        recipes = analyzer.get_recipes(
            total_nutrition, daily_limit, consumed_today, regime
        )
        return {"status": "success", "suggestions": recipes}
    except Exception as e:
        return {"status": "error", "message": f"Step 3 Error: {str(e)}"}


@app.post("/analyze/step4-alerts")
async def step4_alerts(
    total_calories: float = Form(...),
    compliance_json: str = Form(...),
    daily_limit: int = Form(...),
    consumed_today: int = Form(...)
):
    """Step 4: Generate final report with alerts."""
    import json
    try:
        compliance = json.loads(compliance_json)
        total_nutrition = {
            "calories": total_calories,
            "proteines": 0,
            "glucides": 0,
            "lipides": 0,
            "sel": 0,
            "fibres": 0,
            "sucres": 0
        }
        report = analyzer.generate_final_report(
            total_nutrition, compliance, daily_limit, consumed_today
        )

        return {"status": "success", **report}
    except Exception as e:
        return {"status": "error", "message": f"Step 4 Error: {str(e)}"}


@app.post("/analyze/full-analysis")
async def full_analysis(
    file: UploadFile = File(...),
    regime: str = Form(...),
    daily_limit: int = Form(...),
    consumed_today: int = Form(...)
):
    """
    Complete meal analysis in one call.
    Combines detection, nutrition calculation, diet compliance, and recipe suggestions.
    """
    file_path = os.path.join(UPLOAD_DIR, file.filename)
    with open(file_path, "wb") as buffer:
        shutil.copyfileobj(file.file, buffer)

    try:
        analysis = analyzer.analyze_meal(file_path, regime, daily_limit, consumed_today)
        return {"status": analysis.get("status", "success"), **analysis}
    except Exception as e:
        import traceback
        return {
            "status": "error",
            "message": str(e),
            "traceback": traceback.format_exc()
        }
    finally:
        if os.path.exists(file_path):
            os.remove(file_path)


@app.post("/analyze/meal-reminders")
async def meal_reminders(
    regime: str = Form("Standard"),
    repas_par_jour: int = Form(3),
    repas_consommes: int = Form(0),
    calories_consommees: int = Form(0),
    calories_limite: int = Form(2000),
    aliments_recommandes: str = Form("[]"),
    aliments_interdits: str = Form("[]")
):
    """
    Generate intelligent meal reminder notifications.
    Uses the ML analyzer to determine:
    - How many meals are remaining
    - When to eat next
    - What to eat based on the regime
    - Calorie distribution for remaining meals
    """
    import json
    try:
        recommandes = json.loads(aliments_recommandes)
        interdits = json.loads(aliments_interdits)

        result = analyzer.generate_meal_reminders(
            regime_type=regime,
            repas_par_jour=repas_par_jour,
            repas_consommes=repas_consommes,
            calories_consommees=calories_consommees,
            calories_limite=calories_limite,
            aliments_recommandes=recommandes,
            aliments_interdits=interdits
        )
        return result
    except Exception as e:
        import traceback
        return {
            "status": "error",
            "message": f"Meal reminders error: {str(e)}",
            "traceback": traceback.format_exc()
        }


@app.post("/analyze/advanced-analysis")
async def advanced_analysis(
    file: UploadFile = File(...),
    regime: str = Form("Standard"),
    daily_limit: int = Form(2000),
    consumed_today: int = Form(0),
    poids: float = Form(None),
    taille: float = Form(None),
    age: int = Form(None)
):
    """
    Advanced meal analysis with all ML features:
    Multi-food detection, portion estimation, cooking method, risk scoring.
    """
    import uuid, traceback
    safe_name = file.filename if file.filename else f"upload_{uuid.uuid4().hex}.jpg"
    safe_name = os.path.basename(safe_name) or f"upload_{uuid.uuid4().hex}.jpg"
    file_path = os.path.join(UPLOAD_DIR, safe_name)
    try:
        with open(file_path, "wb") as buffer:
            shutil.copyfileobj(file.file, buffer)

        result = analyzer.analyze_meal_advanced(
            image_path=file_path,
            regime_prescrit=regime,
            daily_limit=daily_limit,
            consumed_today=consumed_today,
            poids=poids if poids and poids > 0 else None,
            taille=taille if taille and taille > 0 else None,
            age=age if age and age > 0 else None
        )
        return result
    except Exception as e:
        import traceback
        return {
            "status": "error",
            "message": str(e),
            "traceback": traceback.format_exc()
        }
    finally:
        if os.path.exists(file_path):
            os.remove(file_path)


@app.post("/analyze/trends")
async def food_trends(meal_history: str = Form(...)):
    """Analyze food trends over multiple days."""
    import json
    try:
        history = json.loads(meal_history)
        result = analyzer.analyze_food_trends(history)
        return result
    except Exception as e:
        import traceback
        return {"status": "error", "message": str(e), "traceback": traceback.format_exc()}


@app.post("/analyze/meal-rhythm")
async def meal_rhythm(
    meal_history: str = Form(...),
    repas_par_jour: int = Form(3)
):
    """Analyze meal timing and rhythm patterns."""
    import json
    try:
        history = json.loads(meal_history)
        result = analyzer.analyze_meal_rhythm(history, repas_par_jour)
        return result
    except Exception as e:
        import traceback
        return {"status": "error", "message": str(e), "traceback": traceback.format_exc()}


@app.post("/analyze/risk-score")
async def risk_score(
    poids: float = Form(None),
    taille: float = Form(None),
    age: int = Form(None),
    meal_history: str = Form("[]"),
    regime: str = Form("Standard"),
    daily_limit: int = Form(2000)
):
    """Calculate personalized nutritional risk score."""
    import json
    try:
        history = json.loads(meal_history) if meal_history else []
        result = analyzer.calculate_risk_score(
            poids=poids if poids and poids > 0 else None,
            taille=taille if taille and taille > 0 else None,
            age=age if age and age > 0 else None,
            meal_history=history if history else None,
            regime_type=regime,
            daily_limit=daily_limit
        )
        return result
    except Exception as e:
        import traceback
        return {"status": "error", "message": str(e), "traceback": traceback.format_exc()}


@app.post("/analyze/nutritionist-summary")
async def nutritionist_summary(
    meal_history: str = Form(...),
    regime: str = Form("Standard"),
    daily_limit: int = Form(2000),
    poids: float = Form(None),
    taille: float = Form(None),
    age: int = Form(None),
    aliments_recommandes: str = Form("[]"),
    aliments_interdits: str = Form("[]")
):
    """Generate comprehensive nutritionist summary report."""
    import json
    try:
        history = json.loads(meal_history)
        recommandes = json.loads(aliments_recommandes)
        interdits = json.loads(aliments_interdits)

        result = analyzer.generate_nutritionist_summary(
            meal_history=history,
            regime_type=regime,
            daily_limit=daily_limit,
            poids=poids if poids and poids > 0 else None,
            taille=taille if taille and taille > 0 else None,
            age=age if age and age > 0 else None,
            aliments_recommandes=recommandes,
            aliments_interdits=interdits
        )
        return result
    except Exception as e:
        import traceback
        return {"status": "error", "message": str(e), "traceback": traceback.format_exc()}


if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=8001)
