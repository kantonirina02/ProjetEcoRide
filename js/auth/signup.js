import { me } from "../api.js";

const API = "http://localhost:8001/api";

const $form = document.getElementById("signup-form");
const $btn  = document.getElementById("btn-validation-inscription");

const $err = document.getElementById("signup-error");
const $ok  = document.getElementById("signup-success");

function showErr(msg){ $err.textContent = msg; $err.classList.remove("d-none"); $ok.classList.add("d-none"); }
function showOk(msg){ $ok.textContent = msg; $ok.classList.remove("d-none"); $err.classList.add("d-none"); }

function strongPass(p){
  if (!p || p.length < 8) return false;
  return /[a-z]/.test(p) && /[A-Z]/.test(p) && /\d/.test(p) && /[^A-Za-z0-9]/.test(p);
}

async function signup(payload){
  const res = await fetch(`${API}/auth/register`, {
    method: "POST",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    credentials: "include",
    body: JSON.stringify(payload),
  });
  const body = await res.json().catch(() => ({}));
  if (!res.ok) {
    const code = res.status;
    const msg = body?.error || "";
    throw new Error(`HTTP ${code} ${msg}`);
  }
  return body;
}

async function onSubmit(e){
  e.preventDefault();

  const pseudo = document.getElementById("PseudoInput").value.trim();
  const email  = document.getElementById("EmailInput").value.trim();
  const pass   = document.getElementById("PasswordInput").value;
  const pass2  = document.getElementById("ValidatePasswordInput").value;

  if (!email || !pass){
    showErr("Email et mot de passe requis.");
    return;
  }
  // Email HTML5 + vérif simple JS
  const okEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
  if (!okEmail){
    showErr("Votre email est invalide.");
    return;
  }
  if (pass !== pass2){
    showErr("Les mots de passe ne correspondent pas.");
    return;
  }
  if (!strongPass(pass)){
    showErr("Mot de passe trop faible (≥8, 1 majuscule, 1 minuscule, 1 chiffre, 1 spécial).");
    return;
  }

  const payload = { email, password: pass, pseudo };

  const old = $btn.textContent;
  $btn.disabled = true;
  $btn.textContent = "…";

  try {
    await signup(payload);
    showOk("Compte créé ✔");

    // synchronise la session FE
    try {
      const info = await me();
      window.__session = { user: info.user };
      window.dispatchEvent(new CustomEvent("session:changed"));
    } catch {}

    setTimeout(() => {
      if (typeof window.navigate === "function") window.navigate("/account");
      else window.location.href = "/account";
    }, 700);

  } catch (e) {
    const msg = String(e?.message || "");
    if (msg.includes("409") || msg.includes("déjà utilisé")) {
      showErr("Cet email est déjà utilisé.");
    } else if (msg.includes("email invalide")){
      showErr("Votre email est invalide.");
    } else if (msg.includes("faible") || msg.includes("≥8")){
      showErr("Mot de passe trop faible (≥8, 1 majuscule, 1 minuscule, 1 chiffre, 1 spécial).");
    } else if (msg.includes("requis")){
      showErr("Email et mot de passe requis.");
    } else if (msg.includes("Corps JSON invalide")){
      showErr("Requête invalide.");
    } else {
      showErr("Inscription impossible.");
    }
  } finally {
    $btn.disabled = false;
    $btn.textContent = old;
  }
}

if ($form) $form.addEventListener("submit", onSubmit);
