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
