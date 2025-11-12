/**
 * JavaScript para Gestión de Disputas
 * Permite crear, ver y resolver disputas
 *
 * Usa componentes reutilizables:
 * - API Client centralizado
 * - Toast notifications
 * - Modal component
 * - DataTable component
 * - FormValidator component
 */

// Asegurar que los componentes estén cargados
if (
  typeof API === "undefined" ||
  typeof Toast === "undefined" ||
  typeof Modal === "undefined" ||
  typeof DataTable === "undefined" ||
  typeof FormValidator === "undefined"
) {
  console.error(
    "Componentes no cargados. Asegúrate de incluir components-loader.js"
  );
}

let dataTable = null;
let formValidator = null;

document.addEventListener("DOMContentLoaded", () => {
  // Inicializar componentes UI comunes (tooltips e iconos)
  if (typeof UIInitializer !== 'undefined') {
    UIInitializer.init();
  }
  
  initializeDataTable();
  setupEventListeners();
  initializeFormValidator();
});

function setupEventListeners() {
  // Botón crear disputa
  const btnCreateDispute = document.getElementById("btn-create-dispute");
  if (btnCreateDispute) {
    btnCreateDispute.addEventListener("click", () => {
      openCreateDisputeModal();
    });
  }

  // Botón confirmar crear disputa
  const btnConfirmCreate = document.getElementById("btn-confirm-create-dispute");
  if (btnConfirmCreate) {
    btnConfirmCreate.addEventListener("click", () => {
      createDispute();
    });
  }

  // Botón confirmar resolver disputa
  const btnConfirmResolve = document.getElementById("btn-confirm-resolve-dispute");
  if (btnConfirmResolve) {
    btnConfirmResolve.addEventListener("click", () => {
      resolveDispute();
    });
  }

  // Filtros
  const filterStatus = document.getElementById("filter-status");
  const filterFrom = document.getElementById("filter-from");
  const filterTo = document.getElementById("filter-to");
  const btnClearFilters = document.getElementById("btn-clear-filters");

  if (filterStatus) {
    filterStatus.addEventListener("change", () => {
      if (dataTable) {
        dataTable.currentPage = 1;
        dataTable.loadData();
      }
    });
  }

  if (filterFrom) {
    filterFrom.addEventListener("change", () => {
      if (dataTable) {
        dataTable.currentPage = 1;
        dataTable.loadData();
      }
    });
  }

  if (filterTo) {
    filterTo.addEventListener("change", () => {
      if (dataTable) {
        dataTable.currentPage = 1;
        dataTable.loadData();
      }
    });
  }

  if (btnClearFilters) {
    btnClearFilters.addEventListener("click", () => {
      if (filterStatus) filterStatus.value = "";
      if (filterFrom) filterFrom.value = "";
      if (filterTo) filterTo.value = "";
      if (dataTable) {
        dataTable.currentPage = 1;
        dataTable.loadData();
      }
    });
  }
}

function initializeFormValidator() {
  const form = document.getElementById("form-create-dispute");
  if (form) {
    formValidator = new FormValidator(form, {
      rules: {
        "dispute-reason": {
          required: true,
          minLength: 10,
        },
      },
      messages: {
        "dispute-reason": {
          required: "La razón es requerida",
          minLength: "La razón debe tener al menos 10 caracteres",
        },
      },
    });
  }
}

function initializeDataTable() {
  const container = document.getElementById("disputes-container");
  if (!container) return;

  dataTable = new DataTable(container, {
    columns: [
      {
        key: "id",
        label: "ID",
        sortable: true,
        render: (row) => `#${row.id}`,
      },
      {
        key: "work_log_id",
        label: "Work Log",
        sortable: true,
        render: (row) => {
          if (row.work_log_id) {
            return `<a href="#" onclick="viewWorkLog(${row.work_log_id}); return false;">#${row.work_log_id}</a>`;
          }
          return "-";
        },
      },
      {
        key: "reason",
        label: "Razón",
        sortable: true,
        render: (row) => {
          const reason = escapeHtml(row.reason || "");
          return reason.length > 50
            ? `${reason.substring(0, 50)}...`
            : reason;
        },
      },
      {
        key: "status",
        label: "Estado",
        sortable: true,
        render: (row) => {
          const status = row.status || "sin_disputa";
          const statusLabels = {
            sin_disputa: { label: "Sin disputa", class: "badge-modern badge-modern--secondary" },
            disputado: { label: "Disputado", class: "badge-modern badge-modern--warning" },
            resuelto: { label: "Resuelto", class: "badge-modern badge-modern--success" },
          };
          const statusInfo = statusLabels[status] || statusLabels.sin_disputa;
          return `<span class="${statusInfo.class}">${statusInfo.label}</span>`;
        },
      },
      {
        key: "raised_by_name",
        label: "Creada por",
        sortable: true,
        render: (row) => escapeHtml(row.raised_by_name || "-"),
      },
      {
        key: "created_at",
        label: "Fecha",
        sortable: true,
        render: (row) => {
          if (!row.created_at) return "-";
          const date = new Date(row.created_at);
          return date.toLocaleDateString("es-ES", {
            year: "numeric",
            month: "short",
            day: "numeric",
            hour: "2-digit",
            minute: "2-digit",
          });
        },
      },
      {
        key: "actions",
        label: "Acciones",
        sortable: false,
        render: (row) => {
          let actions = `
            <div class="btn-group btn-group-sm" role="group">
                <button class="btn-modern btn-modern--sm btn-modern--ghost" onclick="viewDisputeDetails(${row.id})" aria-label="Ver detalles">
                    <i class="bi bi-eye"></i>
                </button>
          `;

          if (row.status === "disputado") {
            actions += `
                <button class="btn-modern btn-modern--sm btn-modern--primary" onclick="openResolveDisputeModal(${row.id})" aria-label="Resolver">
                    <i class="bi bi-check-circle"></i>
                </button>
            `;
          }

          actions += `</div>`;
          return actions;
        },
      },
    ],
    fetchData: async (page, pageSize, sortBy, sortDir, filters) => {
      const params = {
        page: page,
        page_size: pageSize,
      };

      // Agregar filtros externos
      const filterStatus = document.getElementById("filter-status")?.value;
      if (filterStatus) params.status = filterStatus;

      const filterFrom = document.getElementById("filter-from")?.value;
      if (filterFrom) params.from = filterFrom;

      const filterTo = document.getElementById("filter-to")?.value;
      if (filterTo) params.to = filterTo;

      // Agregar ordenamiento
      if (sortBy) {
        params.sort_by = sortBy;
        params.sort_dir = sortDir || "ASC";
      }

      try {
        const response = await API.get("/disputes", params);

        if (response && response.success) {
          return {
            data: response.data || [],
            total: response.total || 0,
            page: response.page || page,
            total_pages: response.total_pages || 1,
          };
        } else {
          Toast.error(response?.message || "Error al cargar disputas");
          return { data: [], total: 0, page: 1, total_pages: 1 };
        }
      } catch (error) {
        console.error("Error loading disputes:", error);
        Toast.error("Error al cargar disputas");
        return { data: [], total: 0, page: 1, total_pages: 1 };
      }
    },
    pageSize: 20,
    searchable: false, // Los filtros están en la parte superior
    emptyMessage: "No hay disputas",
  });
}

function openCreateDisputeModal(workLogId = null) {
  const modal = new bootstrap.Modal(
    document.getElementById("modal-create-dispute")
  );
  const form = document.getElementById("form-create-dispute");

  // Limpiar formulario
  if (form) {
    form.reset();
    if (workLogId) {
      document.getElementById("dispute-work-log-id").value = workLogId;
      document.getElementById("work-log-id").value = workLogId;
    }
  }

  modal.show();
}

async function createDispute() {
  const form = document.getElementById("form-create-dispute");
  if (!form) return;

  // Validar formulario
  if (formValidator && !formValidator.validate()) {
    return;
  }

  const workLogId = document.getElementById("work-log-id")?.value;
  const reason = document.getElementById("dispute-reason")?.value;
  const comments = document.getElementById("dispute-comments")?.value;
  const photosText = document.getElementById("dispute-photos")?.value;

  if (!reason || reason.trim().length < 10) {
    Toast.error("La razón debe tener al menos 10 caracteres");
    return;
  }

  // Preparar evidencias
  const evidence = {};
  if (comments && comments.trim()) {
    evidence.comments = [comments.trim()];
  }
  if (photosText && photosText.trim()) {
    const photos = photosText
      .split("\n")
      .map((url) => url.trim())
      .filter((url) => url);
    if (photos.length > 0) {
      evidence.photos = photos;
    }
  }

  const payload = {
    reason: reason.trim(),
  };

  if (workLogId) {
    payload.work_log_id = parseInt(workLogId);
  }

  if (Object.keys(evidence).length > 0) {
    payload.evidence = evidence;
  }

  try {
    const response = await API.post("/disputes", payload);

    if (response && response.success) {
      Toast.success("Disputa creada correctamente");
      const modal = bootstrap.Modal.getInstance(
        document.getElementById("modal-create-dispute")
      );
      if (modal) modal.hide();
      if (dataTable) dataTable.loadData();
    } else {
      Toast.error(response?.message || "Error al crear la disputa");
    }
  } catch (error) {
    console.error("Error creating dispute:", error);
    Toast.error("Error al crear la disputa");
  }
}

function openResolveDisputeModal(disputeId) {
  const modal = new bootstrap.Modal(
    document.getElementById("modal-resolve-dispute")
  );
  document.getElementById("resolve-dispute-id").value = disputeId;
  modal.show();
}

async function resolveDispute() {
  const disputeId = document.getElementById("resolve-dispute-id")?.value;
  const resolution = document.getElementById("dispute-resolution")?.value;

  if (!disputeId || !resolution || resolution.trim().length < 10) {
    Toast.error("La resolución debe tener al menos 10 caracteres");
    return;
  }

  try {
    const response = await API.put(`/disputes/${disputeId}/resolve`, {
      resolution: resolution.trim(),
    });

    if (response && response.success) {
      Toast.success("Disputa resuelta correctamente");
      const modal = bootstrap.Modal.getInstance(
        document.getElementById("modal-resolve-dispute")
      );
      if (modal) modal.hide();
      if (dataTable) dataTable.loadData();
    } else {
      Toast.error(response?.message || "Error al resolver la disputa");
    }
  } catch (error) {
    console.error("Error resolving dispute:", error);
    Toast.error("Error al resolver la disputa");
  }
}

async function viewDisputeDetails(disputeId) {
  try {
    const response = await API.get(`/disputes/${disputeId}`);

    if (response && response.success) {
      const dispute = response.data;
      const detailsContainer = document.getElementById("dispute-details");

      if (detailsContainer) {
        const evidence = dispute.evidence || {};
        const photos = evidence.photos || [];
        const comments = evidence.comments || [];

        detailsContainer.innerHTML = `
          <div class="row">
            <div class="col-md-6">
              <h6>Información General</h6>
              <table class="table table-sm">
                <tr>
                  <th>ID:</th>
                  <td>#${dispute.id}</td>
                </tr>
                <tr>
                  <th>Estado:</th>
                  <td><span class="badge-modern badge-modern--${dispute.status === 'resuelto' ? 'success' : dispute.status === 'disputado' ? 'warning' : 'secondary'}">${dispute.status}</span></td>
                </tr>
                <tr>
                  <th>Work Log ID:</th>
                  <td>${dispute.work_log_id ? `#${dispute.work_log_id}` : "-"}</td>
                </tr>
                <tr>
                  <th>Creada por:</th>
                  <td>${escapeHtml(dispute.raised_by_name || "-")}</td>
                </tr>
                <tr>
                  <th>Fecha de creación:</th>
                  <td>${new Date(dispute.created_at).toLocaleString("es-ES")}</td>
                </tr>
                ${dispute.resolved_at ? `
                <tr>
                  <th>Resuelta por:</th>
                  <td>${escapeHtml(dispute.resolved_by_name || "-")}</td>
                </tr>
                <tr>
                  <th>Fecha de resolución:</th>
                  <td>${new Date(dispute.resolved_at).toLocaleString("es-ES")}</td>
                </tr>
                ` : ""}
              </table>
            </div>
            <div class="col-md-6">
              <h6>Razón</h6>
              <p>${escapeHtml(dispute.reason || "-")}</p>
              ${dispute.resolution ? `
              <h6 class="mt-3">Resolución</h6>
              <p>${escapeHtml(dispute.resolution)}</p>
              ` : ""}
            </div>
          </div>
          ${photos.length > 0 || comments.length > 0 ? `
          <div class="row mt-3">
            <div class="col-12">
              <h6>Evidencias</h6>
              ${photos.length > 0 ? `
              <div class="mb-2">
                <strong>Fotos:</strong>
                <div class="d-flex flex-wrap gap-2 mt-2">
                  ${photos.map(photo => `
                    <a href="${escapeHtml(photo)}" target="_blank" class="btn-modern btn-modern--sm btn-modern--ghost">
                      <i class="bi bi-image"></i> Ver foto
                    </a>
                  `).join("")}
                </div>
              </div>
              ` : ""}
              ${comments.length > 0 ? `
              <div>
                <strong>Comentarios:</strong>
                <ul class="mt-2">
                  ${comments.map(comment => `<li>${escapeHtml(comment)}</li>`).join("")}
                </ul>
              </div>
              ` : ""}
            </div>
          </div>
          ` : ""}
        `;
      }

      const modal = new bootstrap.Modal(
        document.getElementById("modal-details")
      );
      modal.show();
    } else {
      Toast.error("Error al cargar los detalles de la disputa");
    }
  } catch (error) {
    console.error("Error loading dispute details:", error);
    Toast.error("Error al cargar los detalles de la disputa");
  }
}

function viewWorkLog(workLogId) {
  // Redirigir a la página de aprobaciones con el work log específico
  window.location.href = `/approvals?work_log_id=${workLogId}`;
}

function escapeHtml(text) {
  const div = document.createElement("div");
  div.textContent = text;
  return div.innerHTML;
}

// Exportar funciones globales para uso en onclick
window.viewDisputeDetails = viewDisputeDetails;
window.openResolveDisputeModal = openResolveDisputeModal;
window.viewWorkLog = viewWorkLog;

