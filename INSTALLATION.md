# Installation and activation path check

This plugin must be installed as a single WordPress plugin directory named:

```text
wp-content/plugins/tmw-cr-slot-sidebar-banner/
```

The main plugin file must be directly inside that directory:

```text
wp-content/plugins/tmw-cr-slot-sidebar-banner/tmw-cr-slot-sidebar-banner.php
```

Do not upload or leave an extracted directory named `tmw-cr-slotsidebar-banner` (missing the hyphen between `slot` and `sidebar`). Do not upload a ZIP that extracts to an extra wrapper directory such as `tmw-cr-slot-sidebar-banner-clean/tmw-cr-slot-sidebar-banner/`; WordPress must see the main PHP file one level below `wp-content/plugins/tmw-cr-slot-sidebar-banner/`.

If WordPress shows **Plugin file does not exist** during activation, remove any broken plugin directories and reinstall using the exact structure above. Then refresh the Plugins screen so WordPress rebuilds its plugin list before activating **TMW CR Offer Sidebar Banner**.
