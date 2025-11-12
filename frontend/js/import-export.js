/**
 * Import/Export UI
 * 
 * Gestión de importación y exportación masiva de datos
 */

// Inicialización
document.addEventListener('DOMContentLoaded', () => {
    // Inicializar componentes UI comunes (tooltips e iconos)
    if (typeof UIInitializer !== 'undefined') {
        UIInitializer.init();
    }
    
    setupEventListeners();
    setupFilePreview();
});

/**
 * Configura event listeners
 */
function setupEventListeners() {
    // Import
    document.getElementById('importFile').addEventListener('change', handleFileSelect);
    document.getElementById('importBtn').addEventListener('click', handleImport);
    document.getElementById('downloadTemplateBtn').addEventListener('click', handleDownloadTemplate);
    
    // Export
    document.getElementById('exportBtn').addEventListener('click', handleExport);
    document.getElementById('exportResourceType').addEventListener('change', handleExportResourceTypeChange);
}

/**
 * Configura preview de archivo
 */
function setupFilePreview() {
    const fileInput = document.getElementById('importFile');
    fileInput.addEventListener('change', (e) => {
        const file = e.target.files[0];
        if (file) {
            previewFile(file);
        }
    });
}

/**
 * Maneja selección de archivo
 */
function handleFileSelect(e) {
    const file = e.target.files[0];
    const importBtn = document.getElementById('importBtn');
    
    if (file) {
        importBtn.disabled = false;
    } else {
        importBtn.disabled = true;
    }
}

/**
 * Preview del archivo CSV
 */
function previewFile(file) {
    const reader = new FileReader();
    
    reader.onload = (e) => {
        const text = e.target.result;
        const lines = text.split('\n').slice(0, 6); // Primeras 6 líneas
        
        if (lines.length === 0) {
            return;
        }
        
        // Parsear CSV (separador punto y coma)
        const headers = lines[0].split(';').map(h => h.trim().replace(/^"|"$/g, ''));
        const rows = lines.slice(1).map(line => {
            const values = line.split(';').map(v => v.trim().replace(/^"|"$/g, ''));
            return values;
        });
        
        // Mostrar preview
        const previewDiv = document.getElementById('importPreview');
        const table = document.getElementById('previewTable');
        const thead = table.querySelector('thead');
        const tbody = table.querySelector('tbody');
        
        // Headers
        thead.innerHTML = '<tr>' + headers.map(h => `<th>${h}</th>`).join('') + '</tr>';
        
        // Rows
        tbody.innerHTML = rows.map(row => {
            return '<tr>' + row.map(cell => `<td>${cell || ''}</td>`).join('') + '</tr>';
        }).join('');
        
        previewDiv.style.display = 'block';
    };
    
    reader.readAsText(file);
}

/**
 * Descarga plantilla
 */
function handleDownloadTemplate() {
    const resourceType = document.getElementById('importResourceType').value;
    const url = `/api/import-export/templates?resource_type=${resourceType}`;
    window.location.href = url;
}

/**
 * Maneja importación
 */
async function handleImport() {
    const fileInput = document.getElementById('importFile');
    const resourceType = document.getElementById('importResourceType').value;
    const importBtn = document.getElementById('importBtn');
    const resultsDiv = document.getElementById('importResults');
    
    if (!fileInput.files[0]) {
        Toast.error('Selecciona un archivo');
        return;
    }
    
    const formData = new FormData();
    formData.append('file', fileInput.files[0]);
    formData.append('resource_type', resourceType);
    
    importBtn.disabled = true;
    importBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Importando...';
    
    try {
        // Usar API client si está disponible
        let result;
        if (typeof API !== "undefined") {
            result = await API.post('/import-export/import', formData);
        } else {
            // Fallback a fetch directo
            const response = await fetch(`${window.BASE_URL || ''}/api/v1/import-export/import`, {
                method: 'POST',
                body: formData,
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('access_token')}`,
                    'X-Tenant-ID': localStorage.getItem('tenant_id')
                }
            });
            result = await response.json();
        }
        
        if (result.success) {
            const message = `Importación completada: ${result.imported} registros importados, ${result.failed} fallidos`;
            Toast.success(message);
            
            // Mostrar resultados detallados
            if (result.errors && result.errors.length > 0) {
                resultsDiv.innerHTML = `
                    <div class="alert alert-warning">
                        <h6>Errores encontrados:</h6>
                        <ul class="mb-0">
                            ${result.errors.map(e => `<li>${e}</li>`).join('')}
                        </ul>
                    </div>
                `;
                resultsDiv.style.display = 'block';
            } else {
                resultsDiv.style.display = 'none';
            }
            
            // Limpiar formulario
            fileInput.value = '';
            document.getElementById('importPreview').style.display = 'none';
            importBtn.disabled = true;
        } else {
            Toast.error(result.message || 'Error en la importación');
        }
    } catch (error) {
        console.error('Error importando:', error);
        Toast.error('Error al importar datos');
    } finally {
        importBtn.disabled = false;
        importBtn.innerHTML = '<i class="bi bi-upload"></i> Importar';
    }
}

/**
 * Maneja cambio de tipo de recurso en export
 */
function handleExportResourceTypeChange() {
    const resourceType = document.getElementById('exportResourceType').value;
    const filtersDiv = document.getElementById('workLogsFilters');
    
    if (resourceType === 'work_logs') {
        filtersDiv.style.display = 'block';
    } else {
        filtersDiv.style.display = 'none';
    }
}

/**
 * Maneja exportación
 */
async function handleExport() {
    const resourceType = document.getElementById('exportResourceType').value;
    const format = document.getElementById('exportFormat').value;
    const exportBtn = document.getElementById('exportBtn');
    
    let url = `/api/import-export/export?resource_type=${resourceType}&format=${format}`;
    
    // Agregar filtros para work_logs
    if (resourceType === 'work_logs') {
        const dateFrom = document.getElementById('exportDateFrom').value;
        const dateTo = document.getElementById('exportDateTo').value;
        
        if (dateFrom) {
            url += `&date_from=${dateFrom}`;
        }
        if (dateTo) {
            url += `&date_to=${dateTo}`;
        }
    }
    
    exportBtn.disabled = true;
    exportBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Exportando...';
    
    try {
        // Usar API client si está disponible
        if (typeof API !== "undefined") {
            const response = await API.get(url.replace(`${window.BASE_URL || ''}/api/v1`, ''));
            
            if (response && response.success && response.data instanceof Blob) {
                const blob = response.data;
                const downloadUrl = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = downloadUrl;
                a.download = `${resourceType}_export_${new Date().toISOString().split('T')[0]}.${format}`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(downloadUrl);
                
                Toast.success('Exportación completada');
            } else if (response && !response.success) {
                Toast.error(response.message || 'Error en la exportación');
            } else {
                Toast.error('Error en la exportación');
            }
        } else {
            // Fallback a fetch directo
            const response = await fetch(url, {
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('access_token')}`,
                    'X-Tenant-ID': localStorage.getItem('tenant_id')
                }
            });
            
            if (response.ok) {
                const blob = await response.blob();
                const downloadUrl = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = downloadUrl;
                a.download = `${resourceType}_export_${new Date().toISOString().split('T')[0]}.${format}`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(downloadUrl);
                
                Toast.success('Exportación completada');
            } else {
                const error = await response.json();
                Toast.error(error.message || 'Error en la exportación');
            }
        }
    } catch (error) {
        console.error('Error exportando:', error);
        Toast.error('Error al exportar datos');
    } finally {
        exportBtn.disabled = false;
        exportBtn.innerHTML = '<i class="bi bi-download"></i> Exportar';
    }
}

