@echo off
rem Brings the office server in line with the code a git pull just brought
rem in. Nobody runs this by hand: the server's .git\hooks\post-merge calls
rem it after every pull that changes something. See .ai/rules/general.md.
rem
rem The built CSS and JS arrive with the pull (public\build is committed),
rem so the server needs no Node.

cd /d "%~dp0"

echo.
echo == Installing PHP packages
composer install --no-dev --optimize-autoloader --no-interaction || goto :failed

echo.
echo == Updating the database
php artisan migrate --force || goto :failed

echo.
echo == Caching config, views and events
php artisan config:cache || goto :failed
php artisan view:cache || goto :failed
php artisan event:cache || goto :failed

rem Never route:cache, and never optimize (which includes it): under the
rem /ldi-system subfolder a route cache turns the home page into a 405.
rem See .ai/rules/routes.md.
php artisan route:clear || goto :failed

echo.
echo == Updated. Open http://192.168.10.38/ldi-system/ to check.
exit /b 0

:failed
echo.
echo == STOPPED: the step above failed, and the site may be half updated.
echo == Fix it, then run deploy.bat by hand to finish.
exit /b 1
