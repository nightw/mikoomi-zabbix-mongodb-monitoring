#!/bin/bash
PATH=$PATH:/etc/zabbix/externalscripts:/opt/zabbix/externalscripts:/opt/zabbix/bin:/home/zabbix/bin:/bin:/usr/sbin:/usr/bin:/usr/local/bin:/usr/local/sbin
export PATH
BASE_DIR="`dirname $0`"
/usr/bin/php $BASE_DIR/mikoomi-mongodb-plugin.php $* 
echo 0
