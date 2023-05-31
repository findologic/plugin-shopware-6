# 5.0.1

* [SW-799] Ein Problem wurde behoben, wodurch Keywords von der falschen Sprache zusätzlich exportiert wurden.
* Support für 6.5.1.x sichergestellt.

# 5.0.0

- Diese Version ist ein Major Release und enthält damit brechende Änderungen, wenn ein Erweiterungsplugin installiert ist.
- Bevor ein Upgrade durchgeführt wird, beachte den Upgrade-Guide.
- Achtung, diese Version unterstützt nur noch Shop Versionen ab v6.5.0!
- [SW-745] Für die internen API Requests wird nun das JSON Format statt XML verwendet.
- [SW-791] Die Änderungen von SW 6.5 wurden in den überschriebenen Templates übernommen.
- [SW-793] PHP Code Level wurde auf 8.1 erhöht.
- [SW-631] Varianten und UVP werden nun als separate XML Tags exportiert.
- [SW-797] Support für Varianten im Suchergebnis wurde hinzugefügt.
- [SW-784] Der interne Test build verwendet nun das Shopware Flex Setup.
- [SW-721] Codestruktur für interne Parameter wurde verbessert.
- [SW-795] Ein Problem wurde behoben, wodurch Produkte in Dynamic Product Groups eines anderen Channels angezeigt wurde.

# 4.0.4

* [SW-799] Ein Problem wurde behoben, wodurch Keywords von der falschen Sprache zusätzlich exportiert wurden.

# 4.0.3

* [SW-788] Ein Problem wurde behoben, wodurch nicht verfügbare Filterwerte auf Kategorieseiten nicht deaktiviert wurden
* [SW-789] Ein Problem wurde behoben, wodurch bei Produkten mit SEO URLs ohne "/" die generische URL exportiert wurde

# 4.0.2

* [SW-777] Ein Problem wurde behoben, wodurch es bei Bereichsslidern mit mehr als 2 Kommastellen zu Fehlern kam.
* [SW-779] Ein Problem wurde behoben, wodurch spezielle UTF-8 Symbole in der Beschreibung den Import abgebrochen haben.
* [SW-786] Ein Fehler wurde behoben, wodurch es zu einer Fehlermeldung bei neu aufgesetzten Shops kam.

# 4.0.1

- [SW-780] Ein Problem wurde behoben, wodurch unterstützte Shopware Versionen beim Installieren als inkompatible deklariert wurden.
- [SW-783] Ein Problem wurde behoben, wodurch es bei einer Installation mit Composer zu Versionskonflikten kam.

# 4.0.0

- Diese Version ist ein Major Release und enthält damit brechende Änderungen, wenn ein Erweiterungsplugin installiert ist.
- Bevor ein Upgrade durchgeführt wird, beachte den Upgrade-Guide.
- Achtung, diese Version unterstützt nur noch Shop Versionen ab v6.4.6.0!
- [SW-582] Implementierung einer neuen Route zum Cachen der Dynamic Product Groups.
- [SW-736] Support für "Erweiterte Preise" hinzugefügt.
- [SW-747] Shopware Versionen 6.2.x, 6.3.x und Versionen bis zu 6.4.5.1 werden nicht mehr unterstützt.
- [SW-749] PHP Code Level von 7.2 auf 7.4 hochgestuft.
- [SW-752] Lesbarkeit des Codes durch Laden der konkreten Services verbessert.
- [SW-761] Implementierung einer neuen Export Struktur mithilfe der internen shopware6-common Bibliothek.
- [SW-765] Performance wurde verbessert, indem man nur Informationen der Varianten ausliest, die nicht bereits im Hauptprodukt inkludiert sind.
- [SW-768] Performance wurde durch eine Überarbeitung des Cachens der Dynamic Product Groups verbessert.
- [SW-770] Plugin Konfiguration wurde von Container Klassen zu Container Selektoren geändert.
- [SW-772] Die konfigurierten Varianten Eigenschaften in "Varianten generieren" werden nun exportiert.
- [SW-773] Ein Problem wurde behoben für Varianten mit den gleichen Kategorien wie das Hauptprodukt.
- [SW-774] Nicht mehr unterstütze Erweiterungsplugins werden beim Update deinstalliert.
- [SW-764] Ein Fehler wurde behoben, wodurch ohne Suchbegriff die Slider bei Bereichsslidern nicht angezeigt wurden.

# 3.1.3
- [SW-766] Container Klassen sind nun für alle Integrationsarten konfigurierbar.

# 3.1.2
- [SW-651] Skripte für den Bereichsslider werden nur mehr auf Such- und Navigationsseiten geladen.
- [SW-712] Die Limits eines Bereichssliders werden nun aktualisiert, wenn sie sich durch Auswahl anderer Filterwerte ändern.
- [SW-763] Das Attribut 'cat_url' wird nun für alle Integrationsarten exportiert.
- [SW-753] Ein Fehler wurde behoben, wodurch es zu einer Exception bei einem invaliden Hauptprodukt mit validen Varianten kam.

# 3.1.1
- [SW-737] Filterwerte von Farbfilter müssen durch Klick auf den Text selektierbar sein.
- [SW-739] Farbbilder von selektierten Filterwerten müssen geladen werden.
- [SW-740] Ungültige SEO Urls müssen ignoriert werden.
- [SW-746] Es sollte kein Exportfehler auftreten, wenn keine "canonical" SEO Url existiert.
- [PH-719] Shopware 6.4.14.0 wurde zur Test-Matrix hinzugefügt.

# 3.1.0
- [SW-659] Eine neue Route für Export Debug Informationen wurde hinzugefügt ('/findologic/debug')
- [SW-442] ESLint für unsere JavaScript Dateien hinzugefügt
- [PH-672] Shopware 6.4.13.0 wurde zur Test-Matrix hinzugefügt.
- Downgrade der verwendeten Composer Version im internen Test-Runner.

# 3.0.0
- Diese Version ist ein Major Release und enthält damit brechende Änderungen, wenn ein Erweiterungsplugin installiert ist.
- Bevor ein Upgrade durchgeführt wird, beachte den Upgrade-Guide.
- [SW-619] Ein Performance Problem wurde behoben, für Produkte mit hunderten oder tausenden von Varianten.
- [SW-664] Neue "adapter" Klassen wurden hinzugefügt.
- [SW-724] Die Möglichkeit den Export zu erweitern wurde durch Hinzufügen von "adapter" Klassen für ungenutzte XML tags verbessert.
- [SW-666] Produkte ohne die benötigten Daten werden übersprungen.
- [SW-704] Information der Varianten wurde den "adapter" Klassen hinzugefügt.
- [SW-727] Unterstützung der Konfiguration für die billigsten Varianten im neuen Export.
- [SW-730] Die Möglichkeit den Export zu erweitern wurde durch das aufsplitten einer Klasse in zwei Klassen verbessert.
- [SW-728] Weitere Performance Verbesserungen für den neuen Export.
- [SW-705] Kompatibilität zu alten Export Erweiterungen hergestellt.
- [SW-733] Die Möglichkeit den Export zu erweitern wurde durch ein Event nach dem Verarbeiten des Produkts verbessert.
- [SW-732] Ein Upgrade Guide für die neue Version wurde definiert.
- [SW-700] Ein Fehler wurde behoben, wodurch der Hersteller-Filter nicht angezeigt wird, wenn zuvor ein Hersteller in der Smart Suggest selektiert wurde.
- [SW-591] Ein Fehler wurde behoben, wodurch der Kategorie-Filter nicht deaktiviert wurde, wenn kein Filterwert verfügbar war.
- [SW-729] Shopware 6.4.10.1 und 6.4.11.1 wurden zur Test-Matrix hinzugefügt.
- [PH-657] Shopware 6.4.12.0 wurde zur Test-Matrix hinzugefügt.
- Fixed the internal test runner for SW version 6.4.9.0.

# 2.8.2
- [SW-715] Ein Fehler wurde behoben, wodurch inkompatible Filter Werte von anderen Plugins verarbeitet wurden.
- [SW-716] Ein Fehler wurde behoben, wodurch der Preis Filter automatisch selektiert wurde wenn Produktpreise mit mehr als zwei Nachkommastellen verwendet werden.
- [SW-720] Ein Fehler wurde behoben, wodurch Varianten Eigenschaften auf Produktlisting Seiten nicht verfügbar waren.
- [SW-722] Ein Fehler wurde behoben, wodurch Direct Integration auf Navigationsseiten nicht mehr funktionierte nachdem die Smart Suggest auf mobile verwendet wurde.
- [SW-718] Update der Komponenten guzzlehttp/psr7 und minimist.

# 2.8.1
- [SW-689] Ein Fehler wurde behoben, wodurch verfügbare Varianten nicht exportiert wurden weil das Haupt-Produkt nicht verfügbar ist.
- [SW-701] Ein Fehler wurde behoben, wodurch nicht verfügbare Filterwerte nicht deaktiviert wurden.
- [SW-702] Ein Fehler wurde behoben, wodurch eine Variante mit Preis null als günstigste Variante im Export angesehen wurde.
- [SW-703] Ein Fehler wurde behoben, wodurch Filter in der Sidebar anders dargestellt wurden als im Shopware Standard.
- [SW-708] Ein Fehler wurde behoben, wodurch es beim Export von Produkten mit gleichen Erstelldatum zu inkosistenter Sortierung kam.
- [SW-709] Ein Fehler wurde behoben, wodurch der Filter Button in der mobilen Ansicht nicht dargestellt wurde.
- [SW-707] Der interne Test-Runner läuft auf Node 14, wenn die Shopware Version nicht kompatibel mit Node 16 ist.

# 2.8.0
- [SW-695] Die Plugin-Konfiguration für Cross-Selling Kategorien erlaubt die Selektierung von mehr als 500 Kategorien.
- [SW-694] Ein Fehler wurde behoben, wodurch Dynamische Produktgruppen nicht als Cross-Selling Kategorien gesetzt werden können.
- [SW-699] Ein Fehler wurde behoben, wodurch unter Umständen keine Cross-Selling Kategorie Vorschläge angezeigt werden.
- [SW-698] Ein Fehler wurde behoben, wodurch bei einer speziellen Shopware-Installation die Version nicht korrekt erkannt wurde.

# 2.7.1
- [SW-696] Ein Fehler wurde behoben, wodurch Produkte nicht geladen werden bei Paginierung auf Navigationsseiten.

# 2.7.0
- [SW-644] Die exportierten Kategorien und cat_urls enthalten nun auch Daten von nicht-Hauptvarianten.
- [SW-685] Drittanbieter Plugins können nun einfacher die Anfrage an Findologic anhand der gesetzten Sortierung manipulieren.
- [SW-683] Ein Fehler wurde behoben, wodurch bei Direct Integration auf Kategorieseiten ein flicker-Effekt auftrat, wenn viele JavaScript Ressourcen geladen wurden, bevor die Findologic JavaScript Ressourcen laden konnten.
- [SW-690] Ein Fehler wurde behoben, wodurch Produkte nicht exportiert wurden, die keinen Hersteller zugewiesen hatten.
- [SW-691] Ein Fehler wurde behoben, wodurch interne Listing-Anfragen blockiert wurden.
- [SW-693] Ein Fehler wurde behoben, wodurch Daten von inaktiven und ausverkauften Varianten exportiert wurde.
- [SW-688] Ein Fehler wurde behoben, wodurch bei Findologic deaktivierten Verkaufskanälen bei einigen Routen MySQL anstatt ElasticSearch verwendet wurde.
- [SW-692] Ein Fehler wurde behoben, wodurch Keine-Ergebnisse auf Kategorie-Listingseiten mit Dynamischen Produktgruppen angezeigt wurden.
- [SW-687] Shopware 6.4.8.1 wurde zur Test-Matrix hinzugefügt.

# 2.6.1
- [SW-677] HTML-kodierte Attributswerte, werden nun automatisch im export dekodiert.
- [SW-569] Die Option für das Aktivieren von Findologic auf Kategorieseiten, wurde in die API Sektion migriert.
- [SW-678] Ein Fehler wurde behoben, wodurch Fehler in der Konsole auf Direct Integration Seiten ausgegeben wurden, wenn die URL keinen "query" Parameter enthielt.
- [SW-624] Ein Fehler wurde behoben, wodurch bei fehlendem Shopware ElasticSearch Bundle ein Fehler ausgegeben wurde.
- [SW-681] Ein Fehler wurde behoben, wodurch unter Umständen bei manchen Shopware-Installationen die Version nicht korrekt erkannt wurde.

# 2.6.0
- [SW-673] Die Performance auf Kategorieseiten wurde verbessert, indem das Plugin nun einfacher die Information der aktuellen Kategorie erhält.
- [SW-633] Die exportierten Keywords enthalten nun Shopware "Such-Schlagwörter", anstatt der konfigurierten "Tags".
- [SW-674] Ein Fehler wurde behoben, wodurch Fehler in der Konsole auf Direct Integration Seiten ausgegeben wurden, die durch Aktualisierungen des Shopware-Listings ausgelöst wurden.

# 2.5.0
- [SW-613] Die Konfiguration hat nun eine neue "Export" Sektion, wo ausgewählt werden kann, welche Variante als Hauptvariante exportiert werden soll. Auswahlmöglichkeiten sind "Shopware Standard", "Haupt-/Eltern Produkt", "Günstigste Variante".
- [SW-589] Das Dropdown zur Auswahl der Sprache in der Konfiguration, zeigt nun nur noch Sprachen an, die auch eine URL verknüpft haben.
- [SW-672] Ein Fehler wurde behoben, wodurch das Plugin auf Kategorieseiten zu viele Anfragen an die Findologic API gesendet hatte, wodurch die Performance auf diesen Seiten negativ beeinflusst wurde.
- [SW-671] Ein Fehler wurde behoben, wodurch der automatisierte build-Prozess scheiterte, da veraltete Composer 1 Klassen verwendet wurden.
- [SW-668] Shopware 6.4.7.0 wurde zur Test-Matrix hinzugefügt.

# 2.4.1
- [SW-669] Ein Fehler wurde behoben, wodurch auf allen Listing Seiten auf denen Findologic nicht aktiv war, ein Fehler ausgegeben wurde.

# 2.4.0
- [SW-601] Anfragen an die Findologic API enthalten nun das Shopsystem und die Version.
- [SW-662] Ein Fehler wurde behoben, wodurch der Export scheiterte, wenn die konfigurierte Hauptvariante für den exportierenden Verkaufskanal nicht verfügbar war.
- [SW-663] Ein Fehler wurde behoben, wodurch Produkte nicht exportiert wurden, wenn diese ein Zusatzfeld mit dem Wert "0" enthielten.
- [SW-612] Ein Fehler wurde behoben, wodurch ein falscher Produkt-Preis exportiert wurde, wenn der exportierende Verkaufskanal nicht die konfigurierte standard Währung verwendete.
- [SW-632] Ein Fehler wurde behoben, wodurch die untere Pagination angezeigt wurde, wenn nur eine einzige Paginationsseite existierte, was nicht dem Shopware Standard entsprach.

# 2.3.0
- [SW-567] Der Kategoriefilter wurde verbessert und hat ein große UI/UX verbesserungen erhalten.
- [SW-660] Der Export achtet nun auf die konfigurierte Hauptvariante, falls gesetzt.
- [SW-614] Ein Fehler wurde behoben, wodurch die Option für die Plugin-Konfiguration nicht angezeigt wurde. Nun ist die Option "Erweiterung öffnen" verfügbar.
- [SW-661] Ein Fehler wurde behoben, wodurch Bereichsslider Filter selektiert wurden, nachdem zuvor gewählte Filter deselektiert wurden.
- [SW-650] Ein Fehler wurde behoben, wodurch bei einer Änderung der Criteria von Dritten, auf Listing Seiten ein Fehler ausgelöst wurde.
- [SW-658] Shopware 6.4.6.0 wurde zur Test-Matrix hinzugefügt.

# 2.2.1
- [SW-657] Ein Fehler wurde behoben, wodurch die Sortierung nach Release-Datum nicht korrekt funktionierte.
- [SW-649] Ein Fehler wurde behoben, wodurch der Export von Produkten scheiterte, die Mehrfachauswahl-Zusatzfelder enthielten deren Werte leer waren.
- [SW-653] Ein Fehler wurde behoben, welcher dazu führte, dass ein falscher Integrationstyp verwendet wurde, wenn ein Verkaufskanal mehrere Shopkeys für verschiedene Sprachen gesetzt hatte.
- [SW-656] Ein Fehler wurde behoben, wodurch Unterkategorien zusätzlich ohne Kategoriebaum exportiert wurden.
- [SW-654] Ein Fehler wurde behoben, wodurch die Sortieroption "Beste Ergebnisse" doppelt auf Kategorieseiten angezeigt wurde.
- [SW-652] Shopware 6.4.5.1 wurde zur Test-Matrix hinzugefügt.

# 2.2.0
- [SW-648] Ein Fehler wurde behoben, wodurch die Bilder von Promotions auf die breite des gesamten viewports ausgegeben wurden.
- [SW-595] Exportierte Kategorien und Kategorie Urls werden nun rekursiv generiert, und Filternamen werden nicht länger von speziellen Zeichen bereinigt, wenn die Integrationsart Direct Integration ist.
- Hinweis: Nach dem Update sollte überprüft werden, dass die Filter noch korrekt in der Filterkonfiguration konfiguriert sind.
- [SW-609] Der Shopware 6 Plugin Release Prozess ist nun automatisiert.
- [SW-645] Shopware 6.4.4.0 wurde zur Test-Matrix hinzugefügt.

# 2.1.2
- [SW-634] Ein Fehler wurde behoben, wodurch die Slider bei Bereichsslider Filtern mehrmals auf Mobilgeräten ausgegeben wurden.
- [SW-635] Ein Fehler wurde behoben, wodurch Produkte im Export übersprungen wurden, wenn ein Drittanbieter Plugin ein Zusatzfeld hinzugefügt hat, welches Daten in einem multidimensionalen array-format beinhaltete.
- [SW-638] Ein Fehler wurde behoben, wodurch der erste Slider beim Bereichsslider Filter nicht ausgegeben wurde, wenn mehr als ein Bereichsslider Filter ausgegeben wurde.
- [SW-636] Shopware 6.4.3.0 wurde zur Test-Matrix hinzugefügt.
- [SW-639] Die README.md wurde neu strukturiert und aktualisiert, um einen guten Erst-Installations Guide für die lokale Entwicklung bereitzustellen.
- [SW-641] Ein Fehler im Build-Prozess wurde behoben, welcher durch Traits verursacht wurde, die konflikte miteinander hatten.

# 2.1.1
- [SW-620] Ein Fehler wurde behoben, wodurch Findologic die Resultate der Shopware autocomplete beeinflusste.
- [SW-627] Ein Fehler wurde behoben, wodurch einige Shopware Services wie der API Import scheiterten, da im Plugin invalide/inkomplette DAL Relationen gepflegt wurden.
- [SW-617] Ein Fehler wurde behoben, wodurch die Pagination auf Kategorieseiten verschwand, wenn die Kategorieseite durch den Shopware Cache bereitgestellt wurde.
- [SW-621] Ein Fehler wurde behoben, wodurch auf Kategorieseiten ein Fehler ausgegeben wurde, wenn Shopware Version 6.3.2.x verwendet wurde.
- [SW-618] Ein Fehler wurde behoben, wodurch Findologic die Listing-Ergebnisse auf der Homepage beeinflusste.
- [SW-623] Shopware 6.4.2.1 wurde zur Test-Matrix hinzugefügt.

# 2.1.0
- [SW-599] Wenn nach einer Varianten-Spezifischen Produktnummer gesucht wird, wird nun die gesuchte Variante im Listing ausgegeben, anstatt der Variante die Shopware ausspielen würde.
- [SW-606/SW-504] Bereichsslider Filter haben nun einen Bereichsslider unter den Eingabefeldern.
- [SW-600] API Integrationen unterstützen nun Personalisierung mithilfe des pushAttrib Parameters. Wenn dieser gesetzt wird, werden die Werte direkt an die Findologic-API geschickt.
- [SW-607] Ein Fehler wurde behoben, welcher Fehler in der Browserkonsole ausgab, da die Datei range-slider.css nicht verfügbar war.
- [SW-616] Ein Fehler wurde behoben, wodurch die Komponenten der Filter auf Kategorieseiten überschrieben wurden, obwohl Findologic auf Kategorieseiten inaktiv gestellt wurde.
- [SW-610] Der GitHub Actions build verwendet nun Shopware 6.4.0.0 anstatt 6.4.0.0-RC1.

# 2.0.2
- [SW-590] Ein Fehler wurde behoben, wodurch bei bestimmten Produkten kein bzw. ein falsches Bild auf Suchergebnis-/Navigations-Seiten angezeigt wurde, wenn dem Hauptprodukt keine Bilder zugewiesen wurden.
- Hinweis: Durch diese Änderung werden Produkte, welche die Option Auffächern der Eigenschaften in Produktliste konfiguriert haben, als separate Produkte exportiert.
- [SW-605] Ein Fehler wurde behoben, wodurch auf Suchergebnis-/Navigations-Seiten ein Fehler ausgegeben wurde, wenn die Shopware-Version nicht korrekt erkannt wurde.
- [SW-604] Shopware 6.3.5.3 ist nun kompatibel.
- [SW-497] Shopware 6.4.0.0 ist nun kompatibel.

# 2.0.1
- [SW-596] Die Performance für das Holen der sales-frequency von Produkten, wurde verbessert.
- [SW-581] Die sales-frequency inkludiert nun nur noch Bestellungen des letzten Monats.
- [SW-583] Es wurden kleinere Fehler in der Plugin-Konfiguration behoben.
- Shopkeys, welche in der selben Browser-Session hinzugefügt wurden, konnten nicht mehr entfernt werden.
- Nachdem man zu einem anderen Verkaufskanal gewechselt hat, konnte man einen gelöschten Shopkey nicht länger eintragen, da dies zu einem Duplikaten-Shopkey Fehler führte.

# 2.0.0
- Diese Version ist ein Major Release, und enthält damit brechende Änderungen, wenn ein Erweiterungsplugin installiert ist.
- Bevor ein Upgrade durchgeführt wird, beachte den Upgrade-Guide.
- [SW-509] Es ist nun möglich einen Shopkey für einen Sales Channel und mehreren Sprachen zu konfigurieren.
- [SW-521 & SW-578] Filterwerte, welche zu einer keine-Ergebnisseite führen, werden nun automatisch deaktiviert, wenn die Shopware Einstellung "Filteroptionen ohne Ergebnisse deaktivieren" aktiviert ist.
- [SW-481] Es ist nun möglich beim Export den Parameter "productId" anzuhängen. Dieser wird das entsprechende Produkt als XML ausgeben. Wenn das Produkt nicht exportiert werden kann, wird stattdessen ein JSON zurückgegeben, welches alle Informationen enthält warum dies der Fall ist.
- [SW-498] Produkt Streichpreise werden nun als properties exportiert. Die Namen der Properties dafür sind old_price und old_price_net.
- [SW-558] Produkte mit der Option "Produkt hervorheben" erhalten im Export das property "product_promotion". Falls aktiv wird "Ja" als Wert exportiert, ansonsten wird "Nein" exportiert.
- [SW-592] Shopware 6.1 wird nicht länger unterstützt.
- [SW-540] In der Konfiguration werden nur noch Sprachen für den Verkaufskanal angezeigt, welche auch für den Verkaufskanal konfiguriert sind, anstatt alle verfügbaren Sprachen.
- [SW-542] Das Shopkey-Feld in der Konfiguration ist nun einzigartig. Das bedeutet, dass derselbe Shopkey nur einmal pro Kombination aus Sprache und Verkaufskanal konfiguriert werden kann.
- [SW-554] Es wird nun eine Ladeanimation in der Konfigurationsseite angezeigt, sobald man den Verkaufskanal oder die Sprache ändert.
- [SW-512] Sollte versucht werden das Plugin in einer Shopware Version zu installieren, die nicht vom Plugin unterstützt wird, wird nun ein entsprechender Fehler ausgegeben.
- [SW-561] Das Produktbild wird nun anhand des ersten Thumbnails ermittelt, welches größer oder gleich 600px ist, anstatt das Produktbild in der gesamten Größe.
- [SW-547] Die CompatibilityLayer Klassen für Shopware versionen 6.2, 6.3.1 und 6.3.2 wurden entfernt. Die Logik wurde in einen separaten FindologicService ausgelagert, welcher zusätzliche Services wie den PaginationService und den SortingService verwendet.
- [SW-575] Ein Fehler wurde behoben, wodurch alte SEO URLs, die bereits von Shopware als entfernt markiert wurden, vom Export erkannt und exportiert wurden.
- [SW-576] Ein Fehler wurde behoben, wodurch cat_urls nicht den Pfad der Domain inkludiert hatten.
- [SW-580] Ein Fehler wurde behoben, wodurch bei einem manuellen Aufrufs, der Listing Request Route des ProductListingFeaturesSubscriber, ein Fehler ausgegeben wurde.
- [SW-579] Ein Fehler wurde behoben, wodurch die Thumbnails in allen verschiedenen verfügbaren Größen exportiert wurden.
- [SW-585] Ein Fehler wurde behoben, wodurch die Filter nicht auf Kategorieseiten angezeigt wurden, wenn Shopware größer oder gleich 6.3.4.0 verwendet wurde.
- [SW-586] Ein Fehler wurde behoben, wodurch Filter nicht korrekt deaktiviert wurden, wenn ein Filter keine Werte mehr zur Verfügung hatte.
- [SW-520] Ein Fehler wurde behoben, wodurch Produkte nicht exportiert wurden, wenn die Hauptvariante inaktiv gesetzt war.
- [SW-543] Ein Fehler wurde behoben, wodurch Produkt URLs nicht in der korrekten Sprache exportiert wurden. Dies betraf nur Produkte, die keine SEO URLs verknüpft hatten.

# 1.5.3
- [SW-574] Shopware 6.3.5.0 ist nun kompatibel.

# 1.5.2
- [SW-551] Ein Fehler wurde behoben, wodurch Performance-Probleme entstanden, wenn Herstellerlogos oder Farbbilder verwendet wurden.
- [SW-556] Ein Fehler wurde behoben, wodurch die Einstellung "Anzahl der Produkte pro Seite" nur Werte kleiner gleich 24 berücksichtigte.
- [SW-557] Ein Fehler wurde behoben, wodurch beim Export ein erhöhter Speicherverbrauch entstand, wenn Produkte viele Bestellungen hatten.
- [SW-559] Ein Fehler wurde behoben, wodurch Smart Suggest Kategorie und Herstellerklicks nicht länger funktionierten, wenn Elastic Search aktiv war.
- [SW-562] Ein Fehler wurde behoben, wodurch die Sortierung andere Werte anzeigte als tatsächlich sortiert wurde. Dies betraf ältere Shopware Versionen.
- [SW-566] Ein Fehler wurde behoben, wodurch ein Fehler entstand, wenn Drittanbieter Plugins manuell die Methode "handleResult" beim Produkt Listing Subscriber aufgerufen hatten.

# 1.5.1
- [SW-550] Ein Fehler wurde behoben, wodurch das Sortierungsdropdown nicht korrekt aktualisiert wurde, nachdem eine Option gewählt wurde. Davon betroffen waren Shopware version höher oder gleich 6.3.3.0.

# 1.5.0
- [SW-539] Die Sortier-Option "Topseller" wird nun unterstützt.
- [SW-546] Zusatzfelder vom Typ "Mehrfachauswahl" werden nun unterstützt.
- [SW-544] Ein Fehler wurde behoben, wodurch die Shopware Criteria auf Seiten manipuliert wurde, auf denen Findologic nicht aktiv sein sollte (z.B. Checkout, etc.)
- [SW-545] Ein Fehler wurde behoben, wodurch Produkte nicht exportiert wurden, wenn diese ein oder mehr Attributs-werte enthielten, die die von Findologic maximal erlaubte Zeichenketten-Grenze überschritten.

# 1.4.0
- [SW-357] Dynamische Produktgruppen (früher bekannt als Product Streams) werden nun im Export unterstützt. Wenn sie einer Kategorie angehören, werden alle Produkte in dieser, automatisch dieser Kategorie hinzugefügt.
- [SW-536] Ein Fehler wurde behoben, wodurch Kategorieseiten für API nicht korrekt funktionierten, wenn am Ende des Namens der Kategorie, sich Leerzeichen befanden.

# 1.3.2
- [SW-525] Ein Fehler wurde behoben, wodurch die Shopware Autocomplete zusätzlich zur Findologic Smart Suggest angezeigt wurde.
- [SW-527] Ein Fehler wurde behoben, wodurch die Pagination auf Kategorieseiten nicht korrekt angezeigt wurde, wenn Findologic auf Kategorieseiten aktiv war.
- [SW-532] Ein Fehler wurde behoben, wodurch Kategorien sowie "cat_urls" nicht exportiert wurden, wenn deren Name nicht in der Standard Applikationssprache übersetzt wurde.
- [SW-534] Ein Fehler wurde behoben, wodurch die exportierte Produkt-URL doppelte Schrägstriche beinhaltete, wenn die Domain einen Schrägstrich am Ende enthielt.
- [SW-529] Ein Fehler wurde behoben, wodurch Kundengruppen im Export sowie bei API Anfragen nicht berücksichtigt wurden.

# 1.3.1
- [SW-475] Wenn ein Shopping Guide abgeschickt wird, wird nun eine entsprechende Nachricht ausgegeben. Nachricht: "Suchergebnisse für <name-des-shopping-guides> (<Treffer> Treffer)".
- [SW-513] Ein Fehler wurde behoben, wodurch SEO URL Übersetzungen ignoriert wurden. Nun werden SEO URLs anhand der Sprache exportiert.
- [SW-516] Ein Fehler wurde behoben, wodurch auf der Homepage keine Ergebnisse ausgespielt wurden, wenn Findologic aktiv war.
- [SW-522] Ein Fehler wurde behoben, wodurch Produkte nicht exportiert wurden, wenn diese Zusatzfelder Konfiguriert hatten, welche lediglich Sonderzeichen enthielten.
- [SW-502] Das Plugin erlaubt nun, dass Drittanbieter Plugins die Anzahl der Produkte überschreiben können.
- [SW-515] Unsere interne Bibliothek "Findologic API" wurde auf die Version 1.6.x aktualisiert. Dies resultiert in einer kleinen performance Verbesserung.
- [SW-523] Shopware 6.3.3.0 wird nun unterstützt.

# 1.3.0
- [SW-428] Cross-Selling Kategorien können nun konfiguriert werden. Ähnlich wie schon beim Shopware 5 Plugin, werden konfigurierte Kategorien vom Export ausgeschlossen.
- [SW-466] Die Filter auf Suchergebnisseiten können nun auf die linke Seite konfiguriert werden.
- [SW-466] In der Konfiguration werden nur noch Konfigurationsmöglichkeiten für den verwendeten Integrationstyp angezeigt.
- [SW-496] Ein Fehler wurde behoben, wodurch Variationen auf Suchergebnisseiten ausgespielt wurden, wenn Findologic für den verwendeten Verkaufskanal deaktiviert war.
- [SW-501] Ein Fehler wurde behoben, wodurch Smart Suggest Klicks nicht korrekt funktionierten, wenn der Shop unter einem Unterpfad gehostet war (z.B.  https://dein-shop.com/en).
- [SW-500] Ein Fehler wurde behoben, wodurch Kategorien die nicht dem exportierten Verkaufskanal zugewiesen waren, trotzdem exportiert wurden.
- [SW-483] Ein Fehler wurde behoben, wodurch der Export scheiterte, wenn bestimmte Felder keinen Wert beinhalteten.
- [SW-503] Shopware 6.3.2.0 ist nun kompatibel.

# 1.2.0
- [SW-453] Konfigurierte Zusatzfelder werden nun als Attribut/Filter exportiert.
- [SW-484] Eigenschaften die als "nicht-filterbar" markiert sind, werden nicht länger als Attribut/Filter exportiert. Stattdessen werden sie als Property exportiert. Eigenschaften die als filterbar markiert sind werden immer noch als Attribut/Filter exportiert.
- [SW-482] Ein Fehler wurde behoben, wodurch die Suchresultatsseite nicht korrekt ausgegeben wurde, wenn der Preis eines Produktes bei 0 lag.
- [SW-485] Ein Fehler wurde behoben, wodurch keine Ergebnisse ausgespielt wurden, nachdem man einen Filter gewählt hatte, wenn alle Produkte im Suchergebnis die selbe Bewertung hatten.
- [SW-467] Ein Fehler wurde behoben, wodurch ein Fehler auf der Suchergebnisseite ausgegeben wurde, wenn ein Filter in der Smart Suggest gewählt wurde, der in der Filterkonfiguration deaktiviert war.

# 1.1.0
- [SW-426/SW-459] Der Bewertungs-Filter wird nun unterstützt. Er wird als solcher angezeigt, wenn der Filtertyp als Bereichsslider in der Filter-Konfiguration konfiguriert ist.
- [SW-430] Promotions auf Kategorieseiten werden nun unterstützt. Sie können in unserem Account angelegt werden.
- [SW-473] Shopware 6.3.x.x wird nun unterstützt.
- [SW-411] Boolesche Werte werden nun in der entsprechenden Sprache exportiert (Ja/Nein) anstatt 0/1.
- [SW-471] Ein Fehler wurde behoben, der dazu führte, dass die Shopware Filter nicht korrekt funktionierten, wenn Findologic auf Kategorieseiten inaktiv war.
- [SW-469] Ein Fehler wurde behoben, wodurch die Canonical Urls bei Produkten nicht korrekt exportiert wurden.

# 1.0.1
- [SW-465] Ein Fehler wurde behoben, wodurch die Pagination am Ende der Suchresultatsseite nicht angezeigt wurde.
- [SW-451] Ein Fehler wurde behoben, wodurch Filterwerte miteinander kollidierten.
- [SW-463] Ein Fehler wurde behoben, welcher verursachte, dass Filter auf Kategorieseiten nicht aufgeklappt wurden, wenn sie spezielle Zeichen beinhalteten. Dieser Fehler trat nur auf, wenn die Filter auf der linken Seite angezeigt wurden.
- [SW-462] Ein Fehler wurde behoben, wodurch der Export fehlschlug, wenn ein Plugin die Klasse Shopware\Storefront\Framework\Routing\Router überschrieb.
- [SW-468] Ein Fehler wurde behoben, wodurch ein Fehler ausgegeben wurde, falls der HttpCache aktiv war. Davon betroffen waren Shops >= 6.2.

# 1.0.0
- Direct Integration und API werden als Integrationsart unterstützt.
- Funktionalität ident mit [Findologic Shopware 5 plugin](https://store.shopware.com/fin1848466805161f/findologic-suche-navigation.html).
