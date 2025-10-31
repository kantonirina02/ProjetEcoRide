import Route from "./Route.js";

// Définir ici vos routes
export const allRoutes = [
  new Route("/", "Accueil", "pages/home.html", "js/pages/home.js"),
  new Route("/mentions-legales", "Mentions légales", "pages/mentionsLegales.html"),
  new Route("/covoiturages", "Covoiturages", "pages/covoiturages.html", "js/pages/covoiturages.js"),
  new Route("/signin", "Connexion", "pages/signin.html","js/auth/signin.js"),
  new Route("/signup", "Inscription", "pages/signup.html"),
  new Route("/account","Mon Profil","/pages/account.html","js/pages/account.js"),
  new Route("/bookings", "Mes réservations", "pages/bookings.html", "js/pages/bookings.js"),
  new Route("/new-rides", "Créer un trajet", "pages/create-ride.html", "js/pages/create-ride.js"),
  new Route("/ride", "Détail trajet", "pages/ride.html", "js/pages/ride.js"),
];

// Le titre s'affiche comme ceci : Route.titre - websitename
export const websiteName = "EcoRide";
