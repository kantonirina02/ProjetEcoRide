import { fetchRides, bookRide, fetchMyBookings } from "../api.js";

const getSession = () => window.__session ?? null;

const $form = document.getElementById("rideSearchForm");
const $from = document.getElementById("departure");
const $to = document.getElementById("arrival");
const $date = document.getElementById("date");
const $err = document.getElementById("search-error-covoit");
const $list = document.getElementById("covoit-list");
const $feedback = document.getElementById("results-feedback");

const $eco = document.getElementById("ecoFilter");
const $pmax = document.getElementById("priceFilter");
const $dmax = document.getElementById("durationFilter");
const $rmin = document.getElementById("ratingFilter");

const FALLBACK_PHOTO = "/images/Andrea.jpg";

function navigate(href) {
  if (typeof window.navigate === "function") {
    window.navigate(href);
  } else {
    window.location.href = href;
  }
}

async function fetchMyBookedRideIds() {
  try {
    const data = await fetchMyBookings();
    if (!data?.auth || !Array.isArray(data.bookings)) return new Set();
    return new Set(
      data.bookings
        .map((item) => (typeof item === "number" ? item : item.rideId))
        .filter(Boolean)
    );
  } catch (error) {
    console.error("fetchMyBookings failed", error);
    return new Set();
  }
}

(function prefillFromQuery() {
  const q = new URLSearchParams(window.location.search);
  if (q.has("from")) $from.value = q.get("from");
  if (q.has("to")) $to.value = q.get("to");
  if (q.has("date")) $date.value = q.get("date");
})();

function formatRating(driver) {
  if (!driver) return "";
  if (typeof driver.rating === "number") {
    const count = driver.reviews ?? 0;
    return `<span class="badge text-bg-success"><i class="bi bi-star-fill me-1"></i>${driver.rating.toFixed(1)} (${count})</span>`;
  }
  return "";
}

function cardTemplate(ride, state) {
  const session = getSession();
  const isDriver = !!(session?.user?.id) && ride?.driver?.id === session.user.id;
  const soldOut = ride.soldOut || (ride.seatsLeft ?? 0) <= 0;
  const alreadyBooked = state.bookedIds.has(ride.id);

  let actionHtml = "";
  if (isDriver) {
    actionHtml = `<button class="btn btn-secondary btn-sm" disabled>Vous etes le conducteur</button>`;
  } else if (alreadyBooked) {
    actionHtml = `<button class="btn btn-success btn-sm" disabled>Reserve</button>`;
  } else if (soldOut) {
    actionHtml = `<button class="btn btn-outline-secondary btn-sm" disabled>Complet</button>`;
  } else {
    actionHtml = `<button class="btn btn-outline-primary btn-sm js-book" data-id="${ride.id}">Reserver</button>`;
  }

  const vehicle = ride.vehicle || {};
  const driver = ride.driver || {};
  const driverPhoto = driver.photo || FALLBACK_PHOTO;
  const ratingHtml = formatRating(driver);

  return `
    <div class="card mb-3 shadow-sm">
      <div class="card-body">
        <div class="d-flex align-items-start gap-3">
          <div class="flex-shrink-0">
            <img src="${driverPhoto}" alt="${driver.pseudo ?? "Conducteur"}" class="rounded-circle border" style="width:64px;height:64px;object-fit:cover;">
          </div>
          <div class="flex-grow-1">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
              <div>
                <div class="fw-semibold">${ride.from} → ${ride.to}</div>
                <div class="small text-muted">${ride.startAt ?? ""} · ${ride.seatsLeft ?? 0}/${ride.seatsTotal ?? 0} places · ${vehicle.brand ?? ""} ${vehicle.model ?? ""}${vehicle.eco ? " · Trajet eco" : ""}</div>
                ${ratingHtml ? `<div class="mt-1">${ratingHtml}</div>` : ""}
              </div>
              <div class="text-end">
                ${soldOut ? '<div class="badge text-bg-danger mb-2">Complet</div>' : ""}
                <div class="fs-5 fw-bold">${typeof ride.price === "number" ? ride.price.toFixed(2) : ride.price} €</div>
              </div>
            </div>
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3">
              <div class="text-muted small">Conducteur : <strong>${driver.pseudo ?? "Inconnu"}</strong></div>
              <div class="d-flex gap-2">
                <a class="btn btn-light btn-sm" href="/ride?id=${ride.id}" data-link>Voir le detail</a>
                ${actionHtml}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  `;
}

function renderSuggestion(suggestion) {
  if (!suggestion) {
    $feedback.textContent = "Aucun resultat.";
    return;
  }
  const formattedDate = suggestion.startAt ? suggestion.startAt.replace(" ", " · ") : suggestion.date;
  $feedback.innerHTML = `
    <div class="alert alert-warning" role="alert">
      Aucun trajet ne correspond exactement a votre recherche. Prochain trajet disponible :
      <strong>${suggestion.from} → ${suggestion.to}</strong> le <strong>${formattedDate ?? "date inconnue"}</strong>.
      <button class="btn btn-sm btn-outline-primary ms-2" id="applySuggestion">Utiliser cette date</button>
    </div>
  `;
  document.getElementById("applySuggestion")?.addEventListener("click", () => {
    if (suggestion.date && $date) {
      $date.value = suggestion.date;
    }
    render();
  });
}

async function render() {
  $err.textContent = "";
  $feedback.textContent = "Chargement…";
  $list.innerHTML = "";

  try {
    const result = await fetchRides({
      from: $from?.value?.trim() || "",
      to: $to?.value?.trim() || "",
      date: $date?.value || "",
      eco: ($eco && $eco.checked) ? 1 : undefined,
      priceMax: $pmax?.value ? Number($pmax.value) : undefined,
      durationMax: $dmax?.value ? Number($dmax.value) : undefined,
    });

    const rides = Array.isArray(result?.rides) ? result.rides : Array.isArray(result) ? result : [];
    const suggestion = result?.suggestion ?? null;

    // filtre note minimale côté front si le backend ne le gère pas encore
    const ratingMin = $rmin?.value ? Number($rmin.value) : null;
    const filteredRides = ratingMin != null && !Number.isNaN(ratingMin)
      ? rides.filter((ride) => typeof ride?.driver?.rating === "number" ? ride.driver.rating >= ratingMin : ratingMin <= 0)
      : rides;

    if (filteredRides.length === 0) {
      $list.innerHTML = "";
      renderSuggestion(suggestion);
      return;
    }

    $feedback.textContent = "";

    const bookedIds = await fetchMyBookedRideIds();
    const state = { bookedIds };

    $list.innerHTML = filteredRides.map((ride) => cardTemplate(ride, state)).join("");
    bindBookButtons();
  } catch (error) {
    console.error(error);
    $feedback.textContent = "";
    $err.textContent = "Erreur de chargement des trajets.";
  }
}

function bindBookButtons() {
  document.querySelectorAll(".js-book").forEach((btn) => {
    btn.addEventListener("click", async () => {
      const session = getSession();
      if (!session || !session.user) {
        alert("Connecte-toi d'abord pour reserver.");
        navigate("/signin");
        return;
      }

      const id = Number(btn.dataset.id);
      btn.disabled = true;
      const old = btn.textContent;
      btn.textContent = "…";

      try {
        await bookRide(id, { seats: 1 });
        if (typeof window.navigate === "function") {
          window.navigate("/bookings");
        } else {
          window.location.href = "/bookings";
        }
      } catch (error) {
        console.error(error);
        alert("Echec de la reservation");
      } finally {
        btn.disabled = false;
        btn.textContent = old;
      }
    });
  });
}

if ($form) {
  $form.addEventListener("submit", (event) => {
    event.preventDefault();
    render();
  });

  const filterForm = document.getElementById("filterForm");
  if (filterForm) {
    filterForm.addEventListener("change", () => render());
  }

  if (($from && $from.value) || ($to && $to.value) || ($date && $date.value)) {
    render();
  }
}

(function showFilters() {
  const wrap = document.getElementById("filter-section-wrapper");
  if (wrap) wrap.classList.remove("d-none");
})();

const resetBtn = document.getElementById("resetFilters");
if (resetBtn) {
  resetBtn.addEventListener("click", () => {
    if ($eco) $eco.checked = false;
    if ($pmax) $pmax.value = "";
    if ($dmax) $dmax.value = "";
    if ($rmin) $rmin.value = "";
    render();
  });
}
