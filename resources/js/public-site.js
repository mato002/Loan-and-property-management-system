import './swal-init';
import Alpine from 'alpinejs';

/**
 * Lightweight public-site interactivity — loaded only on marketing pages.
 * Keeps portal modules (Turbo, dashboards) off the public site.
 */
document.addEventListener('alpine:init', () => {
    const loadSavedIds = () => {
        try {
            const raw = localStorage.getItem('public.savedListings');
            const parsed = raw ? JSON.parse(raw) : [];
            return Array.isArray(parsed) ? parsed.map(String) : [];
        } catch {
            return [];
        }
    };

    Alpine.store('publicFavorites', {
        ids: loadSavedIds(),
        isSaved(id) {
            return this.ids.includes(String(id));
        },
        toggle(id) {
            const key = String(id);
            if (this.isSaved(key)) {
                this.ids = this.ids.filter((x) => x !== key);
            } else {
                this.ids = [...this.ids, key];
            }
            try {
                localStorage.setItem('public.savedListings', JSON.stringify(this.ids));
            } catch {
                // private mode
            }
        },
    });

    Alpine.data('propertyCardCarousel', (images = []) => ({
        images: Array.isArray(images) ? images.filter(Boolean) : [],
        index: 0,
        get hasMultiple() {
            return this.images.length > 1;
        },
        next(event) {
            event?.preventDefault?.();
            event?.stopPropagation?.();
            if (!this.hasMultiple) return;
            this.index = (this.index + 1) % this.images.length;
        },
        prev(event) {
            event?.preventDefault?.();
            event?.stopPropagation?.();
            if (!this.hasMultiple) return;
            this.index = (this.index - 1 + this.images.length) % this.images.length;
        },
        goTo(i, event) {
            event?.preventDefault?.();
            event?.stopPropagation?.();
            this.index = i;
        },
    }));

    Alpine.data('propertyGallery', (images = []) => ({
        images: Array.isArray(images) ? images.filter(Boolean) : [],
        lightboxOpen: false,
        lightboxIndex: 0,
        openAt(i) {
            if (!this.images.length) return;
            this.lightboxIndex = Math.max(0, Math.min(i, this.images.length - 1));
            this.lightboxOpen = true;
            document.body.classList.add('public-lightbox-open');
        },
        close() {
            this.lightboxOpen = false;
            document.body.classList.remove('public-lightbox-open');
        },
        next() {
            if (!this.images.length) return;
            this.lightboxIndex = (this.lightboxIndex + 1) % this.images.length;
        },
        prev() {
            if (!this.images.length) return;
            this.lightboxIndex = (this.lightboxIndex - 1 + this.images.length) % this.images.length;
        },
    }));

    Alpine.data('heroSearchToggle', () => ({
        listingType: 'rent',
    }));
});

if (!window.Alpine?.started) {
    window.Alpine = Alpine;
    Alpine.start();
}

/** Fade-in on scroll for premium feel */
const observeFadeIns = () => {
    const nodes = document.querySelectorAll('.public-animate-in');
    if (!nodes.length || !('IntersectionObserver' in window)) return;

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        },
        { rootMargin: '0px 0px -40px 0px', threshold: 0.08 }
    );

    nodes.forEach((el) => observer.observe(el));
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', observeFadeIns);
} else {
    observeFadeIns();
}
