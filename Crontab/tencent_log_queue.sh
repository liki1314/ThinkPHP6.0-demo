#!/bin/bash
umask 0022
export PATH='/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin'

task="tencent_log"

BASEDIR='/data/wwwroot/console'
LOGDIR='/var/log/crontab'
LOG="$LOGDIR"/Cron_"$task".log

count=`ps -ef |grep think|grep $task|wc -l`
if [ $count -lt 1 ];then
	cd $BASEDIR
	echo -e "\n\n`date +%Y-%m-%d_%H:%M:%S`" >> $LOG
	/usr/bin/nohup /usr/bin/php73 think queue:listen --queue=$task --timeout=7200 --memory=1024  --quiet >> $LOG 2>&1 &
fi
