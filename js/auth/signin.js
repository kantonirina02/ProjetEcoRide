import { setSession } from "../auth/session.js";
import { login } from "../api.js";

const form = document.getElementById("signinForm");
const email = document.getElementById("email");
const password = document.getElementById("password");
const err = document.getElementById("signin-error");

if (form) {
  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    err.classList.add("d-none");
    err.textContent = "";

    if (!email.value.trim() || !password.value.trim()) {
      err.textContent = "Email et mot de passe requis.";
      err.classList.remove("d-none");
      return;
    }

    try {
      const res = await login({ email: email.value.trim(), password: password.value.trim() });
      // Le backend crée la session (cookie); on garde aussi une copie locale utile au front
      setSession({ user: res.user, token: "session" });
    } catch (e) {
      err.textContent = "Identifiants invalides ou serveur injoignable.";
      err.classList.remove("d-none");
      return;
    }

    // redirection SPA et rechargement du contenu
    if (typeof window.navigate === "function") {
      window.navigate("/");
    } else {
      // (sécurité si router n'est pas chargé, peu probable)
      window.history.pushState({}, "", "/");
      window.location.reload();
    }
  });
}

