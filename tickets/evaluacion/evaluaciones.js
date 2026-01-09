// Función para mostrar evaluación (agregar después de mostrarToast)
function mostrarEvaluacion(idTicket, idEjecutante) {
    if (!idEjecutante || idEjecutante === '') {
        mostrarToast('Debe asignar un ejecutante antes de evaluar', 'warning');
        return;
    }

    const folio = decodeURIComponent(idTicket);
    console.log(folio)

    fetch(`tickets/evaluacion/evaluacion_modal.php?id_ticket=${folio}`)
        .then(response => response.text())
        .then(html => {
            document.body.insertAdjacentHTML('beforeend', html);
        })
        .catch(error => {
            console.error('Error:', error);
            mostrarToast('Error al cargar el formulario de evaluación', 'error');
        });
}

function cerrarModalEvaluacion() {
    const modal = document.querySelector('.fixed.inset-0.bg-black');
    if (modal) {
        modal.remove();
    }
}



function verResultadosEvaluacion(idTicket) {
    console.log('Ver resultados del ticket:', idTicket);

    fetch(`tickets/evaluacion/resultados_modal.php?id_ticket=${encodeURIComponent(idTicket)}`)
        .then(response => response.text())
        .then(html => {
            // Insertar modal en el body
            document.body.insertAdjacentHTML('beforeend', html);
        })
        .catch(err => {
            console.error('Error al cargar resultados:', err);
            if (typeof mostrarToast === 'function') {
                mostrarToast('❌ Error al cargar resultados', 'error');
            } else {
                alert('Error al cargar resultados');
            }
        });
}
