import { me } from "../api.js";

const API = "http://localhost:8001/api";

const $form = document.getElementById("signup-form");
const $btn  = document.getElementById("btn-validation-inscription");

const $err = document.getElementById("signup-error");
const $ok  = document.getElementById("signup-success");

function showErr(msg){ $err.textContent = msg; $err.classList.remove("d-none"); $ok.classList.add("d-none"); }
function showOk(msg){ $ok.textContent = msg; $ok.classList.remove("d-none"); $err.classList.add("d-none"); }

async function signup(payload){
  const res = await fetch(`${API}/auth/signup`, {
    method: "POST",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    credentials: "include",
    body: JSON.stringify(payload),
  });
  if (!res.ok) {
    let d = ""; try { d = JSON.stringify(await res.json()); } catch {}
    throw new Error(`HTTP ${res.status} ${d}`);
  }
  return res.json();
}

async function onSubmit(e){
  e.preventDefault();

  const pseudo = document.getElementById("PseudoInput").value.trim();
  const email  = document.getElementById("EmailInput").value.trim();
  const pass   = document.getElementById("PasswordInput").value;
  const pass2  = document.getElementById("ValidatePasswordInput").value;

  if (!email || !pass){
    showErr("Email et mot de passe requis");
    return;
  }
  if (pass !== pass2){
    showErr("Les mots de passe ne correspondent pas.");
    return;
  }

  const payload = { email, password: pass, pseudo };

  const old = $btn.textContent;
  $btn.disabled = true;
  $btn.textContent = "…";

  try {
    await signup(payload);
    showOk("Compte créé ✔");

    try {
      const info = await me();
      window.__session = { user: info.user };
    } catch {}

    setTimeout(() => {
      if (typeof window.navigate === "function") window.navigate("/account");
      else window.location.href = "/account";
    }, 700);

  } catch (e) {
    const msg = String(e?.message || "");
    if (msg.includes("409")) showErr("Cet email est déjà utilisé.");
    else showErr("Inscription impossible.");
  } finally {
    $btn.disabled = false;
    $btn.textContent = old;
  }
}

if ($form) $form.addEventListener("submit", onSubmit);
