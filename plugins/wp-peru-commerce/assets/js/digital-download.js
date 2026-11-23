/* IMP-35 — solo script propio; el fragmento se borra antes de fetch. */
"use strict";
(() => {
  const root = document.getElementById("pc-download");
  if (!root) return;
  const status = document.getElementById("pc-download-status");
  const action = document.getElementById("pc-download-action");
  const fragment = window.location.hash;
  if (fragment) {
    try {
      window.history.replaceState(null, "", window.location.pathname + window.location.search);
    } catch (_) {
      status.textContent = "No se pudo proteger la dirección de descarga.";
      return;
    }
  }
  const secret = fragment.startsWith("#")
    ? new URLSearchParams(fragment.slice(1)).get("digital")
    : null;
  if (typeof secret !== "string" || !/^pcd1\.[1-9][0-9]{0,19}\.[A-Za-z0-9_-]{43}$/.test(secret)) {
    status.textContent = "No hay un enlace de descarga válido. Solicita uno al comercio.";
    return;
  }
  const run = async () => {
    try {
      const response = await fetch(root.dataset.exchange, {
        method: "POST",
        credentials: "same-origin",
        cache: "no-store",
        redirect: "error",
        headers: {
          "Accept": "application/json",
          "Content-Type": "application/json"
        },
        body: JSON.stringify({ digital_token: secret })
      });
      if (!response.ok) throw new Error("denied");
      const result = await response.json();
      if (!result || typeof result.right_id !== "string"
          || !/^[1-9][0-9]*$/.test(result.right_id)) {
        throw new Error("invalid");
      }
      action.href = root.dataset.fileTemplate.replace("__RIGHT_ID__", result.right_id);
      action.hidden = false;
      status.textContent = "Acceso verificado. Selecciona «Descargar archivo» dentro de los próximos 10 minutos.";
    } catch (_) {
      action.hidden = true;
      status.textContent = "El enlace digital pudo vencer, el derecho pudo revocarse, o la cuota de descargas pudo agotarse. La expiración del enlace no elimina por sí sola el derecho de compra. Solicita una revisión o reemisión verificada al comercio.";
    }
  };
  void run();
})();