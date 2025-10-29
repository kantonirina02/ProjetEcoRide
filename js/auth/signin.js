import { setSession } from "../auth/session.js";

const form = document.getElementById("signinForm");
const email = document.getElementById("email");
const password = document.getElementById("password");
const err = document.getElementById("signin-error");

if (form) {
  form.addEventListener("submit", (e) => {
    e.preventDefault();
    err.classList.add("d-none");
    err.textContent = "";

    if (!email.value.trim() || !password.value.trim()) {
      err.textContent = "Email et mot de passe requis.";
      err.classList.remove("d-none");
      return;
    }

    // MOCK: on crée une session locale (id 1 = fixtures)
    const session = {
      user: {
        id: 1,
        email: email.value.trim(),
        pseudo: "ecoDriver",
      },
      token: "mock-token",
    };

    setSession(session);

    // retour à l’accueil
    window.history.pushState({}, "", "/");
    window.route({ target: { href: "/" }, preventDefault(){} });
  });
}
