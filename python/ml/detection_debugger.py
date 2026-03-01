"""
Detection Debugger - Trace et analyse les erreurs de détection
Permet d'identifier exactement où le système fait des erreurs
"""

import logging
import json
from datetime import datetime
from pathlib import Path

logger = logging.getLogger(__name__)


class DetectionDebugger:
    """
    Trace et enregistre toutes les étapes de la détection pour analyse.
    Permet d'identifier les patterns d'erreurs.
    """
    
    def __init__(self, enable_verbose=True):
        self.enable_verbose = enable_verbose
        self.detections = []
        self.errors = []
        self.warnings = []
        
    def log_detection_step(self, step_name, data, confidence=None, notes=""):
        """Enregistrer une étape de détection"""
        entry = {
            "timestamp": datetime.now().isoformat(),
            "step": step_name,
            "data": data,
            "confidence": confidence,
            "notes": notes
        }
        
        self.detections.append(entry)
        
        if self.enable_verbose:
            if confidence:
                logger.info(f"[{step_name}] {data} (conf: {confidence:.3f}) {notes}")
            else:
                logger.info(f"[{step_name}] {data} {notes}")
    
    def log_error(self, error_type, message, context=None):
        """Enregistrer une erreur de détection"""
        error_entry = {
            "timestamp": datetime.now().isoformat(),
            "type": error_type,
            "message": message,
            "context": context
        }
        
        self.errors.append(error_entry)
        logger.error(f"[ERROR] {error_type}: {message}")
    
    def log_warning(self, warning_type, message, context=None):
        """Enregistrer un avertissement"""
        warning_entry = {
            "timestamp": datetime.now().isoformat(),
            "type": warning_type,
            "message": message,
            "context": context
        }
        
        self.warnings.append(warning_entry)
        logger.warning(f"[WARN] {warning_type}: {message}")
    
    def analyze_detection_quality(self, detected_foods, ground_truth=None):
        """
        Analyser la qualité de la détection.
        
        Args:
            detected_foods: Liste des aliments détectés
            ground_truth: Aliments réels pour comparaison (optionnel)
        
        Returns:
            dict avec métriques de qualité
        """
        analysis = {
            "num_detected": len(detected_foods),
            "average_confidence": 0.0,
            "high_confidence_count": 0,
            "low_confidence_count": 0,
            "false_positives": [],
            "missing": [],
            "recommendations": []
        }
        
        if not detected_foods:
            analysis["recommendations"].append("Aucun aliment détecté - revoir les seuils")
            return analysis
        
        # Calculer statistiques de confiance
        confidences = [f.get('confiance', 0) for f in detected_foods]
        analysis["average_confidence"] = sum(confidences) / len(confidences)
        
        analysis["high_confidence_count"] = sum(1 for c in confidences if c >= 0.70)
        analysis["low_confidence_count"] = sum(1 for c in confidences if c < 0.50)
        
        # Comparer avec la vérité si disponible
        if ground_truth:
            detected_names = set(f['nom'] for f in detected_foods)
            true_names = set(ground_truth)
            
            analysis["false_positives"] = list(detected_names - true_names)
            analysis["missing"] = list(true_names - detected_names)
            
            if analysis["false_positives"]:
                analysis["recommendations"].append(
                    f"Faux positifs détectés: {analysis['false_positives']} - "
                    "Augmenter les seuils de confiance"
                )
            
            if analysis["missing"]:
                analysis["recommendations"].append(
                    f"Aliments manqués: {analysis['missing']} - "
                    "Baisser les seuils ou améliorer la similarité"
                )
        
        # Recommandations générales
        if analysis["low_confidence_count"] > 0:
            analysis["recommendations"].append(
                f"{analysis['low_confidence_count']} aliments avec confiance < 0.50 - "
                "Vérifier la qualité de l'image ou les seuils"
            )
        
        if analysis["average_confidence"] < 0.60:
            analysis["recommendations"].append(
                f"Confiance moyenne basse ({analysis['average_confidence']:.2f}) - "
                "Les seuils peuvent être trop stricts"
            )
        
        return analysis
    
    def get_report(self, image_path=None):
        """Générer un rapport de debugging"""
        report = {
            "image": image_path,
            "timestamp": datetime.now().isoformat(),
            "total_steps": len(self.detections),
            "total_errors": len(self.errors),
            "total_warnings": len(self.warnings),
            "steps": self.detections[-10:],  # Derniers 10 pas
            "errors": self.errors,
            "warnings": self.warnings[-5:]  # Derniers 5 avertissements
        }
        return report
    
    def save_report(self, filepath):
        """Sauvegarder le rapport en JSON"""
        report = self.get_report()
        with open(filepath, 'w', encoding='utf-8') as f:
            json.dump(report, f, ensure_ascii=False, indent=2)
        logger.info(f"Debug report saved: {filepath}")
    
    def reset(self):
        """Réinitialiser le debugger"""
        self.detections = []
        self.errors = []
        self.warnings = []


class DetectionValidator:
    """
    Valide les résultats de détection en comparant avec des patterns connus
    """
    
    # Patterns de faux positifs connus
    FALSE_POSITIVE_PATTERNS = {
        "fruit_avec_plat": {
            "description": "Fruit détecté avec un plat principal (peu plausible)",
            "check": lambda foods: any(
                f['categorie'] == 'fruit' for f in foods
            ) and any(
                'plat' in f['nom'].lower() for f in foods
            ),
            "recommendation": "Filtrer les fruits des plats principaux"
        },
        "trop_d_aliments": {
            "description": "Trop d'aliments détectés (> 5)",
            "check": lambda foods: len(foods) > 5,
            "recommendation": "Limiter à 5 aliments max et garder les plus confiants"
        },
        "confiance_basse_moyenne": {
            "description": "Plus de 50% des aliments ont confiance < 0.60",
            "check": lambda foods: sum(
                1 for f in foods if f.get('confiance', 0) < 0.60
            ) / max(len(foods), 1) > 0.5,
            "recommendation": "La détection générale est douteuse - image de faible qualité ?"
        },
        "deux_plats_complets": {
            "description": "Deux plats complets détectés (pizza + burger par ex)",
            "check": lambda foods: sum(
                1 for f in foods if any(
                    p in f['nom'].lower() for p in ['pizza', 'burger', 'pates', 'lasagne']
                )
            ) > 1,
            "recommendation": "Garder seulement le meilleur plat complet"
        }
    }
    
    @staticmethod
    def validate(foods, debugger=None):
        """
        Valider les résultats de détection.
        
        Returns:
            dict avec problèmes trouvés et recommandations
        """
        issues = []
        
        for pattern_name, pattern_config in DetectionValidator.FALSE_POSITIVE_PATTERNS.items():
            if pattern_config['check'](foods):
                issue = {
                    "pattern": pattern_name,
                    "description": pattern_config['description'],
                    "recommendation": pattern_config['recommendation']
                }
                issues.append(issue)
                
                if debugger:
                    debugger.log_warning(
                        pattern_name,
                        pattern_config['description'],
                        {"recommendation": pattern_config['recommendation']}
                    )
        
        return {
            "is_valid": len(issues) == 0,
            "issues": issues,
            "num_foods": len(foods)
        }
