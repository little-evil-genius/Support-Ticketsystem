# Support-Ticketsystem
Dieses Plugin erweitert das Forum um ein strukturiertes Support-Ticketsystem, das speziell für Foren ausgerichtet ist, in denen für jede Support Anfrage/Problem ein eigenes Thema erstellt wird.<br>
<br>
Im Administrationsbereich (ACP) können individuelle Präfixe für den Support definiert werden, damit Themen beim Erstellen eindeutig eingeordnet werden können. Diese Präfixe können beispielsweise Design, Technik, Lore oder organisatorische Anliegen sein. Für jedes Präfix wird ein klarer und aussagekräftiger Name festgelegt, der später im Auswahlmenü bei der Themenerstellung erscheint. Zusätzlich lässt sich die Darstellung der Präfixe gestalten - von einfachem Text bis hin zu einer Formatierung mit HTML. Darüber hinaus wird für jedes Präfix festgelegt, welche Teammitglieder für entsprechende Anfragen zuständig sind. Dabei kann sowohl eine gezielte Auswahl als auch die Zuweisung an das gesamte Team erfolgen.<br>
<br>
Beim Erstellen eines neuen Themas im Support-Bereich ist die Auswahl eines passenden Präfixes verpflichtend. Sobald ein Thema erstellt wurde, erhalten alle zuständigen Teammitglieder einen Banner auf dem Index. Über diesen Banner kann ein Teammitglied die Anfrage direkt übernehmen. Nach der Übernahme verschwindet die Benachrichtigung für alle anderen Teammitglieder. Im Thema selbst und Forumdisplay wird das entsprechende Teammitglied angezeigt, damit ersichtlich ist, wer für das Thema verantwortlich ist. Bei installierter MyAlerts-Erweiterung - wird der/die Autor:in des Themas darüber informiert werden, welches Teammitglied die Anfrage übernommen hat.<br>
<br>
Der Hinweis auf ein offenes Support-Thema bleibt für das zuständige Teammitglied so lange sichtbar, bis das Thema als erledigt markiert wurde oder sich nicht mehr im entsprechenden Support-Forum befindet, etwa durch Verschiebung ins Archiv. Themen können sowohl von Teammitgliedern als auch vom ursprünglichen Autor:in selbst als erledigt gekennzeichnet werden.<br>
<br>
Darüber hinaus besteht jederzeit die Möglichkeit eine bereits übernommene Anfrage wieder freizugeben. Dies kann direkt über einen entsprechenden Link im Banner erfolgen. In diesem Fall wird die Benachrichtigung erneut für alle zuständigen Teammitglieder angezeigt, sodass die Anfrage neu verteilt werden kann.

# Vorrausetzung
- Das ACP Modul <a href="https://github.com/little-evil-genius/rpgstuff_modul" target="_blank">RPG Stuff</a> <b>muss</b> vorhanden sein.
- Der <a href="https://doylecc.altervista.org/bb/downloads.php?dlid=26&cat=2" target="_blank">Accountswitcher</a> von doylecc <b>muss</b> installiert sein.

# Empfohlen
- <a href="https://github.com/MyBBStuff/MyAlerts\" target="_blank">MyAlerts</a> von EuanT

# Datenbank-Änderungen
hinzugefügte Tabelle:
- ticketsystem<br>
<br>
hinzugefügte Spalte in threads:
- ticketsystem_prefix
- ticketsystem_teammember
- ticketsystem_solved

# Neue Sprachdateien
- deutsch_du/admin/ticketsystem.lang.php
- deutsch_du/ticketsystem.lang.php

# Einstellungen<br>
- Teamgruppen
- Spitzname
- Support-Bereich<br>
<br>
<b>HINWEIS:</b><br>
Das Plugin ist kompatibel mit den klassischen Profilfeldern von MyBB und/oder dem <a href="https://github.com/katjalennartz/application_ucp">Steckbrief-Plugin von Risuena</a>.

# Neue Template-Gruppe innerhalb der Design-Templates
- Support-Ticketsystem

# Neue Templates (nicht global!)
- ticketsystem_banner
- ticketsystem_forumdisplay
- ticketsystem_newthread
- ticketsystem_showthread
- ticketsystem_showthread_button<br>
<br>
<b>HINWEIS:</b><br>
Das Layout wurde auf ein MyBB Default Design angepasst.

# Neue Variablen
- header: {$ticketsystem_banner}
- showthread: {$ticketsystem_button} + {$ticketsystem_prefix} + {$ticketsystem_teammember}
- forumdisplay_thread: {$ticketsystem_prefix} + {$ticketsystem_teammember}

# Benutzergruppen-Berechtigungen setzen
Damit alle Admin-Accounts Zugriff auf die Verwaltung der Ticketsystem-Präfixe haben im ACP, müssen unter dem Reiter Benutzer & Gruppen » Administrator-Berechtigungen » Benutzergruppen-Berechtigungen die Berechtigungen einmal angepasst werden. Die Berechtigungen für das Ticketsystem befinden sich im Tab 'RPG Erweiterungen'.

# Links
### ACP
index.php?module=rpgstuff-rpgstuff-ticketsystem

# Demo
## ACP
<img src="https://stormborn.at/plugins/ticketsystem_acp.png">
<img src="https://stormborn.at/plugins/ticketsystem_add.png">

## Forum
<img src="https://stormborn.at/plugins/ticketsystem_newthread.png">
<img src="https://stormborn.at/plugins/ticketsystem_forumdisplay.png">
<img src="https://stormborn.at/plugins/ticketsystem_showthread.png">
<img src="https://stormborn.at/plugins/ticketsystem_banner.png">
