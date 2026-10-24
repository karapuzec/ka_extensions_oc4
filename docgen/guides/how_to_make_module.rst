How To Make Module
==================

A module based on Ka Extensions is a standard OpenCart 4 extension. It is uploaded on the
*Extensions > Installer* page and installed on the *Extensions > Ka Extensions* page.

Module Directory Structure
--------------------------

All files of the module are in its own directory ``extension/<module>/``, where ``<module>`` is
the extension code. OpenCart 4 takes the code from the archive name, so the archive of the
module ``my_module`` must be called ``my_module.ocmod.zip``. Files are at the root of the archive.

::

    extension/my_module/
        install.json                         - manifest, see below
        admin/controller/extension.php       - installer and settings page
        admin/controller/...                 - other admin controllers of the module
        admin/language/en-gb/extension.php   - language file of the settings page
        admin/model/...
        admin/view/template/...
        catalog/controller/...               - storefront files of the module
        catalog/language/en-gb/...
        catalog/model/...
        catalog/view/template/...
        system/library/...
        kamod/                               - changes of OpenCart and other modules (optional)
            admin/...
            catalog/...
            system/...
            extension/<other_module>/...
        active.kamod                         - created on installation, do not pack it

This is the standard OpenCart 4 layout, only ``kamod/`` and ``active.kamod`` are added by
Ka Extensions.

Manifest (install.json)
-----------------------

Ka Extensions lists a module on its page only when ``install.json`` has
``"ka_type": "ka_extensions"``:

.. code-block:: json

    {
      "name": "My Module",
      "version": "1.0.0.0",
      "author": "My Company",
      "link": "https://www.example.com/my-module",
      "ka_type": "ka_extensions",
      "ka_extensions_version": "5.1.1.0",
      "store_version_min": "4.1.0.3",
      "store_version_max": "4.1.9.9"
    }

``ka_extensions_version`` is the version of Ka Extensions the module is made for. The module can
be installed with Ka Extensions versions that differ in the last number only, here ``5.1.1.0`` -
``5.1.1.99``. All fields are described in :doc:`install_json`.

Namespaces
----------

OpenCart 4 loads extension classes by namespace. The namespace is built from the extension code
in CamelCase (``my_module`` - ``MyModule``) and the path of the file:

=================================================  =====================================================
File                                               Class
=================================================  =====================================================
``admin/controller/extension.php``                 ``\Opencart\Admin\Controller\Extension\MyModule\Extension``
``admin/controller/report/sales.php``              ``\Opencart\Admin\Controller\Extension\MyModule\Report\Sales``
``admin/model/report.php``                         ``\Opencart\Admin\Model\Extension\MyModule\Report``
``catalog/controller/product_label.php``           ``\Opencart\Catalog\Controller\Extension\MyModule\ProductLabel``
``system/library/parser.php``                      ``\Opencart\System\Library\Extension\MyModule\Parser``
=================================================  =====================================================

Routes of the module start with ``extension/<module>/``, for example
``extension/my_module/report/sales``. Classes of Ka Extensions itself are in the
``\Opencart\Extension\KaExtensions\System`` namespace (``extension/ka_extensions/system/``).

Files in ``kamod/`` follow other rules, see :doc:`overriding_core_files`.

Installer and Settings Page
---------------------------

The Ka Extensions page calls the ``install`` and ``uninstall`` methods of the
``extension/<module>/extension`` route and opens this route as the settings page of the module.
So every module has ``admin/controller/extension.php``. It extends
``\Opencart\Extension\KaExtensions\System\ControllerSettings``:

.. code-block:: php

    <?php
    namespace Opencart\Admin\Controller\Extension\MyModule;

    class Extension extends \Opencart\Extension\KaExtensions\System\ControllerSettings {

        protected $ext_code = 'my_module';

        protected function getFields() {
            return array();
        }
    }

``$ext_code`` must be equal to the extension code. The installer:

- checks the Ka Extensions version from ``ka_extensions_version``;
- creates and updates database tables of the module (``$this->tables``, see :doc:`database`);
- saves default values of the settings and gives the current user group access to the settings page.

Settings fields and custom pages are described in :doc:`settings_page`.

Installation and active.kamod
-----------------------------

1. Upload ``my_module.ocmod.zip`` on the *Extensions > Installer* page and click **Install** there.
   OpenCart copies the files to ``extension/my_module/``.
2. Open *Extensions > Ka Extensions* and click **Install** next to the module.

On the second step Ka Extensions creates the ``extension/my_module/active.kamod`` file (when the module
has the ``kamod`` directory) and marks the kamod cache as invalid. The cache is rebuilt on the next
request. Kamod uses the ``kamod`` directory only of modules with ``active.kamod``, the ``ka_extensions``
module is always active. Uninstalling the module on the Ka Extensions page removes ``active.kamod``.

.. note::

    The **Install** button on the Ka Extensions page is enabled for modules registered with a license
    key or marked as free on ka-station.com.

Development Tips
----------------

- Define ``KAMOD_DEBUG`` in ``config.php`` and ``admin/config.php`` to rebuild the kamod cache on
  every request (see :doc:`useful_constants`).
- Generated classes and templates are in ``system/storage/cache.kamod/``.
- Kamod messages are written to ``kamod.log`` and errors to ``kamod.errors.log`` in the logs directory.
