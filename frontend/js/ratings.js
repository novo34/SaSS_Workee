/**
 * JavaScript para Gestión de Valoraciones
 * Permite crear y ver valoraciones de trabajadores temporeros
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
  setupRatingStars();
});

function setupEventListeners() {
  // Botón crear valoración
  const btnCreateRating = document.getElementById("btn-create-rating");
  if (btnCreateRating) {
    btnCreateRating.addEventListener("click", () => {
      openCreateRatingModal();
    });
  }

  // Botón confirmar crear valoración
  const btnConfirmCreate = document.getElementById("btn-confirm-create-rating");
  if (btnConfirmCreate) {
    btnConfirmCreate.addEventListener("click", () => {
      createRating();
    });
  }

  // Filtros
  const filterWorkerId = document.getElementById("filter-worker-id");
  const filterMinScore = document.getElementById("filter-min-score");
  const filterFrom = document.getElementById("filter-from");
  const filterTo = document.getElementById("filter-to");
  const btnClearFilters = document.getElementById("btn-clear-filters");

  if (filterWorkerId) {
    filterWorkerId.addEventListener("input", debounce(() => {
      if (dataTable) {
        dataTable.currentPage = 1;
        dataTable.loadData();
      }
    }, 500));
  }

  if (filterMinScore) {
    filterMinScore.addEventListener("change", () => {
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
      if (filterWorkerId) filterWorkerId.value = "";
      if (filterMinScore) filterMinScore.value = "";
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
  const form = document.getElementById("form-create-rating");
  if (form) {
    formValidator = new FormValidator(form, {
      rules: {
        "rating-worker-id": {
          required: true,
          min: 1,
        },
        "rating-score": {
          required: true,
        },
      },
      messages: {
        "rating-worker-id": {
          required: "El ID del trabajador es requerido",
          min: "El ID debe ser mayor a 0",
        },
        "rating-score": {
          required: "La puntuación es requerida",
        },
      },
    });
  }
}

function setupRatingStars() {
  const stars = document.querySelectorAll(".rating-star");
  stars.forEach((star) => {
    star.addEventListener("click", function () {
      const value = this.getAttribute("for").replace("star-", "");
      document.querySelector(`#star-${value}`).checked = true;
      updateStarDisplay(value);
    });
  });

  const inputs = document.querySelectorAll(".rating-input");
  inputs.forEach((input) => {
    input.addEventListener("change", function () {
      updateStarDisplay(this.value);
    });
  });
}

function updateStarDisplay(selectedValue) {
  const stars = document.querySelectorAll(".rating-star");
  stars.forEach((star, index) => {
    const starValue = 5 - index;
    if (starValue <= selectedValue) {
      star.classList.add("active");
    } else {
      star.classList.remove("active");
    }
  });
}

function initializeDataTable() {
  const container = document.getElementById("ratings-container");
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
        key: "worker_name",
        label: "Trabajador",
        sortable: true,
        render: (row) => {
          return `
            ${escapeHtml(row.worker_name || "-")}<br>
            <small class="text-muted">${escapeHtml(row.worker_code || "")}</small>
          `;
        },
      },
      {
        key: "score",
        label: "Puntuación",
        sortable: true,
        render: (row) => {
          const score = row.score || 0;
          const stars = "★".repeat(score) + "☆".repeat(5 - score);
          const scoreLabels = {
            5: "Excelente",
            4: "Bueno",
            3: "Regular",
            2: "Malo",
            1: "Muy malo",
          };
          return `
            <div>
              <span class="rating-display">${stars}</span>
              <small class="text-muted d-block">${scoreLabels[score] || ""}</small>
            </div>
          `;
        },
      },
      {
        key: "tags",
        label: "Etiquetas",
        sortable: false,
        render: (row) => {
          if (!row.tags || row.tags.length === 0) return "-";
          return row.tags
            .map(
              (tag) =>
                `<span class="badge-modern badge-modern--info badge-modern--sm">${escapeHtml(tag)}</span>`
            )
            .join(" ");
        },
      },
      {
        key: "comment",
        label: "Comentario",
        sortable: false,
        render: (row) => {
          const comment = escapeHtml(row.comment || "");
          return comment.length > 50
            ? `${comment.substring(0, 50)}...`
            : comment || "-";
        },
      },
      {
        key: "rated_by_name",
        label: "Valorado por",
        sortable: true,
        render: (row) => escapeHtml(row.rated_by_name || "-"),
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
    ],
    fetchData: async (page, pageSize, sortBy, sortDir, filters) => {
      const params = {
        page: page,
        page_size: pageSize,
      };

      // Agregar filtros externos
      const filterWorkerId = document.getElementById("filter-worker-id")?.value;
      if (filterWorkerId) params.worker_id = parseInt(filterWorkerId);

      const filterMinScore = document.getElementById("filter-min-score")?.value;
      if (filterMinScore) params.min_score = parseInt(filterMinScore);

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
        const response = await API.get("/ratings", params);

        if (response && response.success) {
          return {
            data: response.data || [],
            total: response.total || 0,
            page: response.page || page,
            total_pages: response.total_pages || 1,
          };
        } else {
          Toast.error(response?.message || "Error al cargar valoraciones");
          return { data: [], total: 0, page: 1, total_pages: 1 };
        }
      } catch (error) {
        console.error("Error loading ratings:", error);
        Toast.error("Error al cargar valoraciones");
        return { data: [], total: 0, page: 1, total_pages: 1 };
      }
    },
    pageSize: 20,
    searchable: false, // Los filtros están en la parte superior
    emptyMessage: "No hay valoraciones",
  });
}

function openCreateRatingModal(workerId = null) {
  const modal = new bootstrap.Modal(
    document.getElementById("modal-create-rating")
  );
  const form = document.getElementById("form-create-rating");

  // Limpiar formulario
  if (form) {
    form.reset();
    if (workerId) {
      document.getElementById("rating-worker-id").value = workerId;
    }
    updateStarDisplay(0);
  }

  modal.show();
}

async function createRating() {
  const form = document.getElementById("form-create-rating");
  if (!form) return;

  // Validar formulario
  if (formValidator && !formValidator.validate()) {
    return;
  }

  const workerId = document.getElementById("rating-worker-id")?.value;
  const scoreInput = document.querySelector('input[name="rating-score"]:checked');
  const comment = document.getElementById("rating-comment")?.value;
  const tagCheckboxes = document.querySelectorAll('input[name="tags"]:checked');

  if (!workerId || !scoreInput) {
    Toast.error("Completa todos los campos requeridos");
    return;
  }

  const score = parseInt(scoreInput.value);
  const tags = Array.from(tagCheckboxes).map((cb) => cb.value);

  const payload = {
    agency_worker_id: parseInt(workerId),
    score: score,
  };

  if (tags.length > 0) {
    payload.tags = tags;
  }

  if (comment && comment.trim()) {
    payload.comment = comment.trim();
  }

  try {
    const response = await API.post("/ratings", payload);

    if (response && response.success) {
      Toast.success("Valoración creada correctamente");
      const modal = bootstrap.Modal.getInstance(
        document.getElementById("modal-create-rating")
      );
      if (modal) modal.hide();
      if (dataTable) dataTable.loadData();
    } else {
      Toast.error(response?.message || "Error al crear la valoración");
    }
  } catch (error) {
    console.error("Error creating rating:", error);
    Toast.error("Error al crear la valoración");
  }
}

function debounce(func, wait) {
  let timeout;
  return function executedFunction(...args) {
    const later = () => {
      clearTimeout(timeout);
      func(...args);
    };
    clearTimeout(timeout);
    timeout = setTimeout(later, wait);
  };
}

function escapeHtml(text) {
  const div = document.createElement("div");
  div.textContent = text;
  return div.innerHTML;
}

// Exportar funciones globales
window.openCreateRatingModal = openCreateRatingModal;

