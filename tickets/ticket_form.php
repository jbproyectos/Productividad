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
                <option value="">Selecciona una ubicación</option>
                <?php foreach ($donde_tickets as $donde): ?>
                    <option value="<?= $donde['id'] ?>"><?= htmlspecialchars($donde['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Segunda fila -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Detalle de dónde</label>
            <select id="detalle_donde" name="detalle_donde" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <option value="">Selecciona un detalle</option>
                <?php foreach ($detalle_donde_tickets as $detalle): ?>
                    <option value="<?= $detalle['id'] ?>"><?= htmlspecialchars($detalle['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Categoría de servicios *</label>
            <select id="categoria" name="categoria" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <option value="">Selecciona una categoría</option>
                <?php foreach ($categorias as $categoria): ?>
                    <option value="<?= $categoria['id'] ?>"><?= htmlspecialchars($categoria['nombre_cat']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Subcategoría -->
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Subcategoría</label>
        <select id="subcategoria" name="subcategoria" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            <option value="">Selecciona una subcategoría</option>
        </select>
    </div>

    <!-- Descripción -->
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Descripción *</label>
        <textarea name="descripcion" id="descripcion" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" rows="3" placeholder="Describe el trabajo o solicitud"></textarea>
    </div>
</form>