import { fetchRide, bookRide, unbookRide, me } from "../api.js";

const $err   = document.getElementById("ride-error");
const $auth  = document.getElementById("ride-auth");
const $fb    = document.getElementById("ride-feedback");
const $card  = document.getElementById("ride-card");
const $parts = document.getElementById("ride-participants");

function qparam(name) {
  const q = new URLSearchParams(window.location.search);
  return q.get(name);
}
function cls($el, hide, ...c) { if ($el) $el.classList[hide ? "add" : "remove"](...c); }
function showErr(msg) {
  if ($err) { $err.textContent = msg || "Erreur"; $err.classList.remove("d-none"); }
  if ($fb) $fb.textContent = "";
}
function badge(text, variant = "secondary") {
  return `<span class="badge text-bg-${variant}">${text}</span>`;
}

function rideView(r, state) {
  const p = typeof r.price === "number" ? r.price.toFixed(2) : r.price;
  const vehicle = r.vehicle ? `${r.vehicle.brand ?? ""} ${r.vehicle.model ?? ""}`.trim() : "—";
  const eco = r.vehicle?.eco ? " 🌿" : "";
  const rules = [
    r.allowSmoker ? "Fumeur: oui" : "Fumeur: non",
    r.allowAnimals ? "Animaux: oui" : "Animaux: non",
    r.music ? `Musique: ${r.music}` : null,
  ].filter(Boolean).join(" · ");

  // CTA selon l’état
  let bookHtml = "";
  if (!state.auth) {
    bookHtml = `<button class="btn btn-outline-primary btn-sm" disabled title="Connectez-vous pour réserver">Réserver</button>`;
  } else if (state.isDriver) {
    bookHtml = `<button class="btn btn-secondary btn-sm" disabled>Vous êtes le conducteur</button>`;
  } else if (state.alreadyBooked) {
    bookHtml = `
      <button class="btn btn-success btn-sm" disabled>Réservé ✓</button>
      <button class="btn btn-outline-danger btn-sm ms-2" id="ride-unbook" data-id="${r.id}">Annuler</button>
    `;
  } else if (state.soldOut) {
    bookHtml = `<button class="btn btn-outline-secondary btn-sm" disabled>Complet</button>`;
  } else {
    bookHtml = `<button class="btn btn-outline-primary btn-sm" id="ride-book" data-id="${r.id}">Réserver</button>`;
  }

  return `
    <div class="card shadow-sm">
      <div class="card-body d-flex flex-wrap justify-content-between gap-3">
        <div>
          <div class="fw-semibold">${r.from} ➜ ${r.to}</div>
          <div class="small text-muted">${r.startAt ?? ""} • ${r.seatsLeft}/${r.seatsTotal} places</div>
          <div class="small text-muted">${vehicle}${eco}</div>
          <div class="mt-2">${badge(r.status, r.status === "open" ? "success" : "secondary")}</div>
          ${rules ? `<div class="small text-muted mt-2">${rules}</div>` : ""}
          ${r.driver ? `<div class="small mt-2">Conducteur: <strong>${r.driver.pseudo ?? "—"}</strong></div>` : ""}
        </div>
        <div class="text-end">
          <div class="fs-5 fw-bold">${p} €</div>
          <div class="mt-2">${bookHtml}</div>
        </div>
      </div>
    </div>
  `;
}

function partsView(arr) {
  if (!arr || !arr.length) {
    return `<div class="text-muted">Aucun participant pour le moment.</div>`;
  }
  return arr.map(p => `
    <div class="border rounded p-2 mb-2">
      <div><strong>${p.user?.pseudo ?? "Utilisateur"}</strong> — ${p.seats} siège(s)</div>
      <div class="small text-muted">Statut: ${p.status}</div>
    </div>
  `).join("");
}

async function render() {
  const id = qparam("id");
  if (!id) return showErr("Paramètre ?id manquant (ex: /ride?id=7)");

  try {
    // état d’auth pour CTA
    let authInfo = { auth: false, user: null };
    try { authInfo = await me(); } catch {}
    if ($auth) cls($auth, authInfo.auth, "d-none"); // si pas auth => afficher CTA

    const r = await fetchRide(id);

    // états
    const isDriver = authInfo.auth && r?.driver?.id === authInfo.user?.id;
    const soldOut  = (r.seatsLeft ?? 0) <= 0;
    const alreadyBooked = authInfo.auth && Array.isArray(r.participants)
      && r.participants.some(p => p?.user?.id === authInfo.user?.id);

    if ($fb) $fb.textContent = "";
    if ($card) $card.innerHTML = rideView(r, { auth: authInfo.auth, isDriver, soldOut, alreadyBooked });
    if ($parts) $parts.innerHTML = partsView(r.participants);

    // actions
    const $b = document.getElementById("ride-book");
    const $u = document.getElementById("ride-unbook");

    $b?.addEventListener("click", async () => {
      const old = $b.textContent; $b.disabled = true; $b.textContent = "…";
      try {
        await bookRide(r.id, { seats: 1 });
        location.reload();
      } catch { alert("Échec de la réservation"); }
      finally { $b.disabled = false; $b.textContent = old; }
    });

    $u?.addEventListener("click", async () => {
      const old = $u.textContent; $u.disabled = true; $u.textContent = "…";
      try {
        await unbookRide(r.id);
        location.reload();
      } catch { alert("Échec de l'annulation"); }
      finally { $u.disabled = false; $u.textContent = old; }
    });

  } catch (e) {
    console.error(e);
    showErr("Trajet introuvable ou erreur serveur.");
  }
}

render();
