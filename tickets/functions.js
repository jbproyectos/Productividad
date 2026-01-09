
document.getElementById('categoria').addEventListener('change', function() {
    const categoriaId = this.value;
    const subcategoriaSelect = document.getElementById('subcategoria');

    // Limpia las subcategorías anteriores
    subcategoriaSelect.innerHTML = '<option value="">Cargando...</option>';

    fetch(`tickets/get_subcategorias.php?categoria_id=${categoriaId}`)
        .then(response => response.json())
        .then(data => {
            subcategoriaSelect.innerHTML = '<option value="">Selecciona una subcategoría</option>';
            data.forEach(item => {
                const option = document.createElement('option');
                option.value = item.id;
                option.textContent = item.nombre_sucat;
                subcategoriaSelect.appendChild(option);
            });
        })
        .catch(() => {
            subcategoriaSelect.innerHTML = '<option value="">Error al cargar</option>';
        });
});



async function saveItem() {
    // Obtenemos los valores desde los select y textarea
    const formContainer = document.getElementById("ticket-form");

    const formData = new FormData();
    formData.append("area", formContainer.querySelector("#area").value);
    formData.append("donde", formContainer.querySelector("#donde").value);
    formData.append("detalle_donde", formContainer.querySelector("#detalle_donde").value);
    formData.append("categoria_servicio", formContainer.querySelector("#categoria").value);
    formData.append("subcategoria", formContainer.querySelector("#subcategoria").value);
    formData.append("descripcion", formContainer.querySelector("#descripcion").value);

    try {
        const response = await fetch("tickets/insert_ticket.php", {
            method: "POST",
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            alert("✅ " + result.message);
            closeModal(); // cerrar modal
            // Limpia los campos después de guardar
            formContainer.querySelectorAll("select, textarea").forEach(el => el.value = "");
        } else {
            alert("⚠️ " + result.message);
        }
    } catch (error) {
        alert("Error al enviar el ticket: " + error.message);
    }
}
