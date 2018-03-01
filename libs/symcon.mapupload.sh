#!/bin/bash

# config
WEBHOOK=
INSTANCE_ID=
RUN_BY_CRONJOB=0

# handle arguments
for i in "$@"
do
case $i in
    --id=*)
        INSTANCE_ID="${i#*=}"
    shift
    ;;
    --webhook=*)
        WEBHOOK="${i#*=}"
    shift
    ;;
    --cronjob=*)
        RUN_BY_CRONJOB=1
    shift
    ;;
esac
done

if [ -z $WEBHOOK ] || [ -z $INSTANCE_ID ]; then
    echo ""
    echo "# IP-Symcon Map Uploader"
    echo ""
    echo "Usage: $0 --id=<Instance ID> --webhook=<Webhook URL>"
    echo ""

    exit;
fi

# map data
echo "find current map file..."
MAP_FILE=$(find /run/shm -type f -name "*.ppm" | head -1)
MAP_COORDINATES=/run/shm/SLAM_fprintf.log

# variables
PNM=$(command -v pnmtopng)
BASEDIR=$(dirname -- $(readlink -e "$0"))
MAP_FOLDER=$(dirname -- "$MAP_FILE")
MAP_IMAGE="$MAP_FOLDER/latest.png"

# install dependencies
if [ -z $PNM ]; then
    echo "Updating apt index..."
    apt-get update &> /dev/null

    echo "Installing dependencies...";
    apt-get -y install netpbm
fi

# check cronjob
CRON_SCRIPT=$(basename $0)
if [ $RUN_BY_CRONJOB -eq 0 ]; then
    echo "installing cronjob..."
    echo "* * * * * root bash $BASEDIR/$CRON_SCRIPT --id=$INSTANCE_ID --webhook=$WEBHOOK --cronjob=1 &> /dev/null" > /etc/cron.d/symcon_mapupload
fi

# check image
if [ -z $MAP_FILE ]; then
    echo 'no map file found, exiting...';
    exit
fi

# convert image to jpg
echo "converting ppm to png..."
$PNM -transparent rgb:7D/7D/7D $MAP_FILE &> /dev/null > $MAP_IMAGE

# upload to webhook
echo "uploading to webhook..."
curl -F "image=@$MAP_IMAGE" -F "coordinates=@$MAP_COORDINATES" "$WEBHOOK?id=$INSTANCE_ID"