#!/bin/bash

# 一键启动Workerman集群服务
# 保存为 start_servers.sh 后执行：bash start_servers.sh

# 启动Channel服务
php channel_server.php start -d && echo -e "\033[32m[√] Channel服务启动成功\033[0m"

# 等待1秒确保Channel服务初始化
sleep 1

# 启动HTTP服务
php http_server.php start -d && echo -e "\033[32m[√] HTTP服务启动成功\033[0m"

# 启动WebSocket服务
php ws_server.php start -d && echo -e "\033[32m[√] WebSocket服务启动成功\033[0m"

# 检查进程状态
echo -e "\n\033[34m=== 运行中的服务进程 ===\033[0m"
ps aux | grep -E 'php.*(channel|http|ws)_server.php' | grep -v grep