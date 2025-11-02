import {
  me,
  fetchAdminMetrics,
  fetchModerationReviews,
  moderateReview,
  suspendUser,
} from "../api.js";

const $error = document.getElementById("admin-error");
const $success = document.getElementById("admin-success");

const $metricsContainer = document.getElementById("admin-metrics");
const $metricsFeedback = document.getElementById("admin-metrics-feedback");
const $reviewsContainer = document.getElementById("admin-reviews");
const $reviewsFeedback = document.getElementById("admin-reviews-feedback");
const $usersContainer = document.getElementById("admin-users");
const $usersFeedback = document.getElementById("admin-users-feedback");

const $statusSelect = document.getElementById("admin-review-status");
const $refreshButton = document.getElementById("admin-refresh");

const state = {
  metrics: [],
  reviews: [],
  users: [],
  reviewStatus: "pending",
};

function resetAlerts() {
  $error?.classList.add("d-none");
  if ($success) {
    $success.textContent = "";
    $success.classList.add("d-none");
  }
}

function showError(message) {
  if ($error) {
    $error.textContent = message;
    $error.classList.remove("d-none");
  }
}

function showSuccess(message) {
  if ($success) {
    $success.textContent = message;
    $success.classList.remove("d-none");
  }
}

function ensureAdmin() {
  return me()
    .then((info) => {
      const roles = Array.isArray(info?.user?.roles) ? info.user.roles : [];
      if (!roles.includes("ROLE_ADMIN")) {
        showError("Accès administrateur requis.");
        return false;
      }
      return true;
    })
    .catch((error) => {
      console.error(error);
      showError("Impossible de vérifier la session.");
      return false;
    });
}

function renderMetrics() {
  if (!state.metrics.length) {
    $metricsContainer.innerHTML = "";
    $metricsFeedback.textContent = "Aucune donnée disponible.";
    return;
  }

  $metricsFeedback.textContent = "";

  const maxRides = Math.max(...state.metrics.map((m) => m.rides), 1);
  const maxBookings = Math.max(...state.metrics.map((m) => m.bookings), 1);
  const maxSignups = Math.max(...state.metrics.map((m) => m.signups), 1);

  const toBar = (value, max) => {
    const percent = Math.round((value / max) * 100);
    return `<div class="chart-bar bg-primary" style="height:${percent || 5}px" title="${value}"></div>`;
  };

  const barsRides = state.metrics.map((m) => toBar(m.rides, maxRides)).join("");
  const barsBookings = state.metrics.map((m) => toBar(m.bookings, maxBookings)).join("");
  const barsSignups = state.metrics.map((m) => toBar(m.signups, maxSignups)).join("");
  const labels = state.metrics.map((m) => `<div class="chart-label">${m.label}</div>`).join("");

  $metricsContainer.innerHTML = `
    <div class="col-12 col-lg-4">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <h4 class="h6">Trajets publiés</h4>
          <div class="chart-stack">${barsRides}</div>
          <div class="d-flex justify-content-between">${labels}</div>
        </div>
      </div>
    </div>
    <div class="col-12 col-lg-4">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <h4 class="h6">Réservations</h4>
          <div class="chart-stack text-bg-success">${barsBookings}</div>
          <div class="d-flex justify-content-between">${labels}</div>
        </div>
      </div>
    </div>
    <div class="col-12 col-lg-4">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <h4 class="h6">Inscriptions</h4>
          <div class="chart-stack text-bg-info">${barsSignups}</div>
          <div class="d-flex justify-content-between">${labels}</div>
        </div>
      </div>
    </div>
  `;
}

function renderReviews() {
  if (!state.reviews.length) {
    $reviewsContainer.innerHTML = "";
    $reviewsFeedback.textContent = "Aucun avis pour ce filtre.";
    return;
  }
  $reviewsFeedback.textContent = "";
  $reviewsContainer.innerHTML = state.reviews.map((review) => {
    const noteInput = review.status === "pending"
      ? `<input type="text" class="form-control form-control-sm js-note" placeholder="Note de modération" />`
      : "";
    const moderation = review.moderation?.validatedAt
      ? `<div class="small text-muted">Modéré le ${review.moderation.validatedAt} (${review.moderation.validatedBy ?? "-"})</div>`
      : "";
    const note = review.moderation?.note
      ? `<div class="small text-muted fst-italic">Note : ${review.moderation.note}</div>`
      : "";
    return `
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start gap-3">
            <div>
              <div class="fw-semibold">Note ${review.rating}/5</div>
              <div class="small text-muted">Auteur : ${review.author?.pseudo ?? "Utilisateur"} &bull; Cible : ${review.target?.pseudo ?? "-"}</div>
              <div class="small text-muted">Trajet : ${review.ride?.from ?? ""} → ${review.ride?.to ?? ""} (${review.ride?.startAt ?? ""})</div>
              <div class="mt-2">${review.comment || "<em>Aucun commentaire</em>"}</div>
              ${moderation}
              ${note}
            </div>
            <span class="badge text-bg-${review.status === "approved" ? "success" : review.status === "rejected" ? "danger" : "secondary"} text-uppercase">${review.status}</span>
          </div>
          <div class="mt-3 d-flex gap-2">
            <button class="btn btn-sm btn-success js-approve" data-id="${review.id}" ${review.status !== "pending" ? "disabled" : ""}>Approuver</button>
            <button class="btn btn-sm btn-outline-danger js-reject" data-id="${review.id}" ${review.status !== "pending" ? "disabled" : ""}>Rejeter</button>
            ${noteInput}
          </div>
        </div>
      </div>
    `;
  }).join("");

  document.querySelectorAll(".js-approve").forEach((button) => {
    button.addEventListener("click", () => handleReviewDecision(button.dataset.id, "approve"));
  });
  document.querySelectorAll(".js-reject").forEach((button) => {
    button.addEventListener("click", () => handleReviewDecision(button.dataset.id, "reject"));
  });
}

function renderUsers() {
  if (!state.users.length) {
    $usersContainer.innerHTML = "";
    $usersFeedback.textContent = "Aucun compte trouvé.";
    return;
  }

  $usersFeedback.textContent = "";

  $usersContainer.innerHTML = state.users.map((user) => {
    const suspendedBadge = user.suspended
      ? `<span class="badge text-bg-danger">Suspendu</span>`
      : `<span class="badge text-bg-success">Actif</span>`;
    return `
      <tr>
        <td>${user.id}</td>
        <td>${user.email}</td>
        <td>${user.pseudo}</td>
        <td>${user.roles.join(", ")}</td>
        <td>${user.credits}</td>
        <td>${suspendedBadge}${user.suspensionReason ? `<div class="small text-muted">${user.suspensionReason}</div>` : ""}</td>
        <td>
          <button class="btn btn-sm ${user.suspended ? "btn-outline-success" : "btn-outline-danger"} js-toggle-suspend" data-id="${user.id}">
            ${user.suspended ? "Réactiver" : "Suspendre"}
          </button>
          <input type="text" class="form-control form-control-sm js-reason mt-1" placeholder="Motif" ${user.suspended ? "disabled" : ""}>
        </td>
      </tr>
    `;
  }).join("");

  document.querySelectorAll(".js-toggle-suspend").forEach((button) => {
    button.addEventListener("click", () => handleSuspend(button));
  });
}

async function handleReviewDecision(id, action) {
  const card = document.querySelector(`button[data-id="${id}"]`)?.closest(".card");
  const noteInput = card?.querySelector(".js-note");
  const note = noteInput?.value ?? "";
  try {
    await moderateReview(Number(id), { action, note });
    showSuccess(action === "approve" ? "Avis approuvé." : "Avis rejeté.");
    await loadReviews();
  } catch (error) {
    console.error(error);
    showError("Impossible d'enregistrer la décision.");
  }
}

async function handleSuspend(button) {
  const id = Number(button.dataset.id);
  if (!Number.isFinite(id)) return;

  const row = button.closest("tr");
  const reasonInput = row?.querySelector(".js-reason");
  const suspend = !button.classList.contains("btn-outline-success");
  const confirmMessage = suspend
    ? "Suspendre ce compte ?"
    : "Réactiver ce compte ?";
  if (!window.confirm(confirmMessage)) return;

  button.disabled = true;
  try {
    await suspendUser(id, { suspend, reason: reasonInput?.value });
    showSuccess(suspend ? "Compte suspendu." : "Compte réactivé.");
    await loadMetrics();
  } catch (error) {
    console.error(error);
    showError("Impossible de mettre à jour le compte.");
  } finally {
    button.disabled = false;
  }
}

async function loadMetrics() {
  $metricsFeedback.textContent = "Chargement des métriques...";
  try {
    const payload = await fetchAdminMetrics();
    state.metrics = payload?.series ?? [];
    state.users = payload?.users ?? [];
    renderMetrics();
    renderUsers();
  } catch (error) {
    console.error(error);
    showError("Erreur lors du chargement des métriques.");
    $metricsFeedback.textContent = "";
  }
}

async function loadReviews() {
  $reviewsFeedback.textContent = "Chargement des avis...";
  try {
    const payload = await fetchModerationReviews(state.reviewStatus);
    state.reviews = payload?.reviews ?? [];
    renderReviews();
  } catch (error) {
    console.error(error);
    showError("Erreur lors du chargement des avis.");
    $reviewsFeedback.textContent = "";
  }
}

async function init() {
  resetAlerts();
  const ok = await ensureAdmin();
  if (!ok) return;
  await Promise.all([loadMetrics(), loadReviews()]);
}

$statusSelect?.addEventListener("change", () => {
  state.reviewStatus = $statusSelect.value;
  loadReviews();
});

$refreshButton?.addEventListener("click", () => {
  init();
});

init();
