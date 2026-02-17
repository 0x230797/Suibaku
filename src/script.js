// Script para Suibaku

document.addEventListener('DOMContentLoaded', function() {
    
    // Previsualización de imagen antes de enviar
    const imageInput = document.querySelector('input[type="file"][name="image"]');
    if (imageInput) {
        imageInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            
            if (file) {
                // Validar tamaño
                const maxSize = 5 * 1024 * 1024; // 5MB
                if (file.size > maxSize) {
                    alert('La imagen es demasiado grande. Máximo 5MB.');
                    imageInput.value = '';
                    return;
                }
                
                // Validar formato
                const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Formato no permitido. Usa JPG, PNG, GIF o WEBP.');
                    imageInput.value = '';
                    return;
                }
                
                // Mostrar previsualización (opcional)
                console.log('Imagen seleccionada:', file.name, 'Tamaño:', (file.size / 1024).toFixed(2) + 'KB');
            }
        });
    }
    
    // Validación del formulario
    const postForm = document.querySelector('form[action*="bbs.php"]');
    if (postForm && !postForm.action.includes('mode=manage')) {
        postForm.addEventListener('submit', function(e) {
            const comment = document.querySelector('textarea[name="com"]');
            
            if (comment && comment.value.trim() === '') {
                e.preventDefault();
                alert('El comentario no puede estar vacío.');
                comment.focus();
                return false;
            }
            
            // Confirmación antes de enviar
            const submitButton = postForm.querySelector('input[type="submit"]');
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.value = 'Enviando...';
            }
        });
    }
    
    // Confirmación para acciones de moderación
    const deleteButtons = document.querySelectorAll('button[name="delete"]');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm('¿Estás seguro de que quieres eliminar este post?')) {
                e.preventDefault();
                return false;
            }
        });
    });
    
    const banButtons = document.querySelectorAll('button[name="ban"]');
    banButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm('¿Estás seguro de que quieres banear esta IP?')) {
                e.preventDefault();
                return false;
            }
        });
    });
    
    const deleteBanButtons = document.querySelectorAll('button[name="delete_ban"]');
    deleteBanButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm('¿Estás seguro de que quieres eliminar este post Y banear la IP?')) {
                e.preventDefault();
                return false;
            }
        });
    });
    
    // Auto-refresh para el panel de administración (opcional)
    const isAdminPanel = window.location.href.includes('mode=manage');
    if (isAdminPanel && localStorage.getItem('autoRefresh') === 'true') {
        setTimeout(function() {
            location.reload();
        }, 60000); // Refresh cada 60 segundos
    }
    
    // Contador de caracteres para el comentario
    const commentField = document.querySelector('textarea[name="com"]');
    if (commentField) {
        const maxLength = 500; // Debe coincidir con $commentlimit en PHP
        
        const counter = document.createElement('div');
        counter.style.fontSize = '0.8em';
        counter.style.color = '#666';
        
        const updateCounter = () => {
            const remaining = maxLength - commentField.value.length;
            counter.textContent = `Caracteres restantes: ${remaining}`;
            counter.style.color = remaining < 50 ? '#ff4444' : '#666';
        };
        
        commentField.addEventListener('input', updateCounter);
        commentField.parentElement.appendChild(counter);
        updateCounter();
    }
    
    // Expandir imágenes al hacer clic
    const thumbnails = document.querySelectorAll('.post-image a img');
    thumbnails.forEach(thumb => {
        thumb.style.cursor = 'pointer';
        thumb.title = 'Click para ver en tamaño completo';
    });
    
    // Atajos de teclado
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + Enter para enviar formulario
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            const activeForm = document.querySelector('form:focus-within');
            if (activeForm) {
                activeForm.submit();
            }
        }
    });
    
    // Mensaje de bienvenida en consola
    console.log('%c¡Bienvenido al Suibaku!', 'color: #117743; font-size: 16px; font-weight: bold;');
    console.log('%cRecuerda ser respetuoso y seguir las reglas.', 'color: #666; font-size: 12px;');
    
});

// Función auxiliar para formatear fechas
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleString('es-ES');
}

// Función para copiar texto al portapapeles
function copyToClipboard(text) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(() => {
            console.log('Copiado al portapapeles:', text);
        });
    }
}

// Agregar funcionalidad de cita rápida (quote)
document.addEventListener('click', function(e) {
    const postLink = e.target.closest('.post-num');
    if (!postLink) return;

    const commentField = document.querySelector('textarea[name="com"]');
    if (!commentField) return;

    const match = postLink.textContent.match(/(\d+)/);
    if (!match) return;

    e.preventDefault();

    const postId = match[1];
    commentField.value += `>>${postId}\n`;
    commentField.focus();
    commentField.scrollIntoView({ behavior: 'smooth', block: 'center' });
});
