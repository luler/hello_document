#!/bin/bash

#初始化数据库表格
cd /home/wwwroot/api && php think init_db

#切换目录用户，防止出现文件权限问题
chown -R www.www /home/wwwroot

#定时任务
cat >/etc/crontab <<EOF
SHELL=/bin/bash
PATH=/sbin:/bin:/usr/sbin:/usr/bin
MAILTO=root

#* * * * * root cd /home/wwwroot/api && php backup.php
EOF
