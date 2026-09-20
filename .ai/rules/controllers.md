---
paths:
  - 'app/Http/Controllers/**'
---

# Controllers

## Inertia::location for any redirect that leaves the application
An Inertia form posts over XHR, and an XHR follows a redirect itself — carrying the X-XSRF-TOKEN header Inertia puts on every request. So a `to_route()` whose target redirects off-site (Discord OAuth, say) dies as a CORS preflight failure with nothing on screen. Return `Inertia::location($url)` instead: a 409 with X-Inertia-Location hands the navigation back to the browser. Widen the return type to SymfonyResponse — location() answers a 409 to an Inertia request and a plain redirect otherwise.

A feature test will NOT catch this: a test request without Inertia's headers takes the plain-redirect branch, so `assertRedirect()` passes while the browser breaks. Assert the XHR path too — post with `withHeaders(['X-Inertia' => 'true'])` and assert 409 plus the X-Inertia-Location target. Assert the target, not just the status: an asset version mismatch also answers 409 with that header, naming the current URL.

A plain `<a href>` is unaffected, which is why the login page's own Discord button always worked.
