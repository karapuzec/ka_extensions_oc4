Overriding Other Modules
========================

A module can extend classes and templates of another extension in the same way as OpenCart files
(see :doc:`overriding_core_files`). Files of other extensions are in ``extension/<module>/``,
so the path in the ``kamod`` directory starts with ``extension/<module>/``:

::

    extension/mod1/admin/model/test.php
        -> extension/mod2/kamod/extension/mod1/admin/model/test.php

    extension/mod1/catalog/controller/label.php
        -> extension/mod2/kamod/extension/mod1/catalog/controller/label.php

    extension/ka_extensions/admin/view/template/common/pages/settings.twig
        -> extension/mod2/kamod/extension/ka_extensions/admin/view/template/common/pages/settings.twig

Example
-------

The module ``mod1`` has the model ``extension/mod1/admin/model/test.php``:

.. code-block:: php

    <?php
    namespace Opencart\Admin\Model\Extension\Mod1;

    class Test extends \Opencart\Extension\KaExtensions\System\Model {

        public function getTitle() {
            return 'Test1';
        }
    }

The module ``mod2`` changes it with ``extension/mod2/kamod/extension/mod1/admin/model/test.php``:

.. code-block:: php

    <?php
    namespace Opencart\Admin\Model\Extension\Mod2\Mod1;

    class Test extends \Opencart\Admin\Model\Extension\Mod1\Test {

        public function getTitle() {
            return parent::getTitle() . ' + Mod2';
        }
    }

After both modules are installed on the Ka Extensions page,
``$this->load->model('extension/mod1/test')`` returns an object whose ``getTitle()`` returns
``Test1 + Mod2``.

The namespace of the kamod class must be unique. Use the namespace of your module with the code of the
changed module, like ``Opencart\Admin\Model\Extension\Mod2\Mod1`` above.

Templates
---------

A template of another module is extended with a template at the same path. When the original
template has blocks, override them:

.. code-block:: twig

    {% block page_buttons %}
        <a href="{{ my_link }}" class="btn btn-light">My button</a>
        {{ parent() }}
    {% endblock %}

See :doc:`twig_templates` for the details.

Order of Modules
----------------

When several modules extend the same file, kamod puts their classes into a chain (see
:doc:`overriding_core_files`). The order of modules in the chain is not defined, do not rely on it.

Dependencies
------------

Kamod does not check that the changed module is installed. If ``mod1`` is removed, the files of
``mod2`` for it are skipped (the target file is not found, it is written to ``kamod.log``).
Check that the other module is installed before using its features, for example in the installer of
your module.
