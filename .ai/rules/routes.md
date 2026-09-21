---
paths:
  - 'routes/**'
---

# Routes

## Do not route:cache on the /ldi-system subfolder deployment
The office server serves the app under a subfolder (192.168.10.38/ldi-system via an Apache Alias). With the route cache on, GET /ldi-system/ answers 405: CompiledRouteCollection::requestWithoutTrailingSlash() strips the slash from REQUEST_URI, Symfony can then no longer find the /ldi-system base URL, and the root route is lost (Laravel 13.30). Deploy with `php artisan config:cache && php artisan view:cache && php artisan event:cache` — never `php artisan optimize` or `route:cache` — or run `php artisan route:clear` after it.
