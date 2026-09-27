@echo off
setlocal

set MYSQL_DIR=C:\Users\mamat\AppData\Local\DevTools\mysql
set NGINX_DIR=C:\Users\mamat\AppData\Local\DevTools\nginx
set PHP_DIR=C:\Users\mamat\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.2_Microsoft.Winget.Source_8wekyb3d8bbwe
set PROJECT_DIR=c:\Users\mamat\OneDrive\Attachments\Desktop\New-proj2-Multiple-ERP (1)\curr-proj2-Multiple-ERP

echo ==================================================
echo  Sayeon - Dev Environment Startup
echo ==================================================

echo.
echo [1/3] MySQL (port 3307)...
netstat -ano | findstr ":3307" | findstr "LISTENING" >nul
if %errorlevel%==0 (
    echo       Already running.
) else (
    echo       Starting...
    start "ERP MySQL" /min "%MYSQL_DIR%\bin\mysqld.exe" --defaults-file="%MYSQL_DIR%\my.ini"
    timeout /t 3 >nul
    netstat -ano | findstr ":3307" | findstr "LISTENING" >nul
    if %errorlevel%==0 (
        echo       Started OK.
    ) else (
        echo       WARNING: could not confirm MySQL is listening yet. Check the "ERP MySQL" window.
    )
)

echo.
echo [2/3] PHP-CGI workers (ports 9000-9003)...
netstat -ano | findstr ":9000" | findstr "LISTENING" >nul
if %errorlevel%==0 (
    echo       Already running.
) else (
    echo       Starting 4 workers...
    cd /d "%PROJECT_DIR%"
    start "ERP PHP Worker 9000" /min "%PHP_DIR%\php-cgi.exe" -b 127.0.0.1:9000
    start "ERP PHP Worker 9001" /min "%PHP_DIR%\php-cgi.exe" -b 127.0.0.1:9001
    start "ERP PHP Worker 9002" /min "%PHP_DIR%\php-cgi.exe" -b 127.0.0.1:9002
    start "ERP PHP Worker 9003" /min "%PHP_DIR%\php-cgi.exe" -b 127.0.0.1:9003
    timeout /t 2 >nul
    netstat -ano | findstr ":9000" | findstr "LISTENING" >nul
    if %errorlevel%==0 (
        echo       Started OK.
    ) else (
        echo       WARNING: could not confirm PHP workers are listening yet.
    )
)

echo.
echo [3/3] nginx (port 8080)...
netstat -ano | findstr ":8080" | findstr "LISTENING" >nul
if %errorlevel%==0 (
    echo       Already running.
) else (
    echo       Starting...
    start "ERP nginx" /min "%NGINX_DIR%\nginx.exe" -c conf/erp.conf -p "%NGINX_DIR%/"
    timeout /t 2 >nul
    netstat -ano | findstr ":8080" | findstr "LISTENING" >nul
    if %errorlevel%==0 (
        echo       Started OK.
    ) else (
        echo       WARNING: could not confirm nginx is listening yet. Check the "ERP nginx" window.
    )
)

echo.
echo Done. Open http://127.0.0.1:8080 in your browser.
echo (nginx + 4 PHP workers now handle real concurrent requests, instead of
echo  queuing one at a time like the old single-threaded dev server did.
echo  Several minimized windows are now running - closing this window will
echo  NOT stop them. Run stop-dev.bat to stop everything.)
echo.
pause
