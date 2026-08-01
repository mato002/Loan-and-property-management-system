export const PropertyFormModal = {
    FRAME_ID: 'property-form-modal',
    /** Teleported shell in layout — must survive #property-main Turbo swaps */
    HOST_MODAL_ID: 'property-form-modal',
    INPUT_NAME: '_property_form_modal',
    isPropertyCrudFormPath(pathname) {
        if (!pathname.startsWith('/property/')) {
            return false;
        }
        if (pathname.includes('/workspace/forms')) {
            return false;
        }
        return /\/(edit|create)\/?$/.test(pathname);
    },
};
