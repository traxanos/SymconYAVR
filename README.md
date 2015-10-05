# SymconYAVR

SymconYAVR ist eine Erweiterung für die Heimautomatisierung IP Symcon. Mithilfe dieser Erweiterung könnt Ihr euren Yamaha A/V-Receiver steuern.

## Einrichtung

Die Einrichtung erfolgt über Modulverwaltung von Symcon. Nach der Installation des Moduls sollte der Dienst neugestartet werden. Danach kann man pro Gerät eine Instanz vom Typ "Yamaha AVR" anlegen.

## Einstellungen

* **Host**  _Der Hostname bzw. die IP-Adresse_
* **Zone**  _Welche Zone mit dieser Instanz gesteuert werden soll_
* **Interval**  _In welchem Abstand soll der Status abgeglichen werden_

## Voraussetzung

* ab Symcon Version 4
* Yamaha A/V-Receiver mit Netzwerkschnittstelle

## Funktionen

	// Manuelle Abfrage des Status
	YAVR_RequestData($id);
	
	// Ein- / Ausschalten
	YAVR_SetState($id, $state);
	
	// Mute an / aus
	YAVR_SetMute($id, $state);
	
	// Aktivierung einer Szene
	// Aktuell gibt es nur "Scene 1" bis "Scene 4"
	YAVR_SetScene($id, $name);
	
	// Schalte auf einen Eingang (z.B. HMDI1)
	YAVR_SetInput($id, $input);
	
	// Lautstärke von -80 bis 16
	YAVR_SetVolume($id, $volume);
	
	// Frage alle möglichen Szenen ab
	YAVR_ListScenes($id);
	
	// Fragt alle möglichen Eingänge ab
	YAVR_ListInputs($id);