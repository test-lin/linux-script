#!/bin/bash

mysql_host=192.168.6.88
mysql_user=deng
mysql_password=deng123
backup_root=/var/www/backup_db
current_date=$(date +%Y-%m-%d)

# 导出表结构
exploadDb() {
    mysql_host=$1
    mysql_user=$2
    mysql_password=$3
    database=$4
    backup_dir=$5

    mysqldump -h$mysql_host -u$mysql_user -p$mysql_password -d $database 2>/dev/null > $backup_dir/db-${database}.sql
}

# 导出表数据
exploadData() {
    mysql_host=$1
    mysql_user=$2
    mysql_password=$3
    database=$4
    backup_dir=$5

    for table in $(mysql -h$mysql_host -u$mysql_user -p$mysql_password $database -e "show tables;" 2>/dev/null | sed '1d')
    do
        mysqldump -h$mysql_host -u$mysql_user -p$mysql_password -t $database $table 2>/dev/null > $backup_dir/${table}.sql
    done
}

# 压缩完成后，删除压缩内容
compressDbDir() {
    backup_dir=$1
    backup_root=$2
    backup_db=$3
    current_date=$(date +%Y-%m-%d)

    if [ -n "$(ls -A $backup_dir)" ]; then
        tar -czf ${backup_dir}.tar.gz -C $backup_root/$backup_db $current_date 2>/dev/null

        rm -rf $backup_dir
    fi
}

# 导出单个数据库
exploadOne() {
    backup_root=$1
    database=$2

    mysql_host=$3
    mysql_user=$4
    mysql_password=$5

    current_date=$(date +%Y-%m-%d)

    backup_dir=$backup_root/$database/$current_date
    if [ ! -d $backup_dir ]; then
        mkdir -p $backup_dir
    fi

    exploadDb $mysql_host $mysql_user $mysql_password $database $backup_dir
    exploadData $mysql_host $mysql_user $mysql_password $database $backup_dir

    compressDbDir $backup_dir $backup_root $database
}

database=$1
if [ ! -n "$database" ]; then
    echo "请填写要备份的数据库"
    read database
fi

exploadOne $backup_root $database $mysql_host $mysql_user $mysql_password


# ignore_db=("mysql" "information_schema" "performance_schema")
# backup_db=("exam" "cqhttp")
# for database in $(mysql -h$mysql_host -u$mysql_user -p$mysql_password -e "show databases;" 2>/dev/null | sed '1d')
# do
#     if [[ "${ignore_db[@]}" =~ "$database" ]]; then
#         continue
#     fi

#     # if [[ ! "${backup_db[@]}" =~ "$database" ]]; then
#     #     echo "$database"
#     #     continue
#     # fi

#     exploadOne $backup_root $database $mysql_host $mysql_user $mysql_password
# done
