import {
  fetchAccountOverview,
  fetchMyBookings,
  fetchMyRides,
  fetchMyVehicles,
  unbookRide,
  logout,
  updateDriverRole,
  saveDriverPreferences,
  saveVehicle,
  deleteVehicle,
  cancelRideAsDriver,
} from "../api.js";

const $welcome = document.getElementById("account-welcome");
const $profile = document.getElementById("account-profile");
const $accName = document.getElementById("acc-name");
const $accEmail = document.getElementById("acc-email");
const $accUserId = document.getElementById("acc-userid");
const $accRoles = document.getElementById("acc-roles");
const $accCredits = document.getElementById("acc-credits");

const $driverToggle = document.getElementById("acc-driver-toggle");
const $driverFeedback = document.getElementById("acc-driver-feedback");

const $prefsForm = document.getElementById("acc-prefs-form");
const $prefSmoker = document.getElementById("acc-pref-smoker");
const $prefAnimals = document.getElementById("acc-pref-animals");
const $prefMusic = document.getElementById("acc-pref-music");
const $prefsFeedback = document.getElementById("acc-prefs-feedback");

const $vehiclesList = document.getElementById("acc-vehicles-list");
const $vehicleForm = document.getElementById("acc-vehicle-form");
const $vehicleId = document.getElementById("acc-vehicle-id");
const $vehicleBrand = document.getElementById("acc-vehicle-brand");
const $vehicleModel = document.getElementById("acc-vehicle-model");
const $vehicleSeats = document.getElementById("acc-vehicle-seats");
const $vehicleEnergy = document.getElementById("acc-vehicle-energy");
const $vehicleColor = document.getElementById("acc-vehicle-color");
const $vehiclePlate = document.getElementById("acc-vehicle-plate");
const $vehicleEco = document.getElementById("acc-vehicle-eco");
const $vehicleReset = document.getElementById("acc-vehicle-reset");
const $vehicleFeedback = document.getElementById("acc-vehicle-feedback");

const $bookingsFeedback = document.getElementById("account-bookings-feedback");
const $bookingsUpcoming = document.getElementById("account-bookings-upcoming");
const $bookingsPast = document.getElementById("account-bookings-past");
const $bookingsCountUpcoming = document.getElementById("count-bookings-upcoming");
const $bookingsCountPast = document.getElementById("count-bookings-past");

const $ridesFeedback = document.getElementById("account-rides-feedback");
const $ridesUpcoming = document.getElementById("account-rides-upcoming");
const $ridesPast = document.getElementById("account-rides-past");
const $ridesCountUpcoming = document.getElementById("count-rides-upcoming");
const $ridesCountPast = document.getElementById("count-rides-past");

const $btnLogout = document.getElementById("account-logout");
const $btnRefresh = document.getElementById("account-refresh");

const state = {
  user: null,
  preferences: { allowSmoker: false, allowAnimals: false, musicStyle: null },
  vehicles: [],
};

function parseDate(value) {
  if (!value) return null;
  const iso = value.replace(" ", "T");
  const date = new Date(iso);
  return Number.isNaN(date.getTime()) ? null : date;
}
function isPast(value) {
  const d = parseDate(value);
  return d ? d.getTime() < Date.now() : false;
}
function sortByDateAsc(list, getter) {
  return [...list].sort((a, b) => {
    const da = parseDate(getter(a))?.getTime() ?? 0;
    const db = parseDate(getter(b))?.getTime() ?? 0;
    return da - db;
  });
}
function formatPrice(value) {
  const n = Number(value);
  return Number.isFinite(n) ? `${n.toFixed(2)} €` : (value != null ? `${value} €` : "");
}
function badge(text) {
  const t = (text || "").toLowerCase();
  const variant = t === "open" || t === "confirmed" ? "success" :
                  t === "cancelled" || t === "canceled" ? "danger" : "secondary";
  return `<span class="badge text-bg-${variant}">${text || "-"}</span>`;
}

/* -- Vehicles --*/
function vehicleSeatsOf(v) {
  return (v.seatsTotal ?? v.seats) ?? 0;
}

function vehicleCard(v) {
  const label = `${v.brand ?? ""} ${v.model ?? ""}`.trim() || "Sans nom";
  const details = [
    vehicleSeatsOf(v) ? `${vehicleSeatsOf(v)} places` : null,
    v.energy || null,
    v.eco ? "Éco" : null,
    v.color || null,
    v.plate || null,
  ].filter(Boolean).join(" • ");
  return `
    <div class="border rounded p-2 mb-2">
      <div class="d-flex justify-content-between align-items-start gap-2">
        <div>
          <div class="fw-semibold">${label}</div>
          <div class="small text-muted">${details || "Aucun détail"}</div>
        </div>
        <div class="d-flex gap-2">
          <button class="btn btn-sm btn-outline-primary js-vehicle-edit" data-id="${v.id}">Modifier</button>
          <button class="btn btn-sm btn-outline-danger js-vehicle-delete" data-id="${v.id}">Supprimer</button>
        </div>
      </div>
    </div>
  `;
}

function renderVehicles() {
  if (!Array.isArray(state.vehicles) || state.vehicles.length === 0) {
    $vehiclesList.innerHTML = `<div class="text-muted">Aucun véhicule enregistré.</div>`;
    return;
  }
  $vehiclesList.innerHTML = state.vehicles.map(vehicleCard).join("");

  document.querySelectorAll(".js-vehicle-edit").forEach((btn) => {
    btn.addEventListener("click", () => {
      const id = Number(btn.dataset.id);
      const vehicle = state.vehicles.find((v) => v.id === id);
      if (!vehicle) return;
      $vehicleId.value = vehicle.id;
      $vehicleBrand.value = vehicle.brand ?? "";
      $vehicleModel.value = vehicle.model ?? "";
      $vehicleSeats.value = vehicleSeatsOf(vehicle) || 4;
      $vehicleEnergy.value = vehicle.energy ?? "";
      $vehicleColor.value = vehicle.color ?? "";
      $vehiclePlate.value = vehicle.plate ?? "";
      $vehicleEco.checked = Boolean(vehicle.eco);
      $vehicleFeedback.textContent = "Modification d'un véhicule existant.";
    });
  });

  document.querySelectorAll(".js-vehicle-delete").forEach((btn) => {
    btn.addEventListener("click", async () => {
      const id = Number(btn.dataset.id);
      if (!Number.isFinite(id)) return;
      if (!window.confirm("Supprimer ce véhicule ?")) return;
      btn.disabled = true;
      try {
        await deleteVehicle(id);
        await reloadVehicles();
        $vehicleFeedback.textContent = "Véhicule supprimé.";
      } catch (e) {
        console.error(e);
        $vehicleFeedback.textContent = "Erreur lors de la suppression.";
      } finally {
        btn.disabled = false;
      }
    });
  });
}

function resetVehicleForm() {
  $vehicleId.value = "";
  $vehicleBrand.value = "";
  $vehicleModel.value = "";
  $vehicleSeats.value = "4";
  $vehicleEnergy.value = "";
  $vehicleColor.value = "";
  $vehiclePlate.value = "";
  $vehicleEco.checked = false;
  $vehicleFeedback.textContent = "";
}

/* -- Bookings / Rides -- */
function bookingCard(booking) {
  const canCancel = !isPast(booking.startAt) && (!booking.status || booking.status === "confirmed");
  return `
    <div class="card mb-2 shadow-sm">
      <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
          <div class="fw-semibold">${booking.from} &rarr; ${booking.to}</div>
          <div class="small text-muted">
            ${booking.startAt ?? ""} • ${booking.seats} siège(s) • statut : ${booking.status || "-"}
          </div>
          <a class="btn btn-link btn-sm p-0 mt-1" href="/ride?id=${booking.rideId}" data-link>Voir le détail</a>
        </div>
        <div class="text-end">
          <button class="btn btn-outline-danger btn-sm js-unbook" data-id="${booking.rideId}" ${canCancel ? "" : "disabled"}>Annuler</button>
        </div>
      </div>
    </div>
  `;
}

function driverRideCard(ride, canCancel) {
  const vehicle = ride.vehicle ? `${ride.vehicle.brand ?? ""} ${ride.vehicle.model ?? ""}`.trim() : "";
  return `
    <div class="card mb-2 shadow-sm">
      <div class="card-body d-flex flex-wrap justify-content-between gap-2">
        <div>
          <div class="fw-semibold">${ride.from} &rarr; ${ride.to}</div>
          <div class="small text-muted">
            ${ride.startAt ?? ""} • ${ride.seatsLeft}/${ride.seatsTotal} places • ${vehicle}
          </div>
          <div class="mt-1">${badge(ride.status)}</div>
          <a class="btn btn-link btn-sm p-0 mt-2" href="/ride?id=${ride.id}" data-link>Voir le détail</a>
        </div>
        <div class="text-end">
          <div class="fw-bold">${formatPrice(ride.price)}</div>
          <button class="btn btn-outline-danger btn-sm mt-2 js-cancel-ride" data-id="${ride.id}" ${canCancel ? "" : "disabled"}>Annuler le trajet</button>
        </div>
      </div>
    </div>
  `;
}

function renderList(target, html, emptyText) {
  target.innerHTML = html && html.trim() !== "" ? html : `<div class="text-muted">${emptyText}</div>`;
}

async function reloadVehicles() {
  try {
    const response = await fetchMyVehicles();
    state.vehicles = response?.auth === false ? [] : (Array.isArray(response?.vehicles) ? response.vehicles : []);
  } catch (e) {
    console.error(e);
    state.vehicles = [];
  }
  renderVehicles();
}

async function renderAccount() {
  $bookingsFeedback.textContent = "Chargement...";
  $ridesFeedback.textContent = "Chargement...";
  $bookingsUpcoming.innerHTML = "";
  $bookingsPast.innerHTML = "";
  $ridesUpcoming.innerHTML = "";
  $ridesPast.innerHTML = "";

  try {
    const [overview, bookingsData, ridesData] = await Promise.all([
      fetchAccountOverview().catch((e) => (e.message.includes("401") ? { auth: false } : Promise.reject(e))),
      fetchMyBookings().catch((e) => { console.error(e); return null; }),
      fetchMyRides().catch((e) => { console.error(e); return null; }),
    ]);

    if (!overview || overview.auth === false) {
      state.user = null;
      $profile.classList.add("d-none");
      $welcome.innerHTML = `<div class="alert alert-warning mb-0">Veuillez vous connecter pour accéder à votre espace.</div>`;
      $bookingsFeedback.textContent = "";
      $ridesFeedback.textContent = "";
      return;
    }

    state.user = overview.user;
    state.preferences = overview.preferences ?? state.preferences;
    state.vehicles = overview.vehicles ?? [];

    $accName.textContent = overview.user?.pseudo ?? "Utilisateur";
    $accEmail.textContent = overview.user?.email ?? "";
    $accUserId.textContent = `#${overview.user?.id ?? ""}`;
    $accRoles.textContent = Array.isArray(overview.user?.roles) ? overview.user.roles.join(", ") : "";
    $accCredits.textContent = overview.user?.credits ?? 0;
    $profile.classList.remove("d-none");
    $welcome.innerHTML = `Bonjour <strong>${overview.user?.pseudo ?? "utilisateur"}</strong> !`;

    const driverEnabled = Array.isArray(overview.user?.roles) && overview.user.roles.includes("ROLE_DRIVER");
    $driverToggle.checked = driverEnabled;
    $driverFeedback.textContent = "";

    $prefSmoker.checked = Boolean(state.preferences.allowSmoker);
    $prefAnimals.checked = Boolean(state.preferences.allowAnimals);
    $prefMusic.value = state.preferences.musicStyle ?? "";
    $prefsFeedback.textContent = "";

    renderVehicles();

    if (bookingsData && Array.isArray(bookingsData.bookings)) {
      const upcoming = sortByDateAsc(bookingsData.bookings.filter((b) => !isPast(b.startAt)), (b) => b.startAt);
      const past = sortByDateAsc(bookingsData.bookings.filter((b) => isPast(b.startAt)), (b) => b.startAt);

      $bookingsCountUpcoming.textContent = String(upcoming.length);
      $bookingsCountPast.textContent = String(past.length);

      renderList($bookingsUpcoming, upcoming.map(bookingCard).join(""), "Aucune réservation à venir.");
      renderList($bookingsPast, past.map(bookingCard).join(""), "Aucune réservation passée.");
      $bookingsFeedback.textContent = bookingsData.bookings.length ? "" : "Aucune réservation.";

      document.querySelectorAll(".js-unbook").forEach((btn) => {
        btn.addEventListener("click", async () => {
          const id = Number(btn.dataset.id);
          if (!Number.isFinite(id)) return;
          const old = btn.textContent;
          btn.disabled = true;
          btn.textContent = "...";
          try {
            await unbookRide(id);
            await renderAccount();
          } catch (e) {
            console.error(e);
            alert("Échec de l'annulation.");
          } finally {
            btn.disabled = false;
            btn.textContent = old;
          }
        });
      });
    } else {
      $bookingsFeedback.textContent = "Impossible de charger les réservations.";
    }

    if (Array.isArray(ridesData)) {
      const upcomingR = sortByDateAsc(ridesData.filter((r) => !isPast(r.startAt)), (r) => r.startAt);
      const pastR = sortByDateAsc(ridesData.filter((r) => isPast(r.startAt)), (r) => r.startAt);

      $ridesCountUpcoming.textContent = String(upcomingR.length);
      $ridesCountPast.textContent = String(pastR.length);

      renderList($ridesUpcoming, upcomingR.map((r) => driverRideCard(r, r.status === "open")).join(""), "Aucun trajet à venir.");
      renderList($ridesPast, pastR.map((r) => driverRideCard(r, false)).join(""), "Aucun trajet passé.");
      $ridesFeedback.textContent = ridesData.length ? "" : "Aucun trajet conducteur.";

      document.querySelectorAll(".js-cancel-ride").forEach((btn) => {
        btn.addEventListener("click", async () => {
          const id = Number(btn.dataset.id);
          if (!Number.isFinite(id)) return;
          if (!window.confirm("Annuler ce trajet ? Les passagers seront remboursés.")) return;
          const old = btn.textContent;
          btn.disabled = true;
          btn.textContent = "...";
          try {
            await cancelRideAsDriver(id);
            await renderAccount();
          } catch (e) {
            console.error(e);
            alert("Échec de l'annulation du trajet.");
          } finally {
            btn.disabled = false;
            btn.textContent = old;
          }
        });
      });
    } else {
      $ridesFeedback.textContent = "Impossible de charger les trajets conducteur.";
    }
  } catch (e) {
    console.error(e);
    $welcome.innerHTML = `<div class="alert alert-danger mb-0">Erreur lors du chargement du compte.</div>`;
  }
}

/* -- actions -- */
$driverToggle?.addEventListener("change", async () => {
  if (!state.user) return;
  const targetValue = $driverToggle.checked;
  $driverToggle.disabled = true;
  $driverFeedback.textContent = "Enregistrement...";
  try {
    const res = await updateDriverRole({ driver: targetValue });
    if (Array.isArray(res?.roles)) {
      state.user.roles = res.roles;
      $accRoles.textContent = res.roles.join(", ");
    }
    $driverFeedback.textContent = "Rôle mis à jour.";
  } catch (e) {
    console.error(e);
    $driverToggle.checked = !targetValue;
    $driverFeedback.textContent = "Impossible de mettre à jour le rôle.";
  } finally {
    $driverToggle.disabled = false;
  }
});

$prefsForm?.addEventListener("submit", async (ev) => {
  ev.preventDefault();
  if (!state.user) return;
  $prefsFeedback.textContent = "Enregistrement...";
  try {
    const payload = {
      allowSmoker: $prefSmoker.checked,
      allowAnimals: $prefAnimals.checked,
      musicStyle: $prefMusic.value.trim() || null,
    };
    const res = await saveDriverPreferences(payload);
    state.preferences = res?.preferences ?? payload;
    $prefsFeedback.textContent = "Préférences enregistrées.";
  } catch (e) {
    console.error(e);
    $prefsFeedback.textContent = "Erreur lors de l’enregistrement.";
  }
});

$vehicleForm?.addEventListener("submit", async (ev) => {
  ev.preventDefault();
  if (!state.user) return;

  const payload = {
    id: $vehicleId.value ? Number($vehicleId.value) : undefined,
    brand: $vehicleBrand.value.trim(),
    model: $vehicleModel.value.trim(),
    seats: Number($vehicleSeats.value) || 4,
    energy: $vehicleEnergy.value.trim() || "electric",
    color: $vehicleColor.value.trim() || null,
    plate: $vehiclePlate.value.trim() || null,
    eco: $vehicleEco.checked,
  };

  $vehicleFeedback.textContent = "Enregistrement...";
  try {
    await saveVehicle(payload);
    resetVehicleForm();
    await reloadVehicles();
    $vehicleFeedback.textContent = "Véhicule enregistré.";
  } catch (e) {
    console.error(e);
    $vehicleFeedback.textContent = "Erreur lors de l'enregistrement.";
  }
});

$vehicleReset?.addEventListener("click", () => resetVehicleForm());
$btnLogout?.addEventListener("click", async () => {
  try {
    await logout();
    if (typeof window.navigate === "function") window.navigate("/signin");
    else window.location.href = "/signin";
  } catch (e) { console.error(e); }
});
$btnRefresh?.addEventListener("click", () => renderAccount());

renderAccount();
