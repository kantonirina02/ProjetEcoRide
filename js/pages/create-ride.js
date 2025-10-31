import { createRide } from "../api.js";
import { getSession } from "../auth/session.js";

const $form = document.getElementById("createRideForm");
const $err = document.getElementById("cr-error");
const $ok  = document.getElementById("cr-success");
const $btn = document.getElementById("cr-submit");

function showErr(msg) {
  $err.textContent = msg || "Erreur";
  $err.classList.remove("d-none");
  $ok.classList.add("d-none");
}
function showOk(msg) {
  $ok.textContent = msg || "Trajet créé";
  $ok.classList.remove("d-none");
  $err.classList.add("d-none");
}

function val($el, trim = true) {
  return trim ? $el.value.trim() : $el.value;
}

async function onSubmit(e) {
  if (e && typeof e.preventDefault === "function") e.preventDefault();

  const session = getSession();
  if (!session || !session.user) {
    showErr("Veuillez vous connecter.");
    if (typeof window.navigate === "function") window.navigate("/signin");
    else window.location.href = "/signin";
    return;
  }

  const payload = {
    driverId: session.user.id,
    vehicle: {
      brand:  val(document.getElementById("cr-brand")),
      model:  val(document.getElementById("cr-model")),
      eco:    document.getElementById("cr-eco").checked,
      seatsTotal: Number(val(document.getElementById("cr-seats")) || 4),
      color:  val(document.getElementById("cr-color")),
      energy: val(document.getElementById("cr-energy"), false),
    },
    fromCity: val(document.getElementById("cr-fromCity")),
    toCity:   val(document.getElementById("cr-toCity")),
    startAt:  val(document.getElementById("cr-startAt")),
    endAt:    val(document.getElementById("cr-endAt")),
    price:    Number(val(document.getElementById("cr-price"))),
    allowSmoker:  document.getElementById("cr-allowSmoker").checked,
    allowAnimals: document.getElementById("cr-allowAnimals").checked,
    musicStyle:   val(document.getElementById("cr-music")),
  };

  // sanitation: datetime-local -> "YYYY-MM-DD HH:mm"
  payload.startAt = payload.startAt.replace("T", " ");
  payload.endAt   = payload.endAt.replace("T", " ");

  const old = $btn.textContent;
  $btn.disabled = true;
  $btn.textContent = "…";

  try {
    const res = await createRide(payload);
    showOk(`Trajet #${res.id} créé ✔`);
    const q = new URLSearchParams({ from: payload.fromCity, to: payload.toCity, date: payload.startAt.slice(0,10) });
    setTimeout(() => {
      if (typeof window.navigate === "function") window.navigate(`/covoiturages?${q.toString()}`);
      else window.location.href = `/covoiturages?${q.toString()}`;
    }, 600);
  } catch (e) {
    console.error(e);
    const msg = String(e?.message || "");
    if (msg.includes("HTTP 400")) {
      showErr("Création impossible. Vérifie les champs (marque, modèle, villes, dates, prix...).");
    } else if (msg.includes("Failed to fetch") || msg.includes("ERR_CONNECTION")) {
      showErr("Serveur API indisponible (port 8001). Lance le backend Symfony puis réessaie.");
    } else {
      showErr("Création impossible. Réessaie plus tard.");
    }
  } finally {
    $btn.disabled = false;
    $btn.textContent = old;
  }
}

// Empêche le refresh et capture le submit
if ($form) {
  $form.addEventListener("submit", onSubmit);
}

// Fallback: capte un clic direct sur le bouton si le submit est bloqué
if ($btn) {
  $btn.addEventListener("click", (e) => onSubmit(e));
}

// Ping API pour indiquer l’état et éviter des essais inutiles
(async function pingApi() {
  try {
    const res = await fetch("http://localhost:8001/api/health", { credentials: "include" });
    if (!res.ok) throw new Error("bad");
    $btn?.removeAttribute("disabled");
  } catch {
    $btn?.setAttribute("disabled", "disabled");
    showErr("Serveur API indisponible (port 8001). Lance le backend Symfony puis réessaie.");
  }
})();
