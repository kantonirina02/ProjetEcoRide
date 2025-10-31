import Route from "./Route.js";
import { allRoutes, websiteName } from "./allRoutes.js";
import { getSession } from "../js/auth/session.js";

// 404
const route404 = new Route("404", "Page introuvable", "/pages/404.html");

// -------- trouve la route (supporte /ride/:id) --------
const getRouteByUrl = (url) => {
  // 1) correspondance exacte
  let r = allRoutes.find(it => it.url === url);
  if (r) return r;

  // 2) motifs dynamiques
  //    /ride/:id -> route '/ride'
  if (/^\/ride(\/\d+)?$/.test(url)) {
    r = allRoutes.find(it => it.url === "/ride");
    if (r) return r;
  }

  return route404;
};

// -------- met à jour la visibilité des liens selon la session --------
function applyAuthVisibility() {
  const sess = getSession();
  document.querySelectorAll("[data-show-connected]").forEach(el => {
    el.style.display = sess && sess.user ? "" : "none";
  });
  document.querySelectorAll("[data-show-disconnected]").forEach(el => {
    el.style.display = sess && sess.user ? "none" : "";
  });
}

// -------- charge la page --------
async function LoadContentPage() {
  const path = window.location.pathname;
  const route = getRouteByUrl(path);

  const html = await fetch(route.pathHtml).then(r => r.text());
  const host = document.getElementById("main-page");
  if (host) host.innerHTML = html;

  document.title = `${route.title} - ${websiteName}`;
  applyAuthVisibility();

  // charge le script de page en module si fourni
  if (route.pathJS) {
    const s = document.createElement("script");
    s.type = "module";
    s.src = route.pathJS;
    s.defer = true;
    s.onerror = (e) => console.error("Page script load failed:", route.pathJS, e);
    document.body.appendChild(s);
  }
}

// -------- navigation SPA --------
function navigate(url) {
  if (window.location.pathname === url) {
    // si on est déjà sur l’URL, on recharge le contenu (utile après login)
    LoadContentPage();
    return;
  }
  window.history.pushState({}, "", url);
  LoadContentPage();
}

// intercepte tous les clics sur <a data-link>
document.addEventListener("click", (e) => {
  const a = e.target.closest("a[data-link]");
  if (!a) return;
  const url = a.getAttribute("href");
  if (!url || url.startsWith("http")) return; // liens externes = laisser passer
  e.preventDefault();
  navigate(url);
});

// gère back/forward
window.onpopstate = LoadContentPage;

// expose pour les autres scripts
window.navigate = navigate;

// refresh header quand la session change
window.addEventListener("session:changed", applyAuthVisibility);

// 1er rendu
LoadContentPage();
