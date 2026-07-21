<style>
    @media (min-width: 768px) {
        .loan-sidebar[data-collapsed="1"] .loan-collapse-text {
            display: none !important;
            width: 0 !important;
            max-width: 0 !important;
            overflow: hidden !important;
            opacity: 0 !important;
            padding: 0 !important;
            margin: 0 !important;
        }

        .loan-sidebar[data-collapsed="1"] .loan-collapse-center {
            justify-content: center !important;
            gap: 0 !important;
        }

        .loan-sidebar[data-collapsed="1"] .loan-collapse-compact {
            padding-left: 0.5rem !important;
            padding-right: 0.5rem !important;
        }

        .loan-sidebar[data-collapsed="1"] .loan-sidebar-brand {
            justify-content: center;
            padding-left: 0.5rem;
            padding-right: 0.5rem;
        }

        .loan-sidebar[data-collapsed="1"] .loan-workspace-rail-item > a[aria-current="page"],
        .loan-sidebar[data-collapsed="1"] .loan-workspace-rail-item[data-section-active] > a {
            border-left-color: rgb(110 231 183) !important;
            background-color: rgb(64 104 102 / 0.35) !important;
            color: #fff !important;
        }

        .loan-sidebar[data-collapsed="1"] .loan-workspace-icon-wrap {
            margin-left: auto;
            margin-right: auto;
        }

        .loan-workspace-flyout {
            display: none;
        }

        .loan-sidebar[data-collapsed="1"] .loan-workspace-flyout {
            position: absolute;
            left: calc(100% + 0.35rem);
            top: 0;
            z-index: 5700;
            min-width: 13.5rem;
            max-width: 16rem;
            border-radius: 0.75rem;
            border: 1px solid rgb(64 104 102 / 0.85);
            background: rgb(23 54 58 / 0.98);
            box-shadow: 0 16px 40px rgb(0 0 0 / 0.35);
            backdrop-filter: blur(8px);
        }

        .loan-sidebar[data-collapsed="1"] .loan-workspace-rail-item:hover > .loan-workspace-flyout,
        .loan-sidebar[data-collapsed="1"] .loan-workspace-rail-item:focus-within > .loan-workspace-flyout {
            display: block;
        }

        .loan-sidebar[data-collapsed="1"] .loan-workspace-rail-item::before {
            content: '';
            position: absolute;
            left: 100%;
            top: 0;
            width: 0.5rem;
            height: 100%;
            z-index: 5650;
        }
    }

    .loan-sidebar[data-collapsed="0"] .loan-workspace-flyout {
        display: none !important;
    }
</style>
