#!/bin/bash

#初始化数据库表格,创建数据库(框架问题，sqlite数据库必须执行两次)
cd /home/wwwroot/api && php think init_db
cd /home/wwwroot/api && php think init_db

#php运行基于www用户，防止初始化生成文件存在权限问题
chown -R www.www /home/wwwroot

#定时任务
cat >/etc/crontab <<EOF
SHELL=/bin/bash
PATH=/sbin:/bin:/usr/sbin:/usr/bin
MAILTO=root

#* * * * * root cd /home/wwwroot/api && php backup.php
EOF
