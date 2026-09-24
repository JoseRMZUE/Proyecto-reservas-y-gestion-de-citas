/* ==========================================================
   STAY_beauty — JavaScript Principal Completo y Corregido
   ========================================================== */

// --- CONSTANTES Y CONFIGURACIÓN ---
const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
const DIAS_CONFIG = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];
const DIAS_LABEL = { 
  lunes: 'Lunes', martes: 'Martes', miercoles: 'Miércoles', 
  jueves: 'Jueves', viernes: 'Viernes', sabado: 'Sábado', domingo: 'Domingo' 
};

// La reserva nace CONFIRMADA (HU-RES-01), por eso ya no existe "pendiente".
const TRANSICIONES_ESTADO = {
  confirmada: ['en_proceso', 'cancelada'],
  en_proceso: ['atendida'],
  atendida:   [],
  cancelada:  []
};

let reservasAdminData = [];

let estadoReserva = {
  paso: 1,
  usuario: null,
  servicios: [],
  servicioSeleccionado: null,
  fecha: '',
  hora: ''
};

/* ==========================================================
   1. UTILIDADES Y HELPERS
   ========================================================== */
function formatoMoneda(valor) {
  const entero = Math.round(valor || 0).toString();
  return '$' + entero.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

function formatoFechaLarga(fechaStr) {
  if (!fechaStr) return '';
  const [y, m, d] = fechaStr.split('-').map(Number);
  return `${d} ${MESES[m - 1]} ${y}`;
}

function formatoFechaHora(isoStr) {
  if (!isoStr) return '';
  const d = new Date(isoStr);
  return `${String(d.getDate()).padStart(2, '0')} ${MESES[d.getMonth()]} ${d.getFullYear()}, ${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
}

function etiquetaEstado(estado) {
  const mapa = { confirmada: 'Confirmada', en_proceso: 'En proceso', atendida: 'Atendida', cancelada: 'Cancelada' };
  return mapa[estado] || estado;
}

function etiquetaCategoria(categoria) {
  const mapa = { facial: 'Facial', corporal: 'Corporal', capilar: 'Capilar' };
  return mapa[categoria] || categoria;
}

function escapeHtml(str) {
  if (!str) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

function evaluarPassword(pw) {
  return {
    longitud: pw.length >= 8,
    numero: /\d/.test(pw),
    letra: /[a-zA-Z]/.test(pw)
  };
}

function passwordEsValida(pw) {
  const r = evaluarPassword(pw);
  return r.longitud && r.numero && r.letra;
}

async function requestAPI(endpoint, options = {}) {
  try {
    const res = await fetch(endpoint, {
      headers: { 'Content-Type': 'application/json', ...options.headers },
      ...options
    });
    return await res.json();
  } catch (error) {
    console.error(`Error en petición a ${endpoint}:`, error);
    return { success: false, exito: false, message: 'Error de conexión con el servidor.' };
  }
}

/* ==========================================================
   2. NAVEGACIÓN Y MODALES
   ========================================================== */
function initNavToggle() {
  const toggle = document.querySelector('.nav__toggle') || document.getElementById('nav-toggle');
  const links = document.querySelector('.nav__links') || document.getElementById('nav-links');
  if (!toggle || !links) return;

  toggle.addEventListener('click', () => {
    const abierto = links.classList.toggle('is-open');
    toggle.setAttribute('aria-expanded', String(abierto));
  });

  links.querySelectorAll('a').forEach(link => {
    link.addEventListener('click', () => {
      toggle.setAttribute('aria-expanded', 'false');
      links.classList.remove('is-open');
    });
  });
}

function initModals() {
  const overlay = document.getElementById('modal-overlay');
  if (!overlay) return;

  const closeBtns = overlay.querySelectorAll('.modal__close, [data-cerrar-modal]');
  closeBtns.forEach(btn => btn.addEventListener('click', cerrarModal));

  overlay.addEventListener('click', (e) => {
    if (e.target === overlay) cerrarModal();
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') cerrarModal();
  });
}

function abrirModal(htmlContenido) {
  const overlay = document.getElementById('modal-overlay');
  const contenido = document.getElementById('modal-contenido');
  if (!overlay || !contenido) return;

  contenido.innerHTML = htmlContenido;
  overlay.classList.add('is-open');
}

function cerrarModal() {
  const overlay = document.getElementById('modal-overlay');
  if (overlay) overlay.classList.remove('is-open');
}

/* ==========================================================
   3. AUTENTICACIÓN
   ========================================================== */
function initLogin() {
  const form = document.getElementById('form-login');
  if (!form) return;

  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const correo = document.getElementById('login-correo')?.value.trim();
    const clave = document.getElementById('login-clave')?.value;
    const alerta = document.getElementById('login-alerta');

    if (!correo || !clave) {
      if (alerta) {
        alerta.className = 'alert alert-error';
        alerta.textContent = 'Por favor completa todos los campos.';
        alerta.hidden = false;
        alerta.style.display = 'block';
      }
      return;
    }

    const formData = new FormData();
    formData.append('correo', correo);
    formData.append('contrasena', clave);

    try {
      const response = await fetch('login.php', {
        method: 'POST',
        body: formData
      });
      const respuesta = await response.json();

      if (respuesta.status !== 'success' && !respuesta.success) {
        if (alerta) {
          alerta.className = 'alert alert-error';
          alerta.textContent = respuesta.message || 'Credenciales incorrectas.';
          alerta.hidden = false;
          alerta.style.display = 'block';
        }
        return;
      }

      window.location.href = respuesta.redirect || 'miPanel.php';
    } catch (error) {
      console.error('Error al iniciar sesión:', error);
      if (alerta) {
        alerta.className = 'alert alert-error';
        alerta.textContent = 'Error de conexión con el servidor.';
        alerta.hidden = false;
        alerta.style.display = 'block';
      }
    }
  });
}

function initRegistro() {
  const form = document.getElementById('form-registro');
  if (!form) return;

  const claveInput = document.getElementById('registro-clave');
  const reglas = document.getElementById('registro-reglas');

  claveInput?.addEventListener('input', () => {
    if (!reglas) return;
    const r = evaluarPassword(claveInput.value);
    reglas.querySelector('[data-regla="longitud"]')?.classList.toggle('is-ok', r.longitud);
    reglas.querySelector('[data-regla="letra"]')?.classList.toggle('is-ok', r.letra);
    reglas.querySelector('[data-regla="numero"]')?.classList.toggle('is-ok', r.numero);
  });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const nombre = document.getElementById('registro-nombre')?.value.trim();
    const correo = document.getElementById('registro-correo')?.value.trim();
    const telefono = document.getElementById('registro-telefono')?.value.trim();
    const clave = claveInput ? claveInput.value : '';
    const alerta = document.getElementById('registro-alerta');

    if (!passwordEsValida(clave)) {
      if (alerta) {
        alerta.className = 'alert alert-error';
        alerta.textContent = 'La contraseña no cumple con los requisitos mínimos.';
        alerta.hidden = false;
        alerta.style.display = 'block';
      }
      return;
    }

    const formData = new FormData();
    formData.append('nombre', nombre);
    formData.append('correo', correo);
    formData.append('telefono', telefono);
    formData.append('contrasena', clave);

    try {
      const response = await fetch('registro.php', {
        method: 'POST',
        body: formData
      });
      const respuesta = await response.json();

      if (respuesta.status !== 'success' && !respuesta.success) {
        if (alerta) {
          alerta.className = 'alert alert-error';
          alerta.textContent = respuesta.message || 'No se pudo completar el registro.';
          alerta.hidden = false;
          alerta.style.display = 'block';
        }
        return;
      }

      form.hidden = true;
      form.style.display = 'none';
      if (alerta) alerta.hidden = true;
      
      const exitoBox = document.getElementById('registro-exito');
      if (exitoBox) {
        exitoBox.hidden = false;
        exitoBox.style.display = 'block';
      }
    } catch (error) {
      console.error('Error durante el registro:', error);
      if (alerta) {
        alerta.className = 'alert alert-error';
        alerta.textContent = 'Error de conexión con el servidor.';
        alerta.hidden = false;
        alerta.style.display = 'block';
      }
    }
  });
}

function initLogout() {
  const botonesLogout = document.querySelectorAll('#btn-logout, #btn-logout-admin, [data-action="logout"]');
  if (!botonesLogout.length) return;

  botonesLogout.forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      localStorage.clear();
      sessionStorage.clear();
      window.location.href = 'index.php';
    });
  });
}

/* ==========================================================
   4. CATÁLOGO Y CARRUSEL
   ========================================================== */
async function renderCatalogoHome() {
  const grid = document.getElementById('catalogo-grid');
  if (!grid) return;

  const respuesta = await requestAPI('servicios.php?action=listar_activos');
  const servicios = respuesta.data || respuesta.servicios || (Array.isArray(respuesta) ? respuesta : []);

  if (!servicios.length) {
    grid.innerHTML = '<p class="empty-state">No hay servicios disponibles en este momento.</p>';
    return;
  }

  grid.innerHTML = servicios.map(s => {
    const id = s.id_servicio || s.id;
    const nombre = s.nombre || s.nombre_servicio || 'Servicio';
    const desc = s.descripcion || '';
    const cat = s.categoria || 'facial';
    const dur = s.duracion_minutos !== undefined ? s.duracion_minutos : (s.duracion || 30);
    const precio = s.precio_actual !== undefined ? s.precio_actual : (s.precio || 0);
    const esp = s.especialista || s.empleado_nombre || 'Por asignar';

    return `
      <article class="service-card" data-categoria="${escapeHtml(cat)}">
        <div class="service-card__media">
          <img src="${escapeHtml(s.imagen) || 'https://picsum.photos/seed/' + id + '/480/360'}" alt="${escapeHtml(nombre)}">
          <span class="service-card__categoria">${etiquetaCategoria(cat)}</span>
        </div>
        <div class="service-card__body">
          <h3>${escapeHtml(nombre)}</h3>
          <p class="service-card__desc">${escapeHtml(desc)}</p>
          <p class="service-card__meta">${dur} min &middot; Especialista: ${escapeHtml(esp)}</p>
          <div class="service-card__footer">
            <span class="service-card__price">${formatoMoneda(precio)}</span>
            <a href="reserva.php?servicio=${id}" class="btn btn-outline btn-sm">Reservar</a>
          </div>
        </div>
      </article>
    `;
  }).join('');

  initCatalogFilters();
}

function initCatalogFilters() {
  const filterBtns = document.querySelectorAll('.catalog-filters .tabs__btn');
  const serviceCards = document.querySelectorAll('.service-card');
  if (!filterBtns.length) return;

  filterBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      filterBtns.forEach(b => b.classList.toggle('is-active', b === btn));
      const cat = btn.dataset.filter || btn.dataset.categoria;

      serviceCards.forEach(card => {
        const categoriaCard = card.dataset.categoria;
        // "multiple" aplica a facial y corporal, asi que debe verse en
        // esos dos filtros, no solo en "Todos" (si no, un servicio asi
        // queda practicamente invisible para quien filtra por categoria).
        const matches = cat === 'todos'
          || categoriaCard === cat
          || (categoriaCard === 'multiple' && (cat === 'facial' || cat === 'corporal'));
        card.classList.toggle('is-hidden', !matches);
        card.style.display = matches ? 'flex' : 'none';
      });
    });
  });
}

function initHeroCarousel() {
  const cont = document.getElementById('hero-carousel') || document.querySelector('.hero__media');
  if (!cont) return;

  const slides = Array.from(cont.querySelectorAll('.hero__slide'));
  const dots = Array.from(cont.querySelectorAll('.hero__dot'));
  if (slides.length < 2) return;

  let indiceActual = 0;
  function mostrarSlide(indice) {
    indiceActual = indice;
    slides.forEach((s, i) => s.classList.toggle('is-active', i === indice));
    dots.forEach((d, i) => d.classList.toggle('is-active', i === indice));
  }

  dots.forEach((d, i) => d.addEventListener('click', () => mostrarSlide(i)));

  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

  let temporizador = setInterval(() => mostrarSlide((indiceActual + 1) % slides.length), 5000);
  cont.addEventListener('mouseenter', () => clearInterval(temporizador));
  cont.addEventListener('mouseleave', () => {
    temporizador = setInterval(() => mostrarSlide((indiceActual + 1) % slides.length), 5000);
  });
}

/* ==========================================================
   5. FLUJO DE RESERVA DE CITAS
   ========================================================== */
async function initReserva() {
  const gate = document.getElementById('gate-reserva');
  const flujo = document.getElementById('flujo-reserva');
  if (!gate && !flujo) return;

  if (flujo) {
    if (gate) {
      gate.hidden = true;
      gate.style.display = 'none';
    }
    flujo.hidden = false;
    flujo.style.display = 'block';

    await cargarServiciosReserva();
    initStepperNavigation();

    const selectEspecialista = document.getElementById('id_especialista');
    if (selectEspecialista) {
      selectEspecialista.addEventListener('change', () => {
        const inputFecha = document.getElementById('input-fecha');
        if (inputFecha && inputFecha.value) {
          cargarSlotsHorarios();
        }
      });
    }
  }
}

async function cargarServiciosReserva() {
  const container = document.getElementById('booking-services');
  if (!container) return;

  const urlParams = new URLSearchParams(window.location.search);
  const preselectedId = urlParams.get('servicio');

  const existingOptions = container.querySelectorAll('.service-option input[name="servicio_id"]');
  if (existingOptions.length > 0) {
    estadoReserva.servicios = Array.from(existingOptions).map(input => {
      const parent = input.closest('.service-option');
      const pNum = parseFloat(parent.dataset.precio || 0);
      const dNum = parseInt(parent.dataset.duracion || 30, 10);
      const nom = parent.dataset.nombre || parent.querySelector('strong')?.textContent?.trim() || 'Servicio';

      return {
        id_servicio: input.value,
        id: input.value,
        nombre: nom,
        nombre_servicio: nom,
        categoria: parent.dataset.categoria || 'facial',
        precio_actual: pNum,
        precio: pNum,
        duracion_minutos: dNum,
        duracion: dNum,
        especialista: parent.dataset.especialista || parent.querySelector('.specialist')?.textContent?.replace('Especialista:', '')?.trim() || 'Por asignar'
      };
    });

    existingOptions.forEach(radio => {
      radio.addEventListener('change', (e) => {
        const selectedId = e.target.value;
        estadoReserva.servicioSeleccionado = estadoReserva.servicios.find(s => String(s.id_servicio || s.id) === String(selectedId));
        if (estadoReserva.fecha) cargarSlotsHorarios();
      });
    });

    const checkedInput = container.querySelector('input[name="servicio_id"]:checked') || existingOptions[0];
    if (checkedInput) {
      checkedInput.checked = true;
      const selectedId = checkedInput.value;
      estadoReserva.servicioSeleccionado = estadoReserva.servicios.find(s => String(s.id_servicio || s.id) === String(selectedId));
    }
    return;
  }

  container.innerHTML = '<p class="booking__hint">Cargando servicios disponibles desde la base de datos...</p>';

  let res = await requestAPI('servicios.php?action=listar_activos');
  let servicios = Array.isArray(res) ? res : (res.data || res.servicios || []);

  if (!servicios.length) {
    res = await requestAPI('servicios.php');
    servicios = Array.isArray(res) ? res : (res.data || res.servicios || []);
  }

  if (!servicios.length) {
    res = await requestAPI('reserva.php?action=listar_servicios');
    servicios = Array.isArray(res) ? res : (res.data || res.servicios || []);
  }

  estadoReserva.servicios = servicios;

  if (!servicios.length) {
    container.innerHTML = '<p class="booking__hint">No hay servicios activos disponibles en este momento.</p>';
    return;
  }

  container.innerHTML = servicios.map(s => {
    const id = s.id_servicio || s.id;
    const nombre = s.nombre || s.nombre_servicio || 'Servicio';
    const cat = s.categoria || 'facial';
    const precio = s.precio_actual !== undefined ? s.precio_actual : (s.precio || 0);
    const duracion = s.duracion_minutos !== undefined ? s.duracion_minutos : (s.duracion || 30);
    const especialista = s.especialista || s.empleado_nombre || 'Por asignar';
    const isChecked = String(id) === String(preselectedId) ? 'checked' : '';

    return `
      <div class="service-option" data-categoria="${escapeHtml(cat)}" data-nombre="${escapeHtml(nombre)}" data-precio="${precio}" data-duracion="${duracion}" data-especialista="${escapeHtml(especialista)}">
        <input type="radio" id="srv_${id}" name="servicio_id" value="${id}" ${isChecked}>
        <label for="srv_${id}">
          <span class="eyebrow">${etiquetaCategoria(cat)}</span>
          <strong>${escapeHtml(nombre)}</strong>
          <span class="price">${formatoMoneda(precio)} &middot; ${duracion} min</span>
          <span class="specialist">Especialista: ${escapeHtml(especialista)}</span>
        </label>
      </div>
    `;
  }).join('');

  const radioButtons = container.querySelectorAll('input[name="servicio_id"]');
  if (radioButtons.length && !container.querySelector('input[name="servicio_id"]:checked')) {
    radioButtons[0].checked = true;
  }

  radioButtons.forEach(radio => {
    radio.addEventListener('change', (e) => {
      const selectedId = e.target.value;
      estadoReserva.servicioSeleccionado = estadoReserva.servicios.find(s => String(s.id_servicio || s.id) === String(selectedId));
      if (estadoReserva.fecha) {
        cargarSlotsHorarios();
      }
    });
  });

  const checkedInput = container.querySelector('input[name="servicio_id"]:checked');
  if (checkedInput) {
    const selectedId = checkedInput.value;
    estadoReserva.servicioSeleccionado = estadoReserva.servicios.find(s => String(s.id_servicio || s.id) === String(selectedId));
  }
}

async function cargarSlotsHorarios() {
  const container = document.getElementById('slots-container');
  const inputFecha = document.getElementById('input-fecha');
  const selectEspecialista = document.getElementById('id_especialista');
  if (!container || !inputFecha) return;

  const fecha = inputFecha.value;
  const idEspecialista = selectEspecialista ? selectEspecialista.value : '';
  const servicio = estadoReserva.servicioSeleccionado;
  const servicioId = servicio ? (servicio.id_servicio || servicio.id) : null;

  if (!fecha) {
    container.innerHTML = '<p class="booking__hint">Elige una fecha para ver los horarios disponibles.</p>';
    return;
  }

  container.innerHTML = '<p class="booking__hint">Consultando horarios de atención...</p>';

  let res = await requestAPI(`horarios.php?fecha=${fecha}&id_especialista=${idEspecialista}&servicio_id=${servicioId || ''}`);

  let slots = [];
  if (res && Array.isArray(res.slots)) {
    slots = res.slots;
  } else if (Array.isArray(res)) {
    slots = res;
  } else {
    slots = [
      { hora: '09:00', ocupado: false },
      { hora: '09:30', ocupado: false },
      { hora: '10:00', ocupado: false },
      { hora: '10:30', ocupado: false },
      { hora: '11:00', ocupado: false },
      { hora: '11:30', ocupado: false },
      { hora: '12:00', ocupado: false },
      { hora: '14:00', ocupado: false },
      { hora: '14:30', ocupado: false },
      { hora: '15:00', ocupado: false },
      { hora: '15:30', ocupado: false },
      { hora: '16:00', ocupado: false },
      { hora: '16:30', ocupado: false },
      { hora: '17:00', ocupado: false },
      { hora: '17:30', ocupado: false },
      { hora: '18:00', ocupado: false }
    ];
  }

  container.innerHTML = `
    <div class="slots-grid" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 0.75rem; margin-top: 1rem;">
      ${slots.map(s => {
        const hora = typeof s === 'string' ? s : (s.hora || s.hora_inicio);
        const ocupado = typeof s === 'object' ? (s.ocupado || s.disponible === false) : false;
        
        return `
          <label class="slot-btn ${ocupado ? 'is-ocupado' : ''}" style="display:flex; align-items:center; justify-content:center; padding:0.6rem; border:1px solid #ddd; border-radius:6px; cursor:${ocupado ? 'not-allowed' : 'pointer'}; opacity:${ocupado ? '0.4' : '1'}; background:${ocupado ? '#f8f9fa' : '#fff'};">
            <input type="radio" name="hora" value="${escapeHtml(hora)}" ${ocupado ? 'disabled' : ''} style="margin-right:6px;">
            <span>${escapeHtml(hora)}</span>
          </label>
        `;
      }).join('')}
    </div>
  `;

  container.querySelectorAll('.slot-btn input').forEach(input => {
    input.addEventListener('change', () => {
      container.querySelectorAll('.slot-btn').forEach(btn => btn.classList.remove('is-active'));
      if (input.checked) {
        input.parentElement.classList.add('is-active');
        estadoReserva.hora = input.value;
      }
    });
  });
}

function initStepperNavigation() {
  const paneles = document.querySelectorAll('.booking__panel');
  const pasosHeader = document.querySelectorAll('.stepper__step');
  const inputFecha = document.getElementById('input-fecha');

  function irAlPaso(numPaso) {
    estadoReserva.paso = numPaso;

    paneles.forEach(p => {
      const activo = (parseInt(p.dataset.panel) === numPaso);
      p.hidden = !activo;
      p.style.display = activo ? 'block' : 'none';
      p.classList.toggle('is-active', activo);
    });

    pasosHeader.forEach(s => {
      const p = parseInt(s.dataset.step);
      s.classList.toggle('is-active', p === numPaso);
      s.classList.toggle('is-done', p < numPaso);
    });
  }

  irAlPaso(1);

  if (inputFecha) {
    inputFecha.min = new Date().toISOString().slice(0, 10);
    inputFecha.addEventListener('change', () => {
      estadoReserva.fecha = inputFecha.value;
      cargarSlotsHorarios();
    });
  }

  document.querySelectorAll('[data-next]').forEach(btn => {
    btn.addEventListener('click', () => {
      if (estadoReserva.paso === 1) {
        const checked = document.querySelector('input[name="servicio_id"]:checked');
        if (!checked) {
          alert('Por favor selecciona un servicio para continuar.');
          return;
        }
        const sId = checked.value;
        estadoReserva.servicioSeleccionado = estadoReserva.servicios.find(s => String(s.id_servicio || s.id) === String(sId));
      } 
      else if (estadoReserva.paso === 2) {
        const fecha = inputFecha ? inputFecha.value : '';
        const horaChecked = document.querySelector('input[name="hora"]:checked');

        if (!fecha || !horaChecked) {
          alert('Por favor selecciona la fecha y un horario disponible.');
          return;
        }
        estadoReserva.fecha = fecha;
        estadoReserva.hora = horaChecked.value;

        construirResumenConfirmacion();
      }

      irAlPaso(estadoReserva.paso + 1);
    });
  });

  document.querySelectorAll('[data-prev]').forEach(btn => {
    btn.addEventListener('click', () => {
      if (estadoReserva.paso > 1) {
        irAlPaso(estadoReserva.paso - 1);
      }
    });
  });

  const form = document.getElementById('form-reserva');
  form?.addEventListener('submit', async (e) => {
    e.preventDefault();

    if (!estadoReserva.servicioSeleccionado || !estadoReserva.fecha || !estadoReserva.hora) {
      alert('Por favor completa todos los pasos del formulario.');
      return;
    }

    const formData = new FormData(form);
    const sId = estadoReserva.servicioSeleccionado.id_servicio || estadoReserva.servicioSeleccionado.id;
    
    formData.append('servicio_id', sId);
    formData.append('id_servicio', sId);
    formData.append('fecha', estadoReserva.fecha);
    formData.append('hora', estadoReserva.hora);

    try {
      const response = await fetch(form.action || 'procesarReserva.php', {
        method: 'POST',
        body: formData
      });
      const respuesta = await response.json();

      if (respuesta.success || respuesta.exito || respuesta.status === 'success') {
        const flujo = document.getElementById('flujo-reserva');
        if (flujo) {
          flujo.innerHTML = `
            <div class="alert alert-success" style="padding: 2.5rem; text-align: center; background: #f4fbf7; border: 1px solid #d1e7dd; border-radius: 8px;">
              <h2 style="color: #0f5132; margin-bottom: 0.5rem;">¡Cita reservada con éxito!</h2>
              <p style="color: #0f5132;">${respuesta.message || respuesta.mensaje || 'Tu reserva ha sido registrada correctamente.'}</p>
              <div style="margin-top: 1.5rem;">
                <a href="miPanel.php" class="btn">Ir a mi panel de citas</a>
              </div>
            </div>
          `;
        }
      } else {
        alert(respuesta.message || respuesta.error || respuesta.mensaje || 'No se pudo completar la reserva.');
      }
    } catch (error) {
      console.error('Error al procesar reserva:', error);
      alert('Ocurrió un error de conexión al enviar la reserva.');
    }
  });
}

function construirResumenConfirmacion() {
  const summary = document.getElementById('booking-summary');
  if (!summary) return;

  const s = estadoReserva.servicioSeleccionado;
  const sNombre = s ? (s.nombre || s.nombre_servicio || 'Servicio') : '';
  const sDuracion = s ? (s.duracion_minutos !== undefined ? s.duracion_minutos : (s.duracion || 30)) : 30;
  const sPrecio = s ? (s.precio_actual !== undefined ? s.precio_actual : (s.precio || 0)) : 0;
  const selectEsp = document.getElementById('id_especialista');
  const nombreEsp = selectEsp && selectEsp.selectedIndex > 0 ? selectEsp.options[selectEsp.selectedIndex].text : 'Por asignar';

  summary.innerHTML = `
    <div style="margin-bottom:0.75rem;"><dt><strong>Servicio:</strong></dt><dd>${escapeHtml(sNombre)}</dd></div>
    <div style="margin-bottom:0.75rem;"><dt><strong>Especialista:</strong></dt><dd>${escapeHtml(nombreEsp)}</dd></div>
    <div style="margin-bottom:0.75rem;"><dt><strong>Duración estimada:</strong></dt><dd>${sDuracion} minutos</dd></div>
    <div style="margin-bottom:0.75rem;"><dt><strong>Precio total:</strong></dt><dd>${formatoMoneda(sPrecio)}</dd></div>
    <div style="margin-bottom:0.75rem;"><dt><strong>Fecha programada:</strong></dt><dd>${formatoFechaLarga(estadoReserva.fecha)}</dd></div>
    <div style="margin-bottom:0.75rem;"><dt><strong>Hora de atención:</strong></dt><dd>${escapeHtml(estadoReserva.hora)}</dd></div>
  `;
}

/* ==========================================================
   6. PANEL DEL CLIENTE (Perfil + Subpestañas Próximas/Historial)
   ========================================================== */
function initPanelCliente() {
  const tabsContainer = document.getElementById('panel-vista-tabs');
  if (!tabsContainer) return;

  const tabBtns = tabsContainer.querySelectorAll('.tabs__btn');
  const vistas = document.querySelectorAll('.panel-vista');

  tabBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      const vistaTarget = btn.dataset.vista;

      tabBtns.forEach(b => b.classList.toggle('is-active', b === btn));
      vistas.forEach(v => {
        const esTarget = (v.dataset.vista === vistaTarget);
        v.hidden = !esTarget;
        v.style.display = esTarget ? 'block' : 'none';
      });
    });
  });

  const btnEditar = document.getElementById('btn-editar-perfil');
  const btnCancelar = document.getElementById('btn-cancelar-edicion');
  const vistaInfo = document.getElementById('perfil-vista-info');
  const formContenedor = document.getElementById('perfil-form-contenedor');

  if (btnEditar && btnCancelar && vistaInfo && formContenedor) {
    btnEditar.addEventListener('click', () => {
      vistaInfo.style.display = 'none';
      formContenedor.style.display = 'block';
    });

    btnCancelar.addEventListener('click', () => {
      formContenedor.style.display = 'none';
      vistaInfo.style.display = 'block';
    });
  }

  // Lógica actualizada para las subpestañas de Próximas e Historial sincronizadas con PHP
  const subtabsContainer = document.getElementById('citas-subtabs');
  const divProximas = document.getElementById('subtab-proximas');
  const divHistorial = document.getElementById('subtab-historial');

  if (subtabsContainer && divProximas && divHistorial) {
    const subtabBtns = subtabsContainer.querySelectorAll('.tabs__btn');
    subtabBtns.forEach(btn => {
      btn.addEventListener('click', () => {
        subtabBtns.forEach(b => b.classList.remove('is-active'));
        btn.classList.add('is-active');

        const filtro = btn.dataset.filtro;
        if (filtro === 'proximas') {
          divProximas.style.display = 'block';
          divHistorial.style.display = 'none';
        } else {
          divProximas.style.display = 'none';
          divHistorial.style.display = 'block';
        }
      });
    });
  }
}

async function cancelarCitaCliente(reservaId) {
  if (!confirm('¿Seguro que deseas cancelar esta cita?')) return;

  const formData = new FormData();
  formData.append('action', 'cancelar');
  formData.append('id_cita', reservaId);
  formData.append('cita_id', reservaId);

  try {
    const res = await fetch('gestion_cita.php', {
      method: 'POST',
      body: formData
    });
    const respuesta = await res.json();

    if (respuesta.exito || respuesta.success) {
      window.location.reload(); // Recarga limpia para actualizar la vista estática de PHP
    } else {
      alert(respuesta.mensaje || respuesta.error || 'No fue posible cancelar la cita.');
    }
  } catch (e) {
    console.error('Error al cancelar cita:', e);
    alert('Error de conexión al cancelar la cita.');
  }
}

function abrirModalReprogramar(reservaId, idEspecialista, idServicio) {
  document.getElementById('modal-titulo').textContent = 'Reprogramar cita';
  const hoy = new Date().toISOString().slice(0, 10);

  abrirModal(`
    <label class="field"><span>Nueva fecha</span><input type="date" id="reprog-fecha" min="${hoy}"></label>
    <div id="reprog-slots"><p class="booking__hint">Elige una fecha para ver los horarios disponibles.</p></div>
    <div class="alert alert-error" id="reprog-error" hidden></div>
    <div style="text-align:right; margin-top:1rem;">
      <button type="button" class="btn" id="reprog-confirmar">Confirmar cambio</button>
    </div>
  `);

  const inputFecha = document.getElementById('reprog-fecha');
  inputFecha.addEventListener('change', async () => {
    const cont = document.getElementById('reprog-slots');
    cont.innerHTML = '<p class="booking__hint">Consultando horarios...</p>';

    const res = await requestAPI(`horarios.php?fecha=${inputFecha.value}&id_especialista=${idEspecialista}&servicio_id=${idServicio || ''}`);
    const slots = (res && Array.isArray(res.slots)) ? res.slots : [];

    if (!slots.length) {
      cont.innerHTML = `<p class="booking__hint">${res.message || 'No hay horarios disponibles ese día.'}</p>`;
      return;
    }

    cont.innerHTML = '<div class="slots-grid" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 0.75rem; margin-top: 0.5rem;">' + slots.map(s => `
      <label class="slot-btn ${s.ocupado ? 'is-ocupado' : ''}" style="display:flex; align-items:center; justify-content:center; padding:0.6rem; border:1px solid #ddd; border-radius:6px; cursor:${s.ocupado ? 'not-allowed' : 'pointer'}; opacity:${s.ocupado ? '0.4' : '1'}; background:${s.ocupado ? '#f8f9fa' : '#fff'};">
        <input type="radio" name="reprog-hora" value="${s.hora}" ${s.ocupado ? 'disabled' : ''} style="margin-right:6px;">
        ${s.hora}
      </label>`).join('') + '</div>';
  });

  document.getElementById('reprog-confirmar').addEventListener('click', async () => {
    const fecha = inputFecha.value;
    const horaInput = document.querySelector('input[name="reprog-hora"]:checked');
    const errorEl = document.getElementById('reprog-error');

    if (!fecha || !horaInput) {
      errorEl.textContent = 'Elige una fecha y un horario.';
      errorEl.hidden = false;
      return;
    }

    const formData = new FormData();
    formData.append('action', 'reprogramar');
    formData.append('id_cita', reservaId);
    formData.append('fecha', fecha);
    formData.append('hora', horaInput.value);

    const res = await fetch('gestion_cita.php', { method: 'POST', body: formData });
    const respuesta = await res.json();

    if (respuesta.success) {
      window.location.reload();
    } else {
      errorEl.textContent = respuesta.mensaje || 'No se pudo reprogramar la cita.';
      errorEl.hidden = false;
    }
  });
}

function abrirModalCalificar(reservaId) {
  document.getElementById('modal-titulo').textContent = 'Calificar servicio';
  let puntuacionElegida = 0;

  abrirModal(`
    <div id="calif-estrellas" style="font-size:2rem; letter-spacing:0.2rem; margin-bottom:1rem;">
      ${[1, 2, 3, 4, 5].map(n => `<button type="button" data-estrella="${n}" style="background:none; border:none; cursor:pointer; color:#ccc;">☆</button>`).join('')}
    </div>
    <label class="field"><span>Comentario (opcional)</span><textarea id="calif-comentario" rows="3"></textarea></label>
    <div style="text-align:right; margin-top:1rem;">
      <button type="button" class="btn" id="calif-enviar">Enviar calificación</button>
    </div>
  `);

  const botones = document.querySelectorAll('#calif-estrellas button');
  botones.forEach(btn => {
    btn.addEventListener('click', () => {
      puntuacionElegida = Number(btn.dataset.estrella);
      botones.forEach(b => {
        const activa = Number(b.dataset.estrella) <= puntuacionElegida;
        b.textContent = activa ? '★' : '☆';
        b.style.color = activa ? '#d4a017' : '#ccc';
      });
    });
  });

  document.getElementById('calif-enviar').addEventListener('click', async () => {
    if (!puntuacionElegida) {
      alert('Elige al menos una estrella.');
      return;
    }

    const formData = new FormData();
    formData.append('action', 'calificar');
    formData.append('id_cita', reservaId);
    formData.append('puntuacion', puntuacionElegida);
    formData.append('comentario', document.getElementById('calif-comentario').value.trim());

    const res = await fetch('gestion_cita.php', { method: 'POST', body: formData });
    const respuesta = await res.json();

    if (respuesta.success) {
      window.location.reload();
    } else {
      alert(respuesta.mensaje || 'No se pudo enviar la calificación.');
    }
  });
}

/* ==========================================================
   7. DASHBOARD ADMINISTRATIVO
   ========================================================== */
async function initDashboardAdmin() {
  const adminTabsContainer = document.getElementById('admin-tabs');
  if (!adminTabsContainer) return;

  const tabBotones = adminTabsContainer.querySelectorAll('.tabs__btn');
  const paneles = document.querySelectorAll('.admin-panel');

  tabBotones.forEach(boton => {
    boton.addEventListener('click', () => {
      const panelTarget = boton.dataset.panel;
      tabBotones.forEach(b => b.classList.toggle('is-active', b === boton));
      paneles.forEach(p => p.classList.toggle('is-active', p.id === panelTarget || p.dataset.panel === panelTarget));

      if (panelTarget === 'reservas') cargarReservasAdmin();
      if (panelTarget === 'servicios') cargarServiciosAdmin();
      if (panelTarget === 'clientes') cargarClientesAdmin();
      if (panelTarget === 'auditoria') cargarAuditoriaAdmin();
      if (panelTarget === 'configuracion') cargarConfiguracionAdmin();
    });
  });

  ['filtro-cliente', 'filtro-estado', 'filtro-fecha'].forEach(id => {
    const el = document.getElementById(id);
    if (el) {
      el.addEventListener('input', filtrarYRenderizarReservasAdmin);
      el.addEventListener('change', filtrarYRenderizarReservasAdmin);
    }
  });

  const btnNuevoServicio = document.getElementById('btn-nuevo-servicio');
  if (btnNuevoServicio) btnNuevoServicio.addEventListener('click', () => abrirModalServicio(null));

  const btnGuardarHorario = document.getElementById('btn-guardar-horario');
  if (btnGuardarHorario) btnGuardarHorario.addEventListener('click', guardarHorarioAdmin);

  const btnExportar = document.getElementById('btn-exportar');
  if (btnExportar) {
    btnExportar.addEventListener('click', () => {
      const desde = document.getElementById('export-desde')?.value || '';
      const hasta = document.getElementById('export-hasta')?.value || '';
      // Navegacion normal (no fetch): asi el navegador dispara la descarga del archivo.
      window.location.href = `admin.php?action=exportar_reservas&desde=${desde}&hasta=${hasta}`;
    });
  }

  cargarReservasAdmin();
}

async function cargarReservasAdmin() {
  const tbody = document.getElementById('tabla-reservas-body');
  if (!tbody) return;

  const respuesta = await requestAPI('admin.php?action=listar_reservas');
  reservasAdminData = respuesta.data || [];

  actualizarKPIsAdmin(reservasAdminData);
  filtrarYRenderizarReservasAdmin();
}

function filtrarYRenderizarReservasAdmin() {
  const tbody = document.getElementById('tabla-reservas-body');
  const emptyMsg = document.getElementById('reservas-admin-empty');
  if (!tbody) return;

  const cliente = (document.getElementById('filtro-cliente')?.value || '').toLowerCase();
  const estado = document.getElementById('filtro-estado')?.value || 'todos';
  const fecha = document.getElementById('filtro-fecha')?.value || '';

  const filtradas = reservasAdminData.filter(r => {
    const matchCliente = !cliente || (r.cliente_nombre && r.cliente_nombre.toLowerCase().includes(cliente));
    const matchEstado = estado === 'todos' || r.estado === estado;
    const matchFecha = !fecha || r.fecha === fecha;
    return matchCliente && matchEstado && matchFecha;
  });

  if (!filtradas.length) {
    tbody.innerHTML = '';
    if (emptyMsg) emptyMsg.hidden = false;
    return;
  }
  if (emptyMsg) emptyMsg.hidden = true;

  tbody.innerHTML = filtradas.map(r => {
    const permitidos = TRANSICIONES_ESTADO[r.estado] || [];
    const esTerminal = permitidos.length === 0;

    const opciones = ['confirmada', 'en_proceso', 'atendida', 'cancelada'].map(est => `
      <option value="${est}" ${est === r.estado ? 'selected' : ''} ${est !== r.estado && !permitidos.includes(est) ? 'disabled' : ''}>
        ${etiquetaEstado(est)}
      </option>`).join('');

    return `
      <tr>
        <td>${escapeHtml(r.cliente_nombre)}</td>
        <td>${escapeHtml(r.servicio_nombre || r.nombre)}</td>
        <td>${formatoFechaLarga(r.fecha)}</td>
        <td>${escapeHtml(r.hora)}</td>
        <td>
          <select class="estado-select" data-id="${r.id}" ${esTerminal ? 'disabled' : ''}>
            ${opciones}
          </select>
        </td>
      </tr>`;
  }).join('');

  tbody.querySelectorAll('.estado-select').forEach(select => {
    select.addEventListener('change', async () => {
      const id = select.dataset.id;
      const nuevoEstado = select.value;

      const res = await requestAPI('admin.php?action=cambiar_estado_reserva', {
        method: 'POST',
        body: JSON.stringify({ id, estado: nuevoEstado })
      });

      if (!res.success && !res.exito) {
        alert(res.message || 'No se pudo cambiar el estado.');
      }
      cargarReservasAdmin();
    });
  });
}

function actualizarKPIsAdmin(reservas) {
  const kpiHoy = document.getElementById('kpi-citas-hoy');
  const kpiPend = document.getElementById('kpi-pendientes');
  const kpiTop = document.getElementById('kpi-top-servicio');
  const kpiIng = document.getElementById('kpi-ingresos-hoy');

  if (!kpiHoy) return;

  const hoyStr = new Date().toISOString().slice(0, 10);
  const citasHoy = reservas.filter(r => r.fecha === hoyStr);
  const pendientes = reservas.filter(r => r.estado === 'confirmada');

  kpiHoy.textContent = citasHoy.length;
  if (kpiPend) kpiPend.textContent = pendientes.length;

  const conteoServicios = {};
  reservas.forEach(r => {
    const sNom = r.servicio_nombre || r.nombre;
    if (sNom) {
      conteoServicios[sNom] = (conteoServicios[sNom] || 0) + 1;
    }
  });

  let topServ = '-';
  let max = 0;
  for (const [serv, cant] of Object.entries(conteoServicios)) {
    if (cant > max) { max = cant; topServ = serv; }
  }
  if (kpiTop) kpiTop.textContent = topServ;

  let ingresos = 0;
  citasHoy.forEach(r => {
    if (r.estado !== 'cancelada') {
      ingresos += parseFloat(r.precio_actual || r.precio || 0);
    }
  });
  if (kpiIng) kpiIng.textContent = formatoMoneda(ingresos);
}

async function cargarServiciosAdmin() {
  const tbody = document.getElementById('tabla-servicios-body');
  if (!tbody) return;

  const res = await requestAPI('admin.php?action=listar_servicios');
  const servicios = res.data || [];
  window.serviciosAdminCache = servicios;

  if (!servicios.length) {
    tbody.innerHTML = '<tr><td colspan="7" class="text-center">No hay servicios registrados.</td></tr>';
    return;
  }

  tbody.innerHTML = servicios.map(s => {
    const id = s.id_servicio || s.id;
    const nombre = s.nombre || s.nombre_servicio || 'Servicio';
    const duracion = s.duracion_minutos !== undefined ? s.duracion_minutos : (s.duracion || 30);
    const precio = s.precio_actual !== undefined ? s.precio_actual : (s.precio || 0);

    return `
      <tr>
        <td>${escapeHtml(nombre)}</td>
        <td>${etiquetaCategoria(s.categoria)}</td>
        <td>${escapeHtml(s.especialista) || 'Sin asignar'}</td>
        <td>${duracion} min</td>
        <td>${formatoMoneda(precio)}</td>
        <td>
          <label class="switch">
            <input type="checkbox" data-toggle-servicio="${id}" ${s.activo ? 'checked' : ''}>
            <span class="switch__track"></span>
            ${s.activo ? 'Activo' : 'Inactivo'}
          </label>
        </td>
        <td><button type="button" class="btn btn-outline btn-sm" data-editar-servicio="${id}">Editar</button></td>
      </tr>
    `;
  }).join('');

  tbody.querySelectorAll('[data-toggle-servicio]').forEach(input => {
    input.addEventListener('change', async () => {
      const id = input.dataset.toggleServicio;
      const res = await requestAPI('admin.php?action=toggle_servicio', {
        method: 'POST',
        body: JSON.stringify({ id, activo: input.checked })
      });
      if (!res.success) alert(res.message || 'No se pudo actualizar el servicio.');
      cargarServiciosAdmin();
    });
  });

  tbody.querySelectorAll('[data-editar-servicio]').forEach(btn => {
    btn.addEventListener('click', () => abrirModalServicio(btn.dataset.editarServicio));
  });
}

function abrirModalServicio(id) {
  const servicio = id ? (window.serviciosAdminCache || []).find(s => String(s.id_servicio || s.id) === String(id)) : null;

  document.getElementById('modal-titulo').textContent = servicio ? 'Editar servicio' : 'Nuevo servicio';
  abrirModal(`
    <form id="form-servicio">
      <label class="field"><span>Nombre</span><input type="text" id="servicio-nombre" value="${servicio ? escapeHtml(servicio.nombre) : ''}" required></label>
      <label class="field"><span>Categoría</span>
        <select id="servicio-categoria">
          <option value="facial" ${servicio && servicio.categoria === 'facial' ? 'selected' : ''}>Facial</option>
          <option value="corporal" ${servicio && servicio.categoria === 'corporal' ? 'selected' : ''}>Corporal</option>
          <option value="capilar" ${servicio && servicio.categoria === 'capilar' ? 'selected' : ''}>Capilar</option>
          <option value="multiple" ${servicio && servicio.categoria === 'multiple' ? 'selected' : ''}>Multiple (facial y corporal)</option>
        </select>
      </label>
      <div style="display:flex; gap:1rem;">
        <label class="field" style="flex:1;"><span>Duración (min)</span><input type="number" id="servicio-duracion" min="15" step="5" value="${servicio ? servicio.duracion_minutos : 60}" required></label>
        <label class="field" style="flex:1;"><span>Precio</span><input type="number" id="servicio-precio" min="0" step="1000" value="${servicio ? servicio.precio_actual : 50000}" required></label>
      </div>
      <label class="field"><span>Descripción</span><textarea id="servicio-descripcion">${servicio ? escapeHtml(servicio.descripcion || '') : ''}</textarea></label>
      <div style="text-align:right;"><button type="submit" class="btn">Guardar</button></div>
    </form>
  `);

  document.getElementById('form-servicio').addEventListener('submit', async (e) => {
    e.preventDefault();
    const payload = {
      id: servicio ? (servicio.id_servicio || servicio.id) : null,
      nombre: document.getElementById('servicio-nombre').value.trim(),
      categoria: document.getElementById('servicio-categoria').value,
      duracion_minutos: document.getElementById('servicio-duracion').value,
      precio_actual: document.getElementById('servicio-precio').value,
      descripcion: document.getElementById('servicio-descripcion').value.trim()
    };

    const res = await requestAPI('admin.php?action=guardar_servicio', {
      method: 'POST',
      body: JSON.stringify(payload)
    });

    if (!res.success) {
      alert(res.message || 'No se pudo guardar el servicio.');
      return;
    }
    cerrarModal();
    cargarServiciosAdmin();
  });
}

async function cargarClientesAdmin() {
  const tbody = document.getElementById('tabla-clientes-body');
  if (!tbody) return;

  const res = await requestAPI('admin.php?action=listar_clientes');
  const clientes = res.data || [];

  if (!clientes.length) {
    tbody.innerHTML = '<tr><td colspan="4" class="text-center">No hay clientes registrados.</td></tr>';
    return;
  }

  tbody.innerHTML = clientes.map(c => `
    <tr>
      <td>${escapeHtml(c.nombre)}</td>
      <td>${escapeHtml(c.correo)}</td>
      <td>${escapeHtml(c.telefono) || '-'}</td>
      <td>
        <label class="switch">
          <input type="checkbox" data-toggle-cliente="${c.id}" ${c.activo ? 'checked' : ''}>
          <span class="switch__track"></span>
          ${c.activo ? 'Activo' : 'Inactivo'}
        </label>
      </td>
    </tr>
  `).join('');

  tbody.querySelectorAll('[data-toggle-cliente]').forEach(input => {
    input.addEventListener('change', async () => {
      const id = input.dataset.toggleCliente;
      const res = await requestAPI('admin.php?action=toggle_cliente', {
        method: 'POST',
        body: JSON.stringify({ id, activo: input.checked })
      });
      if (!res.success) alert(res.message || 'No se pudo actualizar el cliente.');
      cargarClientesAdmin();
    });
  });
}

async function cargarAuditoriaAdmin() {
  const tbody = document.getElementById('tabla-auditoria-body');
  if (!tbody) return;

  const res = await requestAPI('admin.php?action=listar_auditoria');
  const logs = res.data || [];

  if (!logs.length) {
    tbody.innerHTML = '<tr><td colspan="5" class="text-center">No hay registros de auditoría.</td></tr>';
    return;
  }

  tbody.innerHTML = logs.map(a => `
    <tr>
      <td>${formatoFechaHora(a.fecha)}</td>
      <td>${escapeHtml(a.tabla)}</td>
      <td>${escapeHtml(a.operacion)}</td>
      <td>${escapeHtml(a.usuario) || 'Sistema'}</td>
      <td>${escapeHtml(a.detalle)}</td>
    </tr>
  `).join('');
}

async function cargarConfiguracionAdmin() {
  const contenedor = document.getElementById('config-horario-lista');
  if (!contenedor) return;

  const res = await requestAPI('admin.php?action=obtener_horarios');
  const horarios = res.data || [];

  contenedor.innerHTML = DIAS_CONFIG.map(dia => {
    const h = horarios.find(item => item.dia === dia) || { dia, abierto: true, hora_inicio: '09:00', hora_fin: '19:00' };
    return `
      <div class="config-horario__dia" style="display:flex; align-items:center; gap:1rem; margin-bottom:0.75rem;">
        <strong style="width:100px;">${DIAS_LABEL[dia]}</strong>
        <label class="switch">
          <input type="checkbox" name="abierto_${dia}" ${h.abierto ? 'checked' : ''}>
          <span>Abierto</span>
        </label>
        <div class="field">
          <input type="time" name="inicio_${dia}" value="${h.hora_inicio}">
        </div>
        <div class="field">
          <input type="time" name="fin_${dia}" value="${h.hora_fin}">
        </div>
      </div>
    `;
  }).join('');
}

async function guardarHorarioAdmin() {
  const dias = DIAS_CONFIG.map(dia => ({
    dia,
    abierto: document.querySelector(`[name="abierto_${dia}"]`)?.checked ?? true,
    hora_inicio: document.querySelector(`[name="inicio_${dia}"]`)?.value || '09:00',
    hora_fin: document.querySelector(`[name="fin_${dia}"]`)?.value || '19:00'
  }));

  const res = await requestAPI('admin.php?action=guardar_horarios', {
    method: 'POST',
    body: JSON.stringify({ dias })
  });

  const alerta = document.getElementById('horario-alerta');
  if (res.success) {
    if (alerta) { alerta.hidden = false; setTimeout(() => alerta.hidden = true, 3000); }
  } else {
    alert(res.message || 'No se pudo guardar el horario.');
  }
}

/* ==========================================================
   8. INICIALIZACIÓN GLOBAL (DOM READY)
   ========================================================== */
document.addEventListener('DOMContentLoaded', () => {
  initNavToggle();
  initModals();
  initLogout();
  initCatalogFilters();
  initHeroCarousel();
  initLogin();
  initRegistro();
  initReserva();
  initPanelCliente();
  initDashboardAdmin();
});
