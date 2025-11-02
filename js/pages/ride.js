import { fetchRide, bookRide, unbookRide, me } from "../api.js";

const $err = document.getElementById("ride-error");
const $auth = document.getElementById("ride-auth");
const $fb = document.getElementById("ride-feedback");
const $card = document.getElementById("ride-card");
const $parts = document.getElementById("ride-participants");
const $reviews = document.getElementById("ride-reviews");

function qparam(name) {
  const q = new URLSearchParams(window.location.search);
  return q.get(name);
}

function cls($el, hide, ...c) {
  if ($el) $el.classList[hide ? "add" : "remove"](...c);
}

function showErr(msg) {
  if ($err) {
    $err.textContent = msg || "Erreur";
    $err.classList.remove("d-none");
  }
  if ($fb) $fb.textContent = "";
}

function badge(text, variant = "secondary") {
  return `<span class="badge text-bg-${variant}">${text}</span>`;
}

function preferenceChips(preferences) {
  if (!preferences) return "";
  const chips = [];
  chips.push(preferences.allowSmoker ? "Fumeurs acceptés" : "Non fumeurs");
  chips.push(preferences.allowAnimals ? "Animaux acceptés" : "Sans animaux");
  if (preferences.musicStyle) chips.push(`Ambiance&nbsp;: ${preferences.musicStyle}`);
  return chips
    .map((txt) => `<span class="badge text-bg-light text-dark me-1 mb-1">${txt}</span>`)
    .join("");
}

function energyBadge(energy) {
  if (!energy) return "";
  const clean = `${energy}`.toLowerCase();
  // mapping large: essence/petrol/gasoline, gpl, hybride, etc.
  const map = {
    electric: "success",
    électrique: "success",
    hybrid: "info",
    hybride: "info",
    diesel: "secondary",
    petrol: "warning",
    essence: "warning",
    gasoline: "warning",
    gpl: "dark",
    cng: "dark",
  };
  return `<span class="badge text-bg-${map[clean] || "light"}">Énergie&nbsp;: ${energy}</span>`;
}

function rideView(ride, state) {
  const priceValue = typeof ride.price === "number" ? ride.price.toFixed(2) : ride.price;
  const vehicleDetails = ride.vehicle
    ? [
        ride.vehicle.brand || ride.vehicle.model
          ? `${ride.vehicle.brand ?? ""} ${ride.vehicle.model ?? ""}`.trim()
          : null,
        ride.vehicle.seats ? `${ride.vehicle.seats} place(s)` : null,
        ride.vehicle.eco ? "Trajet éco" : null,
        ride.vehicle.color ? `Couleur&nbsp;${ride.vehicle.color}` : null,
      ]
        .filter(Boolean)
        .join(" • ")
    : "Informations véhicule indisponibles";

  const energy = energyBadge(ride.vehicle?.energy);
  const prefChips = preferenceChips(ride.driver?.preferences);

  let bookHtml = "";
  if (!state.auth) {
    bookHtml = `<button class="btn btn-outline-primary btn-sm" disabled title="Connectez-vous pour réserver">Réserver</button>`;
  } else if (state.isDriver) {
    bookHtml = `<button class="btn btn-secondary btn-sm" disabled>Vous êtes le conducteur</button>`;
  } else if (state.alreadyBooked) {
    bookHtml = `
      <button class="btn btn-success btn-sm" disabled>Réservé</button>
      <button class="btn btn-outline-danger btn-sm ms-2" id="ride-unbook" data-id="${ride.id}">Annuler</button>
    `;
  } else if (state.soldOut) {
    bookHtml = `<button class="btn btn-outline-secondary btn-sm" disabled>Complet</button>`;
  } else {
    bookHtml = `<button class="btn btn-outline-primary btn-sm" id="ride-book" data-id="${ride.id}">Réserver</button>`;
  }

  const driverPhoto = ride.driver?.photo
    ? `<img src="${ride.driver.photo}" alt="Photo conducteur" class="rounded-circle me-3" style="width:56px;height:56px;object-fit:cover;" />`
    : "";

  const ratingBadge =
    typeof ride.driver?.rating === "number"
      ? `<span class="badge text-bg-success"><i class="bi bi-star-fill me-1"></i>${ride.driver.rating.toFixed(1)} (${ride.driver.reviews ?? 0})</span>`
      : "";

  const driverEmail = ride.driver?.email ? `<div class="small text-muted">Contact&nbsp;: ${ride.driver.email}</div>` : "";

  const statusVariant =
    ride.status === "open" ? "success" : ride.status === "cancelled" ? "danger" : "secondary";

  return `
    <div class="card shadow-sm">
      <div class="card-body d-flex flex-wrap justify-content-between gap-3">
        <div class="flex-grow-1">
          <div class="d-flex align-items-center mb-3">
            ${driverPhoto}
            <div>
              <div class="fw-semibold h5 mb-1">${ride.from} &rarr; ${ride.to}</div>
              <div class="small text-muted">${ride.startAt ?? ""} — ${ride.seatsLeft}/${ride.seatsTotal} place(s)</div>
              ${driverEmail}
            </div>
          </div>
          <div class="small text-muted mb-2">${vehicleDetails}</div>
          ${energy ? `<div class="mb-2">${energy}</div>` : ""}
          ${prefChips ? `<div class="mb-2">${prefChips}</div>` : ""}
          <div class="mt-2">${badge(ride.status, statusVariant)}</div>
          ${ratingBadge ? `<div class="mt-2">${ratingBadge}</div>` : ""}
          ${ride.driver ? `<div class="mt-2">Conducteur&nbsp;: <strong>${ride.driver.pseudo ?? "Inconnu"}</strong></div>` : ""}
        </div>
        <div class="text-end">
          <div class="fs-4 fw-bold">${priceValue} &euro;</div>
          <div class="mt-2">${bookHtml}</div>
        </div>
      </div>
    </div>
  `;
}

function partsView(list) {
  const items = Array.isArray(list) ? list : [];
  if (!items.length) {
    return `<div class="text-muted">Aucun participant pour le moment.</div>`;
  }
  return items
    .map((p) => {
      const pseudo = p.user?.pseudo ?? "Utilisateur";
      const email = p.user?.email ? `<div class="small text-muted">Email&nbsp;: ${p.user.email}</div>` : "";
      return `
        <div class="border rounded p-2 mb-2">
          <div><strong>${pseudo}</strong> — ${p.seats} siège(s)</div>
          <div class="small text-muted">Statut&nbsp;: ${p.status}</div>
          ${email}
        </div>
      `;
    })
    .join("");
}

function reviewsView(driver) {
  const list = driver?.reviewsList ?? [];
  if (!Array.isArray(list) || list.length === 0) {
    return `<div class="text-muted">Aucun avis pour ce conducteur.</div>`;
  }
  return list
    .map(
      (rev) => `
        <div class="border rounded p-3 mb-3">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <strong>${rev.author ?? "Utilisateur"}</strong>
            <span class="badge text-bg-success"><i class="bi bi-star-fill me-1"></i>${rev.rating}</span>
          </div>
          <div class="small text-muted">${rev.date ?? "Date inconnue"}</div>
          ${rev.comment ? `<p class="mb-0 mt-2">${rev.comment}</p>` : ""}
        </div>
      `
    )
    .join("");
}

async function render() {
  const id = qparam("id");
  if (!id) return showErr("Paramètre ?id manquant (ex. : /ride?id=7)");

  try {
    let authInfo = { auth: false, user: null };
    try {
      authInfo = await me();
    } catch (error) {
      console.error("me() failed", error);
    }
    if ($auth) cls($auth, authInfo.auth, "d-none"); // cache l’alerte si auth

    const ride = await fetchRide(id);
    if (!ride || typeof ride !== "object") {
      return showErr("Trajet introuvable.");
    }

    const isDriver = !!(authInfo.auth && ride?.driver?.id === authInfo.user?.id);
    const soldOut = (ride.seatsLeft ?? 0) <= 0;
    const alreadyBooked =
      !!(authInfo.auth &&
      Array.isArray(ride.participants) &&
      ride.participants.some((p) => p?.user?.id === authInfo.user?.id));

    if ($fb) $fb.textContent = "";
    if ($card) $card.innerHTML = rideView(ride, { auth: authInfo.auth, isDriver, soldOut, alreadyBooked });
    if ($parts) $parts.innerHTML = partsView(ride.participants);
    if ($reviews) $reviews.innerHTML = reviewsView(ride.driver);

    const $book = document.getElementById("ride-book");
    const $unbook = document.getElementById("ride-unbook");

    $book?.addEventListener("click", async () => {
      const old = $book.textContent;
      $book.disabled = true;
      $book.textContent = "...";
      try {
        await bookRide(ride.id, { seats: 1 });
        location.reload();
      } catch (error) {
        console.error(error);
        alert("Échec de la réservation");
      } finally {
        $book.disabled = false;
        $book.textContent = old;
      }
    });

    $unbook?.addEventListener("click", async () => {
      const old = $unbook.textContent;
      $unbook.disabled = true;
      $unbook.textContent = "...";
      try {
        await unbookRide(ride.id);
        location.reload();
      } catch (error) {
        console.error(error);
        alert("Échec de l'annulation");
      } finally {
        $unbook.disabled = false;
        $unbook.textContent = old;
      }
    });
  } catch (error) {
    console.error(error);
    showErr("Trajet introuvable ou erreur serveur.");
  }
}

render();
