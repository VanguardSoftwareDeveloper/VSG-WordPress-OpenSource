/*
 * IMP-60: acciones sensibles de invitado en /seguimiento.
 * Nunca contiene OTP, token, grant o PII en URL/DOM persistente, storage,
 * logs o herramientas de terceros. El servidor tiene la única autoridad.
 */
"use strict";
(() => {
  const root = document.getElementById("pc-track");
  const section = document.getElementById("pc-guest-actions");
  if (!root || !section || location.protocol !== "https:") return;
  const status = document.getElementById("pc-guest-action-status");
  const purpose = document.getElementById("pc-guest-purpose");
  const reason = document.getElementById("pc-guest-reason");
  const ubigeo = document.getElementById("pc-guest-ubigeo");
  const address = document.getElementById("pc-guest-address");
  const addressFields = document.getElementById("pc-guest-address-fields");
  const requestCode = document.getElementById("pc-guest-send-code");
  const verifyCode = document.getElementById("pc-guest-verify-code");
  const code = document.getElementById("pc-guest-code");
  const verifyPanel = document.getElementById("pc-guest-verify-panel");
  const submitPanel = document.getElementById("pc-guest-submit-panel");
  const submit = document.getElementById("pc-guest-submit");
  const urls = {
    challenge: root.dataset.challenge,
    verify: root.dataset.verify,
    submit: root.dataset.guestRequests
  };
  for (const value of Object.values(urls)) {
    try {
      const parsed = new URL(value);
      if (parsed.origin !== location.origin || parsed.protocol !== "https:") return;
    } catch (_) { return; }
  }
  const allowed = new Set([
    "reissue_tracking", "delivery_change", "request_refund",
    "reissue_download", "resend_notification"
  ]);
  let step = "choose";
  let busy = false;
  let attempt = null;

  function say(message) { status.textContent = message; }
  function reset() {
    attempt = null;
    step = "choose";
    code.value = "";
    verifyPanel.hidden = true;
    submitPanel.hidden = true;
    requestCode.disabled = false;
    submit.disabled = false;
    addressFields.hidden = purpose.value !== "delivery_change";
    say("Estas gestiones requieren verificación de tu correo original. Si el pedido solo tiene teléfono, comunícate con el comercio para una revisión manual.");
  }
  purpose.addEventListener("change", reset);
  document.addEventListener("pc-order-verified", () => {
    section.hidden = false;
    reset();
  });
  document.addEventListener("pc-order-unavailable", () => {
    section.hidden = true;
    reset();
  });

  async function post(url, body) {
    const response = await fetch(url, {
      method: "POST",
      credentials: "same-origin",
      cache: "no-store",
      redirect: "error",
      headers: {
        "Accept": "application/json",
        "Content-Type": "application/json"
      },
      body: JSON.stringify(body)
    });
    let result = null;
    try { result = await response.json(); } catch (_) { /* no divulgar detalles */ }
    if (!response.ok || !result || typeof result !== "object") {
      throw new Error(response.status === 429
        ? "Demasiados intentos. Reintenta cuando corresponda."
        : "La solicitud no fue autorizada o no se pudo verificar.");
    }
    return { status: response.status, body: result };
  }
  const guarded = async (fn) => {
    if (busy) return;
    busy = true;
    for (const button of [requestCode, verifyCode, submit]) button.disabled = true;
    try { await fn(); }
    catch (error) {
      say(error instanceof Error ? error.message : "No se pudo completar la gestión.");
    } finally {
      busy = false;
      requestCode.disabled = step !== "choose";
      verifyCode.disabled = step !== "verify";
      submit.disabled = step !== "submit";
    }
  };

  requestCode.addEventListener("click", () => guarded(async () => {
    if (!allowed.has(purpose.value) || step !== "choose") return;
    const reply = await post(urls.challenge, { purpose: purpose.value });
    if (reply.status !== 202 || reply.body.accepted !== true) {
      say("No se pudo iniciar la verificación.");
      return;
    }
    step = "verify";
    verifyPanel.hidden = false;
    code.value = "";
    say("Si tu correo original está habilitado, recibirás un código de 8 dígitos. Dura 10 minutos; máximo 5 intentos.");
    code.focus();
  }));

  verifyCode.addEventListener("click", () => guarded(async () => {
    if (step !== "verify" || !/^[0-9]{8}$/.test(code.value)) {
      say("Introduce un código de 8 dígitos.");
      return;
    }
    const reply = await post(urls.verify, {
      purpose: purpose.value, code: code.value
    });
    code.value = "";
    if (reply.status !== 200 || reply.body.accepted !== true) {
      say("Código inválido, vencido o ya utilizado. Revisa los datos o solicita asistencia.");
      return;
    }
    step = "submit";
    verifyPanel.hidden = true;
    submitPanel.hidden = false;
    attempt = crypto.randomUUID();
    say("Identidad verificada para esta finalidad. La autorización dura como máximo 5 minutos y se consume una sola vez al abrir el expediente.");
  }));

  submit.addEventListener("click", () => guarded(async () => {
    if (step !== "submit" || !attempt || !allowed.has(purpose.value)) return;
    const body = { purpose: purpose.value, idempotency_key: attempt };
    const detail = reason.value.trim();
    if (detail) body.reason = detail;
    if (purpose.value === "delivery_change") {
      if (!/^[0-9]{6}$/.test(ubigeo.value) || !address.value.trim()) {
        say("Es obligatorio indicar UBIGEO de seis dígitos y nueva dirección.");
        return;
      }
      body.requested_ubigeo = ubigeo.value;
      body.requested_address = address.value.trim();
    }
    // Conservar la misma clave si el resultado HTTP es incierto:
    // jamás crear otro expediente automáticamente o consumir otro grant.
    const reply = await post(urls.submit, body);
    if (reply.status !== 202 || reply.body.accepted !== true
        || !/^[1-9][0-9]*$/.test(String(reply.body.case_id))) {
      say("La gestión no se pudo confirmar. No la repitas con otra clave; consulta al comercio.");
      return;
    }
    step = "complete";
    submitPanel.hidden = true;
    say("Solicitud " + reply.body.case_id + " registrada para revisión. No implica aprobación, reembolso ni cambio automático.");
  }));
})();
