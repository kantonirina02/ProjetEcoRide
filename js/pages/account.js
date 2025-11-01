import { me, fetchMyBookings, unbookRide, logout } from "../api.js";

const API = "http://localhost:8001/api";

const $welcome   = document.getElementById("account-welcome");

// Réservations
const $bFeedback = document.getElementById("account-bookings-feedback");
const $bEmpty    = document.getElementById("account-bookings-empty");
const $bList     = document.getElementById("account-bookings-list");

// Trajets conducteur
const $rFeedback = document.getElementById("account-rides-feedback");
const $rEmpty    = document.getElementById("account-rides-empty");
const $rList     = document.getElementById("account-rides-list");

// Badges
const $badgeBookings = document.getElementById("badge-bookings");
const $badgeRides    = document.getElementById("badge-rides");
const $badgeUpcoming = document.getElementById("badge-upcoming");
const $badgePast     = document.getElementById("badge-past");

// Logout
const $btnLogout = document.getElementById("account-logout");

// Utils date
function parseDt(s) {
  if (!s) return null;
  const d = new Date(s.replace(" ", "T"));
  return isNaN(d) ? null : d;
}
function isPast(s) {
  const d = parseDt(s);
  return d ? d.getTime() < Date.now() : false;
}
function fmtPrice(p) {
  if (p == null) return "-";
  const n = Number(p);
  return isFinite(n) ? `${n.toFixed(2)} €` : `${p} €`;
}

// Toast minimal
function toast(msg, type = "success") {
  const id = `t${Date.now()}`;
  const el = document.createElement("div");
  el.id = id;
  el.className = `alert alert-${type} shadow-sm`;
  el.textContent = msg;
  document.getElementById("toast-area")?.appendChild(el);
  setTimeout(() => el.remove(), 2000);
}

// Render cards
function rideCard(r) {
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
        <div class="fw-bold">${fmtPrice(r.price)}</div>
        <div class="small">${r.status ?? "-"}</div>
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
      if (!confirm("Confirmer l'annulation de votre réservation ?")) return;

      btn.disabled = true; btn.textContent = "…";
      try {
        await unbookRide(id);
        toast("Réservation annulée");
        await render();
      } catch (e) {
        console.error(e);
        toast("Échec de l'annulation", "danger");
      } finally {
        btn.disabled = false; btn.textContent = old;
      }
    });
  });
}

async function render() {
  // Init
  $bFeedback.textContent = "Chargement…";
  $rFeedback.textContent = "Chargement…";
  $bEmpty.classList.add("d-none");
  $rEmpty.classList.add("d-none");
  $bList.innerHTML = "";
  $rList.innerHTML = "";
  $badgeBookings.textContent = "0";
  $badgeRides.textContent = "0";
  $badgeUpcoming.textContent = "À venir: 0";
  $badgePast.textContent = "Passées: 0";

  // Auth
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
    $badgeBookings.textContent = String(bookings.length);

    if (!bookings.length) {
      $bEmpty.classList.remove("d-none");
      $bFeedback.textContent = "";
    } else {
      // Split à venir / passées (juste pour les badges)
      const upcoming = bookings.filter(b => !isPast(b.startAt));
      const past     = bookings.filter(b =>  isPast(b.startAt));
      $badgeUpcoming.textContent = `À venir: ${upcoming.length}`;
      $badgePast.textContent     = `Passées: ${past.length}`;

      // Affichage (on garde l’ordre d’origine)
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
    if (!res.ok) throw new Error("HTTP " + res.status);
    const rides = await res.json(); // tableau
    $badgeRides.textContent = String(Array.isArray(rides) ? rides.length : 0);

    if (!Array.isArray(rides) || !rides.length) {
      $rEmpty.classList.remove("d-none");
      $rFeedback.textContent = "";
    } else {
      $rFeedback.textContent = "";
      $rList.innerHTML = rides.map(rideCard).join("");
    }
  } catch (e) {
    console.error(e);
    $rFeedback.textContent = "Erreur de chargement des trajets.";
  }
}

// Logout
$btnLogout?.addEventListener("click", async () => {
  try {
    await logout();
    window.__session = null;
    window.dispatchEvent(new CustomEvent("session:changed"));
    if (typeof window.navigate === "function") window.navigate("/signin");
    else window.location.href = "/signin";
  } catch (e) {
    console.error(e);
    toast("Échec de la déconnexion", "danger");
  }
});

render();
