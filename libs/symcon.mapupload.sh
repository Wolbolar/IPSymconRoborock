#!/bin/bash
# config
WEBHOOK=
INSTANCE_ID=
RUN_BY_CRONJOB=0

# variables
PNM=$(command -v pnmtopng)
BASEDIR=$(dirname -- $(readlink -e "$0"))

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
        chmod +x $0

        echo ""
        echo "# IP-Symcon Map Uploader"
        echo ""

        while [ -z $INSTANCE_ID ]
        do
                echo -n 'Instance ID (e.g. 12345): '
                read INSTANCE_ID
        done

        while [ -z $WEBHOOK ]
        do
                echo -n 'Webhook URL (e.g. http://10.0.0.1:3777/hook/Roborock): '
                read  WEBHOOK
        done
fi

# install dependencies
if [ -z $PNM ]; then
    echo "updating apt index..."
    apt-get update &> /dev/null

    echo "installing dependencies...";
    apt-get -y install netpbm
fi

# install cronjob
CRON_SCRIPT=$(basename $0)
RUNNING=$(pgrep -f "bash.*$CRON_SCRIPT.*cronjob" | wc -l)
if [ $RUN_BY_CRONJOB -eq 0 ]; then
    echo "installing cronjob..."
    echo "* * * * * root bash $BASEDIR/$CRON_SCRIPT --id=$INSTANCE_ID --webhook=$WEBHOOK --cronjob=1 &> /dev/null" > /etc/cron.d/symcon_mapupload
elif [ $RUNNING -gt 2 ]; then
    # exit, if map uploader is already running
     echo "map uploader still running, exiting..."
     exit
fi

# while loop, to execute uploader every second
while true
do
        # map data
        MAP_FILE=$(find /run/shm -type f -name "*.ppm" | head -1)
        MAP_COORDINATES=$(find /run/shm -type f -name "SLAM_fprintf.log" | head -1)
        MAP_FOLDER=$(dirname -- "$MAP_FILE")
        MAP_IMAGE="$MAP_FOLDER/latest.png"

        # check image
        if [ -z $MAP_FILE ]; then
            echo 'no map file found, exiting...';
            exit
        fi

        # check coordinates file
        if [ -z $MAP_COORDINATES ]; then
            echo 'no coordinates file found, exiting...';
            exit
        fi

        # copy coordinate files to tmp dir
        cp $MAP_COORDINATES /tmp/SLAM_fprint.log

        # convert image to jpg
        echo "converting ppm to png..."
        $PNM -transparent rgb:7D/7D/7D $MAP_FILE &> /dev/null > $MAP_IMAGE

        # upload to webhook
        echo "uploading to webhook... $WEBHOOK?id=$INSTANCE_ID"
        curl -F "image=@$MAP_IMAGE" -F "coordinates=@/tmp/SLAM_fprint.log" "$WEBHOOK?id=$INSTANCE_ID"

        # abort on manual execution
        [ $RUN_BY_CRONJOB -eq 0 ] && exit

        # sleep for 1 second
        echo "waiting 1 second..."
        sleep 1
done