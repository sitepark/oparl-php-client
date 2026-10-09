![phpstan](https://img.shields.io/badge/PHPStan-level%209-brightgreen)
![php](https://img.shields.io/badge/PHP-8.1-blue)
![php](https://img.shields.io/badge/PHP-8.2-blue)
![php](https://img.shields.io/badge/PHP-8.3-blue)
![php](https://img.shields.io/badge/PHP-8.4-blue)
![php](https://img.shields.io/badge/PHP-8.5-blue)

English | [Deutsch](README.de.md)

# OParl PHP Client

A PHP client for [OParl](https://oparl.org) 1.0 and 1.1, the standard interface of German council
information systems (Ratsinformationssysteme). It maps all OParl object types to PHP classes,
resolves references between them, iterates over paginated lists and supports the incremental
update mechanism of the specification.

- Typed, immutable classes for all twelve OParl object types, checked against the official schema
- References are resolved lazily, lists are paginated transparently
- Works with any [PSR-18](https://www.php-fig.org/psr/psr-18/) http client, logs via
  [PSR-3](https://www.php-fig.org/psr/psr-3/)
- Filters (`created_since`, `modified_since`, `omit_internal`, `limit`) with correct encoding
- Robust against common server quirks: invalid values like unparsable dates or URLs instead of
  embedded objects, vendor-specific properties

## Requirements

- PHP 8.1 or newer
- A PSR-18 http client and PSR-17 factories, e.g. [Symfony HttpClient](https://symfony.com/doc/current/http_client.html)
  with [nyholm/psr7](https://github.com/Nyholm/psr7), or [Guzzle](https://docs.guzzlephp.org/)
  (see [Redirects](#redirects))

```sh
composer require sitepark/oparl-client symfony/http-client nyholm/psr7
```

Composer asks whether to allow the plugin of `php-http/discovery`, which finds the installed http
client. The client works either way; with the plugin allowed, Composer also installs a
PSR-18 client and PSR-17 factories automatically if none is installed yet.

## Quick start

```php
use SP\OparlClient\Core\Query\Created;
use SP\OparlClient\OparlClient;
use SP\OparlClient\V1\Objects\OparlSystem;

$client = new OparlClient();

// the system object is the entry point of every OParl endpoint
$system = $client->get('https://oparl.example.org/', OparlSystem::class);

// a system lists its bodies (usually one municipality)
$body = $system->getBody()->get()->getData()[0];

// meetings of that body created since 2024-01-21; further pages are fetched while iterating
$since = new DateTimeImmutable('2024-01-21 00:00:00', new DateTimeZone('Europe/Berlin'));
$meetings = $body->getMeeting()->withQueryParams(Created::since($since))->get();

foreach ($meetings->all() as $meeting) {
    echo $meeting->getName(), ' ', $meeting->getStart()?->format('d.m.Y H:i'), "\n";
}
```

Almost all properties are optional in OParl, so most getters return `null` if the server did not
send the value. Static analysis (PHPStan, Psalm) will remind you to check them; the examples in
this README leave the checks out for brevity.

## Usage

### Creating a client

`new OparlClient()` finds an installed PSR-18 client and PSR-17 request factory via
[`php-http/discovery`](https://docs.php-http.org/en/latest/discovery.html). All arguments are
optional, pass them by name:

```php
$client = new OparlClient(
    httpClient: $psr18Client,
    requestFactory: $psr17Factory,
    userAgent: 'my-app/1.0 (+https://example.org/contact)',
    logger: $psr3Logger,
);
```

| Argument         | Default                                                 |
|------------------|---------------------------------------------------------|
| `httpClient`     | an installed PSR-18 client, found by `php-http/discovery` |
| `requestFactory` | an installed PSR-17 request factory                     |
| `userAgent`      | `oparl-client/<version>`                                |
| `logger`         | `NullLogger`                                            |

Every request is sent with `Accept: application/json`. A `userAgent` that names your application
and a way to contact you helps the operators of OParl servers.

A client can be reused for all requests, and it can be registered as a service in a dependency
injection container.

### Timeouts, redirects and proxies

Timeouts, redirects and proxies are configured in the PSR-18 client you pass to the
`OparlClient`. Configure timeouts: by default, neither Symfony HttpClient nor Guzzle limits the
total duration of a request, so a server that answers very slowly can hold up your application
for a long time.

**Symfony HttpClient** follows redirects by default:

```php
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;

$client = new OparlClient(
    httpClient: new Psr18Client(HttpClient::create([
        'timeout' => 10,         // seconds of inactivity
        'max_duration' => 60,    // seconds for the whole request
        'max_redirects' => 5,
        // 'proxy' => 'http://proxy.example.org:3128',
    ])),
);
```

**Guzzle** never follows redirects when used as PSR-18 client, regardless of its
`allow_redirects` option. Wrap it with the `RedirectPlugin` of
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

#### Redirects

OParl servers may redirect to their canonical URLs. If the PSR-18 client does not follow the
redirect, the request fails with an `OparlHttpException` with status code 301 or 302. Note that
`new OparlClient()` without arguments discovers Symfony HttpClient if it is installed, but Guzzle
if that is the only PSR-18 client, which then does not follow redirects; pass the client
explicitly as shown above.

### Resolving objects

`get()` requests an object and maps it to the given class:

```php
$body = $client->get($bodyUrl, OparlBody::class);
```

If the type of an object is not known in advance, `getAny()` returns the matching subclass of
`OparlObjectV1`, determined by its `type` property:

```php
$object = $client->getAny($someUrl);
if ($object instanceof OparlMeeting) {
    // ...
}
```

Objects of vendor-specific or unknown types are returned as plain `OparlObjectV1`, with their
properties available via `getAdditionalProperties()`.

### References

OParl objects refer to each other by URL. These URLs are wrapped in an `OparlReference`, which
knows the type of the referenced object and requests it on demand:

```php
$consultation = $client->get($consultationUrl, OparlConsultation::class);
$reference = $consultation->getMeeting();  // OparlReference<OparlMeeting>

$meetingUrl = $reference->getUri();        // only the URL, no request
$meeting = $reference->get();              // request
```

Lists of references, e.g. the originators of a paper, are `list<OparlReference<…>>`:

```php
foreach ($paper->getOriginatorPerson() ?? [] as $originator) {
    $person = $originator->get();
}
```

References are resolved through the client that read the object. When an object is serialized,
a reference is written as its URL, just like in the original JSON.

### Lists and pagination

Lists of objects, e.g. all meetings of a body, are returned as `OparlList`. The server may split
them into pages; an `OparlList` is one page.

```php
$page = $system->getBody()->get();          // OparlList<OparlBody>

$page->getData();                            // the elements of this page
$page->getPagination()->getTotalElements();  // optional, depending on the server

if ($page->hasNextPage()) {
    $next = $page->fetchNextPage();
}

// all elements of all pages; further pages are fetched while iterating
foreach ($page->all() as $body) {
    // ...
}
```

`all()` is a generator: the next page is only requested when the elements of the previous page
have been consumed, so leaving the loop early saves requests. `all()` starts with the page it is
called on, and every call starts there again.

If a page can not be fetched, `all()` fails with the exception of that page, e.g. an
`OparlHttpException` whose `getUri()` is the URL of the page, so a partial result is never
mistaken for the complete list. Empty pages are skipped, and iteration stops with a warning if a
`next` link points to a URL that has already been requested. Cycles are detected by these URLs
only, not by the `self` links of the pages, so a server that sends the same `self` link on every
page does not cut the list short.

#### Duplicates

Some servers deliver the same object more than once, on one page or on different pages, e.g. when
data changes while you are paging. Objects are identified by their `id`; use it as array key to
remove duplicates:

```php
$papers = [];
foreach ($body->getPaper()->get()->all() as $paper) {
    $papers[$paper->getId()] = $paper;  // a later copy replaces an earlier one
}
```

Objects have no `equals()` method: compare `getId()`, and `getModified()` to detect changes.

### Filters

Lists can be filtered with the URL parameters defined by the specification:

```php
use SP\OparlClient\Core\Query\Limit;
use SP\OparlClient\Core\Query\Modified;
use SP\OparlClient\Core\Query\OmitInternal;

$date = new DateTimeImmutable('2024-08-16 00:00:00', new DateTimeZone('Europe/Berlin'));

$papers = $body->getPaper()
    ->withQueryParams(Modified::since($date), OmitInternal::true(), Limit::of(100))
    ->get();
```

| Class          | URL parameter                         |
|----------------|---------------------------------------|
| `Created`      | `created_since`, `created_until`      |
| `Modified`     | `modified_since`, `modified_until`    |
| `OmitInternal` | `omit_internal`                       |
| `Limit`        | `limit` (servers may ignore it)       |
| `QueryParam`   | any other parameter                   |

The specification requires a full date-time including the time zone, e.g.
`2024-08-16T00:00:00+02:00`. `Created` and `Modified` accept any `DateTimeInterface` and send it
with the offset of its time zone, without fraction of seconds; strings are sent as they are. Pass
all values unencoded; the client URL-encodes them. `withQueryParams()` returns a new reference
and keeps the existing query of the URL.

### Keeping a local copy up to date

OParl 1.1 defines an update mechanism: after an initial import, request only the objects modified
since the last run. With `modified_since`, the server also returns deleted objects, marked with
`deleted`, so they can be removed locally.

```php
$lastRun = ...;                       // DateTimeImmutable of the last complete run
$thisRun = new DateTimeImmutable();   // taken before the run, so no change during the run is missed

// a safety margin covers clock differences between client and server
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

`$lastRun` is only advanced after a complete run: if a page can not be fetched, `all()` throws an
exception and the next run starts again from the previous point in time.

### Vendor-specific properties

OParl servers may add properties with a vendor prefix, e.g. `"BeispielHersteller:faxNumber"`.
These and all other properties that are not mapped are kept as decoded by
`json_decode(..., true)`, JSON objects as associative arrays, and are written back on
serialization:

```php
$faxNumber = $person->getAdditionalProperty('BeispielHersteller:faxNumber');
$all = $person->getAdditionalProperties();
```

### Invalid values

Values that do not match the type required by the specification do not fail the whole response,
e.g. URLs where embedded objects are required, a number where a reference is expected, or a date
that can not be parsed. Invalid elements are left out of a list, an invalid single value is
`null`, and a warning is logged. The original value is kept as additional property under the same
name, so it is not lost and is written back on serialization:

```php
$memberships = $person->getMembership();                // only the valid embedded objects
$original = $person->getAdditionalProperty('membership'); // e.g. ["https://…/membership/1"]
```

A single value where a list is required, e.g. `"email": "info@example.org"`, is read as a list
with that value. A reference may also be sent as embedded object; its `id` is used then. URLs
with characters that are not allowed, e.g. spaces, are percent-encoded; valid URLs are kept
exactly as sent. Responses are read as UTF-8: a byte order mark is ignored, and bytes that are
no valid UTF-8, e.g. of a server sending Latin-1, are replaced with `U+FFFD` (`�`).

### Error handling

Every failed request throws an `OparlException` (a `RuntimeException`). `getUri()` returns the
URL of the failed request, `getPrevious()` the underlying exception, if any.

| Exception                  | Cause                                                                    |
|----------------------------|--------------------------------------------------------------------------|
| `OparlHttpException`       | status code other than 2xx; `getError()` returns the OParl error object, if sent |
| `OparlParseException`      | the response is no valid JSON or no JSON object                          |
| `OparlConnectionException` | the http client failed, e.g. the server could not be reached or did not answer in time |
| `OparlException`           | other errors, e.g. an invalid URL, an empty response or a non-http URL   |

PSR-18 does not distinguish timeouts from other network errors, so a timeout is an
`OparlConnectionException` as well. The same exceptions are thrown while iterating over the pages
of a list.

```php
try {
    $body = $client->get($bodyUrl, OparlBody::class);
} catch (OparlConnectionException $e) {
    // try again later
} catch (OparlHttpException $e) {
    $logger->error($e->getStatusCode() . ' for ' . $e->getUri());
} catch (OparlException $e) {
    $logger->error($e->getMessage());
}
```

Programming errors are reported with the standard exceptions, e.g. an `InvalidArgumentException`
for `Limit::of(0)`.

### Object types

| Class                  | OParl type                                                                              |
|------------------------|-----------------------------------------------------------------------------------------|
| `OparlObjectV1`        | common base class (`id`, `type`, `created`, `modified`, `deleted`, `keyword`, `license`, `web`) |
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

The classes are in the namespace `SP\OparlClient\V1\Objects`; `OparlTypes` maps the `type` URLs of
OParl 1.0 and 1.1 to them.

Objects are immutable (`readonly`). They can be created with named arguments, e.g. for tests:

```php
$meeting = new OparlMeeting(id: 'https://oparl.example.org/meeting/1', name: 'Ratssitzung');
```

Almost all properties are optional in OParl, so every getter returns `null` if the server did not
send the value, also for numbers and booleans (`getOrder(): ?int`, `getCancelled(): ?bool`). The
only exception is `isDeleted()`, which is `false` then, since the specification only marks deleted
objects.

Points in time (`date-time`) are `DateTimeImmutable` with the offset sent by the server. Dates
(`date`) are `DateTimeImmutable` as well, at the start of the day in `Europe/Berlin`. Values that
do not follow the specification are read tolerantly: a date-time without offset is interpreted in
German time (`Europe/Berlin`), a date where a date-time is expected as start of that day, a
date-time where a date is expected as its date; values that can not be parsed at all are read as
`null`, logged and kept as additional property. URLs are strings.

### Serialization

All objects implement `JsonSerializable` and are written in the format of the specification:

```php
$json = json_encode($meeting, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
```

Properties without value are left out, as the specification recommends; `deleted` is only written
if it is `true`. Dates are written as `2024-01-21`, points in time as `2024-01-21T18:00:00+01:00`,
references as URL. Additional properties are written as well, so objects read from a server are
written back without loss.

### Storing objects

To store objects, e.g. in a database or cache, write them as JSON and restore them with the
client; references in the restored objects are resolved through that client again:

```php
$json = json_encode($meeting);
// ...
$meeting = $client->fromJson($json, OparlMeeting::class);
$page = $client->listFromJson($pageJson, OparlMeeting::class);
```

Objects can also be stored with `serialize()`, e.g. by a PSR-6 cache. References can not be
resolved after `unserialize()` then, since they lose the connection to the client; their
`getUri()` still works. List pages hold the logger of the client and are not meant to be
serialized; store their elements, or the page as JSON.

## Security

The client follows every URL a server returns: references and pagination links, and your http
client follows redirects. It reads responses of any size into memory. It is meant for OParl
servers you trust. If your application lets users enter the endpoint, restrict the hosts it may
reach, e.g. with a proxy or network rules, since a malicious server could otherwise make it
request internal hosts (SSRF). Symfony HttpClient offers `NoPrivateNetworkHttpClient` for this.

## Logging

Pass a PSR-3 logger to receive the messages of the client:

| Level     | Message                                                                    |
|-----------|----------------------------------------------------------------------------|
| `warning` | invalid values that were ignored, pagination stopped because of a cycle    |
| `debug`   | every request                                                              |

Values sent by a server are stripped of control characters before they are logged.

## Development

The tools are installed with [phive](https://phar.io/) by `composer install`.

```sh
composer install
composer analyse   # phplint, PHPStan (level 9), php-cs-fixer, PHPCompatibility
composer test      # PHPUnit with coverage
composer fix       # format the code
```

Commit messages follow [Conventional Commits](https://www.conventionalcommits.org/). To check them
locally, enable the hook:

```sh
git config core.hooksPath .githooks
```

`SchemaCoverageTest` and `SchemaValuesTest` check the object classes against the OParl 1.1 schema
in `test/resources/schema/1.1`: every property must have a getter of a matching type, and every
value must arrive at its getter and be written back unchanged.

## License

[MIT](LICENSE). The OParl schema files in `test/resources/schema/1.1`, used for testing only, are
licensed under [CC BY-SA 4.0](https://creativecommons.org/licenses/by-sa/4.0/) by the OParl
authors.
