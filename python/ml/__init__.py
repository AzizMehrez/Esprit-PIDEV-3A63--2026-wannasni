"""
WANNASNI Nutrition ML Module

Core components:
- FullNutritionAnalyzer: Main orchestrator using multi-layer detection
- ImageSimilarityMatcher: Deep learning feature matching (MobileNetV2)
- NutritionKnowledge: Food database with nutritional information
"""

from .full_nutrition_analyzer import FullNutritionAnalyzer
from .similarity_matcher import ImageSimilarityMatcher
from .nutrition_knowledge import NUTRITION_DATA

__all__ = ['FullNutritionAnalyzer', 'ImageSimilarityMatcher', 'NUTRITION_DATA']
__version__ = "2.0"
