const normalizeBase = (value) => {
  if (!value) return null;
  const trimmed = value.trim().replace(/\/+$/, "");
  if (trimmed === "") {
    return null;
  }
  return trimmed.endsWith("/api") ? trimmed : `${trimmed}/api`;
};

const computeApiBase = () => {
  if (typeof window !== "undefined") {
    const override = typeof window.__API_BASE_OVERRIDE === "string" && window.__API_BASE_OVERRIDE.trim() !== ""
      ? window.__API_BASE_OVERRIDE
      : typeof window.__API_BASE === "string" && window.__API_BASE.trim() !== ""
        ? window.__API_BASE
        : null;
    if (override) {
      const normalized = normalizeBase(override);
      if (normalized) {
        return normalized;
      }
    }

    const meta = typeof document !== "undefined"
      ? document.querySelector('meta[name="api-base"]')
      : null;
    if (meta?.content) {
      const normalized = normalizeBase(meta.content);
      if (normalized) {
        return normalized;
      }
    }
  }

  if (typeof window === "undefined" || !window.location) {
    return "http://127.0.0.1:8001/api";
  }

  const { protocol, hostname, port } = window.location;
  const normalizedProtocol = protocol && protocol !== ":" ? protocol : "http:";
  const normalizedHost = hostname && hostname !== "" ? hostname : "localhost";
  const currentPort = port && port !== "" ? port : null;

  if (!currentPort || ["3000", "5173", "8080"].includes(currentPort)) {
    return `${normalizedProtocol}//${normalizedHost}:8001/api`;
  }

  if (["8001", "8002"].includes(currentPort)) {
    return `${normalizedProtocol}//${normalizedHost}:${currentPort}/api`;
  }

  return `${normalizedProtocol}//${normalizedHost}:8001/api`;
};

export const API_BASE = computeApiBase();

if (typeof window !== "undefined") {
  window.__API_BASE = API_BASE;
}

async function json(res) {
  if (!res.ok) {
    let detail = "";
    try {
      detail = JSON.stringify(await res.json());
    } catch {
      // ignore
    }
    throw new Error(`HTTP ${res.status} ${detail}`);
  }
  return res.json();
}

/* ---------- Search rides ---------- */
export async function fetchRides({ from = "", to = "", date = "", eco, priceMax, durationMax, ratingMin } = {}) {
  const params = new URLSearchParams();
  if (from) params.set("from", from);
  if (to) params.set("to", to);
  if (date) params.set("date", date);
  if (eco !== undefined) params.set("eco", eco ? "1" : "0");
  if (priceMax) params.set("priceMax", String(priceMax));
  if (durationMax) params.set("durationMax", String(durationMax));
  if (ratingMin) params.set("ratingMin", String(ratingMin));

  const url = `${API_BASE}/rides${params.toString() ? `?${params.toString()}` : ""}`;
  const res = await fetch(url, {
    headers: { Accept: "application/json" },
    credentials: "include",
  });
  const payload = await json(res);
  if (payload && typeof payload === "object" && "rides" in payload) {
    return payload;
  }
  return { rides: Array.isArray(payload) ? payload : [], suggestion: null };
}

/* ---------- Reservations ---------- */
export async function bookRide(id, { seats = 1 } = {}) {
  const res = await fetch(`${API_BASE}/rides/${id}/book`, {
    method: "POST",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    credentials: "include",
    body: JSON.stringify({ seats }),
  });
  return json(res);
}

export async function unbookRide(rideId) {
  const res = await fetch(`${API_BASE}/rides/${rideId}/book`, {
    method: "DELETE",
    credentials: "include",
  });
  return json(res);
}

export async function cancelRideAsDriver(rideId) {
  const res = await fetch(`${API_BASE}/rides/${rideId}/cancel`, {
    method: "POST",
    credentials: "include",
    headers: { Accept: "application/json" },
  });
  return json(res);
}

/* ---------- Current user data ---------- */
export async function fetchAccountOverview() {
  const res = await fetch(`${API_BASE}/me/overview`, {
    headers: { Accept: "application/json" },
    credentials: "include",
  });
  if (res.status === 401 || res.status === 403) {
    return { auth: false };
  }
  return json(res);
}

export async function updateDriverRole({ driver }) {
  const res = await fetch(`${API_BASE}/me/roles`, {
    method: "POST",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    credentials: "include",
    body: JSON.stringify({ driver: Boolean(driver) }),
  });
  return json(res);
}

export async function saveDriverPreferences(preferences) {
  const res = await fetch(`${API_BASE}/me/preferences`, {
    method: "PATCH",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    credentials: "include",
    body: JSON.stringify(preferences),
  });
  return json(res);
}

/* ---------- Vehicles ---------- */
export async function fetchMyVehicles() {
  const res = await fetch(`${API_BASE}/me/vehicles`, {
    headers: { Accept: "application/json" },
    credentials: "include",
  });
  if (res.status === 401 || res.status === 403) {
    return { auth: false, vehicles: [] };
  }
  return json(res);
}

export async function saveVehicle(vehicle) {
  const res = await fetch(`${API_BASE}/me/vehicles`, {
    method: "POST",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    credentials: "include",
    body: JSON.stringify(vehicle),
  });
  return json(res);
}

export async function deleteVehicle(vehicleId) {
  const res = await fetch(`${API_BASE}/me/vehicles/${vehicleId}`, {
    method: "DELETE",
    credentials: "include",
    headers: { Accept: "application/json" },
  });
  return json(res);
}

export async function uploadProfilePhoto(file) {
  const formData = new FormData();
  formData.append("photo", file);
  const res = await fetch(`${API_BASE}/me/photo`, {
    method: "POST",
    credentials: "include",
    body: formData,
  });
  return json(res);
}

export async function deleteProfilePhoto() {
  const res = await fetch(`${API_BASE}/me/photo`, {
    method: "DELETE",
    credentials: "include",
  });
  return json(res);
}

/* ---------- Moderation / Admin ---------- */
export async function fetchModerationReviews(status = "pending") {
  const params = new URLSearchParams();
  if (status) params.set("status", status);
  const res = await fetch(`${API_BASE}/moderation/reviews?${params.toString()}`, {
    headers: { Accept: "application/json" },
    credentials: "include",
  });
  return json(res);
}

export async function moderateReview(id, { action, note }) {
  const res = await fetch(`${API_BASE}/moderation/reviews/${id}/decision`, {
    method: "POST",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    credentials: "include",
    body: JSON.stringify({ action, note }),
  });
  return json(res);
}

export async function fetchAdminMetrics() {
  const res = await fetch(`${API_BASE}/admin/metrics`, {
    headers: { Accept: "application/json" },
    credentials: "include",
  });
  return json(res);
}

export async function suspendUser(id, { suspend = true, reason } = {}) {
  const res = await fetch(`${API_BASE}/admin/users/${id}/suspend`, {
    method: "POST",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    credentials: "include",
    body: JSON.stringify({ suspend, reason }),
  });
  return json(res);
}

/* ---------- My bookings / rides ---------- */
export async function fetchMyBookings() {
  const res = await fetch(`${API_BASE}/me/bookings`, {
    credentials: "include",
    headers: { Accept: "application/json" },
  });
  return json(res);
}

export async function fetchMyRides() {
  const res = await fetch(`${API_BASE}/me/rides`, {
    headers: { Accept: "application/json" },
    credentials: "include",
  });
  return json(res);
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

/* ---------- Ride creation ---------- */
export async function createRide(payload) {
  const res = await fetch(`${API_BASE}/rides`, {
    method: "POST",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    credentials: "include",
    body: JSON.stringify(payload),
  });
  return json(res);
}

/* ---------- Ride detail ---------- */
export async function fetchRide(id) {
  const res = await fetch(`${API_BASE}/rides/${id}`, {
    headers: { Accept: "application/json" },
    credentials: "include",
  });
  return json(res);
}
