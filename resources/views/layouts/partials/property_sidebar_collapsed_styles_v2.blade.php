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

        .property-sidebar[data-collapsed="1"] nav.custom-scrollbar {
            overflow-y: auto;
            overflow-x: visible;
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

        .property-sidebar[data-collapsed="1"] .property-workspace-rail-item > a[aria-current="page"],
        .property-sidebar[data-collapsed="1"] .property-workspace-rail-item[data-section-active] > a {
            border-left-color: rgb(110 231 183) !important;
            background-color: rgb(64 104 102 / 0.8) !important;
            color: #fff !important;
        }

        .property-sidebar[data-collapsed="1"] .property-workspace-rail-item > a[aria-current="page"] .property-workspace-icon-wrap,
        .property-sidebar[data-collapsed="1"] .property-workspace-rail-item[data-section-active] > a .property-workspace-icon-wrap {
            background-color: rgb(64 104 102 / 0.85) !important;
            box-shadow: 0 0 0 1px rgb(110 231 183 / 0.45);
        }

        .property-sidebar[data-collapsed="1"] .property-workspace-icon-wrap {
            margin-left: auto;
            margin-right: auto;
        }

        /* Collapsed rail flyout */
        .property-workspace-flyout {
            display: none;
        }

        .property-sidebar[data-collapsed="1"] .property-workspace-flyout {
            position: absolute;
            left: calc(100% + 0.35rem);
            top: 0;
            z-index: 5700;
            min-width: 13.5rem;
            max-width: 16rem;
            border-radius: 0.75rem;
            border: 1px solid rgb(64 104 102 / 0.85);
            background: rgb(36 61 61 / 0.98);
            box-shadow: 0 16px 40px rgb(0 0 0 / 0.35);
            backdrop-filter: blur(8px);
        }

        .property-sidebar[data-collapsed="1"] .property-workspace-rail-item:hover > .property-workspace-flyout,
        .property-sidebar[data-collapsed="1"] .property-workspace-rail-item:focus-within > .property-workspace-flyout {
            display: block;
        }

        .property-sidebar[data-collapsed="1"] .property-workspace-rail-item::before {
            content: '';
            position: absolute;
            left: 100%;
            top: 0;
            width: 0.5rem;
            height: 100%;
            z-index: 5650;
        }
    }

    .property-db-safety-collapsed {
        display: none;
    }

    /* Expanded sidebar: hide flyout panels (main link is enough) */
    .property-sidebar[data-collapsed="0"] .property-workspace-flyout {
        display: none !important;
    }
</style>
