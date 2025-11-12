<?php

/**
 * Traducciones - Notificaciones
 * 
 * Plantillas de notificaciones multi-idioma con variables
 * Variables: {variable_name} se reemplazan con datos reales
 */

return [
    // Títulos
    'default_title' => 'Notificación Workee',
    'request_created_title' => 'Nueva Solicitud de Personal',
    'proposal_received_title' => 'Nueva Propuesta Recibida',
    'proposal_accepted_title' => 'Propuesta Aceptada',
    'shift_assigned_title' => 'Turno Asignado',
    'shift_changed_title' => 'Cambio en Turno',
    'report_submitted_title' => 'Parte Enviado',
    'dispute_created_title' => 'Nueva Disputa',
    'dispute_resolved_title' => 'Disputa Resuelta',
    'invoice_issued_title' => 'Factura Emitida',
    'worklog_approved_title' => 'Work Log Aprobado',
    'worklog_rejected_title' => 'Work Log Rechazado',
    
    // Plantillas
    'default' => 'Tienes una nueva notificación de Workee',
    
    'request_created' => 'Se ha creado una nueva solicitud de personal para el proyecto {project_name} del {date_from} al {date_to}. Se necesitan {qty_needed} trabajadores.',
    
    'proposal_received' => 'Has recibido una propuesta de {agency_name} para la solicitud del proyecto {project_name}. Revisa los candidatos propuestos.',
    
    'proposal_accepted' => 'Tu propuesta para la solicitud del proyecto {project_name} ha sido aceptada. Los candidatos han sido confirmados.',
    
    'shift_assigned' => 'Se te ha asignado un turno el {date} de {start_time} a {end_time} en {location}.',
    
    'shift_changed' => 'Tu turno del {date} ha sido modificado. Nuevo horario: {start_time} a {end_time}.',
    
    'report_submitted' => 'El parte del grupo {group_name} del {date} ha sido enviado y está pendiente de aprobación.',
    
    'dispute_created' => 'Se ha creado una disputa sobre el work log del {date}. Motivo: {reason}.',
    
    'dispute_resolved' => 'La disputa sobre el work log del {date} ha sido resuelta. Resolución: {resolution}.',
    
    'invoice_issued' => 'Se ha emitido una nueva factura #{invoice_number} por un total de {total_amount} {currency} correspondiente al período {period_from} - {period_to}.',
    
    'worklog_approved' => 'Tu work log del {date} ({hours} horas) ha sido aprobado.',
    
    'worklog_rejected' => 'Tu work log del {date} ha sido rechazado. Motivo: {reason}.',
];

