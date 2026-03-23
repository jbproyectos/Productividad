<form id="ticketForm" method="POST" action="procesar_ticket.php">
    <!-- Primera fila -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Área *</label>
            <select id="area" name="area" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <option value="">Selecciona un área</option>
                <?php foreach ($areas as $area): ?>
                    <option value="<?= $area['id'] ?>"><?= htmlspecialchars($area['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Dónde *</label>
            <select id="donde" name="donde" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <option value="">Primero selecciona un área</option>
            </select>
        </div>
    </div>

    <!-- Segunda fila -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Detalle de dónde</label>
            <select id="detalle_donde" name="detalle_donde" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <option value="">Primero selecciona Dónde</option>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Categoría de servicios *</label>
            <select id="categoria" name="categoria" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <option value="">Primero selecciona un área</option>
            </select>
        </div>
    </div>

    <!-- Subcategoría -->
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Subcategoría</label>
        <select id="subcategoria" name="subcategoria" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            <option value="">Primero selecciona una categoría</option>
        </select>
    </div>

    <!-- Descripción -->
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Descripción *</label>
        <textarea name="descripcion" id="descripcion" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" rows="3" placeholder="Describe el trabajo o solicitud"></textarea>
    </div>
</form>

<script>
document.getElementById('area').addEventListener('change', function() {
    const areaId = this.value;
    
    // Limpiar y deshabilitar selects dependientes
    const dondeSelect = document.getElementById('donde');
    const detalleDondeSelect = document.getElementById('detalle_donde');
    const categoriaSelect = document.getElementById('categoria');
    const subcategoriaSelect = document.getElementById('subcategoria');
    
    dondeSelect.innerHTML = '<option value="">Cargando...</option>';
    detalleDondeSelect.innerHTML = '<option value="">Primero selecciona Dónde</option>';
    categoriaSelect.innerHTML = '<option value="">Cargando...</option>';
    subcategoriaSelect.innerHTML = '<option value="">Primero selecciona una categoría</option>';
    
    dondeSelect.disabled = true;
    detalleDondeSelect.disabled = true;
    categoriaSelect.disabled = true;
    subcategoriaSelect.disabled = true;
    
    if (!areaId) {
        dondeSelect.innerHTML = '<option value="">Primero selecciona un área</option>';
        categoriaSelect.innerHTML = '<option value="">Primero selecciona un área</option>';
        return;
    }
    
    // Cargar opciones de "Dónde" según el área
    fetch(`tickets/get_donde.php?area_id=${areaId}`)
        .then(response => response.json())
        .then(data => {
            dondeSelect.innerHTML = '<option value="">Selecciona una ubicación</option>';
            data.forEach(item => {
                dondeSelect.innerHTML += `<option value="${item.id}">${item.nombre}</option>`;
            });
            dondeSelect.disabled = false;
        });
    
    // Cargar opciones de "Categoría" según el área
    fetch(`tickets/get_categorias.php?area_id=${areaId}`)
        .then(response => response.json())
        .then(data => {
            categoriaSelect.innerHTML = '<option value="">Selecciona una categoría</option>';
            data.forEach(item => {
                categoriaSelect.innerHTML += `<option value="${item.id}">${item.nombre_cat}</option>`;
            });
            categoriaSelect.disabled = false;
        });
});

// Cuando se selecciona "Dónde", cargar sus detalles
document.getElementById('donde').addEventListener('change', function() {
    const dondeId = this.value;
    const detalleDondeSelect = document.getElementById('detalle_donde');
    
    detalleDondeSelect.innerHTML = '<option value="">Cargando...</option>';
    detalleDondeSelect.disabled = true;
    
    if (!dondeId) {
        detalleDondeSelect.innerHTML = '<option value="">Primero selecciona Dónde</option>';
        return;
    }
    
    // Obtener el área seleccionada
    const areaId = document.getElementById('area').value;
    
    // Cargar detalles de "Dónde" según el área y el donde seleccionado
    fetch(`tickets/get_detalle_donde.php?area_id=${areaId}&donde_id=${dondeId}`)
        .then(response => response.json())
        .then(data => {
            detalleDondeSelect.innerHTML = '<option value="">Selecciona un detalle</option>';
            data.forEach(item => {
                detalleDondeSelect.innerHTML += `<option value="${item.id}">${item.nombre}</option>`;
            });
            detalleDondeSelect.disabled = false;
        });
});

// Cuando se selecciona "Categoría", cargar sus subcategorías
document.getElementById('categoria').addEventListener('change', function() {
    const categoriaId = this.value;
    const subcategoriaSelect = document.getElementById('subcategoria');
    
    subcategoriaSelect.innerHTML = '<option value="">Cargando...</option>';
    subcategoriaSelect.disabled = true;
    
    if (!categoriaId) {
        subcategoriaSelect.innerHTML = '<option value="">Primero selecciona una categoría</option>';
        return;
    }
    
    // Obtener el área seleccionada
    const areaId = document.getElementById('area').value;
    
    // Cargar subcategorías según el área y la categoría
    fetch(`tickets/get_subcategorias.php?area_id=${areaId}&categoria_id=${categoriaId}`)
        .then(response => response.json())
        .then(data => {
            subcategoriaSelect.innerHTML = '<option value="">Selecciona una subcategoría</option>';
            data.forEach(item => {
                subcategoriaSelect.innerHTML += `<option value="${item.id}">${item.nombre_sucat}</option>`;
            });
            subcategoriaSelect.disabled = false;
        });
});
</script>