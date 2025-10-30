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

    // MOCK login → on stocke une session locale (id=1 = fixtures)
    const session = {
      user: { id: 1, email: email.value.trim(), pseudo: "ecoDriver" },
      token: "mock-token",
    };
    setSession(session);

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
