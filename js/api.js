const API_BASE = "http://127.0.0.1:8001/api";

async function json(res) {
  if (!res.ok) {
    let detail = "";
    try { detail = JSON.stringify(await res.json()); } catch {}
    throw new Error(`HTTP ${res.status} ${detail}`);
  }
  return res.json();
}

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
  const res = await fetch(url, { headers: { Accept: "application/json" }, credentials: "include" });
  return json(res);
}

export async function bookRide(id, { userId, seats }) {
  const url = `${API_BASE}/rides/${id}/book`;
  const res = await fetch(url, {
    method: "POST",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    credentials: "include",
    body: JSON.stringify({ userId, seats }),
  });
  return json(res);
}

// --- Auth ---
export async function login({ email, password }) {
  const res = await fetch(`${API_BASE}/auth/login`, {
    method: "POST",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    credentials: "include", // indispensable pour cookie de session
    body: JSON.stringify({ email, password }),
  });
  return json(res); 
}

export async function me() {
  const res = await fetch(`${API_BASE}/me`, {
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
