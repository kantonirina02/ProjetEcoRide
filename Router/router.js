import Route from "./Route.js";
import { allRoutes, websiteName } from "./allRoutes.js";

// Route 404
const route404 = new Route("404", "Page introuvable", "/pages/404.html");

// Trouver la route correspondant à une URL
const getRouteByUrl = (url) => {
  let currentRoute = null;
  allRoutes.forEach((r) => {
    if (r.url === url) currentRoute = r;
  });
  return currentRoute ?? route404;
};

// Charge le HTML et le JS de la page courante
const LoadContentPage = async () => {
  const path = window.location.pathname;
  const actualRoute = getRouteByUrl(path);

  // Injecte le HTML
  const html = await fetch(actualRoute.pathHtml).then((r) => r.text());
  document.getElementById("main-page").innerHTML = html;

  if (actualRoute.pathJS) {
    document
      .querySelectorAll(`script[data-page-js]`)
      .forEach((s) => s.remove());

    const s = document.createElement("script");
    s.type = "module";
    s.src = actualRoute.pathJS;
    s.dataset.pageJs = actualRoute.pathJS;
    document.body.appendChild(s);
  }

  // Titre
  document.title = `${actualRoute.title} - ${websiteName}`;
};

// Navigation SPA réutilisable
function navigate(href) {
  window.history.pushState({}, "", href);
  LoadContentPage();
}

// Gestion des liens <a data-link>
const delegateLinks = () => {
  document.addEventListener("click", (e) => {
    const a = e.target.closest('a[data-link]');
    if (!a) return;
    e.preventDefault();
    navigate(a.getAttribute("href"));
  });
};

// API globale
const routeEvent = (event) => {
  event = event || window.event;
  event.preventDefault();
  navigate(event.target.href);
};

window.onpopstate = LoadContentPage;
window.route = routeEvent;   
window.navigate = navigate;  

// Bootstrap du router
delegateLinks();
LoadContentPage();
