import { API_BASE, me } from "../api.js";

const $form = document.getElementById("signup-form");
const $btn  = document.getElementById("btn-validation-inscription");

const $err = document.getElementById("signup-error");
const $ok  = document.getElementById("signup-success");

const $email = document.getElementById("EmailInput");
const $emailHint = document.getElementById("EmailHint");

function showErr(msg){ $err.textContent = msg; $err.classList.remove("d-none"); $ok.classList.add("d-none"); }
function showOk(msg){ $ok.textContent = msg; $ok.classList.remove("d-none"); $err.classList.add("d-none"); }

function strongPass(p){
  if (!p || p.length < 8) return false;
  return /[a-z]/.test(p) && /[A-Z]/.test(p) && /\d/.test(p) && /[^A-Za-z0-9]/.test(p);
}

function debounce(fn, delay=400){
  let t; return (...args)=>{ clearTimeout(t); t = setTimeout(()=>fn(...args), delay); };
}

async function signup(payload){
  const res = await fetch(`${API_BASE}/auth/register`, {
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

/* -- check e-mail availability -- */
let emailAvailable = null; 

async function checkEmailAvailability(email){
  emailAvailable = null;
  if (!$emailHint) return;
  $email.classList.remove("is-valid","is-invalid");
  $emailHint.classList.remove("text-success","text-danger");
  $emailHint.textContent = "...";

  const fmtOk = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
  if (!fmtOk) {
    if (email.length > 0) {
      $email.classList.add("is-invalid");
      $emailHint.classList.add("text-danger");
      $emailHint.textContent = "Format d’e-mail invalide.";
    }
    return;
  }

  try {
    const res = await fetch(`${API_BASE}/auth/check-email?email=${encodeURIComponent(email)}`, {
      headers: { Accept: "application/json" },
      credentials: "include",
    });
    const body = await res.json().catch(()=>({}));
    if (!res.ok || body?.ok !== true) throw new Error("check failed");

    emailAvailable = !!body.available;
    if (emailAvailable) {
      $email.classList.add("is-valid");
      $emailHint.classList.add("text-success");
      $emailHint.textContent = "Adresse disponible ✅";
    } else {
      $email.classList.add("is-invalid");
      $emailHint.classList.add("text-danger");
      $emailHint.textContent = "Adresse déjà utilisée ❌";
    }
  } catch {
    emailAvailable = null;
  }
}

const debouncedCheck = debounce((v)=>checkEmailAvailability(v), 350);
$email?.addEventListener("input", (e)=> debouncedCheck(e.target.value.trim().toLowerCase()));
async function onSubmit(e){
  e.preventDefault();

  const pseudo = document.getElementById("PseudoInput").value.trim();
  const email  = $email.value.trim().toLowerCase();
  const pass   = document.getElementById("PasswordInput").value;
  const pass2  = document.getElementById("ValidatePasswordInput").value;

  if (!email || !pass){
    showErr("Email et mot de passe requis.");
    return;
  }
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

  if (emailAvailable === null) {
    await checkEmailAvailability(email);
  }
  if (emailAvailable === false) {
    showErr("Cet email est déjà utilisé.");
    return;
  }

  const payload = { email, password: pass, pseudo };

  const old = $btn.textContent;
  $btn.disabled = true;
  $btn.textContent = "...";

  try {
    await signup(payload);
        showOk("Compte cree !");

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
