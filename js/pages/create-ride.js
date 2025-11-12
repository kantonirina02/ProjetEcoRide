import { createRide, fetchMyVehicles, me, API_BASE } from "../api.js";
import { getSession } from "../auth/session.js";

const PLATFORM_FEE = 2;
const FRONT_BASE = (() => {
  const origin = window.location.origin;
  if (origin.includes(":8001")) {
    return origin.replace(":8001", ":3000");
  }
  return origin;
})();

function redirectToSignin() {
  if (typeof window.navigate === "function") {
    window.navigate("/signin");
  } else {
    window.location.href = `${FRONT_BASE}/signin`;
  }
}


const $form = document.getElementById("createRideForm");
const $err = document.getElementById("cr-error");
const $ok = document.getElementById("cr-success");
const $btn = document.getElementById("cr-submit");
const $vehicleSelect = document.getElementById("cr-vehicleId");
const $newVehicleGroups = document.querySelectorAll("[data-new-vehicle]");
const $newVehicleInputs = document.querySelectorAll("[data-vehicle-input]");

function showErr(msg) {
  if (!$err || !$ok) return;
  $err.textContent = msg || "Erreur";
  $err.classList.remove("d-none");
  $ok.classList.add("d-none");
}

function showOk(msg) {
  if (!$err || !$ok) return;
  $ok.textContent = msg || "Trajet cree";
  $ok.classList.remove("d-none");
  $err.classList.add("d-none");
}

function val($el, trim = true) {
  if (!$el) return "";
  return trim ? $el.value.trim() : $el.value;
}

function toggleVehicleFields() {
  const useExisting = Boolean($vehicleSelect && $vehicleSelect.value);
  $newVehicleGroups.forEach((group) => {
    group.classList.toggle("d-none", useExisting);
  });
  $newVehicleInputs.forEach((input) => {
    input.disabled = useExisting;
  });
}

async function loadVehicles() {
  if (!$vehicleSelect) return;
  try {
    const res = await fetchMyVehicles();
    if (!res?.auth || !Array.isArray(res.vehicles)) return;
    res.vehicles.forEach((vehicle) => {
      const option = document.createElement("option");
      option.value = String(vehicle.id);
      const label = vehicle.label && vehicle.label.trim() !== "" ? vehicle.label.trim() : `Vehicule #${vehicle.id}`;
      const seats = vehicle.seatsTotal != null ? `${vehicle.seatsTotal} places` : "places inconnues";
      option.textContent = `${label} (${seats})`;
      $vehicleSelect.appendChild(option);
    });
  } catch (error) {
    console.error("fetchMyVehicles failed", error);
  }
}

let oldLabel = "";

async function ensureAuth() {
  try {
    const info = await me();
    if (!info?.auth) {
      showErr("Session expiree. Veuillez vous reconnecter.");
      setTimeout(() => {
        redirectToSignin();
      }, 400);
      return false;
    }
    if (!window.__session || !window.__session.user) {
      window.__session = { user: info.user };
    }
    return true;
  } catch (error) {
    console.error("ensureAuth failed", error);
    showErr("Impossible de verifier la session. Reconnectez-vous.");
    setTimeout(() => {
      redirectToSignin();
    }, 400);
    return false;
  }
}

async function onSubmit(event) {
  if (event && typeof event.preventDefault === "function") event.preventDefault();

  if (!(await ensureAuth())) return;

  const session = getSession();
  if (!session || !session.user) {
    showErr("Veuillez vous connecter.");
    redirectToSignin();
    return;
  }

  const priceInput = Number(val(document.getElementById("cr-price")));
  if (!Number.isFinite(priceInput) || priceInput <= PLATFORM_FEE) {
    showErr(`Le prix doit etre strictement superieur a ${PLATFORM_FEE} credits.`);
    return;
  }

  const selectedVehicleId = $vehicleSelect && $vehicleSelect.value ? Number($vehicleSelect.value) : null;

  const payload = {
    driverId: session.user.id,
    fromCity: val(document.getElementById("cr-fromCity")),
    toCity: val(document.getElementById("cr-toCity")),
    startAt: val(document.getElementById("cr-startAt")),
    endAt: val(document.getElementById("cr-endAt")),
    price: priceInput,
    allowSmoker: Boolean(document.getElementById("cr-allowSmoker")?.checked),
    allowAnimals: Boolean(document.getElementById("cr-allowAnimals")?.checked),
    musicStyle: val(document.getElementById("cr-music")),
  };

  if (selectedVehicleId) {
    payload.vehicleId = selectedVehicleId;
  } else {
    const plateInput = val(document.getElementById("cr-plate"));
    const firstRegistrationInput = val(document.getElementById("cr-firstreg"));

    payload.vehicle = {
      brand: val(document.getElementById("cr-brand")),
      model: val(document.getElementById("cr-model")),
      eco: Boolean(document.getElementById("cr-eco")?.checked),
      seatsTotal: Number(val(document.getElementById("cr-seats")) || 4),
      color: val(document.getElementById("cr-color")),
      energy: val(document.getElementById("cr-energy"), false),
      plate: plateInput ? plateInput.toUpperCase() : "",
      firstRegistrationAt: firstRegistrationInput,
    };
    if (!payload.vehicle.plate) {
      showErr("La plaque d'immatriculation est requise pour un nouveau véhicule.");
      return;
    }
    if (!payload.vehicle.firstRegistrationAt) {
      showErr("La date de première immatriculation est requise.");
      return;
    }
  }

  payload.startAt = payload.startAt.replace("T", " ");
  payload.endAt = payload.endAt.replace("T", " ");

  if ($btn) {
    oldLabel = $btn.textContent;
    $btn.disabled = true;
    $btn.textContent = "...";
  }

  try {
    const res = await createRide(payload);
    showOk(`Trajet #${res.id} cree`);
    const searchParams = new URLSearchParams({
      from: payload.fromCity,
      to: payload.toCity,
      date: payload.startAt.slice(0, 10),
    });
    setTimeout(() => {
      if (typeof window.navigate === "function") window.navigate(`/covoiturages?${searchParams.toString()}`);
      else window.location.href = `/covoiturages?${searchParams.toString()}`;
    }, 600);
  } catch (error) {
    console.error(error);
    const message = String(error?.message || "");
    if (message.includes("HTTP 401")) {
      showErr("Session expiree. Veuillez vous reconnecter.");
      setTimeout(() => {
        redirectToSignin();
      }, 400);
    } else if (message.includes("HTTP 400")) {
      showErr("Creation impossible. Verifiez les champs (vehicule, villes, dates, prix...).");
    } else if (message.includes("Failed to fetch") || message.includes("ERR_CONNECTION")) {
      showErr("Serveur API indisponible (port 8001). Lancez le backend puis reessayez.");
    } else {
      showErr("Creation impossible. Reessayez plus tard.");
    }
  } finally {
    if ($btn) {
      $btn.disabled = false;
      $btn.textContent = oldLabel;
    }
  }
}

if ($vehicleSelect) {
  $vehicleSelect.addEventListener("change", toggleVehicleFields);
}

if ($form) {
  $form.addEventListener("submit", onSubmit);
}

if ($btn) {
  $btn.addEventListener("click", onSubmit);
}

(async function init() {
  const authOk = await ensureAuth();
  toggleVehicleFields();
  if (authOk) {
    loadVehicles();
  }
})();

(async function pingApi() {
  try {
        const healthUrl = API_BASE.replace(/\/api$/, "/api/health");
    const res = await fetch(healthUrl, { credentials: "include" });
    if (!res.ok) throw new Error("bad");
    $btn?.removeAttribute("disabled");
  } catch {
    $btn?.setAttribute("disabled", "disabled");
    showErr("Serveur API indisponible (port 8001). Lancez le backend puis reessayez.");
  }
})();






