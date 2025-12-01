import { fetchMyBookings, unbookRide, me } from "../api.js";

const $err      = document.getElementById("bookings-error");
const $feedback = document.getElementById("bookings-feedback");
const $list     = document.getElementById("bookings-list");
const $authCta  = document.getElementById("auth-cta");

function hideErr(){ $err.classList.add("d-none"); $err.textContent = ""; }
function showErr(msg){ $err.textContent = msg || "Erreur"; $err.classList.remove("d-none"); }

function parseDt(s){
  if (!s) return null;
  const iso = s.replace(" ", "T");
  const d = new Date(iso);
  return isNaN(d) ? null : d;
}
function isPast(s){
  const d = parseDt(s);
  return d ? d.getTime() < Date.now() : false;
}

function bookingCard(b){
  const disabled = isPast(b.startAt) || (b.status && b.status !== "confirmed");
  const badge = (() => {
    const st = (b.status || "").toLowerCase();
    if (st === "confirmed") return `<span class="badge text-bg-success">confirmée</span>`;
    if (st === "cancelled") return `<span class="badge text-bg-secondary">annulée</span>`;
    return `<span class="badge text-bg-light text-dark">${b.status ?? "—"}</span>`;
  })();

  return `
    <div class="card mb-3 shadow-sm">
      <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
          <div class="fw-semibold">${b.from} ➜ ${b.to}</div>
          <div class="small text-muted">
            ${b.startAt ?? ""} • ${b.seats} place(s)
          </div>
          <div class="mt-1">${badge}</div>
          <a class="btn btn-link btn-sm p-0 mt-1" href="/ride?id=${b.rideId}" data-link>Voir le détail</a>
        </div>
        <div class="text-end">
          <button class="btn btn-outline-danger btn-sm js-unbook" data-id="${b.rideId}" ${disabled ? "disabled" : ""}>Annuler</button>
        </div>
      </div>
    </div>
  `;
}

function renderBuckets(items){
  const upcoming = [];
  const past = [];
  for (const it of items){
    (isPast(it.startAt) ? past : upcoming).push(it);
  }

  let html = "";
  if (upcoming.length){
    html += `<h3 class="h6 text-uppercase text-muted mt-3">À venir</h3>`;
    html += upcoming.map(bookingCard).join("");
  }
  if (past.length){
    html += `<h3 class="h6 text-uppercase text-muted mt-4">Passées</h3>`;
    html += past.map(bookingCard).join("");
  }
  if (!html){
    html = `<div class="text-muted">Aucune réservation.</div>`;
  }
  $list.innerHTML = html;
}

function bindUnbook(){
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

async function render(){
  hideErr();
  $authCta.classList.add("d-none");
  $feedback.textContent = "Chargement…";
  $list.innerHTML = "";

  // Vérif d'auth pour afficher le CTA 
  const info = await me().catch(() => ({ auth:false }));
  if (!info?.auth){
    $feedback.textContent = "";
    $authCta.classList.remove("d-none");
    return;
  }

  try {
    const data = await fetchMyBookings(); // {auth:boolean, bookings:[...]}
    if (!data?.auth){
      $feedback.textContent = "";
      $authCta.classList.remove("d-none");
      return;
    }

    const items = Array.isArray(data.bookings) ? data.bookings : [];
    $feedback.textContent = "";
    renderBuckets(items);
    bindUnbook();
  } catch (e) {
    console.error(e);
    $feedback.textContent = "";
    showErr("Erreur de chargement des réservations.");
  }
}

render();

