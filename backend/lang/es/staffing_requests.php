<?php
/**
 * Traducciones - Solicitudes de Personal (Staffing Requests)
 * 
 * Claves para la UI de solicitudes de personal
 */

return [
    // Títulos y headers
    'title' => 'Solicitudes de Personal',
    'new' => 'Nueva Solicitud',
    'edit' => 'Editar Solicitud',
    'view' => 'Ver Solicitud',
    
    // Filtros
    'filter_status' => 'Estado',
    'filter_search' => 'Buscar',
    'filter_all' => 'Todos',
    'filter_open' => 'Abiertas',
    'filter_partially_filled' => 'Parcialmente cubiertas',
    'filter_filled' => 'Cubiertas',
    'filter_canceled' => 'Canceladas',
    'filter_apply' => 'Filtrar',
    'filter_clear' => 'Limpiar',
    
    // Estados
    'status_open' => 'Abierta',
    'status_partially_filled' => 'Parcialmente cubierta',
    'status_filled' => 'Cubierta',
    'status_canceled' => 'Cancelada',
    
    // Modal - Wizard
    'modal_title' => 'Nueva Solicitud de Personal',
    'step_1_title' => 'Fechas y Ubicación',
    'step_2_title' => 'Cantidad y Requisitos',
    'step_3_title' => 'Proyecto y Destinatarios',
    
    // Campos del formulario
    'field_date_from' => 'Fecha y Hora Inicio',
    'field_date_to' => 'Fecha y Hora Fin',
    'field_location' => 'Ubicación',
    'field_location_placeholder' => 'Dirección o lugar de trabajo',
    'field_latitude' => 'Latitud (opcional)',
    'field_longitude' => 'Longitud (opcional)',
    'field_qty_needed' => 'Cantidad Necesaria',
    'field_skills' => 'Skills Requeridos (separados por comas)',
    'field_skills_placeholder' => 'Ej: Construcción, Albañilería, Carpintería',
    'field_languages' => 'Idiomas Requeridos',
    'field_languages_placeholder' => 'Ej: Español, Alemán',
    'field_other_requirements' => 'Otros Requisitos',
    'field_other_requirements_placeholder' => 'Certificaciones, herramientas, etc.',
    'field_project' => 'Proyecto (opcional)',
    'field_project_select' => 'Seleccionar proyecto...',
    'field_agencies' => 'Enviar a Temporeras',
    'field_agencies_loading' => 'Cargando temporeras...',
    
    // Botones del wizard
    'btn_previous' => 'Anterior',
    'btn_next' => 'Siguiente',
    'btn_cancel' => 'Cancelar',
    'btn_submit' => 'Crear Solicitud',
    
    // Tabla
    'table_no_data' => 'No hay solicitudes creadas',
    'table_loading' => 'Cargando...',
    'table_id' => 'ID',
    'table_status' => 'Estado',
    'table_dates' => 'Fechas',
    'table_location' => 'Ubicación',
    'table_qty' => 'Cantidad',
    'table_project' => 'Proyecto',
    'table_actions' => 'Acciones',
    'table_send' => 'Enviar',
    'table_edit' => 'Editar',
    'table_delete' => 'Eliminar',
    
    // Mensajes
    'loading' => 'Cargando solicitudes...',
    'error_loading' => 'Error al cargar solicitudes',
    'error_connection' => 'Error de conexión',
    'created_success' => 'Solicitud creada correctamente',
    'updated_success' => 'Solicitud actualizada correctamente',
    'canceled_success' => 'Solicitud cancelada correctamente',
    'sent_success' => 'Solicitud enviada a {count} temporera(s)',
    'delete_confirm' => '¿Está seguro de cancelar esta solicitud?',
    
    // Errores de validación
    'error_required' => 'Este campo es requerido',
    'error_date_range' => 'La fecha de inicio debe ser anterior a la fecha de fin',
    'error_qty_invalid' => 'La cantidad necesaria debe ser mayor a 0',
    'error_no_agencies' => 'Debe seleccionar al menos una temporera',
];

