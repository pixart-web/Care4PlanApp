/* Care4All service worker: makes the site installable as an app.
   Nothing is cached (the app holds private data); pages always come from the server.
   Only when there is no connection does it show a short offline notice. */
const OFFLINE_PAGE = '<!doctype html><html lang="pt-PT"><meta charset="utf-8">'
  + '<meta name="viewport" content="width=device-width, initial-scale=1"><title>Care4All</title>'
  + '<body style="font-family:system-ui,sans-serif;padding:2rem;text-align:center;color:#173d36">'
  + '<h1>Sem ligação</h1><p>A Care4All precisa de internet. Verifique a ligação e tente novamente.</p></body></html>';

self.addEventListener('install', () => self.skipWaiting());
// Remove caches left by earlier versions (they could serve outdated files).
self.addEventListener('activate', (event) => event.waitUntil(
  caches.keys().then((keys) => Promise.all(keys.map((key) => caches.delete(key)))).then(() => self.clients.claim())
));

self.addEventListener('fetch', (event) => {
  if (event.request.mode !== 'navigate') return;
  event.respondWith(
    fetch(event.request).catch(() => new Response(OFFLINE_PAGE, { headers: { 'Content-Type': 'text/html; charset=utf-8' } }))
  );
});
