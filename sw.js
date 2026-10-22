/* Service worker de myKronos : nécessaire pour installer l'application (PWA).
   Aucun cache : les pages viennent toujours du serveur, les données sont celles de la base. */
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));
self.addEventListener('fetch', () => {});
