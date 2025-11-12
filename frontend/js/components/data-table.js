/**
 * Componente DataTable Reutilizable - Workee
 * 
 * Tabla de datos con paginación, ordenamiento, filtros y búsqueda
 * 
 * @class DataTable
 * @description Componente reutilizable para todas las tablas de datos con funcionalidades avanzadas
 * 
 * @example
 * // Ejemplo básico
 * const table = new DataTable(container, {
 *   columns: [
 *     { key: 'id', label: 'ID', sortable: true },
 *     { key: 'name', label: 'Nombre', sortable: true },
 *     { key: 'actions', label: 'Acciones', render: (row) => `<button>Editar</button>` }
 *   ],
 *   fetchData: async (page, pageSize, sortBy, sortDir, filters) => {
 *     const response = await API.get('/endpoint', { page, page_size: pageSize, ... });
 *     return { data: response.data, total: response.total };
 *   },
 *   pageSize: 20,
 *   searchable: true
 * });
 * 
 * @example
 * // Ejemplo con selección múltiple
 * const table = new DataTable(container, {
 *   columns: [...],
 *   fetchData: async (...) => { ... },
 *   selectable: true,
 *   onSelectionChange: (selectedRows) => {
 *     console.log('Seleccionados:', selectedRows);
 *   }
 * });
 * 
 * @param {HTMLElement} container - Contenedor donde se renderizará la tabla
 * @param {Object} options - Opciones de configuración
 * @param {Array<Object>} options.columns - Definición de columnas
 * @param {string} options.columns[].key - Clave del campo en los datos
 * @param {string} options.columns[].label - Etiqueta de la columna
 * @param {boolean} [options.columns[].sortable=true] - Si la columna es ordenable
 * @param {Function} [options.columns[].render] - Función para renderizar el contenido de la celda
 * @param {Function} options.fetchData - Función async que obtiene los datos
 * @param {number} [options.pageSize=20] - Tamaño de página
 * @param {boolean} [options.searchable=true] - Si se muestra barra de búsqueda
 * @param {boolean} [options.sortable=true] - Si las columnas son ordenables
 * @param {boolean} [options.selectable=false] - Si se permite selección múltiple
 * @param {string} [options.emptyMessage='No hay datos para mostrar'] - Mensaje cuando no hay datos
 * @param {Function} [options.onRowClick] - Callback cuando se hace click en una fila
 * @param {Function} [options.onSelectionChange] - Callback cuando cambia la selección
 */

class DataTable {
    constructor(container, options = {}) {
        this.container = container;
        this.options = {
            columns: options.columns || [],
            fetchData: options.fetchData || null,
            pageSize: options.pageSize || 20,
            searchable: options.searchable !== undefined ? options.searchable : true,
            sortable: options.sortable !== undefined ? options.sortable : true,
            selectable: options.selectable !== undefined ? options.selectable : false,
            striped: options.striped !== undefined ? options.striped : false,
            filters: options.filters || [],
            emptyMessage: options.emptyMessage || 'No hay datos para mostrar',
            emptyAction: options.emptyAction || null,
            onRowClick: options.onRowClick || null,
            onSelectionChange: options.onSelectionChange || null,
            ...options
        };
        
        this.currentPage = 1;
        this.currentSortBy = null;
        this.currentSortDir = 'ASC';
        this.currentFilters = {};
        this.currentSearch = '';
        this.totalItems = 0;
        this.totalPages = 0;
        this.selectedRows = new Set();
        this.data = [];
        this.loading = false;
        this.swipeActions = options.swipeActions || null; // MOB-137: Configuración de swipe actions
        this.pullToRefresh = options.pullToRefresh !== undefined ? options.pullToRefresh : true; // MOB-140: Pull to refresh habilitado por defecto en móvil
        this.pullToRefreshHandler = null; // MOB-140: Handler para pull to refresh
        this.virtualScroll = options.virtualScroll !== undefined ? options.virtualScroll : false; // MOB-143: Virtual scrolling para listas largas
        this.virtualScrollHandler = null; // MOB-143: Handler para virtual scrolling
        this.useCache = options.useCache !== undefined ? options.useCache : true; // MOB-143: Cache de datos en móvil
        
        this.create();
        this.loadData();
    }

    /**
     * Crea la estructura HTML de la tabla
     */
    create() {
        this.container.innerHTML = '';
        
        // Contenedor principal
        const wrapper = document.createElement('div');
        wrapper.className = 'data-table' + (this.options.striped ? ' data-table--striped' : '');
        
        // Barra de búsqueda (si está habilitada)
        if (this.options.searchable) {
            const searchBar = document.createElement('div');
            searchBar.className = 'data-table__search mb-3';
            searchBar.innerHTML = `
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" id="data-table-search-input" 
                           placeholder="Buscar..." aria-label="Buscar">
                    <button class="btn btn-outline-secondary" type="button" id="data-table-search-clear" style="display: none;">
                        <i class="bi bi-x"></i>
                    </button>
                </div>
            `;
            wrapper.appendChild(searchBar);
            
            // Event listeners para búsqueda
            const searchInput = searchBar.querySelector('#data-table-search-input');
            const searchClear = searchBar.querySelector('#data-table-search-clear');
            
            let searchTimeout;
            searchInput.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    this.currentSearch = e.target.value;
                    this.currentPage = 1;
                    this.loadData();
                }, 300);
                
                searchClear.style.display = e.target.value ? 'block' : 'none';
            });
            
            searchClear.addEventListener('click', () => {
                searchInput.value = '';
                searchClear.style.display = 'none';
                this.currentSearch = '';
                this.currentPage = 1;
                this.loadData();
            });
        }
        
        // Contenedor de la tabla
        const tableContainer = document.createElement('div');
        tableContainer.className = 'data-table__container';
        tableContainer.innerHTML = `
            <div class="table-responsive">
                <table class="table-modern data-table__table">
                    <thead id="data-table-thead"></thead>
                    <tbody id="data-table-tbody">
                        <tr>
                            <td colspan="${this.getColumnCount()}" class="text-center py-5">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Cargando...</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        `;
        wrapper.appendChild(tableContainer);
        
        // Paginación
        const pagination = document.createElement('div');
        pagination.className = 'data-table__pagination';
        pagination.id = 'data-table-pagination';
        wrapper.appendChild(pagination);
        
        this.container.appendChild(wrapper);
        
        // MOB-140: Inicializar pull to refresh en el contenedor de la tabla (solo en móvil)
        if (this.pullToRefresh && window.innerWidth < 992 && typeof PullToRefresh !== 'undefined') {
            const tableContainerEl = wrapper.querySelector('.data-table__container');
            if (tableContainerEl) {
                // Hacer el contenedor scrolleable si es necesario
                tableContainerEl.style.overflowY = 'auto';
                tableContainerEl.style.maxHeight = '70vh';
                
                this.pullToRefreshHandler = PullToRefresh.init(tableContainerEl, {
                    onRefresh: () => {
                        this.currentPage = 1;
                        return this.loadData();
                    },
                    threshold: 80,
                    resistance: 2.5
                });
            }
        }
        
        // Renderizar encabezados
        this.renderHeaders();
    }

    /**
     * Obtiene el número de columnas (incluyendo checkbox si es selectable)
     */
    getColumnCount() {
        let count = this.options.columns.length;
        if (this.options.selectable) count++;
        return count;
    }

    /**
     * Renderiza los encabezados de la tabla
     */
    renderHeaders() {
        const thead = document.getElementById('data-table-thead');
        if (!thead) return;
        
        let html = '<tr>';
        
        // Checkbox para selección múltiple
        if (this.options.selectable) {
            html += `
                <th style="width: 40px;">
                    <input type="checkbox" id="data-table-select-all" aria-label="Seleccionar todos">
                </th>
            `;
        }
        
        // Columnas
        this.options.columns.forEach(col => {
            const sortable = this.options.sortable && col.sortable !== false;
            const sortIcon = sortable ? '<i class="bi bi-arrow-down-up ms-1"></i>' : '';
            const sortClass = sortable ? 'data-table__header--sortable' : '';
            const currentSortClass = (this.currentSortBy === col.key) 
                ? (this.currentSortDir === 'ASC' ? 'data-table__header--sort-asc' : 'data-table__header--sort-desc')
                : '';
            
            html += `
                <th class="data-table__header ${sortClass} ${currentSortClass}" 
                    ${sortable ? `onclick="window.__dataTableInstance?.sort('${col.key}')" style="cursor: pointer;"` : ''}
                    data-key="${col.key}">
                    ${this.escapeHtml(col.label)}${sortIcon}
                </th>
            `;
        });
        
        html += '</tr>';
        thead.innerHTML = html;
        
        // Event listener para seleccionar todos
        if (this.options.selectable) {
            const selectAll = document.getElementById('data-table-select-all');
            if (selectAll) {
                selectAll.addEventListener('change', (e) => {
                    const checked = e.target.checked;
                    this.data.forEach((row, index) => {
                        if (checked) {
                            this.selectedRows.add(index);
                        } else {
                            this.selectedRows.delete(index);
                        }
                    });
                    this.renderBody();
                    if (this.options.onSelectionChange) {
                        this.options.onSelectionChange(this.getSelectedRows());
                    }
                });
            }
        }
        
        // Guardar referencia para métodos estáticos
        window.__dataTableInstance = this;
    }

    /**
     * Ordena por una columna
     */
    sort(columnKey) {
        if (this.currentSortBy === columnKey) {
            // Cambiar dirección
            this.currentSortDir = this.currentSortDir === 'ASC' ? 'DESC' : 'ASC';
        } else {
            this.currentSortBy = columnKey;
            this.currentSortDir = 'ASC';
        }
        
        this.currentPage = 1;
        this.loadData();
    }

    /**
     * Renderiza el cuerpo de la tabla
     */
    renderBody() {
        const tbody = document.getElementById('data-table-tbody');
        if (!tbody) return;
        
        if (this.loading) {
            // Usar skeleton screens en lugar de spinner para mejor velocidad percibida
            const skeletonRows = Math.min(this.options.pageSize, 5); // Máximo 5 filas skeleton
            const skeletonCols = this.getColumnCount();
            
            let html = '';
            for (let i = 0; i < skeletonRows; i++) {
                html += '<tr class="data-table__row">';
                if (this.options.selectable) {
                    html += '<td><div class="skeleton skeleton--text" style="width: 20px; height: 20px;"></div></td>';
                }
                for (let j = 0; j < skeletonCols; j++) {
                    const isShort = j === 0 || j === skeletonCols - 1;
                    const width = isShort ? '60px' : '100%';
                    html += `<td><div class="skeleton skeleton--text" style="width: ${width};"></div></td>`;
                }
                html += '</tr>';
            }
            
            tbody.innerHTML = html;
            return;
        }
        
        if (!this.data || this.data.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="${this.getColumnCount()}" class="text-center py-5">
                        <div class="empty-state__content">
                            <div class="empty-state__icon">📭</div>
                            <p class="text-muted">${this.escapeHtml(this.options.emptyMessage)}</p>
                            ${this.options.emptyAction ? `
                                <button class="btn btn-primary" onclick="${this.options.emptyAction.callback.toString()}">
                                    ${this.escapeHtml(this.options.emptyAction.label)}
                                </button>
                            ` : ''}
                        </div>
                    </td>
                </tr>
            `;
            return;
        }
        
        let html = '';
        this.data.forEach((row, index) => {
            const isSelected = this.selectedRows.has(index);
            const rowClass = isSelected ? 'data-table__row--active' : '';
            const onClick = this.options.onRowClick ? `onclick="window.__dataTableInstance?.handleRowClick(${index})" style="cursor: pointer;"` : '';
            
            html += `<tr class="data-table__row ${rowClass}" data-index="${index}" ${onClick}>`;
            
            // Checkbox de selección
            if (this.options.selectable) {
                html += `
                    <td data-label="Seleccionar">
                        <input type="checkbox" class="data-table__checkbox" 
                               data-index="${index}" 
                               ${isSelected ? 'checked' : ''}
                               onchange="window.__dataTableInstance?.toggleRowSelection(${index})">
                    </td>
                `;
            }
            
            // Celdas de datos
            this.options.columns.forEach(col => {
                let cellContent = '';
                if (col.render) {
                    cellContent = col.render(row, index);
                } else {
                    cellContent = this.escapeHtml(row[col.key] || '');
                }
                // Agregar data-label para responsive móvil
                html += `<td data-label="${this.escapeHtml(col.label)}">${cellContent}</td>`;
            });
            
            html += '</tr>';
        });
        
        tbody.innerHTML = html;
        
        // MOB-137: Inicializar swipe gestures en filas (solo en móvil)
        if (this.swipeActions && window.innerWidth < 992 && typeof SwipeGestures !== 'undefined') {
            const rows = tbody.querySelectorAll('.data-table__row[data-index]');
            rows.forEach((row) => {
                const index = parseInt(row.getAttribute('data-index'));
                const rowData = this.data[index];
                
                SwipeGestures.initTableRowSwipe(row, {
                    onSwipeLeft: this.swipeActions.onSwipeLeft ? () => {
                        this.swipeActions.onSwipeLeft(rowData, index);
                    } : null,
                    onSwipeRight: this.swipeActions.onSwipeRight ? () => {
                        this.swipeActions.onSwipeRight(rowData, index);
                    } : null,
                    leftActionLabel: this.swipeActions.leftActionLabel || 'Eliminar',
                    rightActionLabel: this.swipeActions.rightActionLabel || 'Editar',
                    leftActionColor: this.swipeActions.leftActionColor || 'danger',
                    rightActionColor: this.swipeActions.rightActionColor || 'primary'
                });
            });
        }
    }

    /**
     * Maneja el click en una fila
     */
    handleRowClick(index) {
        if (this.options.onRowClick) {
            this.options.onRowClick(this.data[index], index);
        }
    }

    /**
     * Alterna la selección de una fila
     */
    toggleRowSelection(index) {
        if (this.selectedRows.has(index)) {
            this.selectedRows.delete(index);
        } else {
            this.selectedRows.add(index);
        }
        
        // Actualizar checkbox "seleccionar todos"
        const selectAll = document.getElementById('data-table-select-all');
        if (selectAll) {
            selectAll.checked = this.selectedRows.size === this.data.length && this.data.length > 0;
            selectAll.indeterminate = this.selectedRows.size > 0 && this.selectedRows.size < this.data.length;
        }
        
        this.renderBody();
        
        if (this.options.onSelectionChange) {
            this.options.onSelectionChange(this.getSelectedRows());
        }
    }

    /**
     * Obtiene las filas seleccionadas
     */
    getSelectedRows() {
        return Array.from(this.selectedRows).map(index => this.data[index]);
    }

    /**
     * Renderiza la paginación
     */
    renderPagination() {
        const pagination = document.getElementById('data-table-pagination');
        if (!pagination) return;
        
        if (this.totalPages <= 1) {
            pagination.style.display = 'none';
            return;
        }
        
        pagination.style.display = 'block';
        
        let html = '<nav aria-label="Paginación"><ul class="pagination justify-content-center mb-0">';
        
        // Botón anterior
        html += `
            <li class="page-item ${this.currentPage === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" onclick="window.__dataTableInstance?.goToPage(${this.currentPage - 1}); return false;">
                    <i class="bi bi-chevron-left"></i>
                </a>
            </li>
        `;
        
        // Números de página
        const maxPages = 7;
        let startPage = Math.max(1, this.currentPage - Math.floor(maxPages / 2));
        let endPage = Math.min(this.totalPages, startPage + maxPages - 1);
        
        if (endPage - startPage < maxPages - 1) {
            startPage = Math.max(1, endPage - maxPages + 1);
        }
        
        if (startPage > 1) {
            html += `<li class="page-item"><a class="page-link" href="#" onclick="window.__dataTableInstance?.goToPage(1); return false;">1</a></li>`;
            if (startPage > 2) {
                html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
        }
        
        for (let i = startPage; i <= endPage; i++) {
            html += `
                <li class="page-item ${i === this.currentPage ? 'active' : ''}">
                    <a class="page-link" href="#" onclick="window.__dataTableInstance?.goToPage(${i}); return false;">${i}</a>
                </li>
            `;
        }
        
        if (endPage < this.totalPages) {
            if (endPage < this.totalPages - 1) {
                html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
            html += `<li class="page-item"><a class="page-link" href="#" onclick="window.__dataTableInstance?.goToPage(${this.totalPages}); return false;">${this.totalPages}</a></li>`;
        }
        
        // Botón siguiente
        html += `
            <li class="page-item ${this.currentPage === this.totalPages ? 'disabled' : ''}">
                <a class="page-link" href="#" onclick="window.__dataTableInstance?.goToPage(${this.currentPage + 1}); return false;">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </li>
        `;
        
        html += '</ul></nav>';
        
        // Información de paginación
        const start = (this.currentPage - 1) * this.options.pageSize + 1;
        const end = Math.min(this.currentPage * this.options.pageSize, this.totalItems);
        html += `
            <div class="text-center mt-2 text-muted small">
                Mostrando ${start} - ${end} de ${this.totalItems} resultados
            </div>
        `;
        
        pagination.innerHTML = html;
    }

    /**
     * Va a una página específica
     */
    goToPage(page) {
        if (page < 1 || page > this.totalPages) return;
        this.currentPage = page;
        this.loadData();
    }

    /**
     * Carga los datos
     */
    async loadData() {
        if (!this.options.fetchData) {
            console.error('DataTable: fetchData function is required');
            return;
        }
        
        this.loading = true;
        this.renderBody();
        
        try {
            const params = {
                page: this.currentPage,
                page_size: this.options.pageSize,
                sort_by: this.currentSortBy,
                sort_dir: this.currentSortDir,
                q: this.currentSearch,
                ...this.currentFilters
            };
            
            // MOB-143: Cache agresivo de listas en móvil
            let result;
            if (this.useCache && window.innerWidth < 992 && typeof PerformanceMobile !== 'undefined') {
                const endpoint = this.options.endpoint || 'list';
                result = await PerformanceMobile.getCachedList(
                    endpoint,
                    params,
                    () => this.options.fetchData(
                        this.currentPage,
                        this.options.pageSize,
                        this.currentSortBy,
                        this.currentSortDir,
                        { ...this.currentFilters, q: this.currentSearch }
                    ),
                    60 * 1000 // 1 minuto de cache
                );
            } else {
                result = await this.options.fetchData(
                    this.currentPage,
                    this.options.pageSize,
                    this.currentSortBy,
                    this.currentSortDir,
                    { ...this.currentFilters, q: this.currentSearch }
                );
            }
            
            this.data = result.data || [];
            this.totalItems = result.total || 0;
            this.totalPages = Math.ceil(this.totalItems / this.options.pageSize);
            
            // MOB-143: Virtual scrolling para listas largas en móvil
            if (this.virtualScroll && window.innerWidth < 992 && this.data.length > 50 && typeof VirtualScroll !== 'undefined') {
                this.initVirtualScroll();
            }
            
            this.selectedRows.clear();
            this.loading = false;
            this.renderBody();
            this.renderPagination();
        } catch (error) {
            console.error('DataTable: Error loading data', error);
            this.loading = false;
            this.data = [];
            this.renderBody();
            this.renderPagination();
        }
    }

    /**
     * MOB-143: Inicializa virtual scrolling para listas largas
     */
    initVirtualScroll() {
        if (this.virtualScrollHandler) {
            this.virtualScrollHandler.destroy();
        }

        const tableContainer = this.container.querySelector('.data-table__container');
        if (!tableContainer || this.data.length <= 50) return;

        // Solo aplicar virtual scrolling en móvil y si hay más de 50 items
        if (window.innerWidth >= 992 || this.data.length <= 50) return;

        // Crear función de renderizado de fila
        const renderRow = (row, index) => {
            let html = '<tr class="data-table__row">';
            
            if (this.options.selectable) {
                html += `<td><input type="checkbox" class="form-check-input" data-row-index="${index}"></td>`;
            }
            
            this.options.columns.forEach(column => {
                const value = row[column.key] || '';
                html += `<td data-label="${column.label || column.key}">`;
                if (column.render) {
                    html += column.render(row, index);
                } else {
                    html += this.escapeHtml(value);
                }
                html += '</td>';
            });
            
            html += '</tr>';
            return html;
        };

        // Aplicar virtual scrolling al tbody
        const tbody = tableContainer.querySelector('tbody');
        if (tbody) {
            this.virtualScrollHandler = VirtualScroll.init(
                tableContainer,
                this.data,
                renderRow,
                {
                    itemHeight: 60, // Altura estimada de fila
                    visibleItems: Math.ceil(tableContainer.clientHeight / 60),
                    buffer: 5
                }
            );
        }
    }

    /**
     * Actualiza los filtros
     */
    setFilters(filters) {
        this.currentFilters = { ...this.currentFilters, ...filters };
        this.currentPage = 1;
        this.loadData();
    }

    /**
     * Limpia los filtros
     */
    clearFilters() {
        this.currentFilters = {};
        this.currentSearch = '';
        const searchInput = document.getElementById('data-table-search-input');
        if (searchInput) {
            searchInput.value = '';
        }
        this.currentPage = 1;
        this.loadData();
    }

    /**
     * Refresca los datos
     */
    refresh() {
        this.loadData();
    }

    /**
     * Escapa HTML
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Destruye el DataTable y limpia recursos
     */
    destroy() {
        // MOB-140: Destruir pull to refresh handler
        if (this.pullToRefreshHandler && typeof this.pullToRefreshHandler.destroy === 'function') {
            this.pullToRefreshHandler.destroy();
        }
        
        // MOB-143: Destruir virtual scroll handler
        if (this.virtualScrollHandler && typeof this.virtualScrollHandler.destroy === 'function') {
            this.virtualScrollHandler.destroy();
        }
    }
}

// Exportar para uso global
if (typeof window !== 'undefined') {
    window.DataTable = DataTable;
}

