@echo off
REM ============================================================
REM  WANNASNI - Démarrage automatique du serveur Python ML
REM  Port: 8001 | FastAPI + Uvicorn
REM  Ce script est lancé automatiquement au démarrage de Windows
REM ============================================================

cd /d "%~dp0python"

REM Activer l'environnement virtuel
call venv\Scripts\activate.bat

REM Attendre 5 secondes (laisser le temps au réseau de s'initialiser)
timeout /t 5 /nobreak >nul

REM Démarrer le serveur en arrière-plan (minimisé)
start "WANNASNI ML Server" /MIN uvicorn app:app --host 0.0.0.0 --port 8001

echo Serveur Python ML démarré sur le port 8001
