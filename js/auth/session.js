const KEY = "ecoride_session";

export function getSession() {
  try { return JSON.parse(localStorage.getItem(KEY)) || null; }
  catch { return null; }
}

export function setSession(sess) {
  localStorage.setItem(KEY, JSON.stringify(sess));
  window.__session = sess;
  window.dispatchEvent(new CustomEvent("session:changed", { detail: { session: sess } }));
}

export function clearSession() {
  localStorage.removeItem(KEY);
  window.__session = null;
  window.dispatchEvent(new CustomEvent("session:changed", { detail: { session: null } }));
}

// hydrate en mémoire au chargement
window.__session = getSession();
