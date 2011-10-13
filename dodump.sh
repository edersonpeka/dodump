#!/bin/bash

function showerror {
    echo -n "ERROR: "
    echo $*
    usage
    exit 1
}

function usage {
    echo "USAGE: dodump.sh [dump|load] [[-c path/to/wp-config.php] [-f path/to/dumpfile.sql] [-u your.domain.com]]"
}

if [ $# -eq 0 ]; then
    usage
else

    SITEURL="none"
    DOMAIN="none"
    ACTION="nothing"
    DUMPFILE="dumpfile.sql"
    WP_CONFIG="default"
    while [ "$1" != "" ]; do
        case $1 in
            dump | --dump )
                ACTION="dump"
                ;;
            load | --load )
                ACTION="load"
                ;;
            -c | --config )
                shift
                WP_CONFIG=$1
                ;;
            -f | --file )
                shift
                DUMPFILE=$1
                ;;
            -u | --url )
                shift
                DOMAIN=$1
                ;;
            -h | --help | --? | /? )
                usage
                exit
                ;;
            * )
                usage
                exit 1
        esac
        shift
    done

    DB_NAME='notfound'
    DB_USER='notfound'
    DB_PASSWORD='notfound'
    DB_HOST='notfound'

    if [ "$WP_CONFIG" == "default" ]; then
      WP_CONFIG='wp-config.php'
      if [ ! -f $WP_CONFIG ]; then WP_CONFIG='www/wp-config.php'; fi
      if [ ! -f $WP_CONFIG ]; then WP_CONFIG='../www/wp-config.php'; fi
      if [ ! -f $WP_CONFIG ]; then WP_CONFIG='wordpress/wp-config.php'; fi
      if [ ! -f $WP_CONFIG ]; then WP_CONFIG='../wordpress/wp-config.php'; fi
    fi

    if [ -n "$WP_CONFIG" ]; then
        if [ -f $WP_CONFIG ]; then
          DB_NAME=`cat ${WP_CONFIG} | grep "define" | grep "DB_NAME" | sed "s/define\s*(\s*'DB_NAME'\s*,\s*'\([^']*\)'\s*)\s*;\r\?/\1/g"`
          DB_USER=`cat ${WP_CONFIG} | grep "define" | grep "DB_USER" | sed "s/define\s*(\s*'DB_USER'\s*,\s*'\([^']*\)'\s*)\s*;\r\?/\1/g"`
          DB_PASSWORD=`cat ${WP_CONFIG} | grep "define" | grep "DB_PASSWORD" | sed "s/define\s*(\s*'DB_PASSWORD'\s*,\s*'\([^']*\)'\s*)\s*;\r\?/\1/g"`
          DB_HOST=`cat ${WP_CONFIG} | grep "define" | grep "DB_HOST" | sed "s/define\s*(\s*'DB_HOST'\s*,\s*'\([^']*\)'\s*)\s*;\r\?/\1/g"`
        else
          showerror "Config file \"${WP_CONFIG}\" not found."
        fi
    else
        showerror "Config file not specified."
    fi

    CONNDATA="-h ${DB_HOST} -u ${DB_USER} -p${DB_PASSWORD} ${DB_NAME}"

    if [ "$DOMAIN" == "" ]; then
        showerror "Domain not specified."
    elif [ "$DOMAIN" == "none" ]; then
        SITEURL=`mysql ${CONNDATA} -e "SELECT option_value FROM wp_options WHERE option_name='siteurl'" --skip-column-names --batch | sed "s/\\\\./\\\\\\\\\\\\./g"`

        DOMAIN=`echo ${SITEURL} | sed "s/https\?:\\\\/\\\\/\(.*\)/\1/g"`
        DOMAIN=`echo ${DOMAIN} | sed "s/\\\\//\\\\\\\\\\\\//g"`
        SITEURL=`echo ${SITEURL} | sed "s/\\\\//\\\\\\\\\\\\//g"`
    else
        SITEURL="http://${DOMAIN}"
    fi

    if [ "$SITEURL" == "none" ]; then showerror "Could not retrieve your site URL from database, and [domain] was not specified."; fi

    if [ "$ACTION" == "nothing" ]; then
        showerror "Action not specified."
    elif [ "$ACTION" == "dump" ]; then
        echo -n "Dumping... "
        mysqldump ${CONNDATA} | sed "s/${SITEURL}/\[\[INSERT\-SITEURL\-HERE\]\]/g" | sed "s/${DOMAIN}/\[\[INSERT\-DOMAIN\-HERE\]\]/g" > ${DUMPFILE}
        echo "done!"
        echo "Dump written to file \"${DUMPFILE}\"."
    elif [ "$ACTION" == "load" ]; then
        if [ ! -f $DUMPFILE ]; then
          showerror "Dump file \"${DUMPFILE}\" not found."
        else
          read -n1 -p "Ok to PERMANENTLY overwrite your database information with \"${DUMPFILE}\" file contents? (y/N) "
          case "$REPLY" in
            "y" | "Y" )
              echo
              echo -n "Loading... "
              cat ${DUMPFILE} | sed "s/\[\[INSERT\-DOMAIN\-HERE\]\]/${DOMAIN}/g" | sed "s/\[\[INSERT\-SITEURL\-HERE\]\]/${SITEURL}/g" | mysql ${CONNDATA}
              echo "done!"
              echo "Dump loaded from file \"${DUMPFILE}\"."
              ;;
            "n" | "N" | "" )
              if [ "$REPLY" != "" ]; then echo; fi
              echo "Aborted."
              ;;
            * )
              echo
              echo "What part of the question did you not understand?! Aborted."
              ;;
          esac
        fi
    fi
fi
