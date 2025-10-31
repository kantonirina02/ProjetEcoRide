import { me, fetchMyBookings, unbookRide, logout } from "../api.js";

const API = "http://localhost:8001/api";

const $welcome   = document.getElementById("account-welcome");
const $bFeedback = document.getElementById("account-bookings-feedback");
const $bList     = document.getElementById("account-bookings-list");
const $rFeedback = document.getElementById("account-rides-feedback");
const $rList     = document.getElementById("account-rides-list");
const $btnLogout = document.getElementById("account-logout");

function parseDt(s) {
  if (!s) return null;
  // backend: "YYYY-MM-DD HH:mm" → "YYYY-MM-DDTHH:mm"
  const iso = s.replace(" ", "T");
  const d = new Date(iso);
  return isNaN(d) ? null : d;
}
function isPast(s) {
  const d = parseDt(s);
  return d ? d.getTime() < Date.now() : false;
}

function rideCard(r) {
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
        <div class="small">${r.status}</div>
        <a class="btn btn-link btn-sm p-0 mt-1" href="/ride?id=${r.id}" data-link>Voir le détail</a>
      </div>
    </div>
  </div>`;
}

function bookingRow(b) {
  const disabled = isPast(b.startAt) || (b.status && b.status !== "confirmed");
  return `
  <div class="card mb-2 shadow-sm">
    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div>
        <div class="fw-semibold">${b.from} ➜ ${b.to}</div>
        <div class="small text-muted">${b.startAt ?? ""} • ${b.seats} siège(s) • statut: ${b.status}</div>
        <a class="btn btn-link btn-sm p-0 mt-1" href="/ride?id=${b.rideId}" data-link>Voir le détail</a>
      </div>
      <div class="text-end">
        <button class="btn btn-outline-danger btn-sm js-unbook" data-id="${b.rideId}" ${disabled ? "disabled" : ""}>Annuler</button>
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

  // Réservations
  try {
    const data = await fetchMyBookings();
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

  // Trajets conducteur
  try {
    const res = await fetch(`${API}/me/rides`, { credentials: "include", headers: { Accept: "application/json" }});
    if (!res.ok) throw new Error("HTTP "+res.status);
    const rides = await res.json();
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

$btnLogout?.addEventListener("click", async () => {
  try {
    await logout();
    window.__session = null;
    window.dispatchEvent(new CustomEvent("session:changed"));
    if (typeof window.navigate === "function") window.navigate("/signin");
    else window.location.href = "/signin";
  } catch (e) {
    console.error(e);
  }
});

render();
