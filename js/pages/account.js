import { me, fetchMyBookings, unbookRide, fetchMyRides } from "../api.js";

const $welcome   = document.getElementById("account-welcome");
const $bFeedback = document.getElementById("account-bookings-feedback");
const $bList     = document.getElementById("account-bookings-list");
const $rFeedback = document.getElementById("account-rides-feedback");
const $rList     = document.getElementById("account-rides-list");

function rideCard(r) {
  return `
  <div class="card mb-2 shadow-sm">
    <div class="card-body d-flex flex-wrap justify-content-between">
      <div>
        <div class="fw-semibold">${r.from} ➜ ${r.to}</div>
        <div class="small text-muted">
          ${r.startAt ?? ""} • ${r.seatsLeft}/${r.seatsTotal} places •
          ${r.vehicle?.brand ?? ""} ${r.vehicle?.model ?? ""} ${r.vehicle?.eco ? "🌿" : ""}
        </div>
      </div>
      <div class="text-end">
        <div class="fw-bold">${typeof r.price === "number" ? r.price.toFixed(2) : r.price} €</div>
        <div class="small">${r.status ?? ""}</div>
      </div>
    </div>
  </div>`;
}

function bookingRow(b) {
  return `
  <div class="card mb-2 shadow-sm">
    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div>
        <div class="fw-semibold">${b.from} ➜ ${b.to}</div>
        <div class="small text-muted">${b.startAt ?? ""} • ${b.seats} siège(s) • statut: ${b.status}</div>
      </div>
      <div class="text-end">
        <button class="btn btn-outline-danger btn-sm js-unbook" data-id="${b.rideId}">Annuler</button>
      </div>
    </div>
  </div>`;
}

function bindUnbook() {
  document.querySelectorAll(".js-unbook").forEach((btn) => {
    btn.addEventListener("click", async () => {
      const id = Number(btn.dataset.id);
      const old = btn.textContent;
      btn.disabled = true; btn.textContent = "…";
      try {
        await unbookRide(id);
        await render();
      } catch (e) {
        console.error(e);
        alert("Échec de l'annulation");
      } finally {
        btn.disabled = false; btn.textContent = old;
      }
    });
  });
}

async function render() {
  $bFeedback.textContent = "Chargement…";
  $rFeedback.textContent = "Chargement…";
  $bList.innerHTML = "";
  $rList.innerHTML = "";

  const info = await me().catch(() => ({ auth: false }));
  if (!info?.auth) {
    $welcome.innerHTML = `<div class="alert alert-warning">Veuillez vous connecter pour accéder à votre profil.</div>`;
    $bFeedback.textContent = "";
    $rFeedback.textContent = "";
    return;
  }
  const userLabel = info.user?.pseudo || info.user?.email || "Utilisateur";
  $welcome.innerHTML = `Bonjour <strong>${userLabel}</strong>`;

  // réservations
  try {
    const data = await fetchMyBookings(); // { auth, bookings }
    const bookings = Array.isArray(data?.bookings) ? data.bookings : [];
    if (!bookings.length) {
      $bFeedback.textContent = "Aucune réservation.";
    } else {
      $bFeedback.textContent = "";
      $bList.innerHTML = bookings.map(bookingRow).join("");
      bindUnbook();
    }
  } catch (e) {
    console.error(e);
    $bFeedback.textContent = "Erreur de chargement des réservations.";
  }

  // trajets conducteur
  try {
    const rides = await fetchMyRides(); // tableau direct
    if (!Array.isArray(rides) || !rides.length) {
      $rFeedback.textContent = "Aucun trajet en tant que conducteur.";
    } else {
      $rFeedback.textContent = "";
      $rList.innerHTML = rides.map(rideCard).join("");
    }
  } catch (e) {
    console.error(e);
    $rFeedback.textContent = "Erreur de chargement des trajets.";
  }
}

render();