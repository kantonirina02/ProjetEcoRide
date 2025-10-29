import Route from "./Route.js";
import { allRoutes, websiteName } from "./allRoutes.js";

const route404 = new Route("404", "Page introuvable", "pages/404.html");

const getRouteByUrl = (url) => {
  let currentRoute = null;
  allRoutes.forEach((r) => {
    if (r.url === url) currentRoute = r;
  });
  return currentRoute ?? route404;
};

const LoadContentPage = async () => {
  const path = window.location.pathname;
  const actualRoute = getRouteByUrl(path);

  const html = await fetch(actualRoute.pathHtml).then((res) => res.text());
  document.getElementById("main-page").innerHTML = html;

  // charge le JS associé à la page si présent
  if (actualRoute.pathJS && actualRoute.pathJS !== "") {
    const scriptTag = document.createElement("script");
    scriptTag.type = "module";             // on utilise des modules ES6
    scriptTag.src = actualRoute.pathJS;
    document.body.appendChild(scriptTag);
  }

  document.title = `${actualRoute.title} - ${websiteName}`;
};

// Intercepte tous les liens SPA
const onClick = (event) => {
  const a = event.target.closest('a[data-link]');
  if (!a) return;
  event.preventDefault();
  window.history.pushState({}, "", a.getAttribute("href"));
  LoadContentPage();
};

window.addEventListener("popstate", LoadContentPage);
document.addEventListener("click", onClick);

// 1er rendu
LoadContentPage();
