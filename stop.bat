@echo off
taskkill /F /IM php.exe > nul 2>&1
echo 所有PHP进程已终止
timeout /t 2 > nul
exit