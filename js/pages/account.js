import { me, fetchMyBookings, unbookRide, logout } from "../api.js";

const API = "http://localhost:8001/api";

// Header / actions
const $welcome     = document.getElementById("account-welcome");
const $btnLogout   = document.getElementById("account-logout");
const $btnRefresh  = document.getElementById("account-refresh");

// Carte profil
const $profile     = document.getElementById("account-profile");
const $accName     = document.getElementById("acc-name");
const $accEmail    = document.getElementById("acc-email");
const $accUserId   = document.getElementById("acc-userid");

// réservations
const data = await fetchMyBookings();
const bookings = Array.isArray(data?.bookings) ? data.bookings : [];
const cmpAsc  = (a,b) => (parseDt(a.startAt)?.getTime() ?? 0) - (parseDt(b.startAt)?.getTime() ?? 0);
const cmpDesc = (a,b) => (parseDt(b.startAt)?.getTime() ?? 0) - (parseDt(a.startAt)?.getTime() ?? 0);
const up   = bookings.filter(b => !isPast(b.startAt)).sort(cmpAsc);
const past = bookings.filter(b =>  isPast(b.startAt)).sort(cmpDesc);


// trajets conducteur
const rides = await res.json();
const upR   = (rides || []).filter(r => !isPast(r.startAt)).sort(cmpAsc);
const pastR = (rides || []).filter(r =>  isPast(r.startAt)).sort(cmpDesc);


// ---- utils ----
function parseDt(s) {
  if (!s) return null;
  const iso = s.replace(" ", "T");
  const d = new Date(iso);
  return isNaN(d) ? null : d;
}
function isPast(s) {
  const d = parseDt(s);
  return d ? d.getTime() < Date.now() : false;
}
function fmtPrice(p) {
  if (p == null) return "";
  const n = Number(p);
  return isFinite(n) ? `${n.toFixed(2)} €` : `${p} €`;
}
function badge(text) {
  const t = String(text || "").toLowerCase();
  const v = (t === "open" || t === "confirmed") ? "success"
        : (t === "cancelled" || t === "canceled") ? "danger"
        : "secondary";
  return `<span class="badge text-bg-${v}">${text || "—"}</span>`;
}

// ---- views ----
function rideCard(r) {
  const price = fmtPrice(r.price);
  const veh = `${r?.vehicle?.brand ?? ""} ${r?.vehicle?.model ?? ""}`.trim();
  const eco = r?.vehicle?.eco ? " 🌿" : "";
  const statusVariant = r.status === "open" ? "success" : "secondary";

  return `
  <div class="card mb-2 shadow-sm">
    <div class="card-body d-flex flex-wrap justify-content-between">
      <div>
        <div class="fw-semibold">${r.from} ➜ ${r.to}</div>
        <div class="small text-muted">
          ${r.startAt ?? ""} • ${r.seatsLeft}/${r.seatsTotal} places • ${veh} ${eco}
        </div>
        <div class="mt-1">${badge(r.status || "—", statusVariant)}</div>
      </div>
      <div class="text-end">
        <div class="fw-bold">${price}</div>
        <a class="btn btn-link btn-sm p-0 mt-1" href="/ride?id=${r.id}" data-link>Voir le détail</a>
      </div>
    </div>
  </div>`;
}

function bookingRow(b) {
  const canCancel = !isPast(b.startAt) && (!b.status || b.status === "confirmed");
  return `
  <div class="card mb-2 shadow-sm">
    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div>
        <div class="fw-semibold">${b.from} ➜ ${b.to}</div>
        <div class="small text-muted">${b.startAt ?? ""} • ${b.seats} siège(s) • statut: ${b.status}</div>
        <a class="btn btn-link btn-sm p-0 mt-1" href="/ride?id=${b.rideId}" data-link>Voir le détail</a>
      </div>
      <div class="text-end">
        <button class="btn btn-outline-danger btn-sm js-unbook" data-id="${b.rideId}" ${canCancel ? "" : "disabled"}>Annuler</button>
      </div>
    </div>
  </div>`;
}

function renderList(targetEl, itemsHtml, emptyText) {
  if (!itemsHtml || itemsHtml.trim() === "") {
    targetEl.innerHTML = `<div class="text-muted">${emptyText}</div>`;
  } else {
    targetEl.innerHTML = itemsHtml;
  }
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

// ---- main render ----
async function render() {
  // reset feedbacks
  $bFeedback.textContent = "Chargement…";
  $rFeedback.textContent = "Chargement…";
  $bUp.innerHTML = ""; $bPast.innerHTML = "";
  $rUp.innerHTML = ""; $rPast.innerHTML = "";

  // auth
  const info = await me().catch(() => ({ auth: false }));
  if (!info?.auth) {
    $profile.classList.add("d-none");
    $welcome.innerHTML = `<div class="alert alert-warning">Veuillez vous connecter pour accéder à votre profil.</div>`;
    $bFeedback.textContent = "";
    $rFeedback.textContent = "";
    return;
  }

  // profil mini-card
  const name = info.user?.pseudo || "Utilisateur";
  const email = info.user?.email || "";
  const uid = info.user?.id || "";
  $accName.textContent = name;
  $accEmail.textContent = email;
  $accUserId.textContent = `#${uid}`;
  $profile.classList.remove("d-none");

  $welcome.innerHTML = `Bonjour <strong>${name}</strong>`;

  // réservations
  try {
    const data = await fetchMyBookings(); // {auth, bookings:[]}
    const bookings = Array.isArray(data?.bookings) ? data.bookings : [];
    const up = bookings.filter(b => !isPast(b.startAt));
    const past = bookings.filter(b => isPast(b.startAt));

    $bCountUp.textContent = String(up.length);
    $bCountPast.textContent = String(past.length);

    $bFeedback.textContent = bookings.length ? "" : "Aucune réservation.";
    renderList($bUp, up.map(bookingRow).join(""), "Aucune réservation à venir.");
    renderList($bPast, past.map(bookingRow).join(""), "Aucune réservation passée.");
    bindUnbook();
  } catch (e) {
    console.error(e);
    $bFeedback.innerHTML = `Erreur de chargement des réservations. <button class="btn btn-link btn-sm p-0" id="retry-bookings">Réessayer</button>`;
    document.getElementById("retry-bookings")?.addEventListener("click", render);
  }

  // trajets conducteur
  try {
    const res = await fetch(`${API}/me/rides`, { credentials: "include", headers: { Accept: "application/json" }});
    if (!res.ok) throw new Error("HTTP "+res.status);
    const rides = await res.json();

    const up = (rides || []).filter(r => !isPast(r.startAt));
    const past = (rides || []).filter(r => isPast(r.startAt));

    $rCountUp.textContent = String(up.length);
    $rCountPast.textContent = String(past.length);

    $rFeedback.textContent = Array.isArray(rides) && rides.length ? "" : "Aucun trajet.";
    renderList($rUp, up.map(rideCard).join(""), "Aucun trajet à venir.");
    renderList($rPast, past.map(rideCard).join(""), "Aucun trajet passé.");
  } catch (e) {
    console.error(e);
    $rFeedback.innerHTML = `Erreur de chargement des trajets. <button class="btn btn-link btn-sm p-0" id="retry-rides">Réessayer</button>`;
    document.getElementById("retry-rides")?.addEventListener("click", render);
  }
}

// actions globales
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

$btnRefresh?.addEventListener("click", render);

// first render
render();
