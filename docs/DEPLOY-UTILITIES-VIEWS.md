# Deploy: Utilities & water billing views

Production error `View [property.agent.revenue.utilities._setup] not found` happens when an **old** `utilities.blade.php` (with `@include('..._setup')`) is deployed without the `utilities/` partial folder.

The current app does **not** use `_setup`; the controller passes workspace data via `UtilityWorkspaceViewData::compose()`.

## Files that must exist on the server

```
resources/views/property/agent/revenue/utilities.blade.php
resources/views/property/agent/revenue/utilities/_workspace.blade.php
resources/views/property/agent/revenue/utilities/_charges_list.blade.php
resources/views/property/agent/revenue/utilities/_readings_list.blade.php
resources/views/property/agent/revenue/utilities/_tab_overview.blade.php
resources/views/property/agent/revenue/utilities/_toolbar.blade.php
resources/views/property/agent/partials/filter_toolbars/utilities.blade.php
app/Http/Controllers/Property/Agent/PropertyUtilityChargeController.php
app/Support/Property/UtilityWorkspaceViewData.php
```

## After deploy (SSH on production)

```bash
php artisan view:clear
php artisan config:clear
php artisan route:clear
```

## Git: these paths were untracked locally

```bash
git add resources/views/property/agent/revenue/utilities/
resources/views/property/agent/revenue/utilities.blade.php
resources/views/property/agent/partials/filter_toolbars/
git add app/Http/Controllers/Property/Agent/PropertyUtilityChargeController.php
git add app/Support/Property/UtilityWorkspaceViewData.php
```

Commit and push before deploying to production.
