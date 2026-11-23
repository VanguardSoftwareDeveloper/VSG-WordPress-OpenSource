/**
 * IMP-48 — interfaz pequeña para el shortcode del Libro.
 * Solo DOM/textContent, fetch same-origin y copia imprimible; no analytics,
 * localStorage, SDK, OTP, adjuntos, log ni enlaces de consulta por folio.
 */
(() => {
  "use strict";

  function field(form, name) {
    const el = form.elements.namedItem(name);
    return el && typeof el.value === "string" ? el.value.trim() : "";
  }

  function person(form, prefix = "") {
    const result = {
      full_name: field(form, prefix + "full_name"),
      identification_kind: field(form, prefix + "identification_kind"),
      identification_value: field(form, prefix + "identification_value"),
      address: field(form, prefix + "address"),
    };
    for (const key of ["phone", "email"]) {
      const value = field(form, prefix + key);
      if (value !== "") result[key] = value;
    }
    return result;
  }

  function cents(pen) {
    if (pen === "") return null;
    const match = /^(0|[1-9]\d*)(?:\.(\d{1,2}))?$/.exec(pen);
    if (!match) throw new Error("Ingrese un monto en soles con hasta dos decimales.");
    const digits = (match[1] + (match[2] || "").padEnd(2, "0")).replace(/^0+(?=\d)/, "");
    if (digits.length > 18) throw new Error("El monto indicado excede el límite permitido.");
    return digits;
  }

  function key() {
    const bytes = new Uint8Array(32);
    crypto.getRandomValues(bytes);
    return Array.from(bytes, byte => byte.toString(16).padStart(2, "0")).join("");
  }

  function add(parent, label, value) {
    if (value === null || value === undefined || value === "") return;
    const dt = document.createElement("dt");
    const dd = document.createElement("dd");
    dt.textContent = label;
    dd.textContent = String(value);
    dd.style.whiteSpace = "pre-wrap";
    parent.append(dt, dd);
  }

  function amount(c) {
    if (typeof c !== "string" || !/^\d+$/.test(c)) return null;
    const full = c.padStart(3, "0");
    return "S/ " + full.slice(0, -2) + "." + full.slice(-2);
  }

  function showReceipt(section, receipt) {
    const copy = receipt && receipt.receipt_copy;
    if (!copy || typeof copy !== "object") throw new Error("La constancia requiere revisión.");
    const target = section.querySelector("[data-pc-complaint-copy]");
    const dl = document.createElement("dl");
    add(dl, "Número de hoja", copy.receipt_number);
    add(dl, "Fecha de ingreso (UTC)", copy.submitted_at);
    const merchant = copy.merchant_snapshot || {};
    add(dl, "Proveedor", merchant.merchant_name);
    add(dl, "RUC", merchant.tax_id);
    add(dl, "Dirección del proveedor", merchant.address);
    add(dl, "Contacto del proveedor", merchant.contact);
    add(dl, "Tipo de presentación", copy.kind);
    const claimant = copy.claimant || {};
    add(dl, "Reclamante", claimant.full_name);
    add(dl, "Tipo de documento", claimant.identification_kind);
    add(dl, "Número de documento", claimant.identification_value);
    add(dl, "Domicilio", claimant.address);
    add(dl, "Teléfono", claimant.phone);
    add(dl, "Correo", claimant.email);
    if (copy.representative) {
      add(dl, "Representante", copy.representative.full_name);
      add(dl, "Documento del representante", copy.representative.identification_value);
      add(dl, "Dirección del representante", copy.representative.address);
      add(dl, "Teléfono del representante", copy.representative.phone);
      add(dl, "Correo del representante", copy.representative.email);
    }
    add(dl, "Bien o servicio", copy.subject);
    add(dl, "Descripción del bien o servicio", copy.subject_description);
    add(dl, "Monto reclamado", amount(copy.claimed_amount_cents));
    add(dl, "Detalle", copy.description);
    add(dl, "Pedido concreto", copy.request);
    add(dl, "Plazo indicado (UTC)", copy.due_at);
    target.replaceChildren(dl);
    section.hidden = false;
  }

  for (const form of document.querySelectorAll("form[data-pc-complaint-form]")) {
    const section = form.closest(".pc-libro");
    const status = form.querySelector("[data-pc-complaint-status]");
    const minor = form.elements.namedItem("minor");
    const rep = section.querySelector("[data-pc-representative]");
    const toggle = () => {
      const active = !!minor.checked;
      rep.hidden = !active;
      for (const name of [
        "representative_full_name", "representative_identification_value",
        "representative_address",
      ]) {
        form.elements.namedItem(name).required = active;
      }
    };
    minor.addEventListener("change", toggle);
    toggle();

    const receipt = section.querySelector("[data-pc-complaint-receipt]");
    receipt.querySelector("[data-pc-complaint-print]")
      .addEventListener("click", () => window.print());

    // La misma clave se conserva si la red no confirmó la respuesta;
    // cada presentación posterior debe comenzar en un nuevo formulario.
    let operationKey = key();
    let preparedBody = null;
    let sent = false;
    let inFlight = false;
    const editable = Array.from(form.querySelectorAll("input, select, textarea"));
    // Un resultado incierto exige el MISMO cuerpo y clave. Bloquea la
    // edición, pero deja activo el botón para reintentar el mismo intento.
    const lockFields = (locked) => {
      for (const field of editable) field.disabled = locked;
    };
    form.addEventListener("submit", async event => {
      event.preventDefault();
      if (inFlight || !form.reportValidity()) return;
      inFlight = true;
      const submit = form.querySelector('button[type="submit"]');
      submit.disabled = true;
      status.textContent = "Registrando su reclamación…";
      try {
        if (preparedBody === null) {
          const claimant = person(form);
        if (!claimant.phone && !claimant.email) {
          throw new Error("Indique por lo menos un medio de contacto válido.");
        }
        const body = {
          kind: field(form, "kind"),
          claimant,
          subject: field(form, "subject"),
          subject_description: field(form, "subject_description"),
          description: field(form, "description"),
          request: field(form, "request"),
          idempotency_key: operationKey,
        };
        if (minor.checked) {
          body.representative = person(form, "representative_");
          if (!body.representative.phone && !body.representative.email) {
            throw new Error("Indique un medio de contacto de su representante.");
          }
        }
        const value = cents(field(form, "amount_pen"));
        if (value !== null) body.claimed_amount_cents = value;
        const reference = field(form, "transaction_reference");
        if (reference) body.transaction_reference = reference;
          preparedBody = JSON.stringify(body);
        }
        // El POST puede confirmar en servidor aunque falle la recepción
        // de su respuesta. El mismo JSON y UUID se conservan hasta 201.
        sent = true;
        const res = await fetch(form.dataset.url, {
          method: "POST",
          mode: "same-origin",
          credentials: "same-origin",
          cache: "no-store",
          referrerPolicy: "no-referrer",
          headers: {
            "Content-Type": "application/json",
            "X-PC-Form-CSRF": form.dataset.csrf,
            "X-PC-Complaint-Accepted": "yes",
          },
          body: preparedBody,
        });
        if (res.status !== 201) {
          if (res.status === 400 || res.status === 422) {
            // Rechazo de validación definido: permitir corregir mediante
            // un NUEVO intento lógico solo cuando no se creó la hoja.
            preparedBody = null;
            operationKey = key();
            sent = false;
            lockFields(false);
            throw new Error("Revise los datos de identificación, contacto y reclamación. Se conservaron sus campos para corregirlos.");
          }
          throw new Error("El registro no se confirmó. Revise su conexión y reintente exactamente la misma solicitud; no modifique los datos ni abra otro formulario.");
        }
        const receiptData = await res.json();
        showReceipt(receipt, receiptData);
        preparedBody = null;
        sent = false;
        form.reset();
        form.hidden = true;
        // La persona debe poder localizar la constancia sin recorrer la
        // página original otra vez: mover foco a su acción accesible.
        receipt.querySelector("[data-pc-complaint-print]").focus();
        // La constancia solo existe en este contexto de presentación;
        // no se crea enlace público por folio ni se guarda en storage.
      } catch (error) {
        // Un error anterior al primer POST permite corregir. Tras un
        // POST sin respuesta 201/422, el resultado queda incierto.
        if (sent && preparedBody !== null) lockFields(true);
        status.textContent = error instanceof Error ? error.message :
          "No se confirmó el registro. Reintente con este mismo formulario.";
        submit.textContent = sent && preparedBody !== null
          ? "Reintentar exactamente la misma reclamación"
          : "Registrar reclamación";
        submit.disabled = false;
      } finally {
        inFlight = false;
      }
    });
  }
})();
