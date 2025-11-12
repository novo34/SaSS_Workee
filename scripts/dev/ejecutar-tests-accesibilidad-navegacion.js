/**
 * Helper para Ejecutar Tests de Accesibilidad de Navegación - Workee
 * 
 * Carga y ejecuta automáticamente los tests de accesibilidad de navegación
 * 
 * Ejecutar en consola:
 *   // Copiar y pegar todo el contenido de este archivo
 */

(function() {
    'use strict';
    
    console.log('🚀 Cargando tests de accesibilidad de navegación...\n');
    
    // Verificar si ya está cargado
    if (typeof NavigationAccessibilityTests !== 'undefined') {
        console.log('✅ NavigationAccessibilityTests ya está cargado');
        console.log('💡 Ejecutar: NavigationAccessibilityTests.runAll()');
        return NavigationAccessibilityTests.runAll();
    }
    
    // Cargar script
    const script = document.createElement('script');
    const baseUrl = window.BASE_URL || '';
    const scriptPath = `${baseUrl}/scripts/dev/navigation-accessibility-tests.js`;
    
    script.src = scriptPath;
    script.onload = () => {
        console.log('✅ Script de tests cargado correctamente');
        console.log('⏳ Ejecutando tests...\n');
        
        // Esperar un momento para que el script se inicialice
        setTimeout(() => {
            if (typeof NavigationAccessibilityTests !== 'undefined') {
                const results = NavigationAccessibilityTests.runAll();
                console.log('\n💾 Resultados guardados en window.navigationAccessibilityResults');
                console.log('💡 Ver detalles: console.log(window.navigationAccessibilityResults)');
                return results;
            } else {
                console.error('❌ NavigationAccessibilityTests no está disponible después de cargar');
            }
        }, 500);
    };
    
    script.onerror = () => {
        console.error('❌ Error al cargar script de tests');
        console.log('💡 Verificar que la ruta es correcta:', scriptPath);
        console.log('💡 Intentar cargar manualmente:');
        console.log(`   const script = document.createElement('script');`);
        console.log(`   script.src = '${scriptPath}';`);
        console.log(`   document.head.appendChild(script);`);
    };
    
    document.head.appendChild(script);
})();

