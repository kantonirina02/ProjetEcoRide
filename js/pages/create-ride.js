import { createRide } from "../api.js";
import { getSession } from "../auth/session.js";

const $form = document.getElementById("createRideForm");
const $err = document.getElementById("cr-error");
const $ok  = document.getElementById("cr-success");

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

function val($el, tr = true) {
  return tr ? $el.value.trim() : $el.value;
}

async function onSubmit(e) {
  e.preventDefault();

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
    startAt:  val(document.getElementById("cr-startAt")), // format "YYYY-MM-DDTHH:mm"
    endAt:    val(document.getElementById("cr-endAt")),
    price:    Number(val(document.getElementById("cr-price"))),
    allowSmoker:  document.getElementById("cr-allowSmoker").checked,
    allowAnimals: document.getElementById("cr-allowAnimals").checked,
    musicStyle:   val(document.getElementById("cr-music")),
  };

  // sanitation: datetime-local -> "YYYY-MM-DD HH:mm"
  payload.startAt = payload.startAt.replace("T", " ");
  payload.endAt   = payload.endAt.replace("T", " ");

  const btn = document.getElementById("cr-submit");
  const old = btn.textContent;
  btn.disabled = true;
  btn.textContent = "…";

  try {
    const res = await createRide(payload);
    showOk(`Trajet #${res.id} créé ✅`);
    // Option: rediriger vers la liste avec préfiltre:
    const q = new URLSearchParams({ from: payload.fromCity, to: payload.toCity, date: payload.startAt.slice(0,10) });
    setTimeout(() => {
      if (typeof window.navigate === "function") window.navigate(`/covoiturages?${q.toString()}`);
      else window.location.href = `/covoiturages?${q.toString()}`;
    }, 600);
  } catch (e) {
    console.error(e);
    showErr("Création impossible. Vérifie les champs (marque, modèle, lat/lng, dates, prix…).");
  } finally {
    btn.disabled = false;
    btn.textContent = old;
  }
}

if ($form) $form.addEventListener("submit", onSubmit);
