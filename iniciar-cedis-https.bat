@echo off
title CEDIS CDMX - Servidor Local y Tunel HTTPS
cd /d "%~dp0"
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0iniciar-cedis-https.ps1"
if errorlevel 1 (
    echo.
    echo [ERROR] No se pudo iniciar correctamente. Revisa los mensajes anteriores.
    pause
)
