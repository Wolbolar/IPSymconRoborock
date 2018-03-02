# Roborock vacuum cleaner robot

Module for IP Symcon version 4.3 or higher

## Documentation

**Table of Contents**

1. [Features](#1-features)
2. [Requirements](#2-requirements)
3. [Installation](#3-installation)
4. [Function reference](#4-functionreference)
5. [Configuration](#5-configuration)
6. [Annex](#6-annex)

## 1. Features

With the module it is possible to control a [Roborock](https://www.roborock.com/ "Roborock") robot vacuum cleaner (Xiaomi) from IP-Symcon.

### Features:

- Start / Stop / Pause the suction function
- Spot cleaning
- Return to the charging station
- Show and set timers
- Remote remote control
- Adjusting the fan power
- Adjust the volume
- Locate the vacuum cleaner
- Switch on / off Do not Disturb Mode (DND) and set times
- Display of:
    - cleaned area
    - sum of cleaned area
    - Cleaning time
    - Total cleaning time
    - Battery power
    - Number of cleanings
    - Overview of final cleanings
    - Consumption status of consumable items (main, side brush, filters, sensors)
    - Serial number
    - Hardware version
    - Firmware version
    - SSID of the connected WLAN
    - local IP address
    - Model name
    - MAC
    - Time zone
    - Card (optionally only available for rooted devices)
	  
## 2. Requirements

  - IP-Symcon 4.3
  - MI App (Xiaomi)
  - Roborock vacuum cleaner robot (Xiaomi)

## 3. Installation

### a. Loading the module

Open the IP Symcon (min Ver 4.3) console. In the object tree, under Core instances, open the instance __ * Modules * __ with a double mouse click.

![Modules](img/Modules.png?raw=true "Modules")

In the _Modules_ instance, press the button __*Add*__ in the top right corner.

![ModulesAdd](img/Hinzufuegen.png?raw=true "Hinzufügen")
 
Add the following URL in the window that opens:

```	
https://github.com/Wolbolar/IPSymconRoborock  
```
    
and confirm with _OK_.    
    
Then an entry for the module appears in the list of the instance _Modules_

### b. Obtain the IP address and the token  

#### Look up IP address in the MIHome app

To be able to communicate with the Roborock one needs its IP address and the token.

For this purpose, the Roborock in the [MiHome](https://itunes.apple.com/de/app/mi-home-xiaomi-smarthome/id957323480?mt=8 "MiHome") App by Xiaomi is set up accordingly.
After the Roborock has been set up and assigned to a room, you click on it and come to the further menu selection via the icon

Under _General settings_ and the sub-item _Network info_ you can find the IP address of the Roborock under the field _IP address_.
This will be noted in order to be able to enter it later in IP-Symcon.

#### Get the Token from an iOS device

In order to be able to read the token, you need to create a backup with iTunes. Make sure that _not encrypted_ is selected when creating the backup.
To read the backup then you need special programs. The process is described here for [iBackup Viewer](http://www.imactools.com/iphonebackupviewer/ "iBackup Viewer")
In iBackup Viewer, open the backup and select _Raw Files_ and switch to _Tree View_.
Go to the entry Navigate to _AppDomain-com.xiaomi.mihome_. Here we need a file that
looks like _123456789_mihome.sqlite_ (Important: * _mihome.sqlite * is not the file you are looking for) in the _Documents_ folder.
Select the file and save it to disk with _Export Selected_.
Now we need a tool to open the file. To do this you load

[DB Browser for SQLite](http://sqlitebrowser.org/ "DB Browser for SQLite")

Load the file that was previously saved in the DB Browser.
Now go to the tab _Data search_. Select table _ZDEVICE_ and scroll to the right to the entry
__*ZTOKEN*__ . Mark the entry and set the mode to _Text_ in the field next to it and mark the entry in the right field and copy it with CTRL + C.
The entry usually has a length of 96 characters. The contents of the clipboard are then copied to the Token field of the module's configuration form.

#### Obtain Token from Android

In the new app versions MiHome 5.1.1, the token is no longer stored locally. The token can only obtained up to version 5.0.19.
If a newer version of the MIHome app is available and the token should not already be known, the only option is
temporarily installing an older version of the MiHome app to obtain the token. After the token has been read out, the current version of the MIHome App can be used.
An older version of the MIHome App can be found e.g. under

[Mi Home 5.0.19 (Android 4.0.3+) APK Download by Xiaomi Inc. - APKMirror](https://www.apkmirror.com/apk/xiaomi-inc/mihome/mihome-5-0-19-release/mihome-5-0-19-android-apk-download/ "Mi Home 5.0.19 (Android 4.0.3+) APK Download by Xiaomi Inc. - APKMirror")

With the version we can obtain the token from the MIHome App.

#### Windows and Android 

- First configure the Roborock in the MiHome App (for Android up to 5.0.19)
- Then download the [MIToolkit](https://github.com/ultrara1n/MiToolkit/releases "MiToolkit") and unpack it on the hard disk
- Activate Developer Mode and USB Debugging on the Android device and connect it to the computer with a USB cable
- Start the MiToolkit.exe with a double click and select _Read Token_
- On the device with the MIHome app you must now confirm the backup and here select _no password_ . It will now create a backup.
- Then the token should be displayed in MIToolkit.

#### Linux and Android

First _libffi-dev_ and _libssl-dev_ must be installed.

enter:

  ```
  $ sudo apt-get install libffi-dev libssl-dev
  ```   

- First configure the Roborock in the MiHome App (for Android up to 5.0.19)
- Activate Developer Mode and USB Debugging on the Android device and connect it to the computer with a USB cable
- Install ADB

  ```
  $ sudo apt-get install android-tools-adb
  ``` 
or

  ```
  $ sudo apt-get install adb
  ``` 
Under ADB, the device should be displayed.
Create a backup with adb using

  ```
  $ sudo adb backup -noapk com.xiaomi.smarthome -f backup.ab
  ``` 

 [ADB Backup Extractor](https://sourceforge.net/projects/adbextractor/files/latest/download "ADB Backup Extractor") herunterladen

Read the data from the backup

  ```
  $ java -jar Android\ Backup\ Utilities/Android\ Backup\ Extractor/android-backup-extractor-20171005-bin/abe.jar unpack backup.ab unpacked.tar
  ``` 
  
Unpack the data

  ```
  $ tar -xvf unpacked.tar
  ``` 
  
Then read the token

  ```
  $ sqlite3 apps/com.xiaomi.smarthome/db/miio2.db 'select token from devicerecord where name = "Mi Robot Vacuum";'
  ``` 


### c. Konfiguration in IPS

In IP-Symcon, select Add_Instance_ (_CTRL + 1_) under the category under which you want to add the instance and select _Roborock_.

![AddInstance](img/Roborock_add_instance.png?raw=true "Add Instance")

The configuration form opens. Please specify here:
  - Check the IP address of the vacuum cleaner in the app or the router
  - token of the vacuum cleaner (see above)
  - Update interval in seconds
  - Webfront to send push messages
  - Selection of the desired functions or displays in the webfront

### d. Setup of the map upload (ONLY for rooted devices!)
At the moment you can not read the map view from the outside.
For **rooted** devices you can use the following workaround. Here, however, only the created map is currently synchronized, without the already sucked surfaces or the position of the vaccum cleaner, it is still being worked on.

1. Log in to the robot via SSH
2. Run the following command in the console:

 ```code 
 curl https://raw.githubusercontent.com/Wolbolar/IPSymconRoborock/master/libs/symcon.mapupload.sh > symcon.mapupload.sh && bash symcon.mapupload.sh
 ```
 
Now you have to enter two parameters: The instance id of your Robockrock module and the url to the webhook, which was already created during module installation.
Afterwards, all required programs will be installed (around 25 MB) and the script configures a cronjob, which checks for new map files frequently and upload them via webhook to IP-Symcon, stored as media file.

Note, that any map data will only created, when your robot is running!

| Parameter | Explanation
| :-------: | :-----------------------------------------: |
| ID | Instance ID from the robot in IP-Symcon |
| Webhook URL | URL of the Webhook, etc. http://10.0.0.1:3777/hook/Roborock <br /> The Webhook _Roborock_ will be created automatically. |

## 4. Funktion reference

### Roborock Vacuum Cleaner:

 _**Starts the cleaning process**_
  
 ```php
 Roborock_Start($InstanceID);
 ```   
 
 Parameter _$InstanceID_ __*ObjectID*__ from the Roborock instance
	
 _**Stop the cleaning process**_
  
 ```php
 Roborock_Stop($InstanceID);
 ```   
 
 Parameter _$InstanceID_ __*ObjectID*__ from the Roborock instance
 
 _**Pause the cleaning process**_
   
 ```php
 Roborock_Pause($InstanceID);
 ```   
  
 Parameter _$InstanceID_ __*ObjectID*__ from the Roborock instance
  
 _**Drives to the charging station for charging**_
    
 ```php
 Roborock_Charge($InstanceID);
 ```   
   
 Parameter _$InstanceID_ __*ObjectID*__ from the Roborock instance
   
 _**Tells the vaccum cleaner to play a sound for localization**_
    
 ```php
 Roborock_Locate($InstanceID);
 ```   
   
 Parameter _$InstanceID_ __*ObjectID*__ from the Roborock instance

 _**Spot Cleaning**_
    
 ```php
 Roborock_CleanSpot($InstanceID);
 ```   
   
 Parameter _$InstanceID_ __*ObjectID*__ from the Roborock instance
 
 
 _**Get the state from the Roborock**_
     
 ```php
 Roborock_Get_State($InstanceID);
 ```   
    
 Parameter _$InstanceID_ __*ObjectID*__ from the Roborock instance  
 
Returns:
- Battery charge
- Cleaning surface
- Cleaning time
- DND status
- fan power
  
 _**Serial number from the Roborock**_
      
 ```php
 Roborock_Get_Serial_Number($InstanceID);
 ```   
     
 Parameter _$InstanceID_ __*ObjectID*__ from the Roborock instance  
   
 _**Get state of consumables**_
       
 ```php
 Roborock_Get_Consumables($InstanceID);
 ```   
      
 Parameter _$InstanceID_ __*ObjectID*__ from the Roborock instance  
 
 _**Get clean summary**_
        
  ```php
  Roborock_GetCleanSummary($InstanceID);
  ```   
       
 Parameter _$InstanceID_ __*ObjectID*__ from the Roborock instance
  
 _**Get State of Do Not Disturb Mode**_
          
 ```php
 Roborock_Get_DND_Mode($InstanceID);
 ```   
         
  Parameter _$InstanceID_ __*ObjectID*__ from the Roborock instance     

## 5. Konfiguration:

### Properties:

| Property    | Type     | Standard Value | Function                                      |
| :---------: | :-----: | :------------: | :-------------------------------------------: |
| host        | string  |                | IP Adresse des Roborock Staubsauger Roboters  |
| token       | integer |                | Token aus der MI App, Länge 32 oder 96 Zeichen|



## 6. Annnex

###  a. Methods:

#### Roborock vacuum cleaner:

  _**Starts the cleaning process**_
   
  ```php
  Roborock_Start($InstanceID);
  ```   
  
  Parameter _$InstanceID_ __*ObjectID*__ from the Roborock instance
 	
  _**Stop the cleaning process**_
   
  ```php
  Roborock_Stop($InstanceID);
  ```   
  
  Parameter _$InstanceID_ __*ObjectID*__ from the Roborock instance
  
  _**Pause the cleaning process**_
    
  ```php
  Roborock_Pause($InstanceID);
  ```   
   
  Parameter _$InstanceID_ __*ObjectID*__ from the Roborock instance
   
  _**Drives to the charging station for charging**_
     
  ```php
  Roborock_Charge($InstanceID);
  ```   
    
  Parameter _$InstanceID_ __*ObjectID*__ from the Roborock instance
    
  _**Tells the vaccum cleaner to play a sound for localization**_
     
  ```php
  Roborock_Locate($InstanceID);
  ```   
    
  Parameter _$InstanceID_ __*ObjectID*__ from the Roborock instance
 
  _**Spot Cleaning**_
     
  ```php
  Roborock_CleanSpot($InstanceID);
  ```   
    
  Parameter _$InstanceID_ __*ObjectID*__ from the Roborock instance
  
  
  _**Get the state from the Roborock**_
      
  ```php
  Roborock_Get_State($InstanceID);
  ```   
     
  Parameter _$InstanceID_ __*ObjectID*__ from the Roborock instance  
  
 Returns:
 - Battery charge
 - Cleaning surface
 - Cleaning time
 - DND status
 - fan power
   
  _**Serial number from the Roborock**_
       
  ```php
  Roborock_Get_Serial_Number($InstanceID);
  ```   
      
  Parameter _$InstanceID_ __*ObjectID*__ from the Roborock instance  
    
  _**Get state of consumables**_
        
  ```php
  Roborock_Get_Consumables($InstanceID);
  ```   
       
  Parameter _$InstanceID_ __*ObjectID*__ from the Roborock instance  
  
  _**Get clean summary**_
         
   ```php
   Roborock_GetCleanSummary($InstanceID);
   ```   
        
  Parameter _$InstanceID_ __*ObjectID*__ from the Roborock instance
   
  _**Get State of Do Not Disturb Mode**_
           
  ```php
  Roborock_Get_DND_Mode($InstanceID);
  ```   
          
   Parameter _$InstanceID_ __*ObjectID*__ from the Roborock instance     

 _**Set Fan Power**_
          
 ```php
 Roborock_Fan_Power(integer $InstanceID, integer $power);
 ```   
         
 Parameter _$InstanceID_ __*ObjektID*__ der Roborock Instanz     
 Parameter _$power_ Wert von 0 - 100 zum Einstellen der Leistung     

_**Get area cleaned**_
          
 ```php
 Roborock_Get_Area_Cleaned(integer $InstanceID);
 ```   
         
 Parameter _$InstanceID_ __*ObjektID*__ der Roborock Instanz     

_**Get Time Cleaned**_
          
 ```php
 Roborock_Get_Time_Cleaned(integer $InstanceID);
 ```   
         
 Parameter _$InstanceID_ __*ObjektID*__ der Roborock Instanz     

_**Get cleaning cycles**_
          
 ```php
 Roborock_Get_Cleaning_Cycles(integer $InstanceID);
 ```   
         
 Parameter _$InstanceID_ __*ObjektID*__ der Roborock Instanz     


###  b. GUIDs and data transfer:

#### Roborock:

GUID: `{E65614FB-B37A-219A-4876-E5676C948C33}` 

### c. Sources

[OpenMiHome](https://github.com/OpenMiHome/mihome-binary-protocol/blob/master/doc/PROTOCOL.md "OpenMiHome") _Wolfgang Frisch_ (GPLv3)
