#!/bin/bash
# 保存为 stop_servers.sh
ps aux | grep -E 'php.*(channel|http|ws)_server.php' | grep -v grep | awk '{print $2}' | xargs kill -9
echo -e "\033[31m[!] 所有服务已停止\033[0m"