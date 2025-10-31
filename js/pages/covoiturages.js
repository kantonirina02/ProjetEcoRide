import { fetchRides, bookRide, fetchMyBookings } from "../api.js";

const getSession = () => (window.__session ?? null);

const $form = document.getElementById("rideSearchForm");
const $from = document.getElementById("departure");
const $to   = document.getElementById("arrival");
const $date = document.getElementById("date");
const $err  = document.getElementById("search-error-covoit");
const $list = document.getElementById("covoit-list");
const $feedback = document.getElementById("results-feedback");

const $eco   = document.getElementById("ecoFilter");
const $pmax  = document.getElementById("priceFilter");
const $dmax  = document.getElementById("durationFilter");
// rating non supporté côté API (on l’ignore proprement)
const $rmin  = document.getElementById("ratingFilter");

// --- Utilitaires
const navigate = (href) => {
  if (typeof window.navigate === "function") {
    window.navigate(href);
  } else {
    window.location.href = href;
  }
};

async function fetchMyBookedRideIds() {
  try {
    const data = await fetchMyBookings(); // { auth:boolean, bookings:[...] }
    const ids = new Set(
      Array.isArray(data?.bookings)
        ? data.bookings.map(x => (typeof x === "number" ? x : x.rideId)).filter(Boolean)
        : []
    );
    return ids;
  } catch {
    return new Set();
  }
}

// --- Pré-remplissage via querystring
(function prefillFromQuery() {
  const q = new URLSearchParams(window.location.search);
  if (q.has("from")) $from.value = q.get("from");
  if (q.has("to"))   $to.value   = q.get("to");
  if (q.has("date")) $date.value = q.get("date");
})();

function card(r, state) {
  const price = typeof r.price === "number" ? r.price.toFixed(2) : r.price;

  // Règles d’état bouton
  const session = getSession();
  const isDriver = !!(session?.user?.id) && r?.driver?.id === session.user.id;
  const soldOut  = (r.seatsLeft ?? 0) <= 0;
  const alreadyBooked = state.bookedIds.has(r.id);

  let btnHtml = "";
  if (isDriver) {
    btnHtml = `<button class="btn btn-secondary btn-sm" disabled>Vous êtes le conducteur</button>`;
  } else if (alreadyBooked) {
    btnHtml = `<button class="btn btn-success btn-sm" disabled>Réservé ✓</button>`;
  } else if (soldOut) {
    btnHtml = `<button class="btn btn-outline-secondary btn-sm" disabled>Complet</button>`;
  } else {
    btnHtml = `<button class="btn btn-outline-primary btn-sm js-book" data-id="${r.id}">Réserver</button>`;
  }

  const vehBrand = r?.vehicle?.brand ?? "";
  const vehModel = r?.vehicle?.model ?? "";
  const vehEco   = r?.vehicle?.eco ? "🌿" : "";

  return `
    <div class="card mb-3 shadow-sm">
      <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
          <div class="fw-semibold">${r.from} ➜ ${r.to}</div>
          <div class="small text-muted">
            ${r.startAt ?? ""} • ${r.seatsLeft ?? 0}/${r.seatsTotal ?? 0} places • 
            ${vehBrand} ${vehModel} ${vehEco}
          </div>
        </div>
        <div class="text-end">
          <div class="fs-5 fw-bold">${price} €</div>
          <div class="mt-2 d-flex gap-2 justify-content-end">
            <a class="btn btn-light btn-sm" href="/ride?id=${r.id}" data-link>Voir le détail</a>
            ${btnHtml}
          </div>
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
    // 1) Récupère la liste de trajets
    const items = await fetchRides({
      from: $from?.value?.trim() || "",
      to:   $to?.value?.trim()   || "",
      date: $date?.value || "",
      // eco/priceMax/durationMax ne feront effet que lorsque api.js les transmettra au backend
      eco: ($eco && $eco.checked) ? 1 : undefined,
      priceMax: $pmax?.value ? Number($pmax.value) : undefined,
      durationMax: $dmax?.value ? Number($dmax.value) : undefined,
    });

    // 2) Récupère les réservations de l’utilisateur (si connecté)
    const bookedIds = await fetchMyBookedRideIds();

    if (!Array.isArray(items) || items.length === 0) {
      $feedback.textContent = "Aucun résultat.";
      return;
    }

    $feedback.textContent = "";
    const state = { bookedIds };
    $list.innerHTML = items.map(r => card(r, state)).join("");
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
      const session = getSession();
      if (!session || !session.user) {
        alert("Connecte-toi d'abord pour réserver.");
        navigate("/signin");
        return;
      }

      const id = Number(btn.dataset.id);
      btn.disabled = true;
      const old = btn.textContent;
      btn.textContent = "…";

      try {
        await bookRide(id, { seats: 1 });

        // soit on recharge la liste:
        // await render();

        // soit on redirige vers /bookings :
        if (typeof window.navigate === "function") {
          window.navigate("/bookings");
        } else {
          window.location.href = "/bookings";
        }
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

  // Recharger quand un filtre change (si présents)
  const filterForm = document.getElementById("filterForm");
  if (filterForm) {
    filterForm.addEventListener("change", () => render());
  }

  // Lancer direct si on arrive avec des query params
  if (($from && $from.value) || ($to && $to.value) || ($date && $date.value)) {
    render();
  }
}
