# Założenia i decyzje projektowe

## Architektura

**Backend i frontend jako dwie niezależne aplikacje w jednym repozytorium.** `backend/` (Laravel, wyłącznie API) i `frontend/` (React SPA) nie współdzielą plików. Każda ma własny Dockerfile i `.env`, a komunikują się tylko przez REST API. Lokalnie działają na różnych portach (5173 i 8080), czyli na dwóch różnych originach, więc CORS jest testowany tak samo jak na produkcji.

**Mało warstw.** Kontroler tylko przyjmuje żądanie i zwraca odpowiedź. Zapis pliku spina akcja `StoreImage`, która korzysta z trzech małych serwisów: `ThumbnailGenerator`, `ImageMetadataExtractor` i `WeatherService`. Bez repozytoriów, interfejsów i DTO, bo przy tej skali nic by nie wnosiły.

**Wersje:** PHP 8.4 z OPcache i MariaDB 11.8, te same lokalnie (Docker) i na serwerze demo. Laravel 13 to najnowsza stabilna wersja, która działa na PHP 8.4.

## Lista i paginacja

**Paginacja kursorowa (`cursorPaginate(10)`, sortowanie `id desc`).** Przy infinite scrollu paginacja offsetowa psuje się, gdy ktoś w międzyczasie doda albo usunie plik. Nowy plik na górze przesuwa wszystko o jedną pozycję i element z końca strony pojawia się drugi raz. Usunięty plik przesuwa listę w drugą stronę i jeden element znika. Kursor oznacza „daj 10 rekordów o id mniejszym niż X”, więc jest odporny na takie zmiany. Jest też szybszy na dużych tabelach, bo nie wymaga `OFFSET`. Ceną jest brak numerów stron i „skoku na stronę N”, ale przy infinite scrollu nie są potrzebne.

**Front nie przeładowuje listy po zmianach.** Nowy plik jest dopisywany na początek pierwszej strony w cache TanStack Query, a usunięty jest z cache wycinany. Kursory kolejnych stron opierają się na `id`, więc dalej działają.

**Infinite scroll przez IntersectionObserver**, a nie listener na `scroll`. Pod listą jest niewidoczny element-wartownik. Kolejna strona ładuje się, gdy wartownik zbliży się do ekranu (`rootMargin: 400px`). Nie trzeba niczego przeliczać przy każdym przewinięciu.

## Pliki i miniatury

**Oryginały na dysku prywatnym** (`storage/app/private/images`) pod nazwą `UUID.rozszerzenie`. Nazwa nadana przez użytkownika jest zapisywana tylko w bazie. Dzięki temu nie ma kolizji nazw, nie trzeba sanityzować ścieżek, a z URL-a nie da się zgadywać plików. Oryginały wydaje wyłącznie endpoint `download` z oryginalną nazwą w `Content-Disposition` (z wariantem UTF-8 dla polskich znaków).

**Rozszerzenie pochodzi z zawartości pliku, a nie z nazwy.** `extension` to rozszerzenie odgadnięte z typu MIME. Plik PNG przemianowany na `.jpg` zostanie zapisany i pokazany jako PNG. Dla TIFF-a to `tif`.

**Rozdzielczość to wymiary zapisane w pliku**, odczytane przez `getimagesize()`. Dla zdjęcia z telefonu z orientacją EXIF „obróć o 90°” to wymiary przed obrotem. Tak samo liczy je reguła walidacji `dimensions`, więc wartości są spójne.

**Miniatury generowane po stronie serwera, w WebP, maks. 300 px na dłuższym boku.** Przeglądarki (Chrome, Firefox) nie wyświetlają TIFF-a, a BMP czy kilkumegabajtowe PNG nie nadają się na listę. Miniatury leżą na dysku publicznym i serwuje je nginx bezpośrednio, bez PHP. Przy generowaniu uwzględniana jest orientacja EXIF, więc zdjęcia z telefonu nie leżą na boku, a metadane z miniatur są usuwane.

**Imagick zamiast GD.** GD nie czyta TIFF-a, a TIFF jest wymagany. Obraz Dockera ma ImageMagick 7 z obsługą JPEG, PNG, WebP, TIFF i BMP.

**Sprzątanie po błędzie.** Wszystko, co może się nie udać bez skutków ubocznych (miniatura, metadane, temperatura), dzieje się przed zapisem plików. Jeśli zapis pliku albo rekordu się wywali, akcja usuwa to, co zdążyła zapisać. Usunięcie rekordu (`DELETE`) usuwa też oba pliki w zdarzeniu `deleted` modelu.

## Walidacja

**Reguły:** `mimes:jpg,jpeg,png,webp,tif,tiff,bmp`, `max:5120`, `dimensions:min_width=500,min_height=500`. Reguła `image` celowo nie jest używana, bo nie obejmuje TIFF-a. Na liście jest zarówno `tiff`, jak i `tif`: Laravel sprawdza `mimes` na podstawie rozszerzenia odgadniętego z zawartości, a dla `image/tiff` Symfony zwraca `tif`, więc bez tego wpisu żaden TIFF nie przeszedłby walidacji.

**Komunikaty po polsku** są zdefiniowane w `StoreImageRequest`. Podsumowanie w polu `message` („(i jeszcze 2 błędy)”) jest tłumaczone w `lang/pl.json` z poprawną polską odmianą.

**Walidacja na froncie jest tylko podpowiedzią.** Typ i rozmiar sprawdzam zawsze, rozdzielczość tylko dla formatów, które przeglądarka umie zdekodować (`createImageBitmap`), czyli bez TIFF-a. O wyniku zawsze decyduje backend. Błędy 422 z API trafiają pod właściwe pola formularza.

**Limity serwera ustawione z zapasem:** `upload_max_filesize`/`post_max_size` w PHP i `client_max_body_size` w nginx mają po 8 MB. Domyślne 2 MB (PHP) i 1 MB (nginx) ucięłyby plik, zanim Laravel by go zobaczył, a użytkownik dostałby niezrozumiały błąd zamiast komunikatu „maks. 5 MB”.

**Jeden plik na żądanie.** Upload wielu plików naraz komplikuje walidację (częściowy sukces), komunikaty błędów i pasek postępu. Przy jednym pliku każdy błąd dotyczy jednego, konkretnego pliku. Wiele plików można w przyszłości obsłużyć po stronie frontu jako kolejkę pojedynczych żądań.

## EXIF i IPTC

**Zapisuję surowe dane, bez interpretacji**, w kolumnach JSON `exif` i `iptc`.

- **EXIF:** `exif_read_data()`, natywny czytnik PHP dla JPEG i TIFF, który daje najpełniejsze dane z podziałem na sekcje (`IFD0`, `EXIF`, `GPS`…). Dla PNG i WebP, które też mogą zawierać EXIF, zapasowo używam Imagick (`getImageProperties('exif:*')`). Pomijam sekcję `FILE`, bo opisuje plik tymczasowy uploadu (nazwa `phpXXXX`, czas), a nie obraz.
- **IPTC:** `getimagesize()` + `iptcparse()` z segmentu APP13. W praktyce występuje tylko w JPEG-ach.
- **Format bez metadanych** (np. BMP albo PNG bez EXIF-a) dostaje `null`, a nie pusty obiekt.

**Pomijanie danych binarnych.** EXIF zawiera pola binarne (MakerNote producenta, osadzone miniatury, różne pola typu `UNDEFINED`), na których `json_encode` się wywala albo daje śmieci. Przed zapisem rekurencyjnie odrzucam wartości, które nie są poprawnym UTF-8 albo zawierają znaki sterujące, i obcinam końcowe bajty `\0`, którymi EXIF dopełnia stringi. Dzięki temu zapis JSON-a nigdy nie rzuca wyjątku. Test używa pliku z celowo binarnym MakerNote.

**Błąd odczytu metadanych nie blokuje uploadu.** Każdy odczyt jest w `try/catch`. W razie problemu zapisuję `null` i loguję ostrzeżenie.

## Temperatura (Open-Meteo)

**Wybór bieżącej godziny.** URL z treści zadania zwraca prognozę godzinową na cały dzień (`hourly.time` i `hourly.temperature_2m`) w strefie `Europe/Warsaw`. Wybieram wpis, którego czas równa się bieżącej pełnej godzinie w tej strefie (np. o 14:35 wpis `…T14:00`). Do bazy trafia temperatura i godzina pomiaru (`temperature_time`, w UTC, jak pozostałe daty).

**Cache per godzina.** Dane są godzinowe, więc w obrębie godziny API i tak zwróci to samo. Wynik jest cache'owany (`Cache::remember`) pod kluczem zawierającym datę i godzinę, do końca tej godziny. Przy wielu uploadach Open-Meteo jest odpytywane najwyżej raz na godzinę.

**Zachowanie przy awarii.** Timeout 3 s, jedna ponowna próba po 200 ms. Jeśli API nie odpowie, zwróci błąd albo w odpowiedzi nie ma bieżącej godziny, plik i tak zostaje zapisany z `temperature = null`, a do logów trafia ostrzeżenie. Temperatura jest dodatkiem, a nie warunkiem przyjęcia pliku. Błąd nie jest cache'owany, więc kolejny upload spróbuje ponownie.

**Synchronicznie, bez kolejki.** Kolejka wymagałaby stale działającego workera i obsługi stanu „temperatura jeszcze nieznana”, czyli dodatkowego procesu do utrzymania. Krótki timeout ogranicza najgorszy przypadek do kilku sekund, a dzięki cache'owi w normalnej pracy prawie każdy upload w ogóle nie wychodzi do API. Jeśli API zewnętrzne stałoby się wolne lub zawodne, przeniesienie pobierania do joba to kolejny krok.

## Prywatność i bezpieczeństwo demo

**Brak e-maila w API.** E-mail jest zapisywany w bazie (wymóg zadania), ale API jest publiczne i bez logowania, więc wystawienie adresów każdemu byłoby wyciekiem danych osobowych. `ImageResource` jawnie wylicza zwracane pola, a model dodatkowo ma `uploader_email` w `$hidden`. Testy sprawdzają, że adres nie pojawia się ani na liście, ani w szczegółach, ani w odpowiedzi na upload.

**Rate limiting (per IP):** 20 uploadów i 30 usunięć na minutę, wartości w `.env`. To dość, żeby swobodnie klikać, a jednocześnie nie da się zasypać dysku skryptem. Odczyt nie jest limitowany. Odpowiedź 429 ma polski komunikat i nagłówek `Retry-After`. Nagłówek jest wystawiony przez CORS, więc front może pokazać „spróbuj ponownie za N s”.

**noindex.** Middleware dodaje `X-Robots-Tag: noindex, nofollow` do każdej odpowiedzi Laravela. Miniatury serwowane przez nginx dostają ten sam nagłówek z konfiguracji nginx. Front ma `<meta name="robots" content="noindex, nofollow">` i `robots.txt` z `Disallow: /` (backend też).

**CORS** dopuszcza tylko origin z `FRONTEND_URL` i tylko metody GET, POST i DELETE.

**Brak uwierzytelniania.** Zadanie go nie wymaga, a utrudniłoby testowanie API. W konsekwencji każdy może usunąć każdy plik. W demo to akceptowalne, a ograniczeniem jest rate limit.

## Frontend

- **TanStack Query** trzyma stan serwera (lista, cache, ponawianie), więc nie ma ręcznego `useEffect` + `useState` do pobierania danych.
- **axios** jest użyty zamiast `fetch`, bo `fetch` nie daje postępu wysyłania (`onUploadProgress`) potrzebnego do paska postępu.
- **Pobieranie to zwykły `<a href>`** do endpointu `download`. Przeglądarka sama obsłuży nazwę pliku i duże pliki, bez blobów w pamięci.
- **Pico.css** daje schludny, responsywny wygląd prawie bez własnego CSS. Grid kart to kilkadziesiąt linii własnego CSS.
- **Stany:** ładowanie, pusta lista, błąd sieci (z przyciskiem ponowienia), koniec listy, błędy 422/429 przy formularzu, błąd usuwania na karcie.

## Środowisko lokalne

- **nginx i PHP-FPM w osobnych kontenerach.** To prostsze niż upychanie obu procesów w jednym kontenerze z supervisorem. nginx korzysta z oficjalnego obrazu i jednego pliku konfiguracyjnego.
- **Kod jest montowany jako wolumen**, a `www-data` w kontenerze ma UID/GID użytkownika hosta, więc pliki z `vendor/` czy `storage/` należą do użytkownika hosta, a nie do roota.
- **Porty wystawione tylko na `127.0.0.1`.** Baza nie jest wystawiona wcale (API łączy się z nią w sieci Dockera), więc nie koliduje z bazą zainstalowaną na hoście.
- **Opcjonalne certyfikaty CA w `docker/certs/`.** Niektóre antywirusy i firmowe proxy przechwytują HTTPS, przez co `composer` i `npm` w kontenerach nie mogą pobrać paczek. Zamiast wyłączać weryfikację TLS, obrazy mogą doinstalować dodatkowy certyfikat główny. Domyślnie katalog jest pusty i nic się nie zmienia.
- **Testy na SQLite w pamięci**, żeby były szybkie i bez stanu. Kod nie używa niczego specyficznego dla MariaDB.

## Celowo pominięte i możliwe kolejne kroki

- **Kolejka** do miniatur i temperatury przy dużym ruchu albo dużych plikach, a wtedy też stan „przetwarzanie” na liście.
- **Ochrona przed nadużyciami** mocniejsza niż rate limit: CAPTCHA przy uploadzie, limit całkowitej przestrzeni, ewentualnie usuwanie tylko przez osobę, która plik przesłała (np. token w odpowiedzi na upload).
- **Ochrona przed „bombami dekompresyjnymi”** (obraz o ogromnej rozdzielczości w małym pliku): limity zasobów ImageMagick (`policy.xml`: `width`, `height`, `area`, `memory`) i maksymalna rozdzielczość w walidacji.
- **Retencja danych osobowych**: polityka przechowywania e-maili (RODO), ewentualnie automatyczne czyszczenie starych plików w demo.
- **Wiele plików naraz** jako kolejka pojedynczych uploadów po stronie frontu.
- **Miniatury w kilku rozmiarach** (`srcset`) dla ekranów o wysokiej gęstości.
- **Testy frontendu** (Vitest + Testing Library) i testy e2e (Playwright).
- **CI** (GitHub Actions): testy backendu, Pint, build i lint frontu.
