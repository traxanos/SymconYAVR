# SymconYAVR

> **Achtung:** Das Modul wird nicht aktive von mir weiter entwickelt.

SymconYAVR ist eine Erweiterung für die Heimautomatisierung IP Symcon. Mithilfe dieser Erweiterung könnt Ihr euren Yamaha A/V-Receiver steuern.

### Inhaltverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Software-Installation](#3-software-installation)
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)
6. [WebFront](#6-webfront)
7. [PHP-Befehlsreferenz](#7-php-befehlsreferenz)

### 1. Funktionsumfang

- Ein- und Ausschalten
- Steuerung der Eingänge
- Steuerung der Lautstärke
- Aktivierung einer Szene

### 2. Voraussetzungen

- IP-Symcon ab Version 4.0
- Yamaha A/V-Receiver mit Netzwerkschnittstelle

### 3. Software-Installation

Über das Modul-Control folgende URL hinzufügen.  
`[git://github.com/traxanos/SymconYAVR.git](https://github.com/traxanos/SymconYAVR.git)`

### 4. Einrichten der Instanzen in IP-Symcon

Die Einrichtung erfolgt über die Modulverwaltung von Symcon. Nach der Installation des Moduls sollte der Dienst neugestartet werden. Danach kann man pro Gerät eine Instanz vom Typ "Yamaha AVR" anlegen.

__Konfigurationsseite__:

Name                          | Beschreibung
----------------------------- | ---------------------------------
Host                          | Die IP-Adresse des Yamaha-Receiver
Zone                          | Auswahl der Zone
Interval                      | In welchem Abstand soll der Abgleich stattfinden. (in Sekunden)
Button "Einschalten"          | Schaltet den Receiver ein.
Button "Standby"              | Schaltet den Receiver in den Standby.
Button "Szenen neu erstellen" | Erstellt das Profil für die Szenen neu.
Button "Inputs neu erstellen" | Erstellt das Profil für die Inputs neu.
Button "Status abgleichen"    | Gleicht den Status ab.

### 5. Statusvariablen und Profile

Die Statusvariablen/Kategorien werden automatisch angelegt. Das Löschen einzelner kann zu Fehlfunktionen führen.

##### Statusvariablen

Name         | Typ       | Beschreibung
------------ | --------- | ----------------
Zustand      | Boolean   | Schaltet den Receiver Ein und Aus
Volume       | Integer   | Steuert die Lautstärke in dB
Mute         | Boolean   | Schaltet den Receiver in Mute / Unmute
Szene        | Integer   | Aktiviert eine Szene
Input        | Integer   | Aktiviert einen Input

##### Profile:

Name                | Typ       | Beschreibung
------------------- | --------- | ----------------
YAVR.Inputs%ID%     | Integer   | Beinhaltet alle Eingänge. (Die Liste kann nachträglich bearbeitet werden und über "Inputs neu erstellen" auch wieder zurückgesetzt werden.)
YAVR.Scenes%ID%     | Integer   | Beinhaltet alle Szenen. (Die Liste kann nachträglich bearbeitet werden und über "Szenen neu erstellen" auch wieder zurückgesetzt werden.)
YAVR.Volume         | Float     | Profile für die Lautstärke von -80,0 bis 16,0 dB

### 6. WebFront

Über das WebFront kann der Status, die Lautstärke und die Eingänge gesteuert werden.

### 7. PHP-Befehlsreferenz

`YAVR_SetMute(integer $InstanzID, boolean $Value);`
Schaltet den Receiver mit der InstanzID $InstanzID auf den Wert $Value (true = An; false = Standby).  
Die Funktion liefert keinerlei Rückgabewert.  
`YAVR_SetState(12345, true);`

`YAVR_SetMute(integer $InstanzID, boolean $Value);`
Schaltet den Receiver Mute mit der InstanzID $InstanzID auf den Wert $Value (true = Stumm; false = Normal).  
Die Funktion liefert keinerlei Rückgabewert.  
`YAVR_SetMute(12345, true);`

`YAVR_SetScene(integer $InstanzID, string $Szene);`
Aktiviert für den Receiver mit der InstanzID $InstanzID die Szene $Szene (Aktuell gibt es nur "Scene 1-4").  
Die Funktion liefert keinerlei Rückgabewert.  
`YAVR_SetScene(12345, 'Scene 3');`

`YAVR_SetInput(integer $InstanzID, string $Input);`
Aktiviert für den Receiver mit der InstanzID $InstanzID auf den Eingang $Input (Es muss der Interne Name des Receivers sein z.B. HDMI1).  
Die Funktion liefert keinerlei Rückgabewert.  
`YAVR_SetInput(12345, 'HDMI1');`

`YAVR_SetVolume(integer $InstanzID, float $Volume);`
Stellt für den Receiver mit der InstanzID $InstanzID die Lautstärke auf $Volume (-80.0 bis 16.0 in 0.5er Schritten).  
Die Funktion liefert keinerlei Rückgabewert.  
`YAVR_SetInput(12345, 25.5);`

`YAVR_RequestData(integer $InstanzID);`
Gleicht den Status ab mit der InstanzID $InstanzID.
Die Funktion liefert keinerlei Rückgabewert.
`YAVR_RequestData(12345);`
