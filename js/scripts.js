// Función para manejar la carga de contenido
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar tooltips de Bootstrap
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Inicializar popovers de Bootstrap
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
});

// Función para mostrar mensajes de alerta personalizados
function showAlert(message, type = 'success') {
    Swal.fire({
        icon: type,
        title: type === 'success' ? '¡Éxito!' : 'Error',
        text: message,
        timer: type === 'success' ? 1500 : undefined,
        showConfirmButton: type !== 'success'
    });
}

// Función para confirmar acciones
function confirmAction(title, text, callback) {
    Swal.fire({
        title: title,
        text: text,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Sí, continuar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            callback();
        }
    });
}

// Función para mostrar indicador de carga
function showLoading(message = 'Procesando...') {
    Swal.fire({
        title: message,
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
}

// Función para cerrar indicador de carga
function hideLoading() {
    Swal.close();
}

// Función para manejar errores de fetch
function handleFetchError(error) {
    console.error('Error:', error);
    showAlert('Error al conectar con el servidor', 'error');
}

// Función para validar formularios
function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return false;

    const requiredFields = form.querySelectorAll('[required]');
    let isValid = true;

    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.classList.add('is-invalid');
            isValid = false;
        } else {
            field.classList.remove('is-invalid');
        }
    });

    return isValid;
}

// Función para formatear fechas
function formatDate(date) {
    return new Date(date).toLocaleString('es-ES', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Función para actualizar elementos dinámicamente
function updateElement(elementId, content) {
    const element = document.getElementById(elementId);
    if (element) {
        element.innerHTML = content;
    }
}

// Función para manejar errores de API
function handleApiError(response) {
    if (!response.ok) {
        throw new Error('Error en la respuesta del servidor');
    }
    return response.json();
}

// Función para serializar formularios
function serializeForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return null;
    return new FormData(form);
}

// Función para resetear formularios
function resetForm(formId) {
    const form = document.getElementById(formId);
    if (form) {
        form.reset();
        const invalidFields = form.querySelectorAll('.is-invalid');
        invalidFields.forEach(field => field.classList.remove('is-invalid'));
    }
} 