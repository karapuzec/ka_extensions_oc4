OCMOD in OpenCart 4
===================

How OCMOD Works in OpenCart 4.1
-------------------------------

OCMOD modifications are XML files in the ``ocmod/`` directory of an extension. They are listed on the
*Extensions > Modifications* page after the extension is installed and applied with the **Refresh**
button.

OpenCart 4.1 does not change the original files. Modified copies are saved to
``extension/ocmod/`` (for example ``extension/ocmod/catalog/controller/common/header.php``), and
OpenCart loads them through its events for controllers, models, templates and languages.
Files that are not loaded this way, like ``system/framework.php``, are not used from
``extension/ocmod/`` by OpenCart itself.

The Ka Extensions Modification
------------------------------

Ka Extensions has one modification, *Ka Extensions Ocmod* (code ``ka_extensions``,
``extension/ka_extensions/ocmod/ka_extensions.ocmod.xml``). It changes:

- ``system/framework.php`` - starts kamod (``extension/ka_extensions/engine/startup.php``) right after
  the OpenCart autoloader is created;
- ``admin/controller/marketplace/modification.php`` - the *Modifications* page checks that
  ``system/framework.php`` loads the modified copy;
- ``admin/controller/user/user_permission.php`` - controllers of extensions (``extension/*/admin/controller/*.php``)
  are listed on the *User Permissions* page.

Since OpenCart does not load ``extension/ocmod/system/framework.php``, the *Modifications* page adds a
few lines to the beginning of the original ``system/framework.php`` once (a backup copy is saved):

.. code-block:: php

    //ka: required by kamod
    if (!defined('KAMOD_INCLUDED_FRAMEWORK') && file_exists(DIR_EXTENSION . '/ocmod/system/framework.php')) {
        define('KAMOD_INCLUDED_FRAMEWORK', true);
        ;	require_once(DIR_EXTENSION . '/ocmod/system/framework.php');
        return;
    }
    ///ka

That is why the *Modifications* page has to be opened after the modification is enabled
(see :doc:`how_to_install_ka_extensions`). These lines also make OCMOD changes of ``framework.php`` made by
other modifications work.

Kamod and OCMOD
---------------

- Kamod builds its classes and templates from the original files. Changes made by OCMOD to the same
  files are not included in the kamod cache. Do not change one file with OCMOD and kamod at the same time.
- Kamod starts in ``framework.php``, so OCMOD is the only way to change files loaded before it.
- In modules based on Ka Extensions use kamod classes and templates (see :doc:`overriding_core_files`)
  and kapatch (see :doc:`apply_kapatch`) instead of OCMOD. They do not depend on the *Refresh* button,
  can be combined with changes of other modules and are applied when the module is installed on the
  Ka Extensions page.
