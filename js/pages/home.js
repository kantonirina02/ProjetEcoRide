(function () {
  const form = document.getElementById("search-form");
  if (!form) return;

  const $from = document.getElementById("depart-ville");
  const $to   = document.getElementById("arrivee-ville");
  const $date = document.getElementById("date-voyage");

  form.addEventListener("submit", (e) => {
    e.preventDefault();

    const params = new URLSearchParams();
    if ($from.value.trim()) params.set("from", $from.value.trim());
    if ($to.value.trim())   params.set("to", $to.value.trim());
    if ($date.value)        params.set("date", $date.value);

    const target = `/covoiturages${params.toString() ? "?" + params.toString() : ""}`;

    // Utilise le router SPA (lien virtuel)
    const a = document.createElement("a");
    a.href = target;
    a.setAttribute("data-link", "");   // indispensable pour ton router
    document.body.appendChild(a);
    a.click();
    a.remove();
  });
})();
