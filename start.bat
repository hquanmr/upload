@echo off
chcp 65001 > nul
title Workerman服务启动器

echo 正在启动Channel服务...
start /B php channel_server.php start
timeout /t 1 > nul
if errorlevel 1 (
    echo [错误] Channel服务启动失败
    exit /b 1
)

echo 正在启动HTTP服务...
start /B php http_server.php start
timeout /t 1 > nul
if errorlevel 1 (
    echo [错误] HTTP服务启动失败
    exit /b 1
)

echo 正在启动WebSocket服务...
start /B php ws_server.php start
timeout /t 1 > nul
if errorlevel 1 (
    echo [错误] WebSocket服务启动失败
    exit /b 1
)

echo.
echo 服务启动状态检查：
tasklist /FI "IMAGENAME eq php.exe" | findstr /C:"channel_server" > nul && echo [√] Channel服务运行中 || echo [×] Channel服务未运行
tasklist /FI "IMAGENAME eq php.exe" | findstr /C:"http_server" > nul && echo [√] HTTP服务运行中 || echo [×] HTTP服务未运行
tasklist /FI "IMAGENAME eq php.exe" | findstr /C:"ws_server" > nul && echo [√] WebSocket服务运行中 || echo [×] WebSocket服务未运行

echo.
echo 使用以下命令停止所有服务：
echo taskkill /F /IM php.exe
echo.
pause