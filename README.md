# StockPress

Prosta aplikacja webowa do przechowywania plików graficznych. Backend (REST API w Laravelu) i frontend (React) są od siebie niezależne. Każdy ma własny Dockerfile i `.env`, więc mogą działać na osobnych serwerach.

Funkcje:

- lista przesłanych plików z miniaturą, rozszerzeniem, rozmiarem, rozdzielczością i imieniem osoby przesyłającej, doładowywana po 10 (infinite scroll);
- przesyłanie pliku (JPG, PNG, WebP, TIFF, BMP; maks. 5 MB; min. 500 × 500 px) z imieniem i adresem e-mail;
- pobieranie oryginału i usuwanie pliku z poziomu listy;
- zapis surowych danych EXIF/IPTC i aktualnej temperatury w Katowicach (Open-Meteo) dla każdego pliku.

Demo online: <https://stockpress.itdevstudio.pl> (front chroniony hasłem), API: <https://api.stockpress.itdevstudio.pl/api/images>.

Decyzje projektowe i ich uzasadnienie są opisane w [ZALOZENIA.md](ZALOZENIA.md).

## Stack

| Warstwa | Technologie |
|---|---|
| Backend | PHP 8.4, Laravel 13, Intervention Image 4 + Imagick, MariaDB 11.8 |
| Frontend | React 19, TypeScript, Vite, TanStack Query, axios, react-intersection-observer, Pico.css |
| Lokalnie | Docker Compose: PHP-FPM, nginx, MariaDB, serwer deweloperski Vite |

Wszystkie zależności są na licencjach MIT, BSD lub Apache-2.0.

## Wymagania

- Docker z Docker Compose v2
- `make` (opcjonalnie; polecenia z `Makefile` można też wpisać ręcznie)
- wolne porty `8080` (API) i `5173` (frontend) na `127.0.0.1`

Lokalnie nie trzeba instalować PHP, Composera ani Node.js.

## Uruchomienie lokalne

```bash
make init
```

To jedno polecenie:

1. kopiuje `backend/.env.example` → `backend/.env` i `frontend/.env.example` → `frontend/.env` (jeśli plików jeszcze nie ma),
2. buduje obrazy i uruchamia kontenery,
3. instaluje zależności Composera (zależności npm instaluje sam kontener frontendu przy pierwszym starcie),
4. generuje `APP_KEY`, uruchamia migracje i tworzy link `public/storage`.

Po chwili aplikacja działa pod adresami:

- frontend: <http://localhost:5173>
- API: <http://localhost:8080/api/images>

Kolejne uruchomienia: `make up`, zatrzymanie: `make down`. Pozostałe polecenia są w `Makefile` (`make logs`, `make shell`, `make fresh`).

Bez `make`:

```bash
cp backend/.env.example backend/.env
cp frontend/.env.example frontend/.env
docker compose up -d --build
docker compose exec -u www-data api composer install
docker compose exec -u www-data api php artisan key:generate
docker compose exec -u www-data api php artisan migrate --force
docker compose exec -u www-data api php artisan storage:link
```

### Porty i użytkownik

Porty są wystawione tylko na `127.0.0.1`. Można je zmienić w głównym `.env` (wzór: `.env.example`). Przy zmianie portów trzeba też zaktualizować `APP_URL`/`FRONTEND_URL` w `backend/.env` i `VITE_API_URL` w `frontend/.env`. Jeśli użytkownik na hoście ma UID/GID inne niż 1000, trzeba ustawić `UID`/`GID` w `.env`, żeby pliki tworzone w kontenerach należały do niego.

### Proxy lub antywirus przechwytujący TLS

Jeśli w sieci działa proxy albo antywirus podmieniający certyfikaty HTTPS, kontenery im nie ufają i `composer`/`npm` nie pobiorą paczek. Wtedy certyfikat główny (`*.crt`, PEM) trzeba umieścić w `backend/docker/certs/` i `frontend/docker/certs/`, a następnie przebudować obrazy (`docker compose build`). Oba katalogi są w `.gitignore` i domyślnie puste.

## Testy

```bash
make test
```

Testy (feature, PHPUnit) działają na SQLite w pamięci, z `Storage::fake()` i `Http::fake()`, więc nie zostawiają plików i nie odpytują Open-Meteo. Obrazy testowe (w tym TIFF i BMP) są generowane w locie przez Imagick. Jedyny plik w `tests/fixtures` to mały JPEG z danymi EXIF i IPTC.

Frontend: `docker compose exec frontend npm run build` (sprawdzenie typów i build produkcyjny) oraz `docker compose exec frontend npm run lint`.

## API

Bazowy adres: `APP_URL` + `/api` (lokalnie `http://localhost:8080/api`). Odpowiedzi są w formacie JSON. Komunikaty błędów są po polsku. Uwierzytelnianie nie jest wymagane.

| Metoda | Ścieżka | Opis |
|---|---|---|
| `GET` | `/images` | lista plików, 10 na stronę, od najnowszych (paginacja kursorowa) |
| `POST` | `/images` | przesłanie pliku (`multipart/form-data`: `file`, `name`, `email`) |
| `GET` | `/images/{id}` | szczegóły pliku razem z surowymi danymi EXIF/IPTC |
| `GET` | `/images/{id}/download` | pobranie oryginału (`Content-Disposition: attachment`, oryginalna nazwa) |
| `DELETE` | `/images/{id}` | usunięcie rekordu, oryginału i miniatury |

Adres e-mail osoby przesyłającej jest zapisywany w bazie, ale API nigdy go nie zwraca.

### `GET /api/images`

Kolejną stronę pobiera się z parametrem `cursor` równym `meta.next_cursor`. `null` oznacza koniec listy.

```json
{
  "data": [
    {
      "id": 23,
      "original_name": "IMG_2041.jpg",
      "thumbnail_url": "http://localhost:8080/storage/thumbnails/5c0e…-….webp",
      "download_url": "http://localhost:8080/api/images/23/download",
      "extension": "jpg",
      "size": 425890,
      "width": 2048,
      "height": 1536,
      "uploader_name": "Anna",
      "temperature": 11.1,
      "created_at": "2026-10-16T09:10:19.000000Z"
    }
  ],
  "links": { "first": null, "last": null, "prev": null, "next": "http://localhost:8080/api/images?cursor=eyJpZCI6MTMsIl9wb2ludHNUb05leHRJdGVtcyI6dHJ1ZX0" },
  "meta": {
    "path": "http://localhost:8080/api/images",
    "per_page": 10,
    "next_cursor": "eyJpZCI6MTMsIl9wb2ludHNUb05leHRJdGVtcyI6dHJ1ZX0",
    "prev_cursor": null
  }
}
```

### `POST /api/images`

```bash
curl -H 'Accept: application/json' \
  -F file=@zdjecie.jpg -F name=Anna -F email=anna@example.com \
  http://localhost:8080/api/images
```

`201 Created` zwraca obiekt `data` w tym samym formacie co element listy.

`422 Unprocessable Content`:

```json
{
  "message": "Obraz musi mieć co najmniej 500 × 500 px. (i jeszcze 1 błąd)",
  "errors": {
    "file": ["Obraz musi mieć co najmniej 500 × 500 px."],
    "email": ["Podaj poprawny adres e-mail."]
  }
}
```

`429 Too Many Requests` (z nagłówkiem `Retry-After`):

```json
{ "message": "Zbyt wiele żądań. Spróbuj ponownie za chwilę." }
```

### `GET /api/images/{id}`

To samo co element listy, a dodatkowo `mime`, `temperature_time` (godzina pomiaru z Open-Meteo, UTC), `exif` i `iptc` (surowe dane albo `null`):

```json
{
  "data": {
    "id": 23,
    "…": "…",
    "mime": "image/jpeg",
    "temperature_time": "2026-10-16T09:00:00.000000Z",
    "exif": {
      "IFD0": { "Make": "Apple", "Model": "iPhone 13", "Orientation": 6 },
      "EXIF": { "DateTimeOriginal": "2026:10:16 11:08:42", "ISOSpeedRatings": 64 }
    },
    "iptc": { "2#120": ["Spodek, Katowice"] }
  }
}
```

### `DELETE /api/images/{id}`

`204 No Content`, a dla nieistniejącego pliku `404`.

### Limity i nagłówki

- przesyłanie: 20 żądań na minutę na IP (`RATE_LIMIT_UPLOADS`), usuwanie: 30 (`RATE_LIMIT_DELETES`);
- CORS dopuszcza wyłącznie origin z `FRONTEND_URL`;
- odpowiedzi API mają nagłówek `X-Robots-Tag: noindex, nofollow`.

## Konfiguracja

Wszystkie adresy i ustawienia pochodzą z plików `.env`. Najważniejsze:

| Plik | Zmienna | Lokalnie | Demo online |
|---|---|---|---|
| `backend/.env` | `APP_URL` | `http://localhost:8080` | `https://api.stockpress.itdevstudio.pl` |
| `backend/.env` | `FRONTEND_URL` | `http://localhost:5173` | `https://stockpress.itdevstudio.pl` |
| `backend/.env` | `WEATHER_API_URL` | URL z treści zadania | bez zmian |
| `backend/.env` | `RATE_LIMIT_UPLOADS` / `RATE_LIMIT_DELETES` | `20` / `30` | wg potrzeb |
| `frontend/.env` | `VITE_API_URL` | `http://localhost:8080` | `https://api.stockpress.itdevstudio.pl` |

Limity rozmiaru żądania (8 MB, żeby plik 5 MB plus narzut formularza dotarł do Laravela): `backend/docker/php.ini` (`upload_max_filesize`, `post_max_size`) oraz `backend/docker/nginx.conf` (`client_max_body_size`).

## Struktura repozytorium

```
backend/                     Laravel (API-only)
  app/Actions/StoreImage.php       zapis pliku: miniatura, metadane, temperatura, rekord
  app/Services/                    ThumbnailGenerator, ImageMetadataExtractor, WeatherService
  app/Http/Controllers/            ImageController (chudy kontroler)
  app/Http/Requests/               StoreImageRequest (walidacja, komunikaty PL)
  app/Http/Resources/              ImageResource, ImageDetailsResource
  app/Http/Middleware/             AddNoIndexHeader
  config/stockpress.php            rozmiar miniatur, Open-Meteo, limity żądań
  docker/                          php.ini, nginx.conf, opcjonalne certyfikaty CA
  tests/Feature/                   testy funkcjonalne
frontend/                    React + Vite + TypeScript
  src/api.ts                       klient API (axios) i typy
  src/validation.ts                walidacja pliku po stronie klienta
  src/components/                  ImageList, ImageCard, UploadForm
docker-compose.yml           api (PHP-FPM), web (nginx), db (MariaDB), frontend (Vite)
Makefile                     make init / up / down / test
ZALOZENIA.md                 decyzje i założenia
```
