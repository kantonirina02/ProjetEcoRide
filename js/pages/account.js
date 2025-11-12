import {
  API_BASE,
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
  uploadProfilePhoto,
  deleteProfilePhoto,
} from "../api.js";
import { clearSession } from "../auth/session.js";

const $welcome = document.getElementById("account-welcome");
const $profile = document.getElementById("account-profile");
const $accName = document.getElementById("acc-name");
const $accEmail = document.getElementById("acc-email");
const $accCredits = document.getElementById("acc-credits");

const $photoImg = document.getElementById("acc-photo");
const $photoPlaceholder = document.getElementById("acc-photo-placeholder");
const $photoUpload = document.getElementById("acc-photo-upload");
const $photoRemove = document.getElementById("acc-photo-remove");
const $photoInput = document.getElementById("acc-photo-input");
const $photoFeedback = document.getElementById("acc-photo-feedback");
const $photoNote = document.getElementById("acc-photo-note");

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
const $vehicleFirstReg = document.getElementById("acc-vehicle-firstreg");
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

const MAX_PHOTO_MB = 5;

const API_ROOT = (() => {
  try {
    const base = new URL(API_BASE);
    base.pathname = base.pathname.replace(/\/api\/?$/, "");
    return `${base.origin}${base.pathname.replace(/\/$/, "")}`;
  } catch (error) {
    console.error("Unable to derive API root from API_BASE", error);
    return "";
  }
})();

const state = {
  user: null,
  preferences: {
    allowSmoker: false,
    allowAnimals: false,
    musicStyle: null,
  },
  vehicles: [],
};

/* ---------- Photo de profil ---------- */

function setPhotoFeedback(message = "", isError = false) {
  if (!$photoFeedback) return;
  $photoFeedback.textContent = message;
  $photoFeedback.classList.toggle("text-danger", Boolean(message) && isError);
  $photoFeedback.classList.toggle("text-muted", !message || !isError);
}

function resolvePhotoUrl(photo) {
  if (!photo) return null;
  if (/^data:/i.test(photo) || /^https?:\/\//i.test(photo)) {
    return photo;
  }
  const trimmed = photo.startsWith("/") ? photo : `/${photo}`;
  return API_ROOT ? `${API_ROOT}${trimmed}` : trimmed;
}

function updatePhotoUI(photoUrl) {
  const resolved = resolvePhotoUrl(photoUrl);
  const hasPhoto = Boolean(resolved);

  if ($photoImg) {
    if (hasPhoto) {
      $photoImg.src = resolved;
      $photoImg.style.display = "";
    } else {
      $photoImg.src = "";
      $photoImg.style.display = "none";
    }
  }

  if ($photoPlaceholder) {
    const hidden = hasPhoto;
    $photoPlaceholder.style.display = hidden ? "none" : "";
    if (hidden) {
      $photoPlaceholder.classList.add("d-none");
      $photoPlaceholder.setAttribute("aria-hidden", "true");
    } else {
      $photoPlaceholder.classList.remove("d-none");
      $photoPlaceholder.removeAttribute("aria-hidden");
    }
  }

  if ($photoUpload) {
    $photoUpload.textContent = hasPhoto ? "Modifier la photo" : "Ajouter une photo";
  }

  if ($photoRemove) {
    $photoRemove.style.display = hasPhoto ? "" : "none";
    $photoRemove.disabled = !hasPhoto;
  }

  if ($photoNote) {
    $photoNote.textContent = hasPhoto
      ? `Photo enregistrée. Formats recommandés : JPG ou PNG - ${MAX_PHOTO_MB} Mo max.`
      : `Formats acceptés : JPG ou PNG - ${MAX_PHOTO_MB} Mo maximum.`;
  }
}

updatePhotoUI(null);

function previewLocalPhoto(file) {
  if (!file || !$photoImg) return;
  const reader = new FileReader();
  reader.onload = (event) => {
    const dataUrl = event.target?.result;
    if (typeof dataUrl === "string") {
      $photoImg.src = dataUrl;
      $photoImg.style.display = "";
      if ($photoPlaceholder) {
        $photoPlaceholder.style.display = "none";
        $photoPlaceholder.classList.add("d-none");
        $photoPlaceholder.setAttribute("aria-hidden", "true");
      }
      if ($photoUpload) {
        $photoUpload.textContent = "Modifier la photo";
      }
      if ($photoRemove) {
        $photoRemove.style.display = "";
        $photoRemove.disabled = false;
      }
    }
  };
  reader.readAsDataURL(file);
}

$photoUpload?.addEventListener("click", () => {
  $photoInput?.click();
});

$photoInput?.addEventListener("change", async () => {
  if (!$photoInput?.files?.length) return;
  const file = $photoInput.files[0];

  setPhotoFeedback("");

  if (file.type && !file.type.startsWith("image/")) {
    setPhotoFeedback("Le fichier doit etre une image (JPG ou PNG).", true);
    $photoInput.value = "";
    return;
  }

  if (file.size && file.size > MAX_PHOTO_MB * 1024 * 1024) {
    setPhotoFeedback(`Image trop volumineuse (${MAX_PHOTO_MB} Mo max).`, true);
    $photoInput.value = "";
    return;
  }

  const previousPhoto = state.user?.photo ?? null;
  previewLocalPhoto(file);
  setPhotoFeedback("Envoi en cours...");

  try {
    const response = await uploadProfilePhoto(file);
    const photo = response?.photo ?? null;
    if (state.user) {
      state.user.photo = photo;
    }
    updatePhotoUI(photo);
    setPhotoFeedback("Photo mise a jour.");
  } catch (error) {
    console.error(error);
    updatePhotoUI(previousPhoto);
    const message =
      typeof error?.message === "string" && error.message.trim()
        ? error.message
        : "Echec de l'envoi de la photo (formats JPG ou PNG, 5 Mo maximum).";
    setPhotoFeedback(message, true);
  } finally {
    if ($photoInput) {
      $photoInput.value = "";
    }
  }
});

$photoRemove?.addEventListener("click", async () => {
  if (!state.user?.photo) return;
  if (!window.confirm("Supprimer la photo de profil ?")) return;

  setPhotoFeedback("Suppression en cours...");

  try {
    await deleteProfilePhoto();
    if (state.user) {
      state.user.photo = null;
    }
    updatePhotoUI(null);
    setPhotoFeedback("Photo supprimée.");
  } catch (error) {
    console.error(error);
    const message =
      typeof error?.message === "string" && error.message.trim()
        ? error.message
        : "Échec de la suppression de la photo.";
    setPhotoFeedback(message, true);
  }
});

/* ---------- Helpers dates / affichage ---------- */

function parseDate(value) {
  if (!value) return null;
  const iso = value.replace(" ", "T");
  const date = new Date(iso);
  return Number.isNaN(date.getTime()) ? null : date;
}

function formatDateTime(value) {
  const date = parseDate(value);
  if (!date) {
    return value ?? "";
  }
  try {
    return date.toLocaleString("fr-FR", {
      dateStyle: "medium",
      timeStyle: "short",
    });
  } catch (error) {
    console.error("Unable to format date", error);
    return date.toISOString();
  }
}

function formatDate(value) {
  const date = parseDate(value);
  if (!date) {
    return value ?? "";
  }
  try {
    return date.toLocaleDateString("fr-FR");
  } catch (error) {
    console.error("Unable to format date", error);
    return date.toISOString().slice(0, 10);
  }
}

function isPast(value) {
  const date = parseDate(value);
  return date ? date.getTime() < Date.now() : false;
}

function sortByDateAsc(list, getter) {
  return [...list].sort((a, b) => {
    const timeA = parseDate(getter(a))?.getTime() ?? 0;
    const timeB = parseDate(getter(b))?.getTime() ?? 0;
    return timeA - timeB;
  });
}

function formatPrice(value) {
  const amount = Number(value);
  if (Number.isFinite(amount)) {
    return `${amount.toFixed(2)} €`;
  }
  if (value == null) {
    return "";
  }
  return `${value} €`;
}

function badge(text) {
  const normalized = (text || "").toLowerCase();
  const variant =
    normalized === "open" || normalized === "confirmed"
      ? "success"
      : normalized === "cancelled" || normalized === "canceled"
      ? "danger"
      : "secondary";
  return `<span class="badge text-bg-${variant}">${text || "-"}</span>`;
}

/* ---------- Véhicules ---------- */

function vehicleSeatsOf(vehicle) {
  const seats = vehicle?.seatsTotal ?? vehicle?.seats;
  return Number.isFinite(Number(seats)) ? Number(seats) : 0;
}

function vehicleCard(vehicle) {
  const label = `${vehicle?.brand ?? ""} ${vehicle?.model ?? ""}`.trim() || "Sans nom";
  const details = [
    vehicleSeatsOf(vehicle) ? `${vehicleSeatsOf(vehicle)} places` : null,
    vehicle?.energy || null,
    vehicle?.eco ? "Éco" : null,
    vehicle?.color || null,
    vehicle?.plate || null,
    vehicle?.firstRegistrationAt
      ? `Première immatriculation : ${formatDate(vehicle.firstRegistrationAt)}`
      : null,
  ]
    .filter(Boolean)
    .join(" · ");

  return `
    <div class="border rounded p-2 mb-2">
      <div class="d-flex justify-content-between align-items-start gap-2">
        <div>
          <div class="fw-semibold">${label}</div>
          <div class="small text-muted">${details || "Aucun détail"}</div>
        </div>
        <div class="d-flex gap-2">
          <button class="btn btn-sm btn-outline-primary js-vehicle-edit" data-id="${vehicle.id}">Modifier</button>
          <button class="btn btn-sm btn-outline-danger js-vehicle-delete" data-id="${vehicle.id}">Supprimer</button>
        </div>
      </div>
    </div>
  `;
}

function renderVehicles() {
  if (!$vehiclesList) return;

  if (!Array.isArray(state.vehicles) || state.vehicles.length === 0) {
    $vehiclesList.innerHTML = `<div class="text-muted">Aucun véhicule enregistré.</div>`;
    return;
  }

  $vehiclesList.innerHTML = state.vehicles.map(vehicleCard).join("");

  $vehiclesList.querySelectorAll(".js-vehicle-edit").forEach((button) => {
    button.addEventListener("click", () => {
      const id = Number(button.dataset.id);
      const vehicle = state.vehicles.find((item) => item.id === id);
      if (!vehicle) return;

      if ($vehicleId) $vehicleId.value = vehicle.id ?? "";
      if ($vehicleBrand) $vehicleBrand.value = vehicle.brand ?? "";
      if ($vehicleModel) $vehicleModel.value = vehicle.model ?? "";
      if ($vehicleSeats) $vehicleSeats.value = vehicleSeatsOf(vehicle) || 4;
      if ($vehicleEnergy) $vehicleEnergy.value = vehicle.energy ?? "";
      if ($vehicleColor) $vehicleColor.value = vehicle.color ?? "";
      if ($vehiclePlate) $vehiclePlate.value = vehicle.plate ?? "";
      if ($vehicleFirstReg) $vehicleFirstReg.value = vehicle.firstRegistrationAt ? vehicle.firstRegistrationAt.slice(0, 10) : "";
      if ($vehicleEco) $vehicleEco.checked = Boolean(vehicle.eco);
      if ($vehicleFeedback) $vehicleFeedback.textContent = "Modification d'un véhicule existant.";
    });
  });

  $vehiclesList.querySelectorAll(".js-vehicle-delete").forEach((button) => {
    button.addEventListener("click", async () => {
      const id = Number(button.dataset.id);
      if (!Number.isFinite(id)) return;
      if (!window.confirm("Supprimer ce véhicule ?")) return;

      button.disabled = true;
      try {
        await deleteVehicle(id);
        await reloadVehicles();
        if ($vehicleFeedback) $vehicleFeedback.textContent = "Véhicule supprimé.";
      } catch (error) {
        console.error(error);
        if ($vehicleFeedback) $vehicleFeedback.textContent = "Erreur lors de la suppression du véhicule.";
      } finally {
        button.disabled = false;
      }
    });
  });
}

function resetVehicleForm() {
  if ($vehicleId) $vehicleId.value = "";
  if ($vehicleBrand) $vehicleBrand.value = "";
  if ($vehicleModel) $vehicleModel.value = "";
  if ($vehicleSeats) $vehicleSeats.value = "4";
  if ($vehicleEnergy) $vehicleEnergy.value = "";
  if ($vehicleColor) $vehicleColor.value = "";
  if ($vehiclePlate) $vehiclePlate.value = "";
  if ($vehicleFirstReg) $vehicleFirstReg.value = "";
  if ($vehicleEco) $vehicleEco.checked = false;
  if ($vehicleFeedback) $vehicleFeedback.textContent = "";
}

/* ---------- Réservations & trajets ---------- */

function bookingCard(booking) {
  const canCancel =
    !isPast(booking.startAt) &&
    (!booking.status || booking.status === "confirmed");

  return `
    <div class="card mb-2 shadow-sm">
      <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
          <div class="fw-semibold">${booking.from} &rarr; ${booking.to}</div>
          <div class="small text-muted">
            ${formatDateTime(booking.startAt)} · ${booking.seats} siège(s) · statut : ${booking.status || "-"}
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
  const vehicle = ride.vehicle
    ? `${ride.vehicle.brand ?? ""} ${ride.vehicle.model ?? ""}`.trim()
    : "";

  return `
    <div class="card mb-2 shadow-sm">
      <div class="card-body d-flex flex-wrap justify-content-between gap-2">
        <div>
          <div class="fw-semibold">${ride.from} &rarr; ${ride.to}</div>
          <div class="small text-muted">
            ${formatDateTime(ride.startAt)} · ${ride.seatsLeft}/${ride.seatsTotal} places${vehicle ? ` · ${vehicle}` : ""}
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
  if (!target) return;
  target.innerHTML =
    html && html.trim() !== "" ? html : `<div class="text-muted">${emptyText}</div>`;
}

/* ---------- Chargements ---------- */

async function reloadVehicles() {
  try {
    const response = await fetchMyVehicles();
    state.vehicles =
      response?.auth === false
        ? []
        : Array.isArray(response?.vehicles)
        ? response.vehicles
        : [];
  } catch (error) {
    console.error(error);
    state.vehicles = [];
  }
  renderVehicles();
}

async function renderAccount() {
  if ($bookingsFeedback) $bookingsFeedback.textContent = "Chargement...";
  if ($ridesFeedback) $ridesFeedback.textContent = "Chargement...";
  if ($bookingsUpcoming) $bookingsUpcoming.innerHTML = "";
  if ($bookingsPast) $bookingsPast.innerHTML = "";
  if ($ridesUpcoming) $ridesUpcoming.innerHTML = "";
  if ($ridesPast) $ridesPast.innerHTML = "";

  try {
    const overviewPromise = fetchAccountOverview().catch((error) => {
      const message = String(error?.message || "");
      if (/401|403/.test(message)) {
        return { auth: false };
      }
      throw error;
    });

    const bookingsPromise = fetchMyBookings().catch((error) => {
      console.error(error);
      return null;
    });

    const ridesPromise = fetchMyRides().catch((error) => {
      console.error(error);
      return null;
    });

    const [overview, bookingsData, ridesData] = await Promise.all([
      overviewPromise,
      bookingsPromise,
      ridesPromise,
    ]);

    if (!overview || overview.auth === false) {
      state.user = null;
      updatePhotoUI(null);
      setPhotoFeedback("");
      if ($profile) $profile.classList.add("d-none");
      if ($welcome) {
        $welcome.innerHTML =
          '<div class="alert alert-warning mb-0">Veuillez vous connecter pour accéder à votre espace.</div>';
      }
      if ($bookingsFeedback) $bookingsFeedback.textContent = "";
      if ($ridesFeedback) $ridesFeedback.textContent = "";
      renderList($bookingsUpcoming, "", "Aucune réservation à venir.");
      renderList($bookingsPast, "", "Aucune réservation passée.");
      renderList($ridesUpcoming, "", "Aucun trajet à venir.");
      renderList($ridesPast, "", "Aucun trajet passé.");
      return;
    }

    state.user = { ...overview.user, photo: overview.user?.photo ?? null };
    state.preferences = overview.preferences ?? state.preferences;
    state.vehicles = overview.vehicles ?? [];

    updatePhotoUI(state.user.photo ?? null);
    setPhotoFeedback("");
    resetVehicleForm();
    renderVehicles();

    if ($accName) $accName.textContent = overview.user?.pseudo ?? "Utilisateur";
    if ($accEmail) $accEmail.textContent = overview.user?.email ?? "";
    if ($accCredits) $accCredits.textContent = overview.user?.credits ?? 0;

    if ($profile) $profile.classList.remove("d-none");
    if ($welcome) {
      $welcome.innerHTML = `Bonjour <strong>${overview.user?.pseudo ?? "utilisateur"}</strong> !`;
    }

    const driverEnabled =
      Array.isArray(overview.user?.roles) &&
      overview.user.roles.includes("ROLE_DRIVER");

    if ($driverToggle) {
      $driverToggle.checked = driverEnabled;
    }
    if ($driverFeedback) $driverFeedback.textContent = "";

    if ($prefSmoker) $prefSmoker.checked = Boolean(state.preferences.allowSmoker);
    if ($prefAnimals) $prefAnimals.checked = Boolean(state.preferences.allowAnimals);
    if ($prefMusic) $prefMusic.value = state.preferences.musicStyle ?? "";
    if ($prefsFeedback) $prefsFeedback.textContent = "";

    // Réservations (passager)
    if (bookingsData && Array.isArray(bookingsData.bookings)) {
      const upcoming = sortByDateAsc(
        bookingsData.bookings.filter((booking) => !isPast(booking.startAt)),
        (booking) => booking.startAt
      );
      const past = sortByDateAsc(
        bookingsData.bookings.filter((booking) => isPast(booking.startAt)),
        (booking) => booking.startAt
      );

      if ($bookingsCountUpcoming) {
        $bookingsCountUpcoming.textContent = String(upcoming.length);
      }
      if ($bookingsCountPast) {
        $bookingsCountPast.textContent = String(past.length);
      }

      renderList(
        $bookingsUpcoming,
        upcoming.map(bookingCard).join(""),
        "Aucune réservation à venir."
      );
      renderList(
        $bookingsPast,
        past.map(bookingCard).join(""),
        "Aucune réservation passée."
      );
      if ($bookingsFeedback) {
        $bookingsFeedback.textContent = bookingsData.bookings.length
          ? ""
          : "Aucune réservation.";
      }

      $bookingsUpcoming
        ?.querySelectorAll(".js-unbook")
        .forEach((button) => attachUnbookHandler(button));
      $bookingsPast
        ?.querySelectorAll(".js-unbook")
        .forEach((button) => attachUnbookHandler(button));
    } else if ($bookingsFeedback) {
      $bookingsFeedback.textContent = "Impossible de charger les réservations.";
    }

    // Trajets conducteur
    if (Array.isArray(ridesData)) {
      const upcomingRides = sortByDateAsc(
        ridesData.filter((ride) => !isPast(ride.startAt)),
        (ride) => ride.startAt
      );
      const pastRides = sortByDateAsc(
        ridesData.filter((ride) => isPast(ride.startAt)),
        (ride) => ride.startAt
      );

      if ($ridesCountUpcoming) {
        $ridesCountUpcoming.textContent = String(upcomingRides.length);
      }
      if ($ridesCountPast) {
        $ridesCountPast.textContent = String(pastRides.length);
      }

      renderList(
        $ridesUpcoming,
        upcomingRides
          .map((ride) => driverRideCard(ride, ride.status === "open"))
          .join(""),
        "Aucun trajet à venir."
      );
      renderList(
        $ridesPast,
        pastRides.map((ride) => driverRideCard(ride, false)).join(""),
        "Aucun trajet passé."
      );
      if ($ridesFeedback) {
        $ridesFeedback.textContent = ridesData.length
          ? ""
          : "Aucun trajet conducteur.";
      }

      $ridesUpcoming
        ?.querySelectorAll(".js-cancel-ride")
        .forEach((button) => attachCancelRideHandler(button));
    } else if ($ridesFeedback) {
      $ridesFeedback.textContent = "Impossible de charger les trajets conducteur.";
    }
  } catch (error) {
    console.error(error);
    if ($welcome) {
      $welcome.innerHTML =
        '<div class="alert alert-danger mb-0">Erreur lors du chargement du compte.</div>';
    }
  }
}

function attachUnbookHandler(button) {
  button.addEventListener("click", async () => {
    const id = Number(button.dataset.id);
    if (!Number.isFinite(id)) return;

    const previous = button.textContent;
    button.disabled = true;
    button.textContent = "...";

    try {
      await unbookRide(id);
      await renderAccount();
    } catch (error) {
      console.error(error);
      window.alert("Échec de l'annulation de la réservation.");
    } finally {
      button.disabled = false;
      button.textContent = previous;
    }
  });
}

function attachCancelRideHandler(button) {
  button.addEventListener("click", async () => {
    const id = Number(button.dataset.id);
    if (!Number.isFinite(id)) return;
    if (
      !window.confirm(
        "Annuler ce trajet ? Les passagers seront remboursés automatiquement."
      )
    ) {
      return;
    }

    const previous = button.textContent;
    button.disabled = true;
    button.textContent = "...";

    try {
      await cancelRideAsDriver(id);
      await renderAccount();
    } catch (error) {
      console.error(error);
      window.alert("Échec de l'annulation du trajet.");
    } finally {
      button.disabled = false;
      button.textContent = previous;
    }
  });
}

/* ---------- Actions UI ---------- */

$driverToggle?.addEventListener("change", async () => {
  if (!state.user) return;

  const targetValue = $driverToggle.checked;
  $driverToggle.disabled = true;
  if ($driverFeedback) $driverFeedback.textContent = "Enregistrement...";

  try {
    const response = await updateDriverRole({ driver: targetValue });
    if (Array.isArray(response?.roles)) {
      state.user.roles = response.roles;
    }
    if ($driverFeedback) $driverFeedback.textContent = "Rôle mis à jour.";
  } catch (error) {
    console.error(error);
    $driverToggle.checked = !targetValue;
    if ($driverFeedback) {
      $driverFeedback.textContent = "Impossible de mettre à jour le rôle.";
    }
  } finally {
    $driverToggle.disabled = false;
  }
});

$prefsForm?.addEventListener("submit", async (event) => {
  event.preventDefault();
  if (!state.user) return;
  if ($prefsFeedback) $prefsFeedback.textContent = "Enregistrement...";

  const payload = {
    allowSmoker: Boolean($prefSmoker?.checked),
    allowAnimals: Boolean($prefAnimals?.checked),
    musicStyle: $prefMusic?.value.trim() || null,
  };

  try {
    const response = await saveDriverPreferences(payload);
    state.preferences = response?.preferences ?? payload;
    if ($prefsFeedback) $prefsFeedback.textContent = "Préférences enregistrées.";
  } catch (error) {
    console.error(error);
    if ($prefsFeedback) $prefsFeedback.textContent = "Erreur lors de l'enregistrement.";
  }
});

$vehicleForm?.addEventListener("submit", async (event) => {
  event.preventDefault();
  if (!state.user) return;

  const plateValue = $vehiclePlate
    ? $vehiclePlate.value.trim().toUpperCase()
    : "";
  const firstRegistrationValue = $vehicleFirstReg ? $vehicleFirstReg.value.trim() : "";

  const payload = {
    id: $vehicleId?.value ? Number($vehicleId.value) : undefined,
    brand: $vehicleBrand?.value.trim() ?? "",
    model: $vehicleModel?.value.trim() ?? "",
    seats: Number($vehicleSeats?.value) || 4,
    energy: $vehicleEnergy?.value.trim() || "electric",
    color: $vehicleColor?.value.trim() || null,
    plate: plateValue || null,
    firstRegistrationAt: firstRegistrationValue || null,
    eco: Boolean($vehicleEco?.checked),
  };

  if ($vehicleFeedback) $vehicleFeedback.textContent = "Enregistrement...";

  try {
    await saveVehicle(payload);
    resetVehicleForm();
    await reloadVehicles();
    if ($vehicleFeedback) $vehicleFeedback.textContent = "Véhicule enregistré.";
  } catch (error) {
    console.error(error);
    if ($vehicleFeedback) $vehicleFeedback.textContent = "Erreur lors de l'enregistrement du véhicule.";
  }
});

$vehicleReset?.addEventListener("click", () => {
  resetVehicleForm();
});

$btnLogout?.addEventListener("click", async () => {
  try {
    await logout();
  } catch (error) {
    console.error(error);
  } finally {
    clearSession();
    if (typeof window.navigate === "function") {
      window.navigate("/signin");
    } else {
      window.location.href = "/signin";
    }
  }
});

$btnRefresh?.addEventListener("click", () => {
  renderAccount();
});

/* ---------- Init ---------- */

renderAccount();
