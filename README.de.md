[![codecov](https://codecov.io/gh/sitepark/oparl-php-client/graph/badge.svg)](https://codecov.io/gh/sitepark/oparl-php-client)
![phpstan](https://img.shields.io/badge/PHPStan-level%209-brightgreen)
![php](https://img.shields.io/badge/PHP-8.1-blue)
![php](https://img.shields.io/badge/PHP-8.2-blue)
![php](https://img.shields.io/badge/PHP-8.3-blue)
![php](https://img.shields.io/badge/PHP-8.4-blue)
![php](https://img.shields.io/badge/PHP-8.5-blue)

[English](README.md) | Deutsch

# OParl PHP Client

> **Hinweis:** Dieses Projekt wurde mit sehr viel KI-Unterstützung entwickelt. Beiträge, die mit
> Hilfe von KI entstanden sind, werden ebenfalls angenommen.

Ein PHP-Client für [OParl](https://oparl.org) 1.0 und 1.1, die Standardschnittstelle deutscher
Ratsinformationssysteme. Er bildet alle OParl-Objekttypen auf PHP-Klassen ab, löst Referenzen
zwischen ihnen auf, blättert durch seitenweise ausgelieferte Listen und unterstützt den
Aktualisierungsmechanismus der Spezifikation.

- Typisierte, unveränderliche Klassen für alle zwölf OParl-Objekttypen, geprüft gegen das
  offizielle Schema
- Referenzen werden erst bei Bedarf aufgelöst, Listen transparent seitenweise geladen
- Funktioniert mit jedem [PSR-18](https://www.php-fig.org/psr/psr-18/)-HTTP-Client, protokolliert
  über [PSR-3](https://www.php-fig.org/psr/psr-3/)
- Filter (`created_since`, `modified_since`, `omit_internal`, `limit`) mit korrekter Kodierung
- Robust gegen typische Eigenheiten von Servern: ungültige Werte wie nicht lesbare Datumsangaben
  oder URLs statt eingebetteter Objekte, herstellerspezifische Eigenschaften

## Voraussetzungen

- PHP 8.1 oder neuer
- Ein PSR-18-HTTP-Client und PSR-17-Factories, z. B. [Symfony HttpClient](https://symfony.com/doc/current/http_client.html)
  mit [nyholm/psr7](https://github.com/Nyholm/psr7) oder [Guzzle](https://docs.guzzlephp.org/)
  (siehe [Weiterleitungen](#weiterleitungen))

```sh
composer require sitepark/oparl-client symfony/http-client nyholm/psr7
```

Composer fragt, ob das Plugin von `php-http/discovery` erlaubt werden soll, das den installierten
HTTP-Client findet. Der Client funktioniert in beiden Fällen; ist das Plugin erlaubt, installiert
Composer außerdem automatisch einen PSR-18-Client und PSR-17-Factories, falls noch keine
installiert sind.

## Schnellstart

```php
use SP\OparlClient\Core\Query\Created;
use SP\OparlClient\OparlClient;
use SP\OparlClient\V1\Objects\OparlSystem;

$client = new OparlClient();

// das System-Objekt ist der Einstiegspunkt jeder OParl-Schnittstelle
$system = $client->get('https://oparl.example.org/', OparlSystem::class);

// ein System listet seine Körperschaften auf (meist eine Kommune)
$body = $system->getBody()->get()->getData()[0];

// Sitzungen dieser Körperschaft, erstellt seit dem 21.01.2024; weitere Seiten werden beim Iterieren geladen
$since = new DateTimeImmutable('2024-01-21 00:00:00', new DateTimeZone('Europe/Berlin'));
$meetings = $body->getMeeting()->withQueryParams(Created::since($since))->get();

foreach ($meetings->all() as $meeting) {
    echo $meeting->getName(), ' ', $meeting->getStart()?->format('d.m.Y H:i'), "\n";
}
```

Fast alle Eigenschaften sind in OParl optional, deshalb liefern die meisten Getter `null`, wenn
der Server den Wert nicht geschickt hat. Statische Analyse (PHPStan, Psalm) erinnert daran, das zu
prüfen; die Beispiele in dieser README lassen die Prüfungen der Kürze halber weg.

## Verwendung

### Client erzeugen

`new OparlClient()` findet einen installierten PSR-18-Client und eine PSR-17-Request-Factory über
[`php-http/discovery`](https://docs.php-http.org/en/latest/discovery.html). Alle Argumente sind
optional und werden mit Namen übergeben:

```php
$client = new OparlClient(
    httpClient: $psr18Client,
    requestFactory: $psr17Factory,
    userAgent: 'my-app/1.0 (+https://example.org/contact)',
    logger: $psr3Logger,
);
```

| Argument         | Standard                                                        |
|------------------|-----------------------------------------------------------------|
| `httpClient`     | ein installierter PSR-18-Client, gefunden von `php-http/discovery` |
| `requestFactory` | eine installierte PSR-17-Request-Factory                        |
| `userAgent`      | `oparl-client/<version>`                                        |
| `logger`         | `NullLogger`                                                    |

Jede Anfrage wird mit `Accept: application/json` gesendet. Ein `userAgent`, der Ihre Anwendung und
eine Kontaktmöglichkeit nennt, hilft den Betreibern von OParl-Servern.

Ein Client kann für alle Anfragen wiederverwendet und als Service in einem
Dependency-Injection-Container registriert werden.

### Timeouts, Weiterleitungen und Proxys

Timeouts, Weiterleitungen und Proxys werden im PSR-18-Client konfiguriert, den Sie dem
`OparlClient` übergeben. Konfigurieren Sie Timeouts: Standardmäßig begrenzen weder Symfony
HttpClient noch Guzzle die Gesamtdauer einer Anfrage, ein sehr langsam antwortender Server kann Ihre
Anwendung also lange aufhalten.

**Symfony HttpClient** folgt Weiterleitungen von sich aus:

```php
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;

$client = new OparlClient(
    httpClient: new Psr18Client(HttpClient::create([
        'timeout' => 10,         // Sekunden ohne Aktivität
        'max_duration' => 60,    // Sekunden für die gesamte Anfrage
        'max_redirects' => 5,
        // 'proxy' => 'http://proxy.example.org:3128',
    ])),
);
```

**Guzzle** folgt als PSR-18-Client nie Weiterleitungen, unabhängig von seiner Option
`allow_redirects`. Umhüllen Sie ihn mit dem `RedirectPlugin` aus
[`php-http/client-common`](https://docs.php-http.org/en/latest/plugins/redirect.html):

```sh
composer require guzzlehttp/guzzle php-http/client-common
```

```php
use GuzzleHttp\Client as GuzzleClient;
use Http\Client\Common\Plugin\RedirectPlugin;
use Http\Client\Common\PluginClient;

$client = new OparlClient(
    httpClient: new PluginClient(
        new GuzzleClient([
            'connect_timeout' => 5,
            'timeout' => 60,
            // 'proxy' => 'http://proxy.example.org:3128',
        ]),
        [new RedirectPlugin()],
    ),
);
```

#### Weiterleitungen

OParl-Server können auf ihre kanonischen URLs weiterleiten. Folgt der PSR-18-Client der
Weiterleitung nicht, schlägt die Anfrage mit einer `OparlHttpException` mit Statuscode 301 oder
302 fehl. Beachten Sie: `new OparlClient()` ohne Argumente findet Symfony HttpClient, wenn er
installiert ist, aber Guzzle, wenn das der einzige PSR-18-Client ist, der dann Weiterleitungen nicht
folgt. Übergeben Sie den Client in diesem Fall ausdrücklich wie oben gezeigt.

### Objekte abrufen

`get()` fragt ein Objekt ab und bildet es auf die angegebene Klasse ab:

```php
$body = $client->get($bodyUrl, OparlBody::class);
```

Ist der Typ eines Objekts vorher nicht bekannt, liefert `getAny()` die passende Unterklasse von
`OparlObjectV1`, bestimmt über die Eigenschaft `type`:

```php
$object = $client->getAny($someUrl);
if ($object instanceof OparlMeeting) {
    // ...
}
```

Objekte herstellerspezifischer oder unbekannter Typen kommen als einfaches `OparlObjectV1` zurück;
ihre Eigenschaften sind über `getAdditionalProperties()` verfügbar.

### Referenzen

OParl-Objekte verweisen per URL aufeinander. Diese URLs stecken in einer `OparlReference`, die den
Typ des referenzierten Objekts kennt und es bei Bedarf abfragt:

```php
$consultation = $client->get($consultationUrl, OparlConsultation::class);
$reference = $consultation->getMeeting();  // OparlReference<OparlMeeting>

$meetingUrl = $reference->getUri();        // nur die URL, keine Anfrage
$meeting = $reference->get();              // Anfrage
```

Listen von Referenzen, z. B. die Urheber einer Drucksache, sind `list<OparlReference<…>>`:

```php
foreach ($paper->getOriginatorPerson() ?? [] as $originator) {
    $person = $originator->get();
}
```

Referenzen werden über den Client aufgelöst, der das Objekt gelesen hat. Beim Serialisieren eines
Objekts wird eine Referenz als ihre URL geschrieben, genau wie im ursprünglichen JSON.

### Listen und Seiten

Listen von Objekten, z. B. alle Sitzungen einer Körperschaft, kommen als `OparlList` zurück. Der
Server kann sie in Seiten aufteilen; eine `OparlList` ist eine Seite.

```php
$page = $system->getBody()->get();          // OparlList<OparlBody>

$page->getData();                            // die Elemente dieser Seite
$page->getPagination()->getTotalElements();  // optional, je nach Server

if ($page->hasNextPage()) {
    $next = $page->fetchNextPage();
}

// alle Elemente aller Seiten; weitere Seiten werden beim Iterieren geladen
foreach ($page->all() as $body) {
    // ...
}
```

`all()` ist ein Generator: Die nächste Seite wird erst angefragt, wenn die Elemente der
vorherigen verbraucht sind; wer die Schleife früh verlässt, spart also Anfragen. `all()` beginnt
bei der Seite, auf der es aufgerufen wird, und jeder Aufruf beginnt dort von Neuem.

Kann eine Seite nicht geladen werden, schlägt `all()` mit der Exception dieser Seite fehl, z. B.
einer `OparlHttpException`, deren `getUri()` die URL der Seite ist. So wird ein Teilergebnis nie
für die vollständige Liste gehalten. Leere Seiten werden übersprungen, und das Iterieren endet mit
einer Warnung, wenn ein `next`-Link auf eine bereits angefragte URL zeigt. Zyklen werden nur an
diesen URLs erkannt, nicht an den `self`-Links der Seiten; ein Server, der auf jeder Seite denselben
`self`-Link schickt, kürzt die Liste also nicht ab.

#### Duplikate

Manche Server liefern dasselbe Objekt mehrfach aus, auf einer Seite oder auf verschiedenen Seiten,
z. B. wenn sich Daten während des Blätterns ändern. Objekte werden über ihre `id` identifiziert;
verwenden Sie sie als Array-Schlüssel, um Duplikate zu entfernen:

```php
$papers = [];
foreach ($body->getPaper()->get()->all() as $paper) {
    $papers[$paper->getId()] = $paper;  // eine spätere Kopie ersetzt eine frühere
}
```

Objekte haben keine Methode `equals()`: Vergleichen Sie `getId()`, und `getModified()`, um
Änderungen zu erkennen.

### Filter

Listen lassen sich mit den URL-Parametern der Spezifikation filtern:

```php
use SP\OparlClient\Core\Query\Limit;
use SP\OparlClient\Core\Query\Modified;
use SP\OparlClient\Core\Query\OmitInternal;

$date = new DateTimeImmutable('2024-08-16 00:00:00', new DateTimeZone('Europe/Berlin'));

$papers = $body->getPaper()
    ->withQueryParams(Modified::since($date), OmitInternal::true(), Limit::of(100))
    ->get();
```

| Klasse         | URL-Parameter                           |
|----------------|-----------------------------------------|
| `Created`      | `created_since`, `created_until`        |
| `Modified`     | `modified_since`, `modified_until`      |
| `OmitInternal` | `omit_internal`                         |
| `Limit`        | `limit` (Server dürfen ihn ignorieren)  |
| `QueryParam`   | jeder andere Parameter                  |

Die Spezifikation verlangt ein vollständiges Datum mit Uhrzeit und Zeitzone, z. B.
`2024-08-16T00:00:00+02:00`. `Created` und `Modified` akzeptieren jedes `DateTimeInterface` und
senden es mit dem Offset seiner Zeitzone, ohne Sekundenbruchteile; Strings werden unverändert
gesendet. Übergeben Sie alle Werte unkodiert; der Client kodiert sie für die URL.
`withQueryParams()` liefert eine neue Referenz und behält die bestehende Query der URL bei.

### Lokale Kopie aktuell halten

OParl 1.1 definiert einen Aktualisierungsmechanismus: Nach einem ersten Import werden nur noch die
Objekte abgefragt, die seit dem letzten Lauf geändert wurden. Mit `modified_since` liefert der
Server auch gelöschte Objekte, markiert mit `deleted`, damit sie lokal entfernt werden können.

```php
$lastRun = ...;                       // DateTimeImmutable des letzten vollständigen Laufs
$thisRun = new DateTimeImmutable();   // vor dem Lauf erfasst, damit keine Änderung währenddessen verloren geht

// ein Sicherheitsabstand gleicht Uhrabweichungen zwischen Client und Server aus
$since = $lastRun->modify('-5 minutes');

$papers = $body->getPaper()
    ->withQueryParams(Modified::since($since), OmitInternal::true())
    ->get();
foreach ($papers->all() as $paper) {
    if ($paper->isDeleted()) {
        $repository->delete($paper->getId());
    } else {
        $repository->save($paper);
    }
}
$lastRun = $thisRun;
```

`$lastRun` wird erst nach einem vollständigen Lauf weitergesetzt: Kann eine Seite nicht geladen
werden, wirft `all()` eine Exception, und der nächste Lauf beginnt wieder beim vorherigen Zeitpunkt.

### Herstellerspezifische Eigenschaften

OParl-Server dürfen Eigenschaften mit einem Herstellerpräfix ergänzen, z. B.
`"BeispielHersteller:faxNumber"`. Diese und alle anderen nicht abgebildeten Eigenschaften bleiben
so erhalten, wie `json_decode(..., true)` sie liefert, JSON-Objekte also als assoziative Arrays, und
werden beim Serialisieren wieder geschrieben:

```php
$faxNumber = $person->getAdditionalProperty('BeispielHersteller:faxNumber');
$all = $person->getAdditionalProperties();
```

### Ungültige Werte

Werte, die nicht zum von der Spezifikation verlangten Typ passen, lassen nicht die ganze Antwort
scheitern, z. B. URLs, wo eingebettete Objekte verlangt sind, eine Zahl, wo eine Referenz erwartet
wird, oder ein Datum, das sich nicht lesen lässt. Ungültige Elemente fallen aus einer Liste heraus,
ein ungültiger Einzelwert ist `null`, und eine Warnung wird protokolliert. Der ursprüngliche Wert
bleibt unter demselben Namen als Zusatzeigenschaft erhalten, geht also nicht verloren und wird beim
Serialisieren wieder geschrieben:

```php
$memberships = $person->getMembership();                // nur die gültigen eingebetteten Objekte
$original = $person->getAdditionalProperty('membership'); // z. B. ["https://…/membership/1"]
```

Ein Einzelwert, wo eine Liste verlangt ist, z. B. `"email": "info@example.org"`, wird als Liste mit
diesem Wert gelesen. Eine Referenz darf auch als eingebettetes Objekt kommen; dann wird dessen `id`
verwendet. URLs mit unzulässigen Zeichen, z. B. Leerzeichen, werden prozentkodiert; gültige URLs
bleiben exakt so, wie sie geschickt wurden. Antworten werden als UTF-8 gelesen: Ein Byte Order Mark
wird ignoriert, und Bytes, die kein gültiges UTF-8 sind, z. B. von einem Server, der Latin-1
schickt, werden durch `U+FFFD` (`�`) ersetzt.

### Fehlerbehandlung

Jede fehlgeschlagene Anfrage wirft eine `OparlException` (eine `RuntimeException`). `getUri()`
liefert die URL der fehlgeschlagenen Anfrage, `getPrevious()` die zugrunde liegende Exception,
falls vorhanden.

| Exception                  | Ursache                                                                   |
|----------------------------|---------------------------------------------------------------------------|
| `OparlHttpException`       | Statuscode außerhalb von 2xx; `getError()` liefert das OParl-Fehlerobjekt, falls geschickt |
| `OparlParseException`      | die Antwort ist kein gültiges JSON oder kein JSON-Objekt                  |
| `OparlConnectionException` | der HTTP-Client ist gescheitert, z. B. war der Server nicht erreichbar oder hat nicht rechtzeitig geantwortet |
| `OparlException`           | sonstige Fehler, z. B. eine ungültige URL, eine leere Antwort oder eine Nicht-HTTP-URL |

PSR-18 unterscheidet Timeouts nicht von anderen Netzwerkfehlern, ein Timeout ist also ebenfalls
eine `OparlConnectionException`. Dieselben Exceptions werden auch beim Iterieren über die Seiten
einer Liste geworfen.

```php
try {
    $body = $client->get($bodyUrl, OparlBody::class);
} catch (OparlConnectionException $e) {
    // später erneut versuchen
} catch (OparlHttpException $e) {
    $logger->error($e->getStatusCode() . ' for ' . $e->getUri());
} catch (OparlException $e) {
    $logger->error($e->getMessage());
}
```

Programmierfehler werden mit den Standard-Exceptions gemeldet, z. B. einer
`InvalidArgumentException` bei `Limit::of(0)`.

### Objekttypen

| Klasse                 | OParl-Typ                                                                               |
|------------------------|-----------------------------------------------------------------------------------------|
| `OparlObjectV1`        | gemeinsame Basisklasse (`id`, `type`, `created`, `modified`, `deleted`, `keyword`, `license`, `web`) |
| `OparlSystem`          | [`oparl:System`](https://dev.oparl.org/spezifikation/1.1#entity-system)                 |
| `OparlBody`            | [`oparl:Body`](https://dev.oparl.org/spezifikation/1.1#entity-body)                     |
| `OparlLegislativeTerm` | [`oparl:LegislativeTerm`](https://dev.oparl.org/spezifikation/1.1#entity-legislativeterm) |
| `OparlOrganization`    | [`oparl:Organization`](https://dev.oparl.org/spezifikation/1.1#entity-organization)     |
| `OparlPerson`          | [`oparl:Person`](https://dev.oparl.org/spezifikation/1.1#entity-person)                 |
| `OparlMembership`      | [`oparl:Membership`](https://dev.oparl.org/spezifikation/1.1#entity-membership)         |
| `OparlMeeting`         | [`oparl:Meeting`](https://dev.oparl.org/spezifikation/1.1#entity-meeting)               |
| `OparlAgendaItem`      | [`oparl:AgendaItem`](https://dev.oparl.org/spezifikation/1.1#entity-agendaitem)         |
| `OparlPaper`           | [`oparl:Paper`](https://dev.oparl.org/spezifikation/1.1#entity-paper)                   |
| `OparlConsultation`    | [`oparl:Consultation`](https://dev.oparl.org/spezifikation/1.1#entity-consultation)     |
| `OparlFile`            | [`oparl:File`](https://dev.oparl.org/spezifikation/1.1#entity-file)                     |
| `OparlLocation`        | [`oparl:Location`](https://dev.oparl.org/spezifikation/1.1#entity-location)             |

Die Klassen liegen im Namespace `SP\OparlClient\V1\Objects`; `OparlTypes` ordnet ihnen die
`type`-URLs von OParl 1.0 und 1.1 zu.

Objekte sind unveränderlich (`readonly`). Sie lassen sich mit benannten Argumenten erzeugen,
z. B. für Tests:

```php
$meeting = new OparlMeeting(id: 'https://oparl.example.org/meeting/1', name: 'Ratssitzung');
```

Fast alle Eigenschaften sind in OParl optional, deshalb liefert jeder Getter `null`, wenn der
Server den Wert nicht geschickt hat, auch bei Zahlen und Wahrheitswerten (`getOrder(): ?int`,
`getCancelled(): ?bool`). Die einzige Ausnahme ist `isDeleted()`, das dann `false` liefert, weil
die Spezifikation nur gelöschte Objekte markiert.

Zeitpunkte (`date-time`) sind `DateTimeImmutable` mit dem Offset, den der Server geschickt hat.
Datumsangaben (`date`) sind ebenfalls `DateTimeImmutable`, zu Tagesbeginn in `Europe/Berlin`.
Werte, die nicht der Spezifikation folgen, werden tolerant gelesen: Ein Zeitpunkt ohne Offset gilt
als deutsche Zeit (`Europe/Berlin`), ein Datum, wo ein Zeitpunkt erwartet wird, als Beginn dieses
Tages, ein Zeitpunkt, wo ein Datum erwartet wird, als dessen Datum; Werte, die sich gar nicht lesen
lassen, werden zu `null`, protokolliert und als Zusatzeigenschaft aufbewahrt. URLs sind Strings.

### Serialisierung

Alle Objekte implementieren `JsonSerializable` und werden im Format der Spezifikation geschrieben:

```php
$json = json_encode($meeting, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
```

Eigenschaften ohne Wert werden weggelassen, wie es die Spezifikation empfiehlt; `deleted` wird nur
geschrieben, wenn es `true` ist. Datumsangaben werden als `2024-01-21` geschrieben, Zeitpunkte als
`2024-01-21T18:00:00+01:00`, Referenzen als URL. Zusatzeigenschaften werden mitgeschrieben, von
einem Server gelesene Objekte werden also verlustfrei zurückgeschrieben.

### Objekte speichern

Um Objekte zu speichern, z. B. in einer Datenbank oder einem Cache, schreiben Sie sie als JSON und
stellen sie mit dem Client wieder her; Referenzen in den wiederhergestellten Objekten werden dann
wieder über diesen Client aufgelöst:

```php
$json = json_encode($meeting);
// ...
$meeting = $client->fromJson($json, OparlMeeting::class);
$page = $client->listFromJson($pageJson, OparlMeeting::class);
```

Objekte lassen sich auch mit `serialize()` speichern, z. B. von einem PSR-6-Cache. Referenzen
lassen sich nach `unserialize()` dann nicht mehr auflösen, weil sie die Verbindung zum Client
verlieren; ihr `getUri()` funktioniert weiterhin. Listenseiten halten den Logger des Clients und
sind nicht zum Serialisieren gedacht; speichern Sie ihre Elemente oder die Seite als JSON.

## Sicherheit

Der Client folgt jeder URL, die ein Server liefert: Referenzen und Seiten-Links, und Ihr
HTTP-Client folgt Weiterleitungen. Er liest Antworten beliebiger Größe in den Speicher. Er ist für
OParl-Server gedacht, denen Sie vertrauen. Lässt Ihre Anwendung Nutzer den Endpunkt eingeben,
beschränken Sie die erreichbaren Hosts, z. B. über einen Proxy oder Netzwerkregeln, da ein
bösartiger Server den Client sonst interne Hosts abfragen lassen könnte (SSRF). Symfony HttpClient
bietet dafür `NoPrivateNetworkHttpClient`.

## Logging

Übergeben Sie einen PSR-3-Logger, um die Meldungen des Clients zu erhalten:

| Level     | Meldung                                                                    |
|-----------|----------------------------------------------------------------------------|
| `warning` | ignorierte ungültige Werte, wegen eines Zyklus beendetes Blättern          |
| `debug`   | jede Anfrage                                                               |

Von einem Server gesendete Werte werden vor dem Protokollieren von Steuerzeichen befreit.

## Entwicklung

Die Werkzeuge installiert `composer install` über [phive](https://phar.io/).

```sh
composer install
composer analyse   # phplint, PHPStan (Level 9), php-cs-fixer, PHPCompatibility
composer test      # PHPUnit mit Coverage
composer fix       # Code formatieren
```

Commit-Nachrichten folgen [Conventional Commits](https://www.conventionalcommits.org/). Um sie
lokal zu prüfen, aktivieren Sie den Hook:

```sh
git config core.hooksPath .githooks
```

`SchemaCoverageTest` und `SchemaValuesTest` prüfen die Objektklassen gegen das OParl-1.1-Schema in
`test/resources/schema/1.1`: Jede Eigenschaft muss einen Getter passenden Typs haben, und jeder
Wert muss an seinem Getter ankommen und unverändert zurückgeschrieben werden.

## Lizenz

[MIT](LICENSE). Die OParl-Schemadateien in `test/resources/schema/1.1`, die nur zum Testen
verwendet werden, stehen unter [CC BY-SA 4.0](https://creativecommons.org/licenses/by-sa/4.0/) der
OParl-Autoren.
