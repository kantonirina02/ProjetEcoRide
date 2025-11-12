import {
  me,
  fetchAdminMetrics,
  fetchModerationReviews,
  moderateReview,
  suspendUser,
  createEmployeeAccount,
  fetchSearchLogs,
} from "../api.js";

const $error = document.getElementById("admin-error");
const $success = document.getElementById("admin-success");

const $metricsContainer = document.getElementById("admin-metrics");
const $metricsFeedback = document.getElementById("admin-metrics-feedback");
const $reviewsContainer = document.getElementById("admin-reviews");
const $reviewsFeedback = document.getElementById("admin-reviews-feedback");
const $usersContainer = document.getElementById("admin-users");
const $usersFeedback = document.getElementById("admin-users-feedback");
const $logsContainer = document.getElementById("admin-searchlogs");
const $logsFeedback = document.getElementById("admin-searchlogs-feedback");

const $statusSelect = document.getElementById("admin-review-status");
const $refreshButton = document.getElementById("admin-refresh");

const $employeeForm = document.getElementById("admin-employee-form");
const $employeeEmail = document.getElementById("admin-employee-email");
const $employeePseudo = document.getElementById("admin-employee-pseudo");
const $employeePassword = document.getElementById("admin-employee-password");

const state = {
  metrics: [],
  reviews: [],
  users: [],
  reviewStatus: "pending",
  revenueDays: [],
  platformCredits: 0,
  periodRevenue: 0,
  searchLogs: [],
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

  let html = `
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
  if (state.revenueDays.length) {
    const maxRevenue = Math.max(...state.revenueDays.map((d) => d.credits), 1);
    const revenueBars = state.revenueDays
      .map((day) => {
        const percent = Math.round((day.credits / maxRevenue) * 100);
        return `<div class="chart-bar bg-warning" style="height:${percent || 5}px" title="${day.credits}"></div>`;
      })
      .join("");
    const revenueLabels = state.revenueDays
      .map((day) => `<div class="chart-label">${day.label}</div>`)
      .join("");
    const totalCredits = Number(state.platformCredits || 0).toLocaleString("fr-FR");
    const periodCredits = Number(state.periodRevenue || 0).toLocaleString("fr-FR");

    html += `
      <div class="col-12 col-lg-6">
        <div class="card shadow-sm h-100">
          <div class="card-body">
            <h4 class="h6">Crédits plateforme (14 derniers jours)</h4>
            <div class="chart-stack text-bg-warning">${revenueBars}</div>
            <div class="d-flex justify-content-between">${revenueLabels}</div>
            <div class="small text-muted mt-2">Total période : ${periodCredits} crédits</div>
          </div>
        </div>
      </div>
      <div class="col-12 col-lg-6">
        <div class="card shadow-sm h-100">
          <div class="card-body d-flex flex-column justify-content-center align-items-start">
            <h4 class="h6">Crédits cumulés</h4>
            <p class="display-6 mb-1">${totalCredits}</p>
            <div class="small text-muted">Somme encaissée par la plateforme depuis l'ouverture.</div>
          </div>
        </div>
      </div>
    `;
  }

  $metricsContainer.innerHTML = html;
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

function renderSearchLogs() {
  if (!$logsContainer || !$logsFeedback) return;
  if (!state.searchLogs.length) {
    $logsContainer.innerHTML = "";
    $logsFeedback.textContent = "Aucune recherche récente.";
    return;
  }

  $logsFeedback.textContent = "";
  $logsContainer.innerHTML = state.searchLogs
    .map(
      (log) => `
        <tr>
          <td>${log.createdAt ?? "-"}</td>
          <td>${log.from ?? "-"}</td>
          <td>${log.to ?? "-"}</td>
          <td>${log.date ?? "-"}</td>
          <td>${log.results ?? 0}</td>
          <td>${log.userId ?? "Visiteur"}</td>
          <td>${log.clientIp ?? "-"}</td>
        </tr>
      `
    )
    .join("");
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
    state.revenueDays = payload?.revenueDays ?? [];
    state.periodRevenue = payload?.periodRevenue ?? 0;
    state.platformCredits = payload?.platformTotalCredits ?? 0;
    renderMetrics();
    renderUsers();
  } catch (error) {
    console.error(error);
    showError("Erreur lors du chargement des métriques.");
    $metricsFeedback.textContent = "";
  }
}

async function loadSearchLogs() {
  if (!$logsFeedback) return;
  $logsFeedback.textContent = "Chargement des recherches...";
  try {
    const payload = await fetchSearchLogs();
    state.searchLogs = payload?.logs ?? [];
    renderSearchLogs();
  } catch (error) {
    console.error(error);
    $logsFeedback.textContent = "";
    showError("Erreur lors du chargement des recherches.");
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
  await Promise.all([loadMetrics(), loadReviews(), loadSearchLogs()]);
}

$statusSelect?.addEventListener("change", () => {
  state.reviewStatus = $statusSelect.value;
  loadReviews();
});

$refreshButton?.addEventListener("click", () => {
  init();
});

async function handleEmployeeCreate(event) {
  event.preventDefault();
  if (!$employeeEmail || !$employeePseudo || !$employeePassword) return;
  const email = $employeeEmail.value.trim();
  const pseudo = $employeePseudo.value.trim();
  const password = $employeePassword.value.trim();
  if (!email || !pseudo || !password) {
    showError("Tous les champs sont requis pour créer un employé.");
    return;
  }
  resetAlerts();
  const submitBtn = $employeeForm?.querySelector("button[type='submit']");
  if (submitBtn) submitBtn.disabled = true;
  try {
    await createEmployeeAccount({ email, pseudo, password });
    showSuccess("Compte employé créé.");
    $employeeForm?.reset();
    await loadMetrics();
  } catch (error) {
    console.error(error);
    showError("Impossible de créer le compte employé.");
  } finally {
    if (submitBtn) submitBtn.disabled = false;
  }
}

$employeeForm?.addEventListener("submit", handleEmployeeCreate);

init();
