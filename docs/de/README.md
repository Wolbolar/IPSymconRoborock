# Roborock Staubsauger Roboter

Modul für IP-Symcon ab Version 4.3

## Dokumentation

**Inhaltsverzeichnis**

1. [Funktionsumfang](#1-funktionsumfang)  
2. [Voraussetzungen](#2-voraussetzungen)  
3. [Installation](#3-installation)  
4. [Funktionsreferenz](#4-funktionsreferenz)
5. [Konfiguration](#5-konfiguartion)  
6. [Anhang](#6-anhang)  

## 1. Funktionsumfang

Mit dem Modul ist es möglich einen [Roborock](https://www.roborock.com/ "Roborock") Staubsauger Roboter (Xiaomi) von IP-Symcon aus zu steuern. 

### Funktionen:  

 - Start / Stop / Pause der Saugfunktion 
 - Spotcleaning
 - Zurückfahren an die Aufladestation
 - Timer anzeigen und setzten
 - Remote Fernsteuerung
 - Einstellen der Lüfterleistung
 - Einstellen der Lautstärke
 - Lokalisieren des Saugers
 - Do not Disturb Mode (DND) ein / auschalten und Zeiten einstellen
 - Anzeige von:
    - gereinigte Fläche
    - Summe gereinigte Fläche
    - Reinigungszeit
    - Summe der Reinigungszeit
    - Batterieleistung
    - Anzahl der Reinungen
    - Übersicht letzte Reinigungen
    - Ansicht des Verbrauchsstatus der verbrauchbaren Gegenstände (Haupt-, Seitenbürste, Filter, Sensoren)
    - Seriennummer
    - Hardware Version
    - Firmware Version
    - SSID vom verbundenen WLAN
    - lokale IP Adresse
    - Modellbezeichnung
    - MAC
    - Zeitzone
    - Karte (optional nur für gerootete Geräte verfügbar)
	  

## 2. Voraussetzungen

 - IP-Symcon 4.3
 - MI App (Xiaomi) 
 - Roborock Staubsauger Roboter (Xiaomi)

## 3. Installation

### a. Laden des Moduls

Die IP-Symcon (min Ver. 4.3) Konsole öffnen. Im Objektbaum unter Kerninstanzen die Instanz __*Modules*__ durch einen doppelten Mausklick öffnen.

![Modules](img/Modules.png?raw=true "Modules")

In der _Modules_ Instanz rechts oben auf den Button __*Hinzufügen*__ drücken.

![ModulesAdd](img/Hinzufuegen.png?raw=true "Hinzufügen")
 
In dem sich öffnenden Fenster folgende URL hinzufügen:

```	
https://github.com/Wolbolar/IPSymconRoborock  
```
    
und mit _OK_ bestätigen.    
    
Anschließend erscheint ein Eintrag für das Modul in der Liste der Instanz _Modules_ 

### b. Erhalten der IP Adresse und des Tokens  

#### IP Adresse in der MIHome App nachschlagen

Um mit dem Roborock kommunizieren zu können benötigt man dessen IP Adresse und den Token.

Dazu wird zunächst der Roborock in der [MiHome](https://itunes.apple.com/de/app/mi-home-xiaomi-smarthome/id957323480?mt=8 "MiHome") App von Xiaomi entsprechend eingerichtet.
Nachdem der Roborock eingerichtet und einem Raum zugewiesen worden ist klickt man diesen an und kommt zur weiteren Menüauswahl über das Icon

Unter _General settings_ und dem Unterpunkt _Network info_ findet man die IP Adresse des Roborock unter dem Feld _IP address_.
Diese wird notiert um diese später in IP-Symcon eintragen zu können.

#### Token mit iOS beziehen

Um den Token auslesen zu können muss ein Backup mit iTunes erstellt werden, hierbei ist darauf zu achten, dass _nicht verschlüsseln_ beim Erstellen des Backups ausgewählt wird.
Um das Backup dann auszulesen benötigt man Spezialprogramme. Beschrieben ist der Vorgang hier für [iBackup Viewer](http://www.imactools.com/iphonebackupviewer/ "iBackup Viewer")   
In iBackup Viewer das Backup öffen und _Raw Files_ auswählen und in die _Tree View_ wechseln.
Hier zum Eintrag Navigate to _AppDomain-com.xiaomi.mihome_ wechseln. Hier benötigen wir ein File das
aussieht wie _123456789_mihome.sqlite_ (Wichtig: *_mihome.sqlite* ist nicht das gesuchte File) im Ordner _Documents_.
Das File auswählen und mit _Export Selected_ auf Festplatte speichern.
Nun brauchen wir ein Tool um das File zu öffnen. Dazu lädt man

[DB Browser for SQLite](http://sqlitebrowser.org/ "DB Browser for SQLite")

Die Datei, die zuvor abgespeichert worden ist, im DB Browser laden.
Nun auf den Reiter _Daten durchsuchen_ wechseln. Als Tabelle _ZDEVICE_ auswählen und ganz nach rechts scrollen bis zum Eintrag
__*ZTOKEN*__. Den Eintrag markieren und im Feld daneben den Modus auf _Text_ stellen und den Eintrag im rechten Feld markieren und mit STRG+C kopieren.
Der Eintrag hat meist eine Länge von 96 Zeichen. Den Inhalt aus der Zwischenablage wird dann in das Feld Token des Konfigurationsformulars des Moduls kopiert.

#### Token mit Android beziehen

In den neuen App Versionen MiHome 5.1.1 ist der Token nicht mehr lokal gespeichert. Dieser lässt sich also nur bis zur Version 5.0.19 auslesen.
Falls eine neuere Version der MIHome App vorhanden ist und der Token nicht schon bekannt sein sollte, ist die einzige Möglichkeit
vorrübergehend eine ältere Version der MiHome App aufzuspielen um den Token auszulesen. Nachdem der Token ausgelesen wurde, kann dann wieder auf die 
aktuelle Version der MIHome App upgedated werden.
Eine ältere Version der MIHome App findet man z.B. unter 

[Mi Home 5.0.19 (Android 4.0.3+) APK Download by Xiaomi Inc. - APKMirror](https://www.apkmirror.com/apk/xiaomi-inc/mihome/mihome-5-0-19-release/mihome-5-0-19-android-apk-download/ "Mi Home 5.0.19 (Android 4.0.3+) APK Download by Xiaomi Inc. - APKMirror")

Mit der Version ist auch noch ein Auslesen des Tokens möglich.

#### Windows und Android 

- Zunächst ist der Roborock in der MiHome App (für Android max. 5.0.19) zu konfigurieren.
- Anschließend die [MIToolkit](https://github.com/ultrara1n/MiToolkit/releases "MiToolkit") herunterladen und auf der Festplatte entpacken
- Den Developer Mode und das USB Debugging auf dem Android Gerät aktivieren und dieses dann mit einem USB Kabel mit dem Computer verbinden
- Das MiToolkit.exe mit einem Doppelklick starten und auf _Token auslesen_ drücken
- Auf dem Gerät mit der MIHome App muss man nun das Backup bestätigen und hier _kein Passwort_ auswählen. Es wird nun ein Backup erstellt.
- Anschließend sollte im MIToolkit der Token angezeigt werden.

#### Linux und Android

Zunächst muss _libffi-dev_ und _libssl-dev_ installiert werden.

Dazu wird eingegeben 

  ```
  $ sudo apt-get install libffi-dev libssl-dev
  ```   

- Zunächst ist der Roborock in der MiHome App (für Android max. 5.0.19) zu konfigurieren.
- Den Developer Mode und das USB Debugging auf dem Android Gerät aktivieren und dieses dann mit einem USB Kabel mit dem Computer verbinden
- ADB installieren

  ```
  $ sudo apt-get install android-tools-adb
  ``` 
oder

  ```
  $ sudo apt-get install adb
  ``` 
Unter ADB sollte das Gerät angezeigt werden.
Ein Backup mit adb erstellen mit

  ```
  $ sudo adb backup -noapk com.xiaomi.smarthome -f backup.ab
  ``` 

 [ADB Backup Extractor](https://sourceforge.net/projects/adbextractor/files/latest/download "ADB Backup Extractor") herunterladen

Die Daten aus dem Backup auslesen

  ```
  $ java -jar Android\ Backup\ Utilities/Android\ Backup\ Extractor/android-backup-extractor-20171005-bin/abe.jar unpack backup.ab unpacked.tar
  ``` 
  
Die Daten entpacken

  ```
  $ tar -xvf unpacked.tar
  ``` 
  
Anschließend den Token auslesen

  ```
  $ sqlite3 apps/com.xiaomi.smarthome/db/miio2.db 'select token from devicerecord where name = "Mi Robot Vacuum";'
  ``` 

### c. Einrichtung in IPS

In IP-Symcon nun _Instanz hinzufügen_ (_CTRL+1_) auswählen unter der Kategorie, unter der man die Instanz hinzufügen will, und _Roborock_ auswählen.

![AddInstance](img/Roborock_add_instance.png?raw=true "Add Instance")

Es öffnet sich das Konfigurationsformular. Hier ist anzugeben:
 - IP Adresse des Saugers in der App oder der Router nachschauen
 - Token des Saugers (siehe oben)
 - Aktualisierungsintervall in Sekunden
 - Webfront um Push Nachrichten zu verschicken
 - Auswahl der gewünschten Funktionen bzw. Anzeigen im Webfront

### d. Einrichtung des Kartenuploads (NUR für gerootete Geräte!)
Momentan kann man von außen leider nicht die Kartenansicht auslesen.
Für **gerootete** Geräte kann man nachfolgenden Workaround nutzen.

1. per SSH auf dem Robot einwählen
2. In der Konsole folgenden Befehl ausführen: 

 ```code 
curl https://raw.githubusercontent.com/Wolbolar/IPSymconRoborock/master/libs/symcon.mapupload.sh > symcon.mapupload.sh && bash symcon.mapupload.sh 
 ```
  
Nun werden als erstes 2 Parameter abgefragt: die IP-Symcon Instanz des Roborock Moduls und die URL des durch das Modul angelegten Webhooks. Anschließend werden die benötigte Programme installiert (rund 25 MB) und der Cronjob eingerichtet, welcher regelmäßig prüft, ob eine neue Kartendatei existiert und diese anschließend per Webhook an die IP-Symcon Instanz schickt und dort als Media Bild abspeichert.

Die Kartendateien werden nur dann erstellt, wenn der Sauger auch läuft!

| Parameter | Erklärung                                   |
| :-------: | :-----------------------------------------: |
| ID | Instanz ID des Robots in IP-Symcon |
| Webhook URL | URL zum Webhook, z.B. http://10.0.0.1:3777/hook/Roborock <br /> Der Webhook _Roborock_ wird automatisch angelegt. |

## 4. Funktionsreferenz

### Roborock Staubsauger Roboter:

 _**Startet den Reinigungsvorgang**_
  
 ```php
 Roborock_Start($InstanceID);
 ```   
 
 Parameter _$InstanceID_ __*ObjektID*__ der Roborock Instanz
	
 _**Stoppt den Reinigungsvorgang**_
  
 ```php
 Roborock_Stop($InstanceID);
 ```   
 
 Parameter _$InstanceID_ __*ObjektID*__ der Roborock Instanz
 
 _**Pausiert den Reinigungsvorgang**_
   
 ```php
 Roborock_Pause($InstanceID);
 ```   
  
 Parameter _$InstanceID_ __*ObjektID*__ der Roborock Instanz
  
 _**Fährt zum Aufladen zur Ladestation**_
    
 ```php
 Roborock_Charge($InstanceID);
 ```   
   
 Parameter _$InstanceID_ __*ObjektID*__ der Roborock Instanz
   
 _**Weist den Sauger an sich mit einem Sound zur Lokalisierung zu melden**_
    
 ```php
 Roborock_Locate($InstanceID);
 ```   
   
 Parameter _$InstanceID_ __*ObjektID*__ der Roborock Instanz

 _**Startet eine Reinigung um den Standort des Saugers**_
    
 ```php
 Roborock_CleanSpot($InstanceID);
 ```   
   
 Parameter _$InstanceID_ __*ObjektID*__ der Roborock Instanz
 
 
 _**Liest den Status vom Roborock aus**_
     
 ```php
 Roborock_Get_State($InstanceID);
 ```   
    
 Parameter _$InstanceID_ __*ObjektID*__ der Roborock Instanz  
 
 Gibt zurück:
 - Batterieladung
 - Reinigungsfläche
 - Reinigungszeit
 - DND Status
 - Lüfterleistung
  
 _**Seriennummer des Roborock**_
      
 ```php
 Roborock_Get_Serial_Number($InstanceID);
 ```   
     
 Parameter _$InstanceID_ __*ObjektID*__ der Roborock Instanz  
   
 _**Liest Zustand der Verbrauchsgegenstände aus**_
       
 ```php
 Roborock_Get_Consumables($InstanceID);
 ```   
      
 Parameter _$InstanceID_ __*ObjektID*__ der Roborock Instanz  
 
 _**Liest Zusammenfassung der Reinigung aus**_
        
  ```php
  Roborock_GetCleanSummary($InstanceID);
  ```   
       
 Parameter _$InstanceID_ __*ObjektID*__ der Roborock Instanz
  
 _**Liest Status Do Not Disturb Mode aus**_
          
 ```php
 Roborock_Get_DND_Mode($InstanceID);
 ```   
         
  Parameter _$InstanceID_ __*ObjektID*__ der Roborock Instanz     

## 5. Konfiguration:

### Eigenschaften:

| Eigenschaft | Typ     | Standardwert | Funktion                                      |
| :---------: | :-----: | :----------: | :-------------------------------------------: |
| host        | string  |              | IP Adresse des Roborock Staubsauger Roboters  |
| token       | integer |              | Token aus der MI App, Länge 32 oder 96 Zeichen|






## 6. Anhang

###  a. Funktionen:

#### Roborock Staubsauger Roboter:

 _**Startet den Reinigungsvorgang**_
  
 ```php
 Roborock_Start($InstanceID);
 ```   
 
 Parameter _$InstanceID_ __*ObjektID*__ der Roborock Instanz
	
 _**Stoppt den Reinigungsvorgang**_
  
 ```php
 Roborock_Stop($InstanceID);
 ```   
 
 Parameter _$InstanceID_ __*ObjektID*__ der Roborock Instanz
 
 _**Pausiert den Reinigungsvorgang**_
   
 ```php
 Roborock_Pause($InstanceID);
 ```   
  
 Parameter _$InstanceID_ __*ObjektID*__ der Roborock Instanz
  
 _**Fährt zum Aufladen zur Ladestation**_
    
 ```php
 Roborock_Charge($InstanceID);
 ```   
   
 Parameter _$InstanceID_ __*ObjektID*__ der Roborock Instanz
   
 _**Weist den Sauger an sich mit einem Sound zur Lokalisierung zu melden**_
    
 ```php
 Roborock_Locate($InstanceID);
 ```   
   
 Parameter _$InstanceID_ __*ObjektID*__ der Roborock Instanz

 _**Startet eine Reinigung um den Standort des Saugers**_
    
 ```php
 Roborock_CleanSpot($InstanceID);
 ```   
   
 Parameter _$InstanceID_ __*ObjektID*__ der Roborock Instanz
 
 
 _**Liest den Status vom Roborock aus**_
     
 ```php
 Roborock_Get_State($InstanceID);
 ```   
    
 Parameter _$InstanceID_ __*ObjektID*__ der Roborock Instanz  
 
 Gibt zurück:
 - Batterieladung
 - Reinigungsfläche
 - Reinigungszeit
 - DND Status
 - Lüfterleistung
  
 _**Seriennummer des Roborock**_
      
 ```php
 Roborock_Get_Serial_Number($InstanceID);
 ```   
     
 Parameter _$InstanceID_ __*ObjektID*__ der Roborock Instanz  
   
 _**Liest Zustand der Verbrauchsgegenstände aus**_
       
 ```php
 Roborock_Get_Consumables($InstanceID);
 ```   
      
 Parameter _$InstanceID_ __*ObjektID*__ der Roborock Instanz  
 
 _**Liest Zusammenfassung der Reinigung aus**_
        
  ```php
  Roborock_GetCleanSummary($InstanceID);
  ```   
       
 Parameter _$InstanceID_ __*ObjektID*__ der Roborock Instanz
  
 _**Liest Status Do Not Disturb Mode aus**_
          
 ```php
 Roborock_Get_DND_Mode($InstanceID);
 ```   
         
 Parameter _$InstanceID_ __*ObjektID*__ der Roborock Instanz     

 _**Stellt die Saugleistung des Staubsaugerroboters ein**_
          
 ```php
 Roborock_Fan_Power(integer $InstanceID, integer $power);
 ```   
         
 Parameter _$InstanceID_ __*ObjektID*__ der Roborock Instanz     
 Parameter _$power_ Wert von 0 - 100 zum Einstellen der Leistung     

_**Liest die gereinigte Fläche aus**_
          
 ```php
 Roborock_Get_Area_Cleaned(integer $InstanceID);
 ```   
         
 Parameter _$InstanceID_ __*ObjektID*__ der Roborock Instanz     

_**Liest die Zeit der Saugvorgänge aus**_
          
 ```php
 Roborock_Get_Time_Cleaned(integer $InstanceID);
 ```   
         
 Parameter _$InstanceID_ __*ObjektID*__ der Roborock Instanz     

_**Liest die Anzahl der Reinigungen aus**_
          
 ```php
 Roborock_Get_Cleaning_Cycles(integer $InstanceID);
 ```   
         
 Parameter _$InstanceID_ __*ObjektID*__ der Roborock Instanz     


###  b. GUIDs und Datenaustausch:

#### Roborock:

GUID: `{E65614FB-B37A-219A-4876-E5676C948C33}` 

### c. Quellen

[OpenMiHome](https://github.com/OpenMiHome/mihome-binary-protocol/blob/master/doc/PROTOCOL.md "OpenMiHome") _Wolfgang Frisch_ (GPLv3)
