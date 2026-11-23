/*
 * IMP-31 — vista propia de seguimiento, sin terceros ni dependencias.
 * El fragmento se borra ANTES de cualquier solicitud de red. Nunca se
 * guarda en DOM, localStorage, URL de destino ni trazas de consola.
 */
"use strict";
(() => {
  const root = document.getElementById("pc-track");
  if (!root) return;
  const status = document.getElementById("pc-track-status");
  const details = document.getElementById("pc-track-details");
  const refresh = document.getElementById("pc-track-refresh");
  const hash = window.location.hash;
  // Eliminar TODO el fragmento, también cuando está mal formado.
  if (hash) {
    try {
      window.history.replaceState(null, "", window.location.pathname + window.location.search);
    } catch (_) {
      // Nunca intentar transmitir el secreto por query o Referer.
      status.textContent = "No se pudo asegurar la dirección de seguimiento.";
      return;
    }
  }
  const secret = hash.startsWith("#")
    ? new URLSearchParams(hash.slice(1)).get("tracking")
    : null;

  const label = (text) => {
    const p = document.createElement("p");
    p.textContent = text;
    return p;
  };
  const element = (tag, text, className = "") => {
    const node = document.createElement(tag);
    node.textContent = text;
    if (className) node.className = className;
    return node;
  };
  const money = (cents) => {
    const value = String(cents ?? "");
    if (!/^(0|[1-9][0-9]{0,18})$/.test(value)) return "Importe no disponible";
    const digits = value.padStart(3, "0");
    const whole = digits.slice(0, -2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    return "S/ " + whole + "." + digits.slice(-2);
  };
  const stateText = (code) => {
    const labels = {
      PENDIENTE_PAGO: "Pago pendiente",
      CONFIRMADO: "Pedido confirmado",
      FINALIZADO: "Pedido finalizado",
      CANCELADO: "Pedido cancelado",
      VENCIDO: "Plazo de pago vencido",
      PENDIENTE_COBRO: "Pendiente de cobro",
      ACREDITADO: "Pago registrado",
      ACREDITADO_EN_CONCILIACION: "Pago registrado; asignación de recursos pendiente",
      CONCILIACION_PENDIENTE: "Pago en revisión",
      PENDIENTE: "Pendiente",
      PENDIENTE_CONFIRMACION: "Pendiente de confirmación",
      PREPARANDO: "En preparación",
      LISTO: "Listo",
      DESPACHADO: "Despachado",
      ENTREGADO: "Entregado",
      BLOQUEADO: "Acceso no habilitado",
      HABILITADO: "Acceso habilitado",
      CONFIRMADA: "Confirmada",
      REALIZADA: "Realizada",
      EXPIRADA: "Vencida",
      REVOCADO: "Acceso revocado"
    };
    return labels[code] || String(code ?? "En revisión").replaceAll("_", " ");
  };
  const request = async (url, options = {}) => {
    const response = await fetch(url, {
      method: options.method || "GET",
      credentials: "same-origin",
      cache: "no-store",
      headers: { Accept: "application/json", ...(options.body ? { "Content-Type": "application/json" } : {}) },
      body: options.body ? JSON.stringify(options.body) : undefined,
      redirect: "error"
    });
    if (!response.ok) throw new Error("No autorizado o no disponible");
    const data = await response.json();
    if (!data || typeof data !== "object") throw new Error("Respuesta no válida");
    return data;
  };
  const showStatus = (message) => {
    status.replaceChildren(label(message));
  };
  const section = (title) => {
    const wrap = document.createElement("section");
    wrap.className = "pc-track__panel";
    wrap.append(element("h2", title));
    details.append(wrap);
    return wrap;
  };
  const render = (order) => {
    details.replaceChildren();
    const header = section("Tu pedido");
    header.append(element("p", order.order_number || "Pedido", "pc-track__order-number"));
    header.append(label("Estado: " + stateText(order.order_state)));
    header.append(label("Total pactado: " + money(order.total_cents)));

    const payment = section("Pago");
    payment.append(label(stateText(order.payment_summary?.status_label)));
    if (order.payment_summary?.amount_paid_cents !== undefined) {
      payment.append(label("Registrado: " + money(order.payment_summary.amount_paid_cents)));
    }
    if (order.payment_summary?.amount_outstanding_cents !== undefined) {
      payment.append(label("Importe pendiente: " + money(order.payment_summary.amount_outstanding_cents)));
    }

    const products = section("Productos y servicios");
    const list = document.createElement("ul");
    list.className = "pc-track__list";
    for (const line of Array.isArray(order.lines) ? order.lines : []) {
      const row = document.createElement("li");
      row.append(element("strong", String(line.title || "Artículo")));
      row.append(label("Cantidad: " + String(line.quantity ?? "")));
      row.append(label("Precio unitario: " + money(line.unit_price_cents)));
      row.append(label("Estado: " + stateText(line.fulfillment_state)));
      if (line.kind === "physical" && line.physical_quantities) {
        const counts = line.physical_quantities;
        row.append(label("Unidades pendientes de salida física: " + String(counts.pending_stock_out_units)));
      }
      list.append(row);
    }
    products.append(list);

    if (order.delivery_summary) {
      const delivery = section("Entrega");
      delivery.append(label(order.delivery_summary));
    }
    if (order.appointment_summary) {
      const appt = section("Servicio");
      appt.append(label(order.appointment_summary));
    }
    if (order.agreed_notices) {
      const notices = section("Condiciones informadas al comprar");
      for (const [title, value] of [
        ["Condiciones comerciales", order.agreed_notices.policy_notice],
        ["Privacidad", order.agreed_notices.privacy_notice]
      ]) {
        if (!value) continue;
        const disclosure = document.createElement("details");
        disclosure.append(element("summary", title));
        disclosure.append(element("p", String(value), "pc-track__legal"));
        notices.append(disclosure);
      }
    }
    details.hidden = false;
    refresh.hidden = false;
    showStatus("Información privada del pedido. Los cambios de estado pueden tardar en reflejarse.");
    document.dispatchEvent(new Event("pc-order-verified"));
  };
  let busy = false;
  const load = async () => {
    if (busy) return;
    busy = true;
    refresh.disabled = true;
    try {
      showStatus("Consultando tu pedido…");
      render(await request(root.dataset.order));
    } catch (_) {
      details.hidden = true;
      refresh.hidden = true;
      showStatus("No se pudo verificar la sesión. Abre tu enlace de seguimiento vigente o solicita al comercio una recuperación de acceso verificada.");
      document.dispatchEvent(new Event("pc-order-unavailable"));
    } finally {
      busy = false;
      refresh.disabled = false;
    }
  };
  refresh.addEventListener("click", load);
  const begin = async () => {
    if (secret !== null) {
      if (!/^pc1\.[1-9][0-9]{0,19}\.[A-Za-z0-9_-]{43}$/.test(secret)) {
        showStatus("El enlace no es válido. Solicita al comercio una recuperación de acceso verificada.");
        return;
      }
      try {
        // Solo body JSON same-origin. El secreto NO viaja en URL.
        await request(root.dataset.exchange, {
          method: "POST",
          body: { tracking_secret: secret }
        });
      } catch (_) {
        showStatus("El enlace no está disponible o fue revocado. Solicita al comercio una recuperación de acceso verificada.");
        return;
      }
    }
    await load();
  };
  void begin();
})();
