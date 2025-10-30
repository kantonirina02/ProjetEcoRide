import { fetchMyBookings, unbookRide, me } from "../api.js";

const $err = document.getElementById("bookings-error");
const $feedback = document.getElementById("bookings-feedback");
const $list = document.getElementById("bookings-list");
const $authCta = document.getElementById("auth-cta");

function row(b) {
  return `
    <div class="card mb-3 shadow-sm">
      <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
          <div class="fw-semibold">${b.from} ➜ ${b.to}</div>
          <div class="small text-muted">
            ${b.startAt ?? ""} • ${b.seats} place(s) • statut: ${b.status}
          </div>
        </div>
        <div class="text-end">
          <button class="btn btn-outline-danger btn-sm js-unbook" data-id="${b.rideId}">Annuler</button>
        </div>
      </div>
    </div>
  `;
}

async function render() {
  $err.classList.add("d-none"); $err.textContent = "";
  $authCta.classList.add("d-none");
  $feedback.textContent = "Chargement…";
  $list.innerHTML = "";

  try {
    // Optionnel : vérif rapide d'auth pour éviter un appel si pas loggé
    const info = await me().catch(() => ({ auth:false }));
    if (!info || info.auth === false) {
      $feedback.textContent = "";
      $authCta.classList.remove("d-none");
      return;
    }

    const data = await fetchMyBookings(); // { auth: boolean, bookings: [...] }
    if (!data.auth) {
      $feedback.textContent = "";
      $authCta.classList.remove("d-none");
      return;
    }

    const items = data.bookings ?? [];
    if (!items.length) {
      $feedback.textContent = "Aucune réservation.";
      return;
    }

    $feedback.textContent = "";
    $list.innerHTML = items.map(row).join("");
    bindUnbook();
  } catch (e) {
    console.error(e);
    $feedback.textContent = "";
    $err.textContent = "Erreur de chargement des réservations.";
    $err.classList.remove("d-none");
  }
}

function bindUnbook() {
  document.querySelectorAll(".js-unbook").forEach((btn) => {
    btn.addEventListener("click", async () => {
      const id = Number(btn.dataset.id);
      btn.disabled = true;
      const old = btn.textContent;
      btn.textContent = "…";
      try {
        await unbookRide(id);
        await render();
      } catch (e) {
        console.error(e);
        alert("Échec de l'annulation");
      } finally {
        btn.disabled = false;
        btn.textContent = old;
      }
    });
  });
}

render();
