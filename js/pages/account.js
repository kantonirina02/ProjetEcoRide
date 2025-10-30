import { me } from "../api.js";

const API = "http://127.0.0.1:8001/api";

const $err       = document.getElementById("account-error");
const $welcome   = document.getElementById("account-welcome");

const $bFeedback = document.getElementById("account-bookings-feedback");
const $bList     = document.getElementById("account-bookings-list");

const $rFeedback = document.getElementById("account-rides-feedback");
const $rList     = document.getElementById("account-rides-list");

function showError(msg) {
  $err.textContent = msg;
  $err.classList.remove("d-none");
}

function bookingRow(b) {
  // b vient de /api/me/bookings : { rideId, from, to, startAt, seats, status }
  return `
    <div class="card mb-2 shadow-sm">
      <div class="card-body d-flex flex-wrap justify-content-between">
        <div>
          <div class="fw-semibold">${b.from} ➜ ${b.to}</div>
          <div class="small text-muted">${b.startAt ?? ""} • ${b.seats} place(s)</div>
        </div>
        <div class="text-end"><span class="badge bg-secondary">${b.status}</span></div>
      </div>
    </div>
  `;
}

function rideRow(r) {
  // r attendu: { id, from, to, startAt, endAt, price, seatsLeft, seatsTotal, status, vehicle:{brand,model,eco} }
  const price = typeof r.price === "number" ? r.price.toFixed(2) : r.price;
  return `
    <div class="card mb-2 shadow-sm">
      <div class="card-body d-flex flex-wrap justify-content-between">
        <div>
          <div class="fw-semibold">${r.from} ➜ ${r.to}</div>
          <div class="small text-muted">
            ${r.startAt ?? ""} • ${r.seatsLeft}/${r.seatsTotal} places • ${r.vehicle?.brand ?? ""} ${r.vehicle?.model ?? ""} ${r.vehicle?.eco ? "🌿" : ""}
          </div>
        </div>
        <div class="text-end">
          <div class="fw-bold">${price} €</div>
          <div class="small"><span class="badge bg-secondary">${r.status}</span></div>
        </div>
      </div>
    </div>
  `;
}

async function fetchJSON(url, opts = {}) {
  const res = await fetch(url, { credentials: "include", headers: { Accept: "application/json" }, ...opts });
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  return res.json();
}

async function loadBookings() {
  try {
    const data = await fetchJSON(`${API}/me/bookings`);
    const list = data?.bookings ?? [];
    if (!data?.auth) {
      $bFeedback.textContent = "Veuillez vous connecter.";
      return;
    }
    if (list.length === 0) {
      $bFeedback.textContent = "Aucune réservation.";
      return;
    }
    $bFeedback.textContent = "";
    $bList.innerHTML = list.map(bookingRow).join("");
  } catch (e) {
    console.error(e);
    $bFeedback.textContent = "Erreur de chargement.";
  }
}

async function loadMyRides() {
  try {
    const data = await fetchJSON(`${API}/me/rides`);
    const rides = Array.isArray(data) ? data : (data?.rides ?? []);
    if (!rides.length) {
      $rFeedback.textContent = "Aucun trajet en tant que conducteur.";
      return;
    }
    $rFeedback.textContent = "";
    $rList.innerHTML = rides.map(rideRow).join("");
  } catch (e) {
    console.error(e);
    // Si l’endpoint n’existe pas encore (404), on le signale 
    $rFeedback.textContent = "Aucun trajet en tant que conducteur (endpoint /api/me/rides indisponible).";
  }
}

async function run() {
  try {
    const info = await me(); // GET /api/auth/me
    if (!info?.auth) {
      window.alert("Veuillez vous connecter.");
      if (typeof window.navigate === "function") window.navigate("/signin");
      else window.location.href = "/signin";
      return;
    }
    const u = info.user;
    $welcome.innerHTML = `Bonjour <strong>${u.pseudo ?? u.email}</strong>`;

    await Promise.all([
      loadBookings(),
      loadMyRides(),
    ]);
  } catch (e) {
    console.error(e);
    showError("Erreur lors du chargement du profil.");
  }
}

run();
