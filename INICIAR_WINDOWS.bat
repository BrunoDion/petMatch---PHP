@echo off
setlocal
cd /d "%~dp0"
set "PHP_CMD=php"
where php >nul 2>nul
if errorlevel 1 (
  if exist "C:\xampp\php\php.exe" (
    set "PHP_CMD=C:\xampp\php\php.exe"
  ) else (
    echo [ERRO] PHP nao encontrado no PATH nem em C:\xampp\php\php.exe.
    echo Consulte LEIA_PRIMEIRO.md.
    pause
    exit /b 1
  )
)
if not exist ".env" (
  copy ".env.example" ".env" >nul
  echo [ATENCAO] Arquivo .env foi criado. Configure DB_NAME, DB_USER e DB_PASSWORD antes de continuar.
  notepad ".env"
)
echo.
echo ================================
echo   PETMATCH PHP + OLLAMA LOCAL
echo ================================
echo Abra no navegador: http://127.0.0.1:8000
 echo Para parar, pressione Ctrl+C.
echo.
"%PHP_CMD%" -S 127.0.0.1:8000 -t public
pause
