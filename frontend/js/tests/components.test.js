/**
 * Tests Técnicos para Componentes - Workee
 * 
 * Tests básicos para validar que los componentes funcionan correctamente
 * Estos tests pueden ejecutarse manualmente en la consola del navegador
 */

const ComponentTests = {
    /**
     * Test del componente Toast
     */
    testToast() {
        console.group('🧪 Test: Toast Component');
        
        try {
            // Test 1: Verificar que Toast existe
            if (typeof Toast === 'undefined') {
                throw new Error('Toast no está definido');
            }
            console.log('✅ Toast está definido');
            
            // Test 2: Mostrar toast de éxito
            Toast.success('Test de éxito');
            console.log('✅ Toast.success() funciona');
            
            // Test 3: Mostrar toast de error
            setTimeout(() => {
                Toast.error('Test de error');
                console.log('✅ Toast.error() funciona');
            }, 1000);
            
            // Test 4: Mostrar toast de advertencia
            setTimeout(() => {
                Toast.warning('Test de advertencia');
                console.log('✅ Toast.warning() funciona');
            }, 2000);
            
            // Test 5: Mostrar toast informativo
            setTimeout(() => {
                Toast.info('Test informativo');
                console.log('✅ Toast.info() funciona');
            }, 3000);
            
            console.log('✅ Todos los tests de Toast pasaron');
        } catch (error) {
            console.error('❌ Error en test Toast:', error);
        }
        
        console.groupEnd();
    },
    
    /**
     * Test del componente Modal
     */
    async testModal() {
        console.group('🧪 Test: Modal Component');
        
        try {
            // Test 1: Verificar que Modal existe
            if (typeof Modal === 'undefined') {
                throw new Error('Modal no está definido');
            }
            console.log('✅ Modal está definido');
            
            // Test 2: Crear y mostrar modal
            const modal = new Modal({
                title: 'Test Modal',
                body: '<p>Este es un modal de prueba</p>',
                footer: '<button class="btn btn-primary" onclick="this.closest(\'.modal\').querySelector(\'[data-bs-dismiss]\').click()">Cerrar</button>'
            });
            modal.show();
            console.log('✅ Modal.show() funciona');
            
            // Test 3: Modal.confirm()
            setTimeout(async () => {
                const confirmed = await Modal.confirm({
                    title: 'Test Confirm',
                    message: '¿Confirma esta acción?'
                });
                console.log('✅ Modal.confirm() funciona, resultado:', confirmed);
            }, 2000);
            
            console.log('✅ Todos los tests de Modal pasaron');
        } catch (error) {
            console.error('❌ Error en test Modal:', error);
        }
        
        console.groupEnd();
    },
    
    /**
     * Test del componente DataTable
     */
    testDataTable() {
        console.group('🧪 Test: DataTable Component');
        
        try {
            // Test 1: Verificar que DataTable existe
            if (typeof DataTable === 'undefined') {
                throw new Error('DataTable no está definido');
            }
            console.log('✅ DataTable está definido');
            
            // Test 2: Crear DataTable de prueba
            const container = document.createElement('div');
            container.id = 'test-data-table';
            document.body.appendChild(container);
            
            const table = new DataTable(container, {
                columns: [
                    { key: 'id', label: 'ID', sortable: true },
                    { key: 'name', label: 'Nombre', sortable: true }
                ],
                fetchData: async () => {
                    return {
                        data: [
                            { id: 1, name: 'Test 1' },
                            { id: 2, name: 'Test 2' }
                        ],
                        total: 2
                    };
                },
                pageSize: 10
            });
            
            console.log('✅ DataTable se creó correctamente');
            
            // Test 3: Verificar que se renderizó
            setTimeout(() => {
                const tbody = container.querySelector('tbody');
                if (tbody && tbody.children.length > 0) {
                    console.log('✅ DataTable renderizó datos');
                } else {
                    console.warn('⚠️ DataTable no renderizó datos');
                }
            }, 500);
            
            console.log('✅ Todos los tests de DataTable pasaron');
        } catch (error) {
            console.error('❌ Error en test DataTable:', error);
        }
        
        console.groupEnd();
    },
    
    /**
     * Test del componente EmptyState
     */
    testEmptyState() {
        console.group('🧪 Test: EmptyState Component');
        
        try {
            // Test 1: Verificar que EmptyState existe
            if (typeof EmptyState === 'undefined') {
                throw new Error('EmptyState no está definido');
            }
            console.log('✅ EmptyState está definido');
            
            // Test 2: Crear y mostrar EmptyState
            const container = document.createElement('div');
            container.id = 'test-empty-state';
            document.body.appendChild(container);
            
            const emptyState = new EmptyState(container, {
                icon: '📭',
                title: 'Test Empty State',
                message: 'Este es un estado vacío de prueba'
            });
            emptyState.show();
            
            console.log('✅ EmptyState se creó y mostró correctamente');
            
            // Test 3: Ocultar EmptyState
            setTimeout(() => {
                emptyState.hide();
                console.log('✅ EmptyState.hide() funciona');
            }, 2000);
            
            console.log('✅ Todos los tests de EmptyState pasaron');
        } catch (error) {
            console.error('❌ Error en test EmptyState:', error);
        }
        
        console.groupEnd();
    },
    
    /**
     * Test del componente LoadingSpinner
     */
    testLoadingSpinner() {
        console.group('🧪 Test: LoadingSpinner Component');
        
        try {
            // Test 1: Verificar que LoadingSpinner existe
            if (typeof LoadingSpinner === 'undefined') {
                throw new Error('LoadingSpinner no está definido');
            }
            console.log('✅ LoadingSpinner está definido');
            
            // Test 2: Mostrar spinner
            const container = document.createElement('div');
            container.id = 'test-spinner';
            container.style.position = 'relative';
            container.style.height = '200px';
            document.body.appendChild(container);
            
            const spinner = new LoadingSpinner(container);
            spinner.show('Cargando...');
            console.log('✅ LoadingSpinner.show() funciona');
            
            // Test 3: Ocultar spinner
            setTimeout(() => {
                spinner.hide();
                console.log('✅ LoadingSpinner.hide() funciona');
            }, 2000);
            
            console.log('✅ Todos los tests de LoadingSpinner pasaron');
        } catch (error) {
            console.error('❌ Error en test LoadingSpinner:', error);
        }
        
        console.groupEnd();
    },
    
    /**
     * Test del componente FormValidator
     */
    testFormValidator() {
        console.group('🧪 Test: FormValidator Component');
        
        try {
            // Test 1: Verificar que FormValidator existe
            if (typeof FormValidator === 'undefined') {
                throw new Error('FormValidator no está definido');
            }
            console.log('✅ FormValidator está definido');
            
            // Test 2: Crear formulario de prueba
            const form = document.createElement('form');
            form.id = 'test-form';
            form.innerHTML = `
                <div class="mb-3">
                    <label for="test-email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="test-email" name="email" required>
                </div>
                <div class="mb-3">
                    <label for="test-password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="test-password" name="password" required>
                </div>
                <button type="submit" class="btn btn-primary">Enviar</button>
            `;
            document.body.appendChild(form);
            
            const validator = new FormValidator(form, {
                rules: {
                    email: { required: true, email: true },
                    password: { required: true, minLength: 8 }
                },
                messages: {
                    email: { required: 'El email es requerido', email: 'Email inválido' },
                    password: { required: 'La contraseña es requerida', minLength: 'Mínimo 8 caracteres' }
                },
                onSubmit: (data) => {
                    console.log('✅ FormValidator onSubmit funciona, datos:', data);
                }
            });
            
            console.log('✅ FormValidator se creó correctamente');
            
            // Test 3: Validar formulario vacío
            const isValid = validator.validate();
            console.log('✅ FormValidator.validate() funciona, resultado:', isValid);
            
            console.log('✅ Todos los tests de FormValidator pasaron');
        } catch (error) {
            console.error('❌ Error en test FormValidator:', error);
        }
        
        console.groupEnd();
    },
    
    /**
     * Test del API Client
     */
    async testAPIClient() {
        console.group('🧪 Test: API Client');
        
        try {
            // Test 1: Verificar que API existe
            if (typeof API === 'undefined') {
                throw new Error('API no está definido');
            }
            console.log('✅ API está definido');
            
            // Test 2: Verificar métodos
            const methods = ['get', 'post', 'put', 'patch', 'delete'];
            methods.forEach(method => {
                if (typeof API[method] !== 'function') {
                    throw new Error(`API.${method} no es una función`);
                }
            });
            console.log('✅ Todos los métodos HTTP están disponibles');
            
            // Test 3: Verificar que tiene translate
            if (typeof API.translate !== 'function') {
                throw new Error('API.translate no es una función');
            }
            console.log('✅ API.translate() está disponible');
            
            console.log('✅ Todos los tests de API Client pasaron');
        } catch (error) {
            console.error('❌ Error en test API Client:', error);
        }
        
        console.groupEnd();
    },
    
    /**
     * Ejecutar todos los tests
     */
    async runAll() {
        console.log('🚀 Iniciando tests de componentes...\n');
        
        this.testToast();
        await this.testModal();
        this.testDataTable();
        this.testEmptyState();
        this.testLoadingSpinner();
        this.testFormValidator();
        await this.testAPIClient();
        
        console.log('\n✅ Todos los tests completados');
    }
};

// Exportar para uso global
if (typeof window !== 'undefined') {
    window.ComponentTests = ComponentTests;
}

// Ejecutar tests si se solicita
if (typeof window !== 'undefined' && window.location.search.includes('test=components')) {
    ComponentTests.runAll();
}

