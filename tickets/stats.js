function mostrarToast(mensaje, tipo = 'info') {
    const colores = {
        info: 'bg-blue-500',
        success: 'bg-green-500',
        error: 'bg-red-500',
        warning: 'bg-yellow-500'
    };

    const toast = document.createElement('div');
    toast.className = `${colores[tipo]} text-white px-4 py-2 rounded shadow fixed bottom-5 right-5 z-50 animate-bounce`;
    toast.textContent = mensaje;

    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}

// document.addEventListener('DOMContentLoaded', () => {
//     const estadoSelectModal = document.getElementById('estado-ticket-select');
//     const modal = document.getElementById('detail-modal');

//     if (!estadoSelectModal) {
//         console.error('No se encontró el select de estado.');
//         return;
//     }

//     estadoSelectModal.addEventListener('change', function () {
//         const itemId = modal.getAttribute('data-ticket-id');
//         const tipo = modal.getAttribute('data-tipo');
//         const nuevoEstado = this.value;

//         if (!itemId || !nuevoEstado || !tipo) {
//             console.warn('Falta itemId, estado o tipo.');
//             return;
//         }

//         let endpoint = '';

//         switch (tipo) {
//             case 'ticket':
//                 endpoint = 'tickets/actualizar_ticket.php';
//                 break;

//             case 'tarea':
//                 endpoint = 'tareas/actualizar_tarea.php';
//                 break;

//             case 'recurrent_task':
//                 endpoint = 'task/actualizar_ciclica.php';
//                 break;

//             case 'reunion':
//                 endpoint = 'reuniones/actualizar_reunion.php';
//                 break;

//             case 'stats':
//                 endpoint = 'stats/actualizar_stats.php';
//                 break;

//             default:
//                 console.error('Tipo no reconocido:', tipo);
//                 mostrarToast('Tipo de elemento no válido', 'error');
//                 return;
//         }

//         fetch(endpoint, {
//             method: 'POST',
//             headers: { 'Content-Type': 'application/json' },
//             body: JSON.stringify({
//                 id: itemId,
//                 campo: 'estado',
//                 valor: nuevoEstado
//             })
//         })
//         .then(res => res.json())
//         .then(data => {
//             if (data.success) {
//                 mostrarToast(`${tipo} ${itemId} actualizado a ${nuevoEstado}`, 'success');
//                 if (['Cerrado', 'Cancelado'].includes(nuevoEstado)) {
//                     estadoSelectModal.disabled = true;
//                     estadoSelectModal.classList.add('opacity-50', 'cursor-not-allowed');
//                 }
//             } else {
//                 mostrarToast('Error al actualizar', 'error');
//             }
//         })
//         .catch(() => {
//             mostrarToast('Error de conexión', 'error');
//         });
//     });
// });
