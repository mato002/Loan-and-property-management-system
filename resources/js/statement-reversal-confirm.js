import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';

document.addEventListener('submit', async (event) => {
    const form = event.target.closest('form[data-swal-confirm]');
    if (!form || form.dataset.swalSubmitting === '1') return;

    event.preventDefault();
    const result = await Swal.fire({
        icon: 'warning',
        title: form.dataset.swalTitle || 'Please confirm',
        text: form.dataset.swalConfirm || 'Are you sure?',
        showCancelButton: true,
        confirmButtonText: form.dataset.swalConfirmText || 'Yes, continue',
        cancelButtonText: form.dataset.swalCancelText || 'Cancel',
        confirmButtonColor: '#b42318',
        cancelButtonColor: '#64748b',
        reverseButtons: true,
        focusCancel: true,
    });

    if (result.isConfirmed) {
        form.dataset.swalSubmitting = '1';
        form.submit();
    }
});
