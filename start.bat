@echo off
chcp 65001 > nul
title Workerman服务启动器

echo 正在启动QueueWorker服务...
start /B php QueueWorker.php start
timeout /t 1 > nul
if errorlevel 1 (
    echo [错误] QueueWorker服务启动失败
    exit /b 1
)

echo 正在启动HTTP服务...
start /B php HttpServer.php start
timeout /t 1 > nul
if errorlevel 1 (
    echo [错误] HTTP服务启动失败
    exit /b 1
)

echo 正在启动WebSocket服务...
start /B php WsServer.php start
timeout /t 1 > nul
if errorlevel 1 (
    echo [错误] WebSocket服务启动失败
    exit /b 1
)

echo.
echo 服务启动状态检查：
tasklist /FI "IMAGENAME eq php.exe" | findstr /C:"QueueWorker" > nul && echo [√] QueueWorker服务运行中 || echo [×] QueueWorker服务未运行
tasklist /FI "IMAGENAME eq php.exe" | findstr /C:"HttpServer" > nul && echo [√] HTTP服务运行中 || echo [×] HTTP服务未运行
tasklist /FI "IMAGENAME eq php.exe" | findstr /C:"WsServer" > nul && echo [√] WebSocket服务运行中 || echo [×] WebSocket服务未运行

echo.
echo 使用以下命令停止所有服务：
echo taskkill /F /IM php.exe
echo.
pause