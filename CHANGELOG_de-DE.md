# 2.0.2
- Ein Fehler wurde behoben, wodurch bei bestimmten Produkten kein bzw. ein falsches Bild auf Suchergebnis-/Navigations-Seiten angezeigt wurde, wenn dem Hauptprodukt keine Bilder zugewiesen wurden.
- Hinweis: Durch diese Änderung werden Produkte, welche die Option Auffächern der Eigenschaften in Produktliste konfiguriert haben, als separate Produkte exportiert.
- Ein Fehler wurde behoben, wodurch auf Suchergebnis-/Navigations-Seiten ein Fehler ausgegeben wurde, wenn die Shopware-Version nicht korrekt erkannt wurde.
- Shopware 6.3.5.3 ist nun kompatibel.
- Shopware 6.4.0.0 ist nun kompatibel.

# 2.0.1
- Die Performance für das Holen der sales-frequency von Produkten, wurde verbessert.
- Die sales-frequency inkludiert nun nur noch Bestellungen des letzten Monats.
- Es wurden kleinere Fehler in der Plugin-Konfiguration behoben.
- Shopkeys, welche in der selben Browser-Session hinzugefügt wurden, konnten nicht mehr entfernt werden.
- Nachdem man zu einem anderen Verkaufskanal gewechselt hat, konnte man einen gelöschten Shopkey nicht länger eintragen, da dies zu einem Duplikaten-Shopkey Fehler führte.

# 2.0.0
- Diese Version ist ein Major Release, und enthält damit brechende Änderungen, wenn ein Erweiterungsplugin installiert ist.
- Bevor ein Upgrade durchgeführt wird, beachte den Upgrade-Guide.
- Es ist nun möglich einen Shopkey für einen Sales Channel und mehreren Sprachen zu konfigurieren.
- Filterwerte, welche zu einer keine-Ergebnisseite führen, werden nun automatisch deaktiviert, wenn die Shopware Einstellung "Filteroptionen ohne Ergebnisse deaktivieren" aktiviert ist.
- Es ist nun möglich beim Export den Parameter "productId" anzuhängen. Dieser wird das entsprechende Produkt als XML ausgeben. Wenn das Produkt nicht exportiert werden kann, wird stattdessen ein JSON zurückgegeben, welches alle Informationen enthält warum dies der Fall ist.
- Produkt Streichpreise werden nun als properties exportiert. Die Namen der Properties dafür sind old_price und old_price_net.
- Produkte mit der Option "Produkt hervorheben" erhalten im Export das property "product_promotion". Falls aktiv wird "Ja" als Wert exportiert, ansonsten wird "Nein" exportiert.
- Shopware 6.1 wird nicht länger unterstützt.
- In der Konfiguration werden nur noch Sprachen für den Verkaufskanal angezeigt, welche auch für den Verkaufskanal konfiguriert sind, anstatt alle verfügbaren Sprachen.
- Das Shopkey-Feld in der Konfiguration ist nun einzigartig. Das bedeutet, dass derselbe Shopkey nur einmal pro Kombination aus Sprache und Verkaufskanal konfiguriert werden kann.
- Es wird nun eine Ladeanimation in der Konfigurationsseite angezeigt, sobald man den Verkaufskanal oder die Sprache ändert.
- Sollte versucht werden das Plugin in einer Shopware Version zu installieren, die nicht vom Plugin unterstützt wird, wird nun ein entsprechender Fehler ausgegeben.
- Das Produktbild wird nun anhand des ersten Thumbnails ermittelt, welches größer oder gleich 600px ist, anstatt das Produktbild in der gesamten Größe.
- Die CompatibilityLayer Klassen für Shopware versionen 6.2, 6.3.1 und 6.3.2 wurden entfernt. Die Logik wurde in einen separaten FindologicService ausgelagert, welcher zusätzliche Services wie den PaginationService und den SortingService verwendet.
- Ein Fehler wurde behoben, wodurch alte SEO URLs, die bereits von Shopware als entfernt markiert wurden, vom Export erkannt und exportiert wurden.
- Ein Fehler wurde behoben, wodurch cat_urls nicht den Pfad der Domain inkludiert hatten.
- Ein Fehler wurde behoben, wodurch bei einem manuellen Aufrufs, der Listing Request Route des ProductListingFeaturesSubscriber, ein Fehler ausgegeben wurde.
- Ein Fehler wurde behoben, wodurch die Thumbnails in allen verschiedenen verfügbaren Größen exportiert wurden.
- Ein Fehler wurde behoben, wodurch die Filter nicht auf Kategorieseiten angezeigt wurden, wenn Shopware größer oder gleich 6.3.4.0 verwendet wurde.
- Ein Fehler wurde behoben, wodurch Filter nicht korrekt deaktiviert wurden, wenn ein Filter keine Werte mehr zur Verfügung hatte.
- Ein Fehler wurde behoben, wodurch Produkte nicht exportiert wurden, wenn die Hauptvariante inaktiv gesetzt war.
- Ein Fehler wurde behoben, wodurch Produkt URLs nicht in der korrekten Sprache exportiert wurden. Dies betraf nur Produkte, die keine SEO URLs verknüpft hatten.

# 1.5.3
- Shopware 6.3.5.0 ist nun kompatibel.

# 1.5.2
- Ein Fehler wurde behoben, wodurch Performance-Probleme entstanden, wenn Herstellerlogos oder Farbbilder verwendet wurden.
- Ein Fehler wurde behoben, wodurch die Einstellung "Anzahl der Produkte pro Seite" nur Werte kleiner gleich 24 berücksichtigte.
- Ein Fehler wurde behoben, wodurch beim Export ein erhöhter Speicherverbrauch entstand, wenn Produkte viele Bestellungen hatten.
- Ein Fehler wurde behoben, wodurch Smart Suggest Kategorie und Herstellerklicks nicht länger funktionierten, wenn Elastic Search aktiv war.
- Ein Fehler wurde behoben, wodurch die Sortierung andere Werte anzeigte als tatsächlich sortiert wurde. Dies betraf ältere Shopware Versionen.
- Ein Fehler wurde behoben, wodurch ein Fehler entstand, wenn Drittanbieter Plugins manuell die Methode "handleResult" beim Produkt Listing Subscriber aufgerufen hatten.

# 1.5.1
- Ein Fehler wurde behoben, wodurch das Sortierungsdropdown nicht korrekt aktualisiert wurde, nachdem eine Option gewählt wurde. Davon betroffen waren Shopware version höher oder gleich 6.3.3.0.

# 1.5.0
- Die Sortier-Option "Topseller" wird nun unterstützt.
- Zusatzfelder vom Typ "Mehrfachauswahl" werden nun unterstützt.
- Ein Fehler wurde behoben, wodurch die Shopware Criteria auf Seiten manipuliert wurde, auf denen Findologic nicht aktiv sein sollte (z.B. Checkout, etc.)
- Ein Fehler wurde behoben, wodurch Produkte nicht exportiert wurden, wenn diese ein oder mehr Attributs-werte enthielten, die die von Findologic maximal erlaubte Zeichenketten-Grenze überschritten.

# 1.4.0
- Dynamische Produktgruppen (früher bekannt als Product Streams) werden nun im Export unterstützt. Wenn sie einer Kategorie angehören, werden alle Produkte in dieser, automatisch dieser Kategorie hinzugefügt.
- Ein Fehler wurde behoben, wodurch Kategorieseiten für API nicht korrekt funktionierten, wenn am Ende des Namens der Kategorie, sich Leerzeichen befanden.

# 1.3.2
- Ein Fehler wurde behoben, wodurch die Shopware Autocomplete zusätzlich zur Findologic Smart Suggest angezeigt wurde.
- Ein Fehler wurde behoben, wodurch die Pagination auf Kategorieseiten nicht korrekt angezeigt wurde, wenn Findologic auf Kategorieseiten aktiv war.
- Ein Fehler wurde behoben, wodurch Kategorien sowie "cat_urls" nicht exportiert wurden, wenn deren Name nicht in der Standard Applikationssprache übersetzt wurde.
- Ein Fehler wurde behoben wodurch die exportierte Produkt-URL doppelte Schrägstriche beinhaltete, wenn die Domain einen Schrägstrich am Ende enthielt.
- Ein Fehler wurde behoben, wodurch Kundengruppen im Export sowie bei API Anfragen nicht berücksichtigt wurden.

# 1.3.1
- Wenn ein Shopping Guide abgeschickt wird, wird nun eine entsprechende Nachricht ausgegeben. Nachricht: "Suchergebnisse für <name-des-shopping-guides> (<Treffer> Treffer)".
- Ein Fehler wurde behoben, wodurch SEO URL Übersetzungen ignoriert wurden. Nun werden SEO URLs anhand der Sprache exportiert.
- Ein Fehler wurde behoben, wodurch auf der Homepage keine Ergebnisse ausgespielt wurden, wenn Findologic aktiv war.
- Ein Fehler wurde behoben, wodurch Produkte nicht exportiert wurden, wenn diese Zusatzfelder Konfiguriert hatten, welche lediglich Sonderzeichen enthielten.
- Das Plugin erlaubt nun, dass Drittanbieter Plugins die Anzahl der Produkte überschreiben können.
- Unsere interne Bibliothek "Findologic API" wurde auf die Version 1.6.x aktualisiert. Dies resultiert in einer kleinen performance Verbesserung.
- Shopware 6.3.3.0 wird nun unterstützt.

# 1.3.0
- Cross-Selling Kategorien können nun konfiguriert werden. Ähnlich wie schon beim Shopware 5 Plugin, werden konfigurierte Kategorien vom Export ausgeschlossen.
- Die Filter auf Suchergebnisseiten können nun auf die linke Seite konfiguriert werden.
- In der Konfiguration werden nur noch Konfigurationsmöglichkeiten für den verwendeten Integrationstyp angezeigt.
- Ein Fehler wurde behoben, wodurch Variationen auf Suchergebnisseiten ausgespielt wurden, wenn Findologic für den verwendeten Verkaufskanal deaktiviert war.
- Ein Fehler wurde behoben, wodurch Smart Suggest Klicks nicht korrekt funktionierten, wenn der Shop unter einem Unterpfad gehostet war (z.B.  https://dein-shop.com/en).
- Ein Fehler wurde behoben, wodurch Kategorien die nicht dem exportierten Verkaufskanal zugewiesen waren, trotzdem exportiert wurden.
- Ein Fehler wurde behoben, wodurch der Export scheiterte, wenn bestimmte Felder keinen Wert beinhalteten.
- Shopware 6.3.2.0 ist nun kompatibel.

# 1.2.0
- Konfigurierte Zusatzfelder werden nun als Attribut/Filter exportiert.
- Eigenschaften die als "nicht-filterbar" markiert sind, werden nicht länger als Attribut/Filter exportiert. Stattdessen werden sie als Property exportiert. Eigenschaften die als filterbar markiert sind werden immer noch als Attribut/Filter exportiert.
- Ein Fehler wurde behoben, wodurch die Suchresultatsseite nicht korrekt ausgegeben wurde, wenn der Preis eines Produktes bei 0 lag.
- Ein Fehler wurde behoben, wodurch keine Ergebnisse ausgespielt wurden, nachdem man einen Filter gewählt hatte, wenn alle Produkte im Suchergebnis die selbe Bewertung hatten.
- Ein Fehler wurde behoben, wodurch ein Fehler auf der Suchergebnisseite ausgegeben wurde, wenn ein Filter in der Smart Suggest gewählt wurde, der in der Filterkonfiguration deaktiviert war.

# 1.1.0
- Der Bewertungs-Filter wird nun unterstützt. Er wird als solcher angezeigt, wenn der Filtertyp als Bereichsslider in der Filter-Konfiguration konfiguriert ist.
- Promotions auf Kategorieseiten werden nun unterstützt. Sie können in unserem Account angelegt werden.
- Shopware 6.3.x.x wird nun unterstützt.
- Boolesche Werte werden nun in der entsprechenden Sprache exportiert (Ja/Nein) anstatt 0/1.
- Ein Fehler wurde behoben, der dazu führte, dass die Shopware Filter nicht korrekt funktionierten, wenn Findologic auf Kategorieseiten inaktiv war.
- Ein Fehler wurde behoben, wodurch die Canonical Urls bei Produkten nicht korrekt exportiert wurden.

# 1.0.1
- Ein Fehler wurde behoben, wodurch die Pagination am Ende der Suchresultatsseite nicht angezeigt wurde.
- Ein Fehler wurde behoben, wodurch Filterwerte miteinander kollidierten.
- Ein Fehler wurde behoben, welcher verursachte, dass Filter auf Kategorieseiten nicht aufgeklappt wurden, wenn sie spezielle Zeichen beinhalteten. Dieser Fehler trat nur auf, wenn die Filter auf der linken Seite angezeigt wurden.
- Ein Fehler wurde behoben, wodurch der Export fehlschlug, wenn ein Plugin die Klasse Shopware\Storefront\Framework\Routing\Router überschrieb.
- Ein Fehler wurde behoben, wodurch ein Fehler ausgegeben wurde, falls der HttpCache aktiv war. Davon betroffen waren Shops >= 6.2.

# 1.0.0
- Direct Integration und API werden als Integrationsart unterstützt.
- Funktionalität ident mit [Findologic Shopware 5 plugin](https://store.shopware.com/fin1848466805161f/findologic-suche-navigation.html).
