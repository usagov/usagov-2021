#!/bin/sh

### Run mariadb-check w/ the --check or --analyze option
### --analyze updates the table/index stats, allowing disk usage monitoring

# just testing?
if [ x$1 == x"--dryrun" ]; then
  export echo=echo
  shift
fi

SCHEMA_OP=$1
if [ ! -z $SCHEMA_OP ]; then
    case $SCHEMA_OP in
        check) # OK 
            ;;
        analyze) # OK
            ;;
        repair) # Not supported by USAgov's mariadb engine, but a valid option for mariadb-check
            ;;
        *)
        echo Unknown schema operation requested: "$SCHEMA_OP"
        exit 2
        ;;
    esac    
else
    echo "Please specify one of the following schema operations: check|analyze|repair"
    exit 1
fi

DB_NAME=$(echo $VCAP_SERVICES | jq -r '.["aws-rds"][] | .credentials.db_name')
DB_NAME=$(echo $VCAP_SERVICES | jq -r '.["aws-rds"][] | .credentials.db_name')
DB_USER=$(echo $VCAP_SERVICES | jq -r '.["aws-rds"][] | .credentials.username')
DB_PW=$(echo $VCAP_SERVICES | jq -r '.["aws-rds"][] | .credentials.password')
DB_HOST=$(echo $VCAP_SERVICES | jq -r '.["aws-rds"][] | .credentials.host')
DB_PORT=$(echo $VCAP_SERVICES | jq -r '.["aws-rds"][] | .credentials.port')

$echo mariadb-check \
 --protocol=TCP -h$DB_HOST -P$DB_PORT -u$DB_USER -p$DB_PW \
 --all-databases \
 --${SCHEMA_OP}
