Removing Ka Extensions
======================

Before removing Ka Extensions uninstall the modules based on it (the modules listed on the
*Extensions > Ka Extensions* page): first on the *Ka Extensions* page, then on the *Extensions > Installer*
page. They do not work without the library, and OpenCart does not uninstall Ka Extensions while they are
installed. Uninstalling a module on the Ka Extensions page deletes its settings.

Steps
-----

1. Open *Extensions > Modifications*, disable the *Ka Extensions Ocmod* modification and click
   **Refresh**. Kamod is not started anymore.
2. Open *Extensions > Installer* and click **Uninstall** next to *Ka Extensions*. OpenCart deletes the
   ``extension/ka_extensions/`` directory and the modification.
3. Refresh modifications again and check the admin area and the storefront.
4. Clear the browser cache or check the store in a private browser window. If a cache plugin or
   server cache is used, clear it too.

What Stays in the Store
-----------------------

These files do not affect the store after the steps above. You can delete them:

- ``system/storage/cache.kamod/`` and ``system/storage/theme.kamod/`` - the kamod cache;
- ``system/config/kamod.php`` - the name of the admin directory saved by kamod;
- ``kamod.log``, ``kamod.errors.log``, ``ka_mail_log.log`` in the logs directory.

During installation Ka Extensions adds a few lines to the beginning of ``system/framework.php``.
They load ``extension/ocmod/system/framework.php`` (the copy of ``framework.php`` changed by
OCMOD modifications) when it exists, so OCMOD modifications of ``framework.php`` keep working.
The original file is saved as ``system/framework.php.<random>.bak``. To restore it, delete the lines
between ``//ka: required by kamod`` and ``///ka`` or replace ``framework.php`` with the backup copy.
Note that OpenCart 4.1 itself does not load this copy: after restoring the original file, OCMOD changes
of ``framework.php`` made by other modifications stop working.
