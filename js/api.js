const API_BASE = "http://localhost:8001/api";

async function json(res) {
  if (!res.ok) {
    let detail = "";
    try { detail = JSON.stringify(await res.json()); } catch {}
    throw new Error(`HTTP ${res.status} ${detail}`);
  }
  return res.json();
}

/* ---------- Rides (recherche) ---------- */
export async function fetchRides({ from = "", to = "", date = "", eco, priceMax, durationMax, ratingMin } = {}) {
  const p = new URLSearchParams();
  if (from) p.set("from", from);
  if (to) p.set("to", to);
  if (date) p.set("date", date);
  if (eco !== undefined) p.set("eco", eco ? "1" : "0");
  if (priceMax) p.set("priceMax", String(priceMax));
  if (durationMax) p.set("durationMax", String(durationMax));
  if (ratingMin) p.set("ratingMin", String(ratingMin));

  const url = `${API_BASE}/rides${p.toString() ? "?" + p.toString() : ""}`;
  const res = await fetch(url, {
    headers: { Accept: "application/json" },
    credentials: "include",
  });
  return json(res);
}

/* ---------- Réservation / Annulation ---------- */
// Réserver un trajet (seats par défaut à 1 côté backend)
export async function bookRide(id, { seats = 1 } = {}) {
  const res = await fetch(`${API_BASE}/rides/${id}/book`, {
    method: "POST",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    credentials: "include",
    body: JSON.stringify({ seats }), // NE PAS envoyer userId : backend = session
  });
  return json(res);
}

// Annuler sa réservation (DELETE aussi accepté par le backend)
export async function unbookRide(rideId) {
  const res = await fetch(`${API_BASE}/rides/${rideId}/book`, {
    method: "DELETE",
    credentials: "include",
  });
  return json(res);
}

/* ---------- Mes réservations ---------- */
export async function fetchMyBookings() {
  const res = await fetch(`${API_BASE}/me/bookings`, {
    credentials: "include",
  });
  return json(res); // { auth:boolean, bookings:[...] }
}

/* ---------- Auth ---------- */
export async function login({ email, password }) {
  const res = await fetch(`${API_BASE}/auth/login`, {
    method: "POST",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    credentials: "include",
    body: JSON.stringify({ email, password }),
  });
  return json(res);
}

export async function me() {
  const res = await fetch(`${API_BASE}/auth/me`, {
    headers: { Accept: "application/json" },
    credentials: "include",
  });
  return json(res);
}

export async function logout() {
  await fetch(`${API_BASE}/auth/logout`, {
    method: "POST",
    credentials: "include",
  });
}
/* ---------- Création de trajet (conducteur) ---------- */
export async function createRide(payload) {
  const res = await fetch(`${API_BASE}/rides`, {
    method: "POST",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    credentials: "include",
    body: JSON.stringify(payload),
  });
  return json(res);
}
