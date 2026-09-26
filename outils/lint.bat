@echo off
REM Controle de compatibilite en deux passes, sous une version precise de PHP.
REM
REM   lint.bat [chemin\vers\php.exe] [racine-src] [prefixe-namespace] [version]
REM
REM Exemple, pour un projet en 7.0 :
REM   outils\lint.bat C:\wamp64\bin\php\php7.0.33\php.exe src App\Paiement 7.0
REM
REM VERIFIEZ LA VERSION AFFICHEE. Lancer ce script avec PHP 8 sur un projet
REM destine a PHP 7.0 ne prouve rien du tout.

setlocal enabledelayedexpansion

set "PHP=%~1"
if "%PHP%"=="" set "PHP=php"

set "SRC=%~2"
if "%SRC%"=="" set "SRC=%~dp0..\src"

set "PREFIXE=%~3"
if "%PREFIXE%"=="" set "PREFIXE=PhpCleanCode"

set "CIBLE=%~4"
if "%CIBLE%"=="" set "CIBLE=7.4"

where "%PHP%" >nul 2>&1
if errorlevel 1 if not exist "%PHP%" (
    echo Binaire introuvable : %PHP%
    echo.
    echo Indiquez le chemin en argument, par exemple :
    echo   outils\lint.bat C:\wamp64\bin\php\php7.0.33\php.exe
    exit /b 2
)

echo === Passe 1 : syntaxe ===
"%PHP%" -r "echo 'PHP ', PHP_VERSION, PHP_EOL;"
echo.

set /a NB=0
set /a KO=0

for /r "%SRC%" %%F in (*.php) do (
    set /a NB+=1
    "%PHP%" -l "%%F" >nul 2>&1
    if errorlevel 1 (
        set /a KO+=1
        echo ERREUR : %%F
        "%PHP%" -l "%%F"
    )
)

echo !NB! fichiers analyses, !KO! en erreur de syntaxe.
echo.

if !KO! gtr 0 exit /b 1

echo === Passe 2 : liaison des classes ===
echo (visibilite, signatures, interfaces -- ce que "php -l" ne voit pas)
echo.
"%PHP%" "%~dp0compat.php" "%SRC%" "%PREFIXE%" "%CIBLE%"

exit /b %errorlevel%

