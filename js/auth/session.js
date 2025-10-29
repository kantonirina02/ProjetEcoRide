const KEY = "ecoride.session";

export function getSession() {
  try {
    return JSON.parse(localStorage.getItem(KEY) || "null");
  } catch {
    return null;
  }
}

export function setSession(sess) {
  localStorage.setItem(KEY, JSON.stringify(sess));
  applyHeaderVisibility();
}

export function clearSession() {
  localStorage.removeItem(KEY);
  applyHeaderVisibility();
}

export function isConnected() {
  const s = getSession();
  return !!(s && s.user && s.user.id);
}

// Utilitaire pour le header : show/hide selon l’état
export function applyHeaderVisibility() {
  const connectedEls = document.querySelectorAll("[data-show-connected]");
  const disconnectedEls = document.querySelectorAll("[data-show-disconnected]");
  const connected = isConnected();

  connectedEls.forEach(el => el.classList.toggle("d-none", !connected));
  disconnectedEls.forEach(el => el.classList.toggle("d-none", connected));
}

// Appel initial (au chargement module)
applyHeaderVisibility();
