<?php
/**
 * Servicio de Matching de Trabajadores
 * 
 * Algoritmo de matching que considera:
 * - Disponibilidad
 * - Cercanía (Haversine)
 * - Skills
 * - Rating histórico
 * - Conflictos de horario
 * 
 * Ponderación configurable por tenant
 */

require_once __DIR__ . '/../helpers/db_helper.php';
require_once __DIR__ . '/../core/Config.php';

class MatchingService
{
    /**
     * Encuentra trabajadores que coinciden con una solicitud
     * 
     * @param int $requestId ID de la solicitud
     * @param int $agencyTenantId ID del tenant temporera
     * @param array $options Opciones de matching (weights, max_distance, etc.)
     * @return array Lista de trabajadores ordenados por score
     */
    public static function findMatches($requestId, $agencyTenantId, $options = [])
    {
        $conn = DBHelper::getConnection();
        
        // Obtener solicitud
        $stmt = $conn->prepare("
            SELECT sr.*, sr.requirements_json as requirements
            FROM staffing_requests sr
            WHERE sr.id = ?
        ");
        $stmt->bind_param('i', $requestId);
        $stmt->execute();
        $result = $stmt->get_result();
        $request = $result->fetch_assoc();
        $stmt->close();
        
        if (!$request) {
            return [];
        }
        
        $request['requirements'] = json_decode($request['requirements_json'] ?? '{}', true);
        
        // Obtener configuración de matching
        $maxDistance = $options['max_distance_km'] ?? Config::get($agencyTenantId, 'matching.max_distance_km', 50);
        $weightAvailability = $options['weight_availability'] ?? Config::get($agencyTenantId, 'matching.weight_availability', 0.3);
        $weightProximity = $options['weight_proximity'] ?? Config::get($agencyTenantId, 'matching.weight_proximity', 0.2);
        $weightSkills = $options['weight_skills'] ?? Config::get($agencyTenantId, 'matching.weight_skills', 0.3);
        $weightRating = $options['weight_rating'] ?? Config::get($agencyTenantId, 'matching.weight_rating', 0.2);
        
        // Normalizar pesos (deben sumar 1.0)
        $totalWeight = $weightAvailability + $weightProximity + $weightSkills + $weightRating;
        if ($totalWeight > 0) {
            $weightAvailability /= $totalWeight;
            $weightProximity /= $totalWeight;
            $weightSkills /= $totalWeight;
            $weightRating /= $totalWeight;
        }
        
        // Obtener trabajadores activos de la temporera
        $stmt = $conn->prepare("
            SELECT aw.*
            FROM agency_workers aw
            WHERE aw.tenant_id = ? AND aw.is_active = 1
        ");
        $stmt->bind_param('i', $agencyTenantId);
        $stmt->execute();
        $result = $stmt->get_result();
        $workers = [];
        while ($row = $result->fetch_assoc()) {
            $workers[] = $row;
        }
        $stmt->close();
        
        // Calcular score para cada trabajador
        $matches = [];
        $requestLat = $request['latitude'];
        $requestLng = $request['longitude'];
        $requestDateFrom = $request['date_from'];
        $requestDateTo = $request['date_to'];
        
        foreach ($workers as $worker) {
            $score = 0.0;
            $details = [];
            
            // 1. Disponibilidad (0.0 - 1.0)
            $availabilityScore = self::calculateAvailabilityScore($worker, $requestDateFrom, $requestDateTo, $conn);
            $score += $availabilityScore * $weightAvailability;
            $details['availability'] = $availabilityScore;
            
            // 2. Cercanía (0.0 - 1.0)
            if ($requestLat && $requestLng && $worker['latitude'] && $worker['longitude']) {
                $distanceKm = self::haversineDistance(
                    $requestLat, $requestLng,
                    $worker['latitude'], $worker['longitude']
                );
                $proximityScore = $distanceKm <= $maxDistance ? (1.0 - ($distanceKm / $maxDistance)) : 0.0;
                $score += $proximityScore * $weightProximity;
                $details['proximity'] = $proximityScore;
                $details['distance_km'] = round($distanceKm, 2);
            } else {
                $details['proximity'] = 0.5; // Neutral si no hay coordenadas
                $details['distance_km'] = null;
                $score += 0.5 * $weightProximity;
            }
            
            // 3. Skills (0.0 - 1.0)
            $skillsScore = self::calculateSkillsScore($worker, $request['requirements']);
            $score += $skillsScore * $weightSkills;
            $details['skills'] = $skillsScore;
            
            // 4. Rating histórico (0.0 - 1.0)
            $ratingScore = self::calculateRatingScore($worker['id'], $request['tenant_company_id'], $conn);
            $score += $ratingScore * $weightRating;
            $details['rating'] = $ratingScore;
            
            // 5. Verificar conflictos
            $hasConflict = self::hasConflict($worker['id'], $requestDateFrom, $requestDateTo, $conn);
            $details['has_conflict'] = $hasConflict;
            
            // Si tiene conflicto, reducir score significativamente
            if ($hasConflict) {
                $score *= 0.3; // Reducir a 30% del score original
            }
            
            $matches[] = [
                'worker_id' => $worker['id'],
                'worker_code' => $worker['worker_code'],
                'first_name' => $worker['first_name'],
                'last_name' => $worker['last_name'],
                'score' => round($score, 4),
                'details' => $details
            ];
        }
        
        // Ordenar por score descendente
        usort($matches, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });
        
        return $matches;
    }
    
    /**
     * Calcula score de disponibilidad (0.0 - 1.0)
     */
    private static function calculateAvailabilityScore($worker, $dateFrom, $dateTo, $conn)
    {
        $availability = json_decode($worker['availability_json'] ?? '{}', true);
        
        if (empty($availability)) {
            return 0.5; // Neutral si no hay disponibilidad definida
        }
        
        // Convertir fechas a timestamps
        $fromTs = strtotime($dateFrom);
        $toTs = strtotime($dateTo);
        $requestDayOfWeek = (int)date('w', $fromTs); // 0 = domingo, 6 = sábado
        $requestHour = (int)date('H', $fromTs);
        
        // Verificar disponibilidad por día de la semana
        $dayNames = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
        $dayName = $dayNames[$requestDayOfWeek];
        
        if (isset($availability[$dayName])) {
            $dayAvailability = $availability[$dayName];
            
            // Verificar si está disponible en ese horario
            if (isset($dayAvailability['available']) && $dayAvailability['available']) {
                if (isset($dayAvailability['hours'])) {
                    $hours = $dayAvailability['hours'];
                    // Verificar si el horario solicitado está dentro del rango disponible
                    if (isset($hours['from']) && isset($hours['to'])) {
                        $fromHour = (int)explode(':', $hours['from'])[0];
                        $toHour = (int)explode(':', $hours['to'])[0];
                        if ($requestHour >= $fromHour && $requestHour < $toHour) {
                            return 1.0;
                        }
                    } else {
                        return 1.0; // Disponible todo el día
                    }
                } else {
                    return 1.0; // Disponible sin restricción de horas
                }
            }
        }
        
        // Verificar disponibilidad por rango de fechas
        if (isset($availability['ranges'])) {
            foreach ($availability['ranges'] as $range) {
                if (isset($range['from']) && isset($range['to'])) {
                    $rangeFrom = strtotime($range['from']);
                    $rangeTo = strtotime($range['to']);
                    if ($fromTs >= $rangeFrom && $toTs <= $rangeTo) {
                        return 1.0;
                    }
                }
            }
        }
        
        return 0.0; // No disponible
    }
    
    /**
     * Calcula distancia Haversine entre dos puntos (en km)
     */
    private static function haversineDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371; // Radio de la Tierra en km
        
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        
        return $earthRadius * $c;
    }
    
    /**
     * Calcula score de skills (0.0 - 1.0)
     */
    private static function calculateSkillsScore($worker, $requirements)
    {
        if (empty($requirements) || empty($requirements['skills'])) {
            return 0.5; // Neutral si no hay requisitos de skills
        }
        
        $workerSkills = json_decode($worker['skills_json'] ?? '[]', true);
        if (empty($workerSkills)) {
            return 0.0;
        }
        
        $requiredSkills = is_array($requirements['skills']) ? $requirements['skills'] : [];
        if (empty($requiredSkills)) {
            return 0.5;
        }
        
        // Contar skills que coinciden
        $matchingSkills = 0;
        foreach ($requiredSkills as $requiredSkill) {
            if (in_array($requiredSkill, $workerSkills)) {
                $matchingSkills++;
            }
        }
        
        return $matchingSkills / count($requiredSkills);
    }
    
    /**
     * Calcula score de rating histórico (0.0 - 1.0)
     */
    private static function calculateRatingScore($workerId, $companyTenantId, $conn)
    {
        // Obtener rating promedio del trabajador con esta empresa
        $stmt = $conn->prepare("
            SELECT AVG(r.score) as avg_rating, COUNT(*) as rating_count
            FROM ratings r
            INNER JOIN work_logs wl ON r.work_log_id = wl.id
            WHERE r.worker_id = ? AND wl.tenant_id = ?
        ");
        $stmt->bind_param('ii', $workerId, $companyTenantId);
        $stmt->execute();
        $result = $stmt->get_result();
        $rating = $result->fetch_assoc();
        $stmt->close();
        
        if (!$rating || !$rating['avg_rating'] || $rating['rating_count'] == 0) {
            return 0.5; // Neutral si no hay rating
        }
        
        // Normalizar rating (asumiendo escala 1-5) a 0.0-1.0
        $normalizedRating = ($rating['avg_rating'] - 1) / 4; // (rating - 1) / (5 - 1)
        
        // Aplicar factor de confianza basado en cantidad de ratings
        $confidenceFactor = min(1.0, $rating['rating_count'] / 10); // Máxima confianza con 10+ ratings
        
        return $normalizedRating * $confidenceFactor + (1 - $confidenceFactor) * 0.5;
    }
    
    /**
     * Verifica si el trabajador tiene conflictos de horario
     */
    private static function hasConflict($workerId, $dateFrom, $dateTo, $conn)
    {
        // Verificar asignaciones existentes que se solapen
        $stmt = $conn->prepare("
            SELECT COUNT(*) as conflict_count
            FROM assignments a
            INNER JOIN shifts s ON a.shift_id = s.id
            WHERE a.worker_id = ? 
            AND s.date_from < ? 
            AND s.date_to > ?
            AND a.status IN ('assigned', 'confirmed')
        ");
        $stmt->bind_param('iss', $workerId, $dateTo, $dateFrom);
        $stmt->execute();
        $result = $stmt->get_result();
        $conflict = $result->fetch_assoc();
        $stmt->close();
        
        return ($conflict && $conflict['conflict_count'] > 0);
    }
}

