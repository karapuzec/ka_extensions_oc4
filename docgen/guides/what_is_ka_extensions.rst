What Is Ka Extensions
=====================

Ka Extensions is a shared library for OpenCart 4 used by the modules of Karapuz Team
(`ka-station.com <https://www.ka-station.com>`_). It is installed as a separate OpenCart extension
with the code ``ka_extensions``, so updates and fixes are applied to all modules at once.

Requirements: OpenCart 4.1.0.3 - 4.1.x, see :doc:`how_to_install_ka_extensions`.

Kamod Engine
------------

Kamod lets a module change OpenCart classes and templates by inheritance instead of patching
the original files:

- a module class extends the original controller, model or library class and overrides
  only the methods it needs (see :doc:`overriding_core_files`);
- several modules can extend the same class: kamod builds a chain where every class extends
  the previous one;
- twig templates are extended in the same way (see :doc:`twig_templates`);
- a module can extend files of another module (see :doc:`overriding_other_modules`).

Kamod generates the resulting classes and templates in the kamod cache
(``system/storage/cache.kamod/``) and loads them instead of the original files.

KaPatch
-------

KaPatch is a part of kamod that makes small text changes in files, like OCMOD:
plain text or regular expression replacement (see :doc:`apply_kapatch`).

Ka Extensions Page
------------------

Ka Extensions adds the *Extensions > Ka Extensions* page to the admin area. Modules based on
the library (``"ka_type": "ka_extensions"`` in ``install.json``, see :doc:`install_json`) are listed
there, installed, uninstalled and registered with a license key.

Base Classes for Modules
------------------------

Classes in the ``\Opencart\Extension\KaExtensions\System`` namespace
(``extension/ka_extensions/system/``):

- ``Controller``, ``Model`` - base classes with the ``onLoad()`` hook and the ``kadb`` database helper;
- ``TraitController`` - render helpers: ``disableRender()``, ``enableRender()``,
  ``getRenderData()``, ``getRenderTemplate()``, ``showPage()``, top messages;
- ``ControllerInstaller``, ``ControllerSettings`` - module installer and settings page
  (see :doc:`settings_page`);
- ``ControllerForm``, ``TraitControllerForm`` - forms built from a list of fields;
- ``Db``, ``QB``, ``Tables`` - database helpers (see :doc:`database`);
- ``Mail`` - emails from twig templates (see :doc:`mail`);
- ``KaGlobal``, ``Language``, ``Store``, ``Arrays``, ``Format``, ``Curl``, ``UrlParams``,
  ``Pagination`` - helpers.

The API reference of these classes is a part of this documentation.

Twig Functions
--------------

``t()``, ``has_t()``, ``linka()`` and other twig functions, and base page templates
for the admin area (see :doc:`twig_templates`).

Other Changes
-------------

The library also changes some standard behaviour through kamod:

- the *Ka Extensions* menu item and the ambulance icon with the number of kamod errors in the admin header;
- missing language files of extensions fall back to English (``en-gb``);
- email attachments can have their own file names;
- user permissions include routes of extension controllers.
