/**
 * IMP-20 — contrato ESM del mapa OPCIONAL para el checkout futuro.
 *
 * No importa Leaflet ni CSS ni toca la red al evaluar este archivo.
 * IMP-58/66 suministrarán loadLocalLeaflet(), que deberá cargar únicamente
 * el paquete versionado/local con licencia validada, DESPUÉS de pulsar
 * «Abrir mapa». No hay geocoding, navigator.geolocation, analytics,
 * prefetch, mapas offline, peticiones al backend ni cálculo de tarifas.
 *
 * @module peru-commerce/map-optin
 */

const OSM_TEMPLATE = "https://tile.openstreetmap.org/{z}/{x}/{y}.png";
const OSM_COPYRIGHT = "https://www.openstreetmap.org/copyright";

function checkedTileConfig(source) {
  if (!source || source.enabled !== true ||
      typeof source.tile_url_template !== "string" ||
      typeof source.attribution_url !== "string" ||
      typeof source.attribution_text !== "string") {
    throw new Error("pc_map_config_unavailable");
  }
  const template = source.tile_url_template;
  const attrUrl = new URL(source.attribution_url);
  const sample = template
    .replace("{z}", "0")
    .replace("{x}", "0")
    .replace("{y}", "0");
  const parsed = new URL(sample);
  if (parsed.protocol !== "https:" || attrUrl.protocol !== "https:" ||
      parsed.username || parsed.password || parsed.search || parsed.hash ||
      attrUrl.username || attrUrl.password || attrUrl.search || attrUrl.hash ||
      !/^[a-z0-9.-]+\.[a-z]{2,63}$/i.test(parsed.hostname) ||
      !/^[a-z0-9.-]+\.[a-z]{2,63}$/i.test(attrUrl.hostname) ||
      ["{z}", "{x}", "{y}"].some(part => template.split(part).length !== 2) ||
      /[{}\\%]/.test(sample) ||
      !source.attribution_text.trim() ||
      /[<>\u0000-\u001f\u007f]/.test(source.attribution_text)) {
    throw new Error("pc_map_config_invalid");
  }
  if (parsed.hostname === "tile.openstreetmap.org" &&
      (template !== OSM_TEMPLATE ||
       source.attribution_url !== OSM_COPYRIGHT ||
       !source.attribution_text.includes("OpenStreetMap"))) {
    throw new Error("pc_map_config_invalid");
  }
  return {
    template,
    attributionUrl: source.attribution_url,
    attributionText: source.attribution_text,
  };
}

function safePoint(value) {
  if (!Array.isArray(value) || value.length !== 2 ||
      !value.every(Number.isFinite) ||
      Math.abs(value[0]) > 90 || Math.abs(value[1]) > 180) {
    throw new Error("pc_map_point_invalid");
  }
  return [value[0], value[1]];
}

function escapeHtml(text) {
  return text.replace(/&/g, "&amp;")
    .replace(/</g, "&lt;").replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;").replace(/'/g, "&#39;");
}

/**
 * A reusable, deliberately inert adapter. Consumers must render their
 * own local/manual address fields and initialize only on a public, safe
 * checkout surface with no URL tokens, private order data or fragments.
 *
 * @param {{
 *   openButton: HTMLButtonElement,
 *   mapElement: HTMLElement,
 *   statusElement: HTMLElement,
 *   provider: object,
 *   center: number[],
 *   loadLocalLeaflet: () => Promise<object>,
 *   safeSurface: () => boolean,
 *   onPoint: (point: {lat:number,lng:number}) => void
 * }} options
 * @returns {{dispose:()=>void}}
 */
export function bindOptionalMap(options) {
  if (!options ||
      !(options.openButton instanceof HTMLButtonElement) ||
      !(options.mapElement instanceof HTMLElement) ||
      !(options.statusElement instanceof HTMLElement) ||
      !options.mapElement.id ||
      typeof options.loadLocalLeaflet !== "function" ||
      typeof options.safeSurface !== "function" ||
      typeof options.onPoint !== "function") {
    throw new Error("pc_map_adapter_invalid");
  }
  const center = safePoint(options.center);
  let disposed = false;
  let pending = false;
  let map = null;
  const button = options.openButton;
  const target = options.mapElement;
  const notice = options.statusElement;

  // DOM root is born hidden. Merely binding this adapter requests nothing.
  target.hidden = true;
  button.setAttribute("aria-expanded", "false");
  button.setAttribute("aria-controls", target.id);
  button.type = "button";

  function close() {
    if (map) {
      map.remove(); // Stop tile fetches after closing; browser cache remains.
      map = null;
    }
    target.hidden = true;
    button.setAttribute("aria-expanded", "false");
    button.textContent = "Abrir mapa opcional";
  }

  async function toggle() {
    if (disposed || pending) return;
    if (map) {
      close();
      return;
    }
    // All third-party data flow is after an explicit user click.
    // Abort before importing Leaflet if the page/consent is not appropriate.
    let safe = false;
    try {
      safe = options.safeSurface() === true &&
        window.location.hash === "" && window.location.search === "";
    } catch (_) {
      safe = false;
    }
    if (!safe) {
      notice.textContent =
        "El mapa no está disponible en esta página. Continúa con tu dirección escrita.";
      return;
    }
    let config;
    try {
      config = checkedTileConfig(options.provider);
    } catch (_) {
      notice.textContent = "Mapa no configurado. Continúa con tu dirección escrita.";
      return;
    }

    pending = true;
    button.disabled = true;
    notice.textContent = "Abriendo mapa opcional…";
    try {
      // Provided by the release manifest in IMP-58/66. No CDN/autoload.
      const leafletModule = await options.loadLocalLeaflet();
      if (disposed) return;
      const L = leafletModule?.default ?? leafletModule;
      if (!L || typeof L.map !== "function" ||
          typeof L.tileLayer !== "function" ||
          typeof L.marker !== "function") {
        throw new Error("pc_map_library_unavailable");
      }

      target.hidden = false;
      map = L.map(target, {
        center,
        zoom: 12,
        attributionControl: true,
        zoomControl: true,
      });
      const copyright = "<a href=\"" + escapeHtml(config.attributionUrl) +
        "\" target=\"_blank\" rel=\"noopener noreferrer\">" +
        escapeHtml(config.attributionText) + "</a>";
      L.tileLayer(config.template, {
        attribution: copyright,
        referrerPolicy: "strict-origin-when-cross-origin",
        detectRetina: false,
        keepBuffer: 1,
      }).addTo(map);
      let pin = null;
      map.on("click", (event) => {
        if (disposed || !event?.latlng) return;
        const {lat, lng} = event.latlng;
        if (!Number.isFinite(lat) || !Number.isFinite(lng) ||
            Math.abs(lat) > 90 || Math.abs(lng) > 180) return;
        if (pin) pin.setLatLng([lat, lng]);
        else pin = L.marker([lat, lng]).addTo(map);
        // Pin does not change UBIGEO, address, delivery fee or eligibility.
        options.onPoint({lat, lng});
      });
      map.invalidateSize();
      button.setAttribute("aria-expanded", "true");
      button.textContent = "Cerrar mapa";
      notice.textContent =
        "Mapa orientativo. Puedes señalar un punto o usar solo la dirección escrita.";
    } catch (_) {
      close();
      notice.textContent =
        "No se pudo abrir el mapa. Tu dirección escrita sigue disponible.";
    } finally {
      pending = false;
      if (!disposed) button.disabled = false;
    }
  }

  button.addEventListener("click", toggle);
  return {
    dispose() {
      disposed = true;
      button.removeEventListener("click", toggle);
      close();
    },
  };
}
