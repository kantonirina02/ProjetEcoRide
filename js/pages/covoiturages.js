import { fetchRides, bookRide } from "../api.js";

const $form = document.getElementById("rideSearchForm");
const $from = document.getElementById("departure");
const $to   = document.getElementById("arrival");
const $date = document.getElementById("date");
const $err  = document.getElementById("search-error-covoit");
const $list = document.getElementById("covoit-list");
const $feedback = document.getElementById("results-feedback");

// --- Pré-remplissage via query string (from, to, date)
(function prefillFromQuery() {
  const q = new URLSearchParams(window.location.search);
  if (q.has("from")) $from.value = q.get("from");
  if (q.has("to"))   $to.value   = q.get("to");
  if (q.has("date")) $date.value = q.get("date");
})();

function card(r) {
  const price = typeof r.price === "number" ? r.price.toFixed(2) : r.price;
  return `
    <div class="card mb-3 shadow-sm">
      <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
          <div class="fw-semibold">${r.from} ➜ ${r.to}</div>
          <div class="small text-muted">
            ${r.startAt} • ${r.seatsLeft}/${r.seatsTotal} places • 
            ${r.vehicle.brand} ${r.vehicle.model} ${r.vehicle.eco ? "🌿" : ""}
          </div>
        </div>
        <div class="text-end">
          <div class="fs-5 fw-bold">${price} €</div>
          <button class="btn btn-outline-primary btn-sm mt-2 js-book" data-id="${r.id}">Réserver</button>
        </div>
      </div>
    </div>
  `;
}

async function render() {
  $err.textContent = "";
  $feedback.textContent = "Chargement…";
  $list.innerHTML = "";

  try {
    const items = await fetchRides({
      from: $from.value.trim(),
      to:   $to.value.trim(),
      date: $date.value,
    });

    if (!items.length) {
      $feedback.textContent = "Aucun résultat.";
      return;
    }

    $feedback.textContent = "";
    $list.innerHTML = items.map(card).join("");
    bindBookButtons();
  } catch (e) {
    console.error(e);
    $feedback.textContent = "";
    $err.textContent = "Erreur de chargement des trajets.";
  }
}

function bindBookButtons() {
  document.querySelectorAll(".js-book").forEach((btn) => {
    btn.addEventListener("click", async () => {
      const id = Number(btn.dataset.id);
      btn.disabled = true;
      const old = btn.textContent;
      btn.textContent = "…";
      try {
        // TODO: remplacer 1 par l'ID du user connecté quand l'auth sera en place
        const res = await bookRide(id, { userId: 1, seats: 1 });
        alert(`Réservation OK ! Places restantes : ${res.seatsLeft}`);
        await render();
      } catch (e) {
        console.error(e);
        alert("Échec de la réservation");
      } finally {
        btn.disabled = false;
        btn.textContent = old;
      }
    });
  });
}

if ($form) {
  $form.addEventListener("submit", (e) => {
    e.preventDefault();
    render();
  });

  // Si la page vient de Home avec des query params, on lance directement
  if ($from.value || $to.value || $date.value) {
    render();
  }
}
