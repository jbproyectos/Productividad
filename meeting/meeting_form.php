<form id="meetingForm" method="POST" action="procesar_reunion.php">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Título *</label>
            <input type="text" name="titulo" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Título de la reunión">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Fecha *</label>
            <input type="date" name="fecha" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        </div>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Hora *</label>
            <input type="time" name="hora" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Duración (minutos)</label>
            <input type="number" name="duracion" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="60" value="60">
        </div>
    </div>
    
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Descripción *</label>
        <textarea name="descripcion" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" rows="3" placeholder="Describe el objetivo de la reunión"></textarea>
    </div>
</form>