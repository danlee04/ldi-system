---
paths:
  - app/Providers/FortifyServiceProvider.php
---

# Providers

## Login lockout: failure-only limiter, not a named throttle
config/fortify.php sets limiters.login to null on purpose. A named limiter runs as throttle middleware: it counts every attempt, good ones included, and answers with a bare 429 page. Null hands login to Fortify's LoginRateLimiter, which counts only wrong passwords and clears on success; it is rebound to App\Actions\Fortify\FailedLoginLimiter (5 wrong → 15-minute lock per email+IP) and App\Http\Responses\LoginLockoutResponse (message in minutes, on the email field).
