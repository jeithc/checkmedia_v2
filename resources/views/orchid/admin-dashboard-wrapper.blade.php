<div id="livewire-dashboard-container">
    @livewire('admin-dashboard')
</div>

<script>
    // Reinicializar Livewire cuando Turbo carga la página
    document.addEventListener('turbo:load', function() {
        // Esperar un momento para que Livewire se inicialice
        setTimeout(function() {
            if (window.Livewire) {
                // Intentar métodos compatibles sin causar errores
                try {
                    if (typeof window.Livewire.restart === 'function') {
                        window.Livewire.restart();
                    }
                } catch(e) {
                    console.log('Livewire restart not available:', e);
                }
            }
        }, 100);
    });
</script>