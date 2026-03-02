@echo off
REM Start WANNASNI Chat Service (Ollama-based)
REM Runs on http://localhost:8002
setlocal enabledelayedexpansion

echo ============================================
echo  WANNASNI Chat Service (Ollama)
echo ============================================

REM Kill existing processes on port 8002
for /f "tokens=5" %%a in ('netstat -aon ^| findstr :8002') do taskkill /pid %%a /f 2>nul

REM Check if Ollama is running
ollama list >nul 2>&1
if errorlevel 1 (
    echo [INFO] Starting Ollama server...
    start /B ollama serve
    timeout /t 3 /nobreak >nul
)

REM Set encoding for Windows console
set PYTHONIOENCODING=utf-8

echo [INFO] Starting chat service on port 8002...
echo [INFO] The service will auto-pull a model on first run (~1GB download)
echo.

C:\Users\azizm\OneDrive\Desktop\my_project\.venv\Scripts\python.exe python\chat_service.py

pause
