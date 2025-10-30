import { getSession } from "../auth/session.js";

const API = "http://127.0.0.1:8001/api";

const $welcome = document.getElementById("account-welcome");
const $bFeedback = document.getElementById("account-bookings-feedback");
const $bList = document.getElementById("account-bookings-list");
const $rFeedback = document.getElementById("account-rides-feedback");
const $rList = document.getElementById("account-rides-list");

function rideCard(r) {
  return `
  <div class="card mb-2 shadow-sm">
    <div class="card-body d-flex flex-wrap justify-content-between">
      <div>
        <div class="fw-semibold">${r.from} ➜ ${r.to}</div>
        <div class="small text-muted">${r.startAt} • ${r.seatsLeft}/${r.seatsTotal} places • ${r.vehicle.brand} ${r.vehicle.model} ${r.vehicle.eco ? "🌿" : ""}</div>
      </div>
      <div class="text-end">
        <div class="fw-bold">${r.price} €</div>
        <div class="small">${r.status}</div>
      </div>
    </div>
  </div>`;
}

function bookingCard(bk) {
  const r = bk.ride;
  return `
  <div class="card mb-2 shadow-sm">
    <div class="card-body d-flex flex-wrap justify-content-between">
      <div>
        <div class="fw-semibold">${r.from} ➜ ${r.to}</div>
        <div class="small text-muted">${r.startAt} • ${bk.seatsBooked} siège(s) • conducteur ${r.driver.pseudo}</div>
      </div>
      <div class="text-end">
        <div class="fw-bold">${r.price} €</div>
        <div class="small">${bk.status}</div>
      </div>
    </div>
  </div>`;
}

async function run() {
  const session = getSession();
  if (!session || !session.user) {
    alert("Veuillez vous connecter.");
    if (typeof window.navigate === "function") {
      window.navigate("/signin");
    } else {
      window.location.href = "/signin";
    }
    return;
  }

  $welcome.innerHTML = `Bonjour <strong>${session.user.pseudo ?? session.user.email}</strong>`;

  try {
    // bookings
    const bRes = await fetch(`${API}/users/${session.user.id}/bookings`, { headers: { Accept: "application/json" }});
    if (!bRes.ok) throw new Error("bookings HTTP " + bRes.status);
    const bookings = await bRes.json();
    if (!bookings.length) {
      $bFeedback.textContent = "Aucune réservation.";
    } else {
      $bFeedback.textContent = "";
      $bList.innerHTML = bookings.map(bookingCard).join("");
    }

    // my rides
    const rRes = await fetch(`${API}/users/${session.user.id}/rides`, { headers: { Accept: "application/json" }});
    if (!rRes.ok) throw new Error("rides HTTP " + rRes.status);
    const rides = await rRes.json();
    if (!rides.length) {
      $rFeedback.textContent = "Aucun trajet en tant que conducteur.";
    } else {
      $rFeedback.textContent = "";
      $rList.innerHTML = rides.map(rideCard).join("");
    }
  } catch (e) {
    console.error(e);
    $bFeedback.textContent = "Erreur de chargement.";
    $rFeedback.textContent = "Erreur de chargement.";
  }
}

run();
