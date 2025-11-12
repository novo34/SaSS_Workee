<?php

/**
 * Traducciones - Propuestas de Candidatos (Staffing Proposals)
 * 
 * Claves para la UI de propuestas
 */

return [
    // Títulos y headers
    'title' => 'Propuestas de Candidatos',
    'new' => 'Nueva Propuesta',
    'edit' => 'Editar Propuesta',
    'view' => 'Ver Propuesta',

    // Filtros
    'filter_status' => 'Estado',
    'filter_request' => 'Solicitud',
    'filter_all' => 'Todos',
    'filter_pending' => 'Pendientes',
    'filter_accepted' => 'Aceptadas',
    'filter_rejected' => 'Rechazadas',
    'filter_changed' => 'Con cambios',
    'filter_apply' => 'Filtrar',
    'filter_clear' => 'Limpiar',
    'filter_request_placeholder' => 'ID de solicitud',

    // Estados
    'status_pending' => 'Pendiente',
    'status_accepted' => 'Aceptada',
    'status_rejected' => 'Rechazada',
    'status_changed' => 'Con cambios',

    // Modal
    'modal_title' => 'Nueva Propuesta',
    'field_request' => 'Solicitud',
    'field_request_select' => 'Seleccionar solicitud...',
    'field_auto_matching' => 'Usar Auto-Matching (recomendado)',
    'field_auto_matching_desc' => 'El sistema encontrará automáticamente los mejores candidatos',
    'matching_options_title' => 'Opciones de Matching',
    'field_max_distance' => 'Distancia Máxima (km)',
    'manual_candidates_title' => 'Candidatos Manuales',
    'manual_candidates_desc' => 'Selecciona trabajadores de la lista',

    // Botones
    'btn_cancel' => 'Cancelar',
    'btn_submit' => 'Crear Propuesta',
    'btn_accept' => 'Aceptar',
    'btn_reject' => 'Rechazar',
    'btn_change' => 'Solicitar Cambios',

    // Tabla
    'table_no_data' => 'No hay propuestas creadas',
    'table_loading' => 'Cargando...',
    'table_id' => 'ID',
    'table_request' => 'Solicitud',
    'table_status' => 'Estado',
    'table_dates' => 'Fechas',
    'table_location' => 'Ubicación',
    'table_candidates' => 'Candidatos',
    'table_score' => 'Score',
    'table_actions' => 'Acciones',

    // Candidatos
    'candidate_name' => 'Nombre',
    'candidate_code' => 'Código',
    'candidate_score' => 'Score de Matching',
    'candidate_distance' => 'Distancia',
    'candidate_availability' => 'Disponibilidad',
    'candidate_skills' => 'Skills',
    'candidate_rating' => 'Rating',
    'candidate_details' => 'Detalles',

    // Mensajes
    'loading' => 'Cargando propuestas...',
    'error_loading' => 'Error al cargar propuestas',
    'error_connection' => 'Error de conexión',
    'created_success' => 'Propuesta creada correctamente',
    'accepted_success' => 'Propuesta aceptada correctamente',
    'rejected_success' => 'Propuesta rechazada correctamente',
    'changed_success' => 'Cambios solicitados correctamente',
    'delete_confirm' => '¿Está seguro de eliminar esta propuesta?',

    // Errores
    'error_no_request' => 'Debe seleccionar una solicitud',
    'error_no_candidates' => 'No se encontraron candidatos que coincidan con los requisitos',
    'error_matching_failed' => 'Error al ejecutar el matching automático',
];
