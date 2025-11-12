/**
 * JavaScript para Panel de Aprobaciones (REFACTORIZADO CON DATATABLE)
 * Permite aprobar/rechazar work logs pendientes
 *
 * Usa componentes reutilizables:
 * - API Client centralizado
 * - Toast notifications
 * - Modal component
 * - DataTable component (Fase 4)
 */

// Asegurar que los componentes estén cargados
if (
  typeof API === "undefined" ||
  typeof Toast === "undefined" ||
  typeof Modal === "undefined" ||
  typeof DataTable === "undefined"
) {
  console.error(
    "Componentes no cargados. Asegúrate de incluir components-loader.js"
  );
}

let dataTable = null;
let selectedWorkLogs = new Set();

document.addEventListener("DOMContentLoaded", () => {
  // Inicializar componentes UI comunes (tooltips e iconos)
  if (typeof UIInitializer !== 'undefined') {
    UIInitializer.init();
  }
  
  loadPendingCount();
  initializeDataTable();
  setupEventListeners();
});

function setupEventListeners() {
  // Filtros
  const filterEmployeeType = document.getElementById("filter-employee-type");
  const filterFrom = document.getElementById("filter-from");
  const filterTo = document.getElementById("filter-to");
  const btnClearFilters = document.getElementById("btn-clear-filters");

  if (filterEmployeeType) {
    filterEmployeeType.addEventListener("change", () => {
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
      if (filterEmployeeType) filterEmployeeType.value = "";
      if (filterFrom) filterFrom.value = "";
      if (filterTo) filterTo.value = "";
      if (dataTable) {
        dataTable.currentPage = 1;
        dataTable.loadData();
      }
    });
  }

  // Acciones masivas
  const btnBulkApprove = document.getElementById("btn-bulk-approve");
  const btnBulkReject = document.getElementById("btn-bulk-reject");

  if (btnBulkApprove) {
    btnBulkApprove.addEventListener("click", () => {
      bulkApprove();
    });
  }

  if (btnBulkReject) {
    btnBulkReject.addEventListener("click", () => {
      openBulkRejectModal();
    });
  }

  // Modal rechazo
  const btnConfirmReject = document.getElementById("btn-confirm-reject");
  if (btnConfirmReject) {
    btnConfirmReject.addEventListener("click", () => {
      const workLogId = document.getElementById("reject-work-log-id")?.value;
      const reason = document.getElementById("rejection-reason")?.value;
      if (workLogId && reason) {
        if (workLogId === "bulk") {
          bulkReject(reason);
        } else {
          rejectWorkLog(workLogId, reason);
        }
      }
    });
  }
}

async function loadPendingCount() {
  try {
    const response = await API.get("/approvals/pending");

    if (response && response.success) {
      const pendingNumber = document.getElementById("pending-number");
      const pendingCount = document.getElementById("pending-count");
      const count = response.data?.count || 0;

      if (count > 0) {
        if (pendingNumber) pendingNumber.textContent = count;
        if (pendingCount) pendingCount.classList.remove('d-none');
      } else {
        if (pendingCount) pendingCount.classList.add('d-none');
      }
      
      // Actualizar badge en navegación
      if (typeof updateNavItemBadge !== 'undefined') {
        updateNavItemBadge("approvals", count);
      }
    }
  } catch (error) {
    console.error("Error loading pending count:", error);
  }
}

function initializeDataTable() {
  const container = document.getElementById("approvals-container");
  if (!container) return;

  dataTable = new DataTable(container, {
    columns: [
      {
        key: "employee",
        label: "Empleado",
        sortable: true,
        render: (row) => {
          return `
            ${escapeHtml(row.employee_name || "-")}<br>
            <small class="text-muted">${escapeHtml(
              row.employee_code || ""
            )}</small>
          `;
        },
      },
      {
        key: "date",
        label: "Fecha",
        sortable: true,
        render: (row) => {
          const date = new Date(row.date).toLocaleDateString("es-ES");
          const clockIn = row.clock_in
            ? new Date(row.clock_in).toLocaleTimeString("es-ES", {
                hour: "2-digit",
                minute: "2-digit",
              })
            : "-";
          const clockOut = row.clock_out
            ? new Date(row.clock_out).toLocaleTimeString("es-ES", {
                hour: "2-digit",
                minute: "2-digit",
              })
            : "-";
          return `
            ${date}<br>
            <small class="text-muted">${clockIn} - ${clockOut}</small>
          `;
        },
      },
      {
        key: "hours",
        label: "Horas",
        sortable: true,
        render: (row) => {
          return `${(row.hours || 0).toFixed(2)}h`;
        },
      },
      {
        key: "project",
        label: "Proyecto",
        sortable: true,
        render: (row) => {
          return escapeHtml(row.project_name || "-");
        },
      },
      {
        key: "co_approval",
        label: "Co-aprobación",
        sortable: false,
        render: (row) => {
          return row.requires_co_approval
            ? '<span class="badge-modern badge-modern--info">Sí</span>'
            : "-";
        },
      },
      {
        key: "actions",
        label: "Acciones",
        sortable: false,
        render: (row) => {
          return `
            <div class="d-flex gap-2">
                <button class="btn-modern btn-modern--sm btn-modern--primary" onclick="viewDetails(${row.id})" aria-label="Ver detalles">
                    <svg class="btn-modern__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                    </svg>
                </button>
                <button class="btn-modern btn-modern--sm btn-modern--success" onclick="approveWorkLog(${row.id})" aria-label="Aprobar">
                    <svg class="btn-modern__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </button>
                <button class="btn-modern btn-modern--sm btn-modern--danger" onclick="openRejectModal(${row.id})" aria-label="Rechazar">
                    <svg class="btn-modern__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
          `;
        },
      },
    ],
    fetchData: async (page, pageSize, sortBy, sortDir, filters) => {
      const params = {
        page: page,
        page_size: pageSize,
      };

      // Agregar filtros externos
      const filterEmployeeType = document.getElementById(
        "filter-employee-type"
      )?.value;
      if (filterEmployeeType) params.employee_type = filterEmployeeType;

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
        const response = await API.get("/approvals", params);

        if (response && response.success) {
          return {
            data: response.data || [],
            total: response.total || 0,
            page: response.page || page,
            total_pages: response.total_pages || 1,
          };
        } else {
          Toast.error(response?.message || "Error al cargar aprobaciones");
          return { data: [], total: 0, page: 1, total_pages: 1 };
        }
      } catch (error) {
        console.error("Error loading approvals:", error);
        Toast.error("Error al cargar aprobaciones");
        return { data: [], total: 0, page: 1, total_pages: 1 };
      }
    },
    pageSize: 20,
    selectable: true,
    searchable: false, // Los filtros están en la parte superior
    emptyMessage: "No hay work logs pendientes de aprobación",
    onSelectionChange: (selectedRows) => {
      selectedWorkLogs.clear();
      selectedRows.forEach((row) => {
        selectedWorkLogs.add(row.id);
      });
      updateBulkActions();
    },
  });
}

function updateBulkActions() {
  const count = selectedWorkLogs.size;
  const selectedCount = document.getElementById("selected-count");
  const bulkActions = document.getElementById("bulk-actions");

  if (selectedCount)
    selectedCount.textContent = `${count} seleccionado${
      count !== 1 ? "s" : ""
    }`;
  if (bulkActions) {
    if (count > 0) {
      bulkActions.classList.remove('d-none');
    } else {
      bulkActions.classList.add('d-none');
    }
  }
}

async function approveWorkLog(workLogId) {
  const confirmed = await Modal.confirm({
    title: "Aprobar Work Log",
    message: "¿Deseas aprobar este work log?",
    confirmLabel: "Aprobar",
    cancelLabel: "Cancelar",
  });

  if (!confirmed) return;

  try {
    const response = await API.put(`/approvals/${workLogId}/approve`);

    if (response && response.success) {
      Toast.success("Work log aprobado correctamente");
      loadPendingCount();
      if (dataTable) dataTable.loadData();
    } else {
      Toast.error(response?.message || "Error al aprobar work log");
    }
  } catch (error) {
    console.error("Error approving work log:", error);
    Toast.error("Error al aprobar work log");
  }
}

function openRejectModal(workLogId) {
  const rejectWorkLogId = document.getElementById("reject-work-log-id");
  const rejectionReason = document.getElementById("rejection-reason");

  if (rejectWorkLogId) rejectWorkLogId.value = workLogId;
  if (rejectionReason) rejectionReason.value = "";

  const modalEl = document.getElementById("modal-reject");
  if (!modalEl) return;

  const bootstrapModal = new bootstrap.Modal(modalEl);
  bootstrapModal.show();
}

async function rejectWorkLog(workLogId, reason) {
  try {
    const response = await API.put(`/approvals/${workLogId}/reject`, {
      reason,
    });

    if (response && response.success) {
      const modalEl = document.getElementById("modal-reject");
      if (modalEl) {
        const bootstrapModal = bootstrap.Modal.getInstance(modalEl);
        if (bootstrapModal) bootstrapModal.hide();
      }
      Toast.success("Work log rechazado correctamente");
      loadPendingCount();
      if (dataTable) dataTable.loadData();
    } else {
      Toast.error(response?.message || "Error al rechazar work log");
    }
  } catch (error) {
    console.error("Error rejecting work log:", error);
    Toast.error("Error al rechazar work log");
  }
}

async function bulkApprove() {
  if (selectedWorkLogs.size === 0) {
    return;
  }

  const confirmed = await Modal.confirm({
    title: "Aprobar Múltiples Work Logs",
    message: `¿Deseas aprobar ${selectedWorkLogs.size} work log(s)?`,
    confirmLabel: "Aprobar",
    cancelLabel: "Cancelar",
  });

  if (!confirmed) return;

  try {
    const response = await API.post("/approvals/batch", {
      work_log_ids: Array.from(selectedWorkLogs),
      action: "approve",
    });

    if (response && response.success) {
      Toast.success(response?.message || "Work logs aprobados correctamente");
      selectedWorkLogs.clear();
      loadPendingCount();
      if (dataTable) {
        dataTable.selectedRows.clear();
        dataTable.loadData();
      }
    } else {
      Toast.error(response?.message || "Error al aprobar work logs");
    }
  } catch (error) {
    console.error("Error bulk approving:", error);
    Toast.error("Error al aprobar work logs");
  }
}

function openBulkRejectModal() {
  if (selectedWorkLogs.size === 0) {
    return;
  }

  const rejectWorkLogId = document.getElementById("reject-work-log-id");
  const rejectionReason = document.getElementById("rejection-reason");

  if (rejectWorkLogId) rejectWorkLogId.value = "bulk";
  if (rejectionReason) rejectionReason.value = "";

  const modalEl = document.getElementById("modal-reject");
  if (!modalEl) return;

  const bootstrapModal = new bootstrap.Modal(modalEl);
  bootstrapModal.show();
}

async function bulkReject(reason) {
  try {
    const response = await API.post("/approvals/batch", {
      work_log_ids: Array.from(selectedWorkLogs),
      action: "reject",
      reason: reason,
    });

    if (response && response.success) {
      const modalEl = document.getElementById("modal-reject");
      if (modalEl) {
        const bootstrapModal = bootstrap.Modal.getInstance(modalEl);
        if (bootstrapModal) bootstrapModal.hide();
      }
      Toast.success(response?.message || "Work logs rechazados correctamente");
      selectedWorkLogs.clear();
      loadPendingCount();
      if (dataTable) {
        dataTable.selectedRows.clear();
        dataTable.loadData();
      }
    } else {
      Toast.error(response?.message || "Error al rechazar work logs");
    }
  } catch (error) {
    console.error("Error bulk rejecting:", error);
    Toast.error("Error al rechazar work logs");
  }
}

async function viewDetails(workLogId) {
  try {
    const response = await API.get(`/approvals/${workLogId}`);

    if (response && response.success) {
      const workLog = response.data;
      const details = document.getElementById("work-log-details");
      if (!details) return;

      const date = new Date(workLog.date).toLocaleDateString("es-ES");
      const clockIn = workLog.clock_in
        ? new Date(workLog.clock_in).toLocaleString("es-ES")
        : "-";
      const clockOut = workLog.clock_out
        ? new Date(workLog.clock_out).toLocaleString("es-ES")
        : "-";

      details.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <strong>Empleado:</strong> ${escapeHtml(
                          workLog.employee_name || "-"
                        )}<br>
                        <strong>Código:</strong> ${escapeHtml(
                          workLog.employee_code || "-"
                        )}<br>
                        <strong>Tipo:</strong> ${
                          workLog.employee_type === "own_employee"
                            ? "Empleado Propio"
                            : "Trabajador Temporero"
                        }<br>
                        ${
                          workLog.agency_name
                            ? `<strong>Agencia:</strong> ${escapeHtml(
                                workLog.agency_name
                              )}<br>`
                            : ""
                        }
                    </div>
                    <div class="col-md-6">
                        <strong>Fecha:</strong> ${date}<br>
                        <strong>Entrada:</strong> ${clockIn}<br>
                        <strong>Salida:</strong> ${clockOut}<br>
                        <strong>Horas:</strong> ${(workLog.hours || 0).toFixed(
                          2
                        )}h<br>
                        <strong>Pausas:</strong> ${
                          workLog.breaks_minutes || 0
                        } min
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-12">
                        <strong>Proyecto:</strong> ${escapeHtml(
                          workLog.project_name || "-"
                        )}<br>
                        <strong>Estado:</strong> <span class="badge-modern badge-modern--warning">${escapeHtml(
                          workLog.status || ""
                        )}</span><br>
                        ${
                          workLog.requires_co_approval
                            ? '<br><span class="badge-modern badge-modern--info">Requiere co-aprobación</span>'
                            : ""
                        }
                    </div>
                </div>
                ${
                  workLog.notes
                    ? `<hr><div class="row"><div class="col-12"><strong>Notas:</strong><br>${escapeHtml(
                        workLog.notes
                      )}</div></div>`
                    : ""
                }
            `;

      const modalEl = document.getElementById("modal-details");
      if (modalEl) {
        const bootstrapModal = new bootstrap.Modal(modalEl);
        bootstrapModal.show();
      }
    } else {
      Toast.error(response?.message || "Error al cargar detalles");
    }
  } catch (error) {
    console.error("Error loading details:", error);
    Toast.error("Error al cargar detalles");
  }
}

function escapeHtml(text) {
  const div = document.createElement("div");
  div.textContent = text;
  return div.innerHTML;
}

// Hacer funciones accesibles globalmente
window.approveWorkLog = approveWorkLog;
window.openRejectModal = openRejectModal;
window.viewDetails = viewDetails;
