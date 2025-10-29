import Route from "./Route.js";

// Définir ici vos routes
export const allRoutes = [
  new Route("/", "Accueil", "pages/home.html", "js/pages/home.js"),
  new Route("/mentions-legales", "Mentions légales", "pages/mentionsLegales.html"),
  new Route("/covoiturages", "Covoiturages", "pages/covoiturages.html", "js/pages/covoiturages.js"),
  new Route("/signin", "Connexion", "pages/signin.html"),
  new Route("/signup", "Inscription", "pages/signup.html"),
];

// Le titre s'affiche comme ceci : Route.titre - websitename
export const websiteName = "EcoRide";
