@echo off
setlocal enabledelayedexpansion
set PHP_PATH=C:\laragon\bin\php\php-8.3.26-Win32-vs16-x64
set PATH=!PHP_PATH!;!PATH!

cd /d "%~dp0"
php artisan serve --host=127.0.0.1 --port=8000
pause
