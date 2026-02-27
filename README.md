# Mini Shop – Senior naloga (PHP)

To je enostavna PHP aplikacija, ki uporablja zunanji API za pridobivanje skupin in artiklov ter omogoča prikaz, filtriranje in straničenje rezultatov.

Aplikacija je narejena brez ogrodij (frameworkov) in brez Composerja. Uporabljen je čist PHP 8+, Bootstrap za izgled ter lasten preprost servisni sloj.

## Funkcionalnosti

- Pridobivanje skupin iz API-ja
- Prikaz drevesne strukture skupin
- Pridobivanje artiklov iz API-ja
- Prikaz artiklov po skupinah (vključno s podskupinami)
- Backend filter po ceni (min / max)
- Straničenje (paging)
- Prikaz podrobnosti artikla
- Prikaz popusta (če obstaja stara cena)
- Preprost cache na datotečnem sistemu


## Tehnologija

- PHP 8+
- Bootstrap 5 (CDN)
- cURL za API klice
- Datotečni cache (brez baze)


## Struktura projekta

public/
- index.php

src/
- Services/
  - ApiClient.php
  - Cache.php
  - GroupService.php
  - ItemService.php
- Utils/
  - Formatter.php
  - Html.php
- config.php

## Zagon aplikacije

1. Namesti PHP 8 ali novejši
2. V root mapi projekta zaženi:
php -S localhost:8080 -t public
3. Odpri v brskalniku:
http://localhost:8080

## Konfiguracija

Nastavitve API-ja so v: src/config.php

Tam se nastavi:
- baseUrl
- API ključ
- timeout
- pot do cache mape
- čas trajanja cache-a


## Cache

Aplikacija uporablja preprost file cache.  
Če API vrne napako, se poskusi uporabiti zadnji shranjeni odgovor.

Cache se nahaja v mapi, definirani v config.php.

## Straničenje

Straničenje deluje na nivoju aplikacije.  
Po filtriranju rezultatov se podatki razdelijo na strani.

Parametri:
- `page`
- `per`

Primer:?group=30179&page=2&per=18



## Filter cene

Filter deluje na backendu.

Parametri:?min=100&max=500

Možno je kombinirati z group in paging parametri.


## Opombe

- Aplikacija je namenjena kot demonstracija strukture kode in dela z API-jem.
- Ni uporabljen noben framework.
- Koda je razdeljena na servisni sloj in prikaz (index.php).
- Vsa logika za API in obdelavo podatkov je v `src/Services`.


## Avtor

Emir Smlatić