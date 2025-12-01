import { me, fetchModerationReviews, moderateReview, fetchRideIssues } from "../api.js";

const $error = document.getElementById("employee-error");
const $success = document.getElementById("employee-success");
const $reviews = document.getElementById("employee-reviews");
const $reviewsFeedback = document.getElementById("employee-reviews-feedback");
const $statusSelect = document.getElementById("employee-status");
const $refresh = document.getElementById("employee-refresh");
const $issues = document.getElementById("employee-issues");
const $issuesFeedback = document.getElementById("employee-issues-feedback");

const state = {
  status: "pending",
  reviews: [],
  issues: [],
};

function showError(message) {
  if ($error) {
    $error.textContent = message;
    $error.classList.remove("d-none");
  }
}

function clearAlerts() {
  $error?.classList.add("d-none");
  if ($success) {
    $success.classList.add("d-none");
    $success.textContent = "";
  }
}

function reviewCard(review) {
  const author = review.author?.pseudo ?? "Utilisateur";
  const target = review.target?.pseudo ?? "Conducteur";
  const rideInfo = review.ride ? `${review.ride.from} → ${review.ride.to} (${review.ride.startAt ?? ""})` : "";
  const moderationInfo = review.moderation?.validatedAt
    ? `<div class="small text-muted">Modéré le ${review.moderation.validatedAt} par ${review.moderation.validatedBy ?? "—"}</div>`
    : "";
  const noteInfo = review.moderation?.note ? `<div class="small text-muted fst-italic">Note: ${review.moderation.note}</div>` : "";

  const disabled = review.status !== "pending" ? "disabled" : "";

  return `
    <div class="card shadow-sm">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start gap-3">
          <div>
            <div class="fw-semibold">Note: ${review.rating}/5</div>
            <div class="small text-muted">Auteur: ${author} &bull; Cible: ${target}</div>
            <div class="small text-muted">Trajet: ${rideInfo}</div>
            <div class="mt-2">${review.comment ? review.comment : "<em>Aucun commentaire</em>"}</div>
            ${moderationInfo}
            ${noteInfo}
          </div>
          <div class="text-end">
            <span class="badge text-bg-${review.status === "approved" ? "success" : review.status === "rejected" ? "danger" : "secondary"} text-uppercase">${review.status}</span>
          </div>
        </div>
        <div class="mt-3 d-flex gap-2">
          <button class="btn btn-sm btn-success js-approve" data-id="${review.id}" ${disabled}>Approuver</button>
          <button class="btn btn-sm btn-outline-danger js-reject" data-id="${review.id}" ${disabled}>Rejeter</button>
          <input type="text" class="form-control form-control-sm js-note" placeholder="Note de modération" ${disabled ? "disabled" : ""} />
        </div>
      </div>
    </div>
  `;
}

function bindModerationActions() {
  document.querySelectorAll(".js-approve").forEach((button) => {
    button.addEventListener("click", () => handleDecision(button.dataset.id, "approve"));
  });
  document.querySelectorAll(".js-reject").forEach((button) => {
    button.addEventListener("click", () => handleDecision(button.dataset.id, "reject"));
  });
}

async function handleDecision(id, action) {
  const card = document.querySelector(`button[data-id="${id}"]`)?.closest(".card");
  const noteInput = card?.querySelector(".js-note");
  const note = noteInput?.value ?? "";

  try {
    await moderateReview(Number(id), { action, note });
    if ($success) {
      $success.textContent = action === "approve" ? "Avis approuvé." : "Avis rejeté.";
      $success.classList.remove("d-none");
    }
    await loadReviews();
  } catch (error) {
    console.error(error);
    showError("Impossible d'enregistrer la décision.");
  }
}

async function ensureEmployee() {
  try {
    const info = await me();
    const roles = Array.isArray(info?.user?.roles) ? info.user.roles : [];
    const allowed = roles.includes("ROLE_EMPLOYEE") || roles.includes("ROLE_ADMIN");
    if (!allowed) {
      showError("Accès refusé. Ce module est réservé aux employés ou administrateurs.");
      $reviewsFeedback.textContent = "";
      return false;
    }
    return true;
  } catch (error) {
    console.error(error);
    showError("Impossible de vérifier la session.");
    return false;
  }
}

async function loadReviews() {
  clearAlerts();
  $reviewsFeedback.textContent = "Chargement des avis...";
  try {
    const payload = await fetchModerationReviews(state.status);
    state.reviews = payload?.reviews ?? [];
    if (!state.reviews.length) {
      $reviewsFeedback.textContent = "Aucun avis pour ce filtre.";
      $reviews.innerHTML = "";
      return;
    }
    $reviewsFeedback.textContent = "";
    $reviews.innerHTML = state.reviews.map(reviewCard).join("");
    bindModerationActions();
  } catch (error) {
    console.error(error);
    showError("Erreur lors du chargement des avis.");
    $reviewsFeedback.textContent = "";
  }
}

function issueCard(issue) {
  const driver = issue.driver || {};
  const passenger = issue.passenger || {};
  const ride = issue.ride || {};
  const feedback = issue.feedback || {};
  const vehicle = ride.vehicle
    ? `${ride.vehicle.brand ?? ""} ${ride.vehicle.model ?? ""}`.trim()
    : "";

  return `
    <div class="card shadow-sm">
      <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between gap-3">
          <div>
            <div class="fw-semibold">Trajet #${ride.id ?? "-"} : ${ride.from ?? "?"} &rarr; ${ride.to ?? "?"}</div>
            <div class="small text-muted">
              Départ : ${ride.startAt ?? "-"}${ride.endAt ? ` &middot; Arrivée : ${ride.endAt}` : ""}
            </div>
            ${vehicle ? `<div class="small text-muted">Véhicule : ${vehicle}${ride.vehicle?.plate ? ` (${ride.vehicle.plate})` : ""}</div>` : ""}
            <div class="mt-2">
              <strong>Conducteur :</strong> ${driver.pseudo ?? "-"} <span class="text-muted">${driver.email ?? ""}</span>
            </div>
            <div class="mt-1">
              <strong>Passager :</strong> ${passenger.pseudo ?? "-"} <span class="text-muted">${passenger.email ?? ""}</span>
            </div>
          </div>
          <div class="text-end">
            <span class="badge text-bg-danger text-uppercase">Problème signalé</span>
            <div class="small text-muted mt-2">${feedback.date ?? ""}</div>
          </div>
        </div>
        <div class="mt-3">
          <strong>Description :</strong>
          <div class="mt-1">${feedback.note ? feedback.note : "<em>Aucun détail fourni.</em>"}</div>
        </div>
      </div>
    </div>
  `;
}

async function loadIssues() {
  if (!$issues || !$issuesFeedback) return;
  $issuesFeedback.textContent = "Chargement des trajets signalés...";
  try {
    const payload = await fetchRideIssues();
    state.issues = payload?.issues ?? [];
    if (!state.issues.length) {
      $issues.innerHTML = "";
      $issuesFeedback.textContent = "Aucun trajet signalé.";
      return;
    }
    $issuesFeedback.textContent = "";
    $issues.innerHTML = state.issues.map(issueCard).join("");
  } catch (error) {
    console.error(error);
    $issuesFeedback.textContent = "";
    showError("Erreur lors du chargement des trajets signalés.");
  }
}

async function init() {
  const ok = await ensureEmployee();
  if (!ok) return;
  await Promise.all([loadReviews(), loadIssues()]);
}

$statusSelect?.addEventListener("change", () => {
  state.status = $statusSelect.value;
  loadReviews();
});

$refresh?.addEventListener("click", () => {
  loadReviews();
  loadIssues();
});

init();

