@echo off
setlocal

set NGINX_DIR=C:\Users\mamat\AppData\Local\DevTools\nginx

echo Stopping Sayeon dev environment...

echo Stopping nginx...
"%NGINX_DIR%\nginx.exe" -s stop -c conf/erp.conf -p "%NGINX_DIR%/" >nul 2>&1

for /f "tokens=5" %%p in ('netstat -ano ^| findstr ":9000 :9001 :9002 :9003" ^| findstr "LISTENING"') do (
    echo Stopping PHP worker (PID %%p)...
    taskkill /PID %%p /F >nul 2>&1
)

for /f "tokens=5" %%p in ('netstat -ano ^| findstr ":3307" ^| findstr "LISTENING"') do (
    echo Stopping MySQL (PID %%p)...
    taskkill /PID %%p /F >nul 2>&1
)

echo Done.
pause
