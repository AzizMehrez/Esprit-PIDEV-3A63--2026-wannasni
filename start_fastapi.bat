@echo off
REM Start FastAPI with UTF-8 encoding enabled for Windows console
setlocal enabledelayedexpansion

REM Kill existing Python processes on port 8001
for /f "tokens=5" %%a in ('netstat -aon ^| findstr :8001') do taskkill /pid %%a /f 2>nul

REM Set encoding to UTF-8 for Python
set PYTHONIOENCODING=utf-8

REM Wait a moment for port to be freed
timeout /t 3 /nobreak

REM Start FastAPI - suppress TensorFlow warnings
set TF_CPP_MIN_LOG_LEVEL=2
C:\ProgramData\anaconda3\python.exe python\app.py

pause
