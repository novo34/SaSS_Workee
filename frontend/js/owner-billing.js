/**
 * JavaScript para Billing Owner
 * Panel de facturación con MRR/ARR y gestión de facturas
 *
 * Usa componentes reutilizables:
 * - API Client centralizado
 * - Toast notifications
 * - DataTable component
 */

// Asegurar que los componentes estén cargados
if (
  typeof API === "undefined" ||
  typeof Toast === "undefined" ||
  typeof DataTable === "undefined"
) {
  console.error(
    "Componentes no cargados. Asegúrate de incluir components-loader.js"
  );
}

let dataTable = null;

document.addEventListener("DOMContentLoaded", () => {
  // Inicializar componentes UI comunes (tooltips e iconos)
  if (typeof UIInitializer !== 'undefined') {
    UIInitializer.init();
  }
  
  loadMetrics();
  initializeDataTable();
  setupEventListeners();
});

function setupEventListeners() {
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

async function loadMetrics() {
  try {
    // Calcular MRR: suma de fixed_price + variable_price_per_employee * avg_units de suscripciones activas
    const subscriptionsResponse = await API.get("/tenants?include_subscriptions=true");
    
    let mrr = 0;
    let totalInvoices = 0;
    let pendingInvoices = 0;
    let monthlyRevenue = 0;
    
    if (subscriptionsResponse && subscriptionsResponse.success && subscriptionsResponse.data) {
      const tenants = Array.isArray(subscriptionsResponse.data) 
        ? subscriptionsResponse.data 
        : [subscriptionsResponse.data];
      
      for (const tenant of tenants) {
        if (tenant.subscriptions && Array.isArray(tenant.subscriptions)) {
          for (const sub of tenant.subscriptions) {
            if (sub.status === 'active') {
              mrr += parseFloat(sub.fixed_price || 0);
              // Variable: usar promedio de snapshots del mes actual
              // Por simplicidad, asumimos un promedio (en producción se calcularía desde snapshots)
              const avgUnits = 10; // Placeholder - debería calcularse desde snapshots
              mrr += parseFloat(sub.variable_price_per_employee || 0) * avgUnits;
            }
          }
        }
      }
    }
    
    // Obtener facturas del mes actual
    const currentMonth = new Date().toISOString().substring(0, 7);
    const invoicesResponse = await API.get("/invoices", {
      from: currentMonth + "-01",
      to: currentMonth + "-31"
    });
    
    if (invoicesResponse && invoicesResponse.success && invoicesResponse.data) {
      const invoices = invoicesResponse.data;
      totalInvoices = invoices.length;
      pendingInvoices = invoices.filter(inv => inv.status === 'issued' || inv.status === 'failed').length;
      monthlyRevenue = invoices
        .filter(inv => inv.status === 'paid')
        .reduce((sum, inv) => sum + parseFloat(inv.total_amount || 0), 0);
    }
    
    // Actualizar KPIs
    const mrrElement = document.getElementById("metric-mrr");
    const arrElement = document.getElementById("metric-arr");
    const pendingElement = document.getElementById("metric-pending");
    const monthlyElement = document.getElementById("metric-monthly");
    
    if (mrrElement) {
      mrrElement.textContent = formatCurrency(mrr);
    }
    if (arrElement) {
      arrElement.textContent = formatCurrency(mrr * 12); // ARR = MRR * 12
    }
    if (pendingElement) {
      pendingElement.textContent = pendingInvoices;
    }
    if (monthlyElement) {
      monthlyElement.textContent = formatCurrency(monthlyRevenue);
    }
  } catch (error) {
    console.error("Error loading metrics:", error);
    Toast.error("Error al cargar métricas");
  }
}

function initializeDataTable() {
  const container = document.getElementById("invoices-container");
  if (!container) return;

  dataTable = new DataTable(container, {
    columns: [
      {
        key: "invoice_number",
        label: "Número",
        sortable: true,
        render: (row) => `<strong>${escapeHtml(row.invoice_number || "-")}</strong>`,
      },
      {
        key: "tenant_name",
        label: "Tenant",
        sortable: true,
        render: (row) => escapeHtml(row.tenant_name || "-"),
      },
      {
        key: "period",
        label: "Período",
        sortable: true,
        render: (row) => {
          const from = row.period_from ? new Date(row.period_from).toLocaleDateString("es-ES") : "-";
          const to = row.period_to ? new Date(row.period_to).toLocaleDateString("es-ES") : "-";
          return `${from} - ${to}`;
        },
      },
      {
        key: "total_amount",
        label: "Total",
        sortable: true,
        render: (row) => {
          const amount = parseFloat(row.total_amount || 0);
          const currency = row.currency || "CHF";
          return `<strong>${formatCurrency(amount, currency)}</strong>`;
        },
      },
      {
        key: "status",
        label: "Estado",
        sortable: true,
        render: (row) => {
          const status = row.status || "draft";
          const statusLabels = {
            draft: { label: "Borrador", class: "badge-modern badge-modern--secondary" },
            issued: { label: "Emitida", class: "badge-modern badge-modern--warning" },
            paid: { label: "Pagada", class: "badge-modern badge-modern--success" },
            failed: { label: "Fallida", class: "badge-modern badge-modern--danger" },
            canceled: { label: "Cancelada", class: "badge-modern badge-modern--secondary" },
          };
          const statusInfo = statusLabels[status] || statusLabels.draft;
          return `<span class="${statusInfo.class}">${statusInfo.label}</span>`;
        },
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
                <button class="btn-modern btn-modern--sm btn-modern--ghost" onclick="viewInvoice(${row.id})" aria-label="Ver detalles">
                    <i class="bi bi-eye"></i>
                </button>
          `;

          if (row.pdf_url) {
            actions += `
                <a href="${row.pdf_url}" target="_blank" class="btn-modern btn-modern--sm btn-modern--ghost" aria-label="Descargar PDF">
                    <i class="bi bi-download"></i>
                </a>
            `;
          }

          if (row.status === "failed") {
            actions += `
                <button class="btn-modern btn-modern--sm btn-modern--primary" onclick="retryPayment(${row.id})" aria-label="Reintentar pago">
                    <i class="bi bi-arrow-clockwise"></i>
                </button>
            `;
          }

          if (row.status === "issued" || row.status === "failed") {
            actions += `
                <button class="btn-modern btn-modern--sm btn-modern--success" onclick="markPaid(${row.id})" aria-label="Marcar como pagada">
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
        // Owner puede ver todas las facturas (tenant_id = null)
        const response = await API.get("/invoices", params);

        if (response && response.success) {
          return {
            data: response.data || [],
            total: response.total || 0,
            page: response.page || page,
            total_pages: response.total_pages || 1,
          };
        } else {
          Toast.error(response?.message || "Error al cargar facturas");
          return { data: [], total: 0, page: 1, total_pages: 1 };
        }
      } catch (error) {
        console.error("Error loading invoices:", error);
        Toast.error("Error al cargar facturas");
        return { data: [], total: 0, page: 1, total_pages: 1 };
      }
    },
    pageSize: 20,
    searchable: false, // Los filtros están en la parte superior
    emptyMessage: "No hay facturas",
  });
}

async function viewInvoice(invoiceId) {
  try {
    const response = await API.get(`/invoices/${invoiceId}`);

    if (response && response.success) {
      const invoice = response.data;
      // Mostrar modal con detalles (implementar si es necesario)
      Toast.info(`Factura: ${invoice.invoice_number} - Total: ${formatCurrency(invoice.total_amount, invoice.currency)}`);
    } else {
      Toast.error("Error al cargar los detalles de la factura");
    }
  } catch (error) {
    console.error("Error loading invoice details:", error);
    Toast.error("Error al cargar los detalles de la factura");
  }
}

async function retryPayment(invoiceId) {
  const confirmed = await Modal.confirm({
    title: "Reintentar Pago",
    message: "¿Deseas reintentar el pago de esta factura?",
    confirmLabel: "Reintentar",
    cancelLabel: "Cancelar",
  });

  if (!confirmed) return;

  try {
    const response = await API.put(`/invoices/${invoiceId}/retry-payment`);

    if (response && response.success) {
      Toast.success("Pago reintentado correctamente");
      if (dataTable) dataTable.loadData();
      loadMetrics();
    } else {
      Toast.error(response?.message || "Error al reintentar el pago");
    }
  } catch (error) {
    console.error("Error retrying payment:", error);
    Toast.error("Error al reintentar el pago");
  }
}

async function markPaid(invoiceId) {
  const confirmed = await Modal.confirm({
    title: "Marcar como Pagada",
    message: "¿Deseas marcar esta factura como pagada?",
    confirmLabel: "Marcar",
    cancelLabel: "Cancelar",
  });

  if (!confirmed) return;

  try {
    const response = await API.put(`/invoices/${invoiceId}/mark-paid`);

    if (response && response.success) {
      Toast.success("Factura marcada como pagada");
      if (dataTable) dataTable.loadData();
      loadMetrics();
    } else {
      Toast.error(response?.message || "Error al marcar la factura");
    }
  } catch (error) {
    console.error("Error marking invoice as paid:", error);
    Toast.error("Error al marcar la factura");
  }
}

function formatCurrency(amount, currency = "CHF") {
  return new Intl.NumberFormat("es-ES", {
    style: "currency",
    currency: currency,
    minimumFractionDigits: 2,
  }).format(amount);
}

function escapeHtml(text) {
  const div = document.createElement("div");
  div.textContent = text;
  return div.innerHTML;
}

// Exportar funciones globales
window.viewInvoice = viewInvoice;
window.retryPayment = retryPayment;
window.markPaid = markPaid;

