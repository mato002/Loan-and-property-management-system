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

    Alpine.data('propertyCardCarousel', (media = []) => {
        const items = (Array.isArray(media) ? media : [])
            .map((item) => {
                if (typeof item === 'string' && item) {
                    return { url: item, type: 'image' };
                }
                if (item && typeof item === 'object' && item.url) {
                    return {
                        url: String(item.url),
                        type: item.type === 'video' ? 'video' : 'image',
                    };
                }
                return null;
            })
            .filter(Boolean);

        return {
            items,
            index: 0,
            get hasMultiple() {
                return this.items.length > 1;
            },
            get current() {
                return this.items[this.index] || null;
            },
            next(event) {
                event?.preventDefault?.();
                event?.stopPropagation?.();
                if (!this.hasMultiple) return;
                this.index = (this.index + 1) % this.items.length;
            },
            prev(event) {
                event?.preventDefault?.();
                event?.stopPropagation?.();
                if (!this.hasMultiple) return;
                this.index = (this.index - 1 + this.items.length) % this.items.length;
            },
            goTo(i, event) {
                event?.preventDefault?.();
                event?.stopPropagation?.();
                this.index = i;
            },
        };
    });

    Alpine.data('propertyGallery', (media = []) => {
        const items = (Array.isArray(media) ? media : [])
            .map((item) => {
                if (typeof item === 'string' && item) {
                    return { url: item, type: 'image' };
                }
                if (item && typeof item === 'object' && item.url) {
                    return {
                        url: String(item.url),
                        type: item.type === 'video' ? 'video' : 'image',
                    };
                }
                return null;
            })
            .filter(Boolean);

        return {
            items,
            lightboxOpen: false,
            lightboxIndex: 0,
            get current() {
                return this.items[this.lightboxIndex] || null;
            },
            openAt(i) {
                if (!this.items.length) return;
                this.lightboxIndex = Math.max(0, Math.min(i, this.items.length - 1));
                this.lightboxOpen = true;
                document.body.classList.add('public-lightbox-open');
            },
            close() {
                this.lightboxOpen = false;
                document.body.classList.remove('public-lightbox-open');
            },
            next() {
                if (!this.items.length) return;
                this.lightboxIndex = (this.lightboxIndex + 1) % this.items.length;
            },
            prev() {
                if (!this.items.length) return;
                this.lightboxIndex = (this.lightboxIndex - 1 + this.items.length) % this.items.length;
            },
        };
    });

    Alpine.data('heroSearchToggle', () => ({
        listingType: 'rent',
    }));

    Alpine.data('applyLocationCascade', (listings = [], initial = {}) => {
        const rentBands = [10000, 15000, 20000, 30000, 50000, 80000, 120000];
        const layoutLabel = (key) => ({
            0: 'Studio / bedsitter',
            1: '1 bedroom',
            2: '2 bedrooms',
            '3plus': '3+ bedrooms',
        }[key] || 'Any layout');
        const layoutKey = (bedrooms) => {
            const n = Number(bedrooms);
            if (n <= 0) return '0';
            if (n >= 3) return '3plus';
            return String(n);
        };
        const uniqueBy = (rows, keyFn) => {
            const seen = new Map();
            rows.forEach((row) => {
                const key = keyFn(row);
                if (!seen.has(key)) seen.set(key, row);
            });
            return [...seen.values()];
        };

        return {
            listings: Array.isArray(listings) ? listings : [],
            city: initial.city || '',
            area: initial.area || '',
            property_id: initial.property_id ? String(initial.property_id) : '',
            unit_type: initial.unit_type || '',
            bedrooms: initial.bedrooms || 'any',
            max_rent: initial.max_rent ? String(initial.max_rent) : '',
            filteredBy(level) {
                return this.listings.filter((row) => {
                    if (level >= 1 && this.city && row.city !== this.city) return false;
                    if (level >= 2 && this.area && row.area !== this.area) return false;
                    if (level >= 3 && this.property_id && String(row.property_id) !== this.property_id) return false;
                    if (level >= 4 && this.unit_type && row.unit_type !== this.unit_type) return false;
                    if (level >= 5 && this.bedrooms && this.bedrooms !== 'any') {
                        if (layoutKey(row.bedrooms) !== this.bedrooms) return false;
                    }
                    return true;
                });
            },
            get cities() {
                return uniqueBy(this.listings, (row) => row.city)
                    .map((row) => {
                        const count = this.listings.filter((item) => item.city === row.city).length;
                        return { value: row.city, label: row.city_label || row.city, count };
                    })
                    .sort((a, b) => a.label.localeCompare(b.label));
            },
            get areas() {
                return uniqueBy(this.filteredBy(1), (row) => row.area)
                    .map((row) => {
                        const count = this.filteredBy(1).filter((item) => item.area === row.area).length;
                        return { value: row.area, label: row.area, count };
                    })
                    .sort((a, b) => a.label.localeCompare(b.label));
            },
            get buildings() {
                return uniqueBy(this.filteredBy(2), (row) => String(row.property_id))
                    .map((row) => {
                        const count = this.filteredBy(2).filter((item) => String(item.property_id) === String(row.property_id)).length;
                        return { value: String(row.property_id), label: row.property_label || row.property, count };
                    })
                    .sort((a, b) => a.label.localeCompare(b.label));
            },
            get unitTypes() {
                return uniqueBy(this.filteredBy(3), (row) => row.unit_type)
                    .filter((row) => row.unit_type)
                    .map((row) => ({ value: row.unit_type, label: row.unit_type_label || row.unit_type }))
                    .sort((a, b) => a.label.localeCompare(b.label));
            },
            get layouts() {
                return uniqueBy(this.filteredBy(4), (row) => layoutKey(row.bedrooms))
                    .map((row) => {
                        const key = layoutKey(row.bedrooms);
                        return { value: key, label: layoutLabel(key) };
                    })
                    .sort((a, b) => String(a.value).localeCompare(String(b.value)));
            },
            get rentBands() {
                const rents = this.filteredBy(5).map((row) => Number(row.rent) || 0);
                if (!rents.length) return [];
                const lowest = Math.min(...rents);
                return rentBands
                    .filter((band) => lowest <= band)
                    .map((band) => ({ value: String(band), label: `Up to KES ${band.toLocaleString()}` }));
            },
            get remainingCount() {
                let rows = this.filteredBy(5);
                if (this.max_rent) {
                    const cap = Number(this.max_rent);
                    rows = rows.filter((row) => Number(row.rent) <= cap);
                }
                return rows.length;
            },
            get needsArea() {
                return this.city !== '' && this.areas.length > 1;
            },
            get needsBuilding() {
                return this.city !== '' && this.buildings.length > 1;
            },
            get cityIsBroad() {
                return this.listings.some((row) => row.city === this.city && row.broad_city);
            },
            get canSearch() {
                if (!this.city) return false;
                if (this.needsArea && !this.area) return false;
                if (this.needsBuilding && !this.property_id) return false;
                return this.remainingCount > 0;
            },
            onCity() {
                this.area = '';
                this.property_id = '';
                this.resetHome();
                if (this.areas.length === 1) {
                    this.area = this.areas[0].value;
                    this.onArea();
                }
            },
            onArea() {
                this.property_id = '';
                this.resetHome();
                if (this.buildings.length === 1) {
                    this.property_id = this.buildings[0].value;
                    this.onBuilding();
                }
            },
            onBuilding() {
                this.resetHome();
            },
            resetHome() {
                this.unit_type = '';
                this.bedrooms = 'any';
                this.max_rent = '';
            },
        };
    });

    Alpine.data('listingShareSave', (url = '', text = '') => ({
        copied: false,
        async shareListing() {
            const payload = { title: document.title, text, url };
            try {
                if (navigator.share) {
                    await navigator.share(payload);
                    return;
                }
            } catch (error) {
                if (error?.name === 'AbortError') {
                    return;
                }
            }
            try {
                await navigator.clipboard.writeText(url || window.location.href);
                this.copied = true;
                setTimeout(() => { this.copied = false; }, 2000);
            } catch {
                window.prompt('Copy this listing link', url || window.location.href);
            }
        },
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
