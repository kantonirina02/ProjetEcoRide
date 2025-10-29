const API_BASE = "http://127.0.0.1:8001/api";

export async function fetchRides({ from = "", to = "", date = "" } = {}) {
  const p = new URLSearchParams();
  if (from) p.set("from", from);
  if (to) p.set("to", to);
  if (date) p.set("date", date);

  const url = `${API_BASE}/rides${p.toString() ? "?" + p.toString() : ""}`;
  const res = await fetch(url, { headers: { Accept: "application/json" } });
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  return res.json();
}

export async function bookRide(id, { userId, seats }) {
  const url = `${API_BASE}/rides/${id}/book`;
  const res = await fetch(url, {
    method: "POST",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    body: JSON.stringify({ userId, seats }),
  });
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  return res.json();
}
