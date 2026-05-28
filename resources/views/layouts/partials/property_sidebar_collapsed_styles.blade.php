<style>
    @media (min-width: 1024px) {
        .property-sidebar[data-collapsed="1"] .property-collapse-text {
            display: none !important;
            width: 0 !important;
            max-width: 0 !important;
            overflow: hidden !important;
            opacity: 0 !important;
            padding: 0 !important;
            margin: 0 !important;
        }

        .property-sidebar[data-collapsed="1"] .property-collapse-hide {
            display: none !important;
        }

        .property-sidebar[data-collapsed="1"] .property-collapse-center {
            justify-content: center !important;
            gap: 0 !important;
        }

        .property-sidebar[data-collapsed="1"] .property-collapse-compact {
            padding-left: 0.5rem !important;
            padding-right: 0.5rem !important;
        }

        .property-sidebar[data-collapsed="1"] [data-property-nav-section] > div,
        .property-sidebar[data-collapsed="1"] .property-nav-subgroup {
            display: none !important;
        }

        .property-sidebar[data-collapsed="1"] .property-section-rail-icon {
            display: flex !important;
        }

        .property-sidebar[data-collapsed="1"] .property-section-expanded-only {
            display: none !important;
        }

        .property-sidebar[data-collapsed="1"] nav.custom-scrollbar {
            overflow-y: auto;
            scrollbar-width: none;
        }

        .property-sidebar[data-collapsed="1"] nav.custom-scrollbar::-webkit-scrollbar {
            display: none;
            width: 0;
            height: 0;
        }

        .property-sidebar[data-collapsed="1"] .property-sidebar-brand {
            justify-content: center;
            padding-left: 0.5rem;
            padding-right: 0.5rem;
        }

        .property-sidebar[data-collapsed="1"] .property-sidebar-brand-logo {
            margin-left: auto;
            margin-right: auto;
        }

        .property-sidebar[data-collapsed="1"] .property-sidebar-collapse-toggle-wrap {
            justify-content: center;
        }

        .property-sidebar[data-collapsed="1"] .property-sidebar-footer-link {
            justify-content: center;
            padding-left: 0.5rem;
            padding-right: 0.5rem;
        }

        .property-sidebar[data-collapsed="1"] .property-sidebar-footer-link .property-sidebar-avatar {
            width: 2.25rem;
            height: 2.25rem;
            font-size: 0.875rem;
        }

        .property-sidebar[data-collapsed="1"] .property-sidebar-footer-link .property-collapse-icon-only {
            margin-left: 0;
            margin-right: 0;
        }

        .property-sidebar[data-collapsed="1"] .property-db-safety-expanded {
            display: none !important;
        }

        .property-sidebar[data-collapsed="1"] .property-db-safety-collapsed {
            display: flex !important;
        }

        .property-sidebar[data-collapsed="1"] .property-nav-single-link {
            justify-content: center;
            padding-left: 0.5rem;
            padding-right: 0.5rem;
        }

        .property-sidebar[data-collapsed="1"] .property-section-toggle {
            justify-content: center;
            padding: 0.625rem 0.5rem;
        }

        .property-sidebar[data-collapsed="1"] nav > div.border-t {
            border-top-width: 0;
            margin-top: 0.125rem;
            padding-top: 0;
        }

        .property-sidebar[data-collapsed="1"] [data-section-active] > .property-section-toggle,
        .property-sidebar[data-collapsed="1"] a[aria-current="page"],
        .property-sidebar[data-collapsed="1"] button[aria-current="page"] {
            border-left-color: rgb(110 231 183) !important;
            background-color: rgb(64 104 102 / 0.8) !important;
            color: #fff !important;
        }

        .property-sidebar[data-collapsed="1"] [data-section-active] > .property-section-toggle .property-section-rail-icon,
        .property-sidebar[data-collapsed="1"] a[aria-current="page"] i,
        .property-sidebar[data-collapsed="1"] a[aria-current="page"] svg {
            color: rgb(197 235 232) !important;
        }
    }

    .property-section-rail-icon {
        display: none;
    }

    .property-db-safety-collapsed {
        display: none;
    }
</style>
