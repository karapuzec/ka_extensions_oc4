Overriding Core Files
=====================

Kamod lets a module extend OpenCart classes by inheritance. The module does not change the
original files: kamod builds the resulting classes in its cache and loads them instead of the
original ones.

Almost any class file of OpenCart can be extended: controllers, models and libraries of the admin
area, the storefront and ``system/``. Files that run before kamod starts (``system/framework.php``,
``system/startup.php``, ``config.php``) cannot be extended.

The kamod Directory
-------------------

Files that extend OpenCart are placed in the ``kamod`` directory of the module and repeat the
path of the original file from the store root:

==================================================  ===============================================
Original file                                       File in the module
==================================================  ===============================================
``admin/controller/catalog/product.php``            ``extension/my_module/kamod/admin/controller/catalog/product.php``
``catalog/model/catalog/product.php``               ``extension/my_module/kamod/catalog/model/catalog/product.php``
``system/library/cart/cart.php``                    ``extension/my_module/kamod/system/library/cart/cart.php``
``catalog/view/template/common/footer.twig``        ``extension/my_module/kamod/catalog/view/template/common/footer.twig``
``extension/opencart/admin/...``                    ``extension/my_module/kamod/extension/opencart/admin/...``
==================================================  ===============================================

Always use ``admin`` in the path, even if the admin directory of the store is renamed.
Files of ``system/storage/`` (for example libraries in ``system/storage/vendor/``) are addressed
with the ``storage/`` prefix: ``kamod/storage/vendor/...``.

The kamod directory is used only when the module is installed on the Ka Extensions page
(``active.kamod`` exists, see :doc:`how_to_make_module`). After adding or removing files in ``kamod``
rebuild the kamod cache: define ``KAMOD_DEBUG`` (see :doc:`useful_constants`) or delete
``system/storage/cache.kamod/valid.kamod``.

Overriding Controllers
----------------------

To extend the storefront footer controller ``catalog/controller/common/footer.php``, create
``extension/my_module/kamod/catalog/controller/common/footer.php``:

.. code-block:: php

    <?php
    namespace Opencart\Catalog\Controller\Extension\MyModule\Common;

    class Footer extends \Opencart\Catalog\Controller\Common\Footer {

        use \Opencart\Extension\KaExtensions\System\TraitController;

        public function index(): string {

            // run the original method without rendering the template
            $this->disableRender();
            parent::index();
            $this->enableRender();

            $data     = $this->getRenderData();
            $template = $this->getRenderTemplate();

            // change the data passed to the template
            $data['my_module_text'] = $this->config->get('my_module_footer_text');

            return $this->load->view($template, $data);
        }
    }

Rules for kamod classes:

- The file contains one class. The class extends the original class with its full name.
- The namespace of the class must be unique. Use the namespace of the original class with
  ``Extension\<Module>`` inserted after the class type, like above
  (``Opencart\Catalog\Controller\Common`` - ``Opencart\Catalog\Controller\Extension\MyModule\Common``).
- Method signatures must be compatible with the original methods (OpenCart 4 uses return types,
  for example ``index(): string``).
- Call the parent method unless you really need to replace it completely, other modules may extend
  the same method (see :doc:`code_design_guidlines`).

``disableRender()`` makes ``$this->load->view()`` in the parent method return an empty string and
remember the template and its data. ``getRenderTemplate()`` and ``getRenderData()`` return them.
For controllers that call ``$this->response->setOutput()`` render the page and set the output yourself:

.. code-block:: php

    public function index(): void {
        $this->disableRender();
        parent::index();
        $this->enableRender();

        $data = $this->getRenderData();
        // ...
        $this->response->setOutput($this->load->view($this->getRenderTemplate(), $data));
    }

Overriding Models and Libraries
-------------------------------

Models and libraries are extended in the same way. For example,
``extension/my_module/kamod/admin/model/catalog/product.php``:

.. code-block:: php

    <?php
    namespace Opencart\Admin\Model\Extension\MyModule\Catalog;

    class Product extends \Opencart\Admin\Model\Catalog\Product {

        public function addProduct(array $data): int {

            $product_id = parent::addProduct($data);
            $this->saveMyFields($product_id, $data);

            return $product_id;
        }

        protected function saveMyFields(int $product_id, array $data): void {
            // ...
        }
    }

How Kamod Builds Classes
------------------------

When several modules extend the same file, kamod builds a chain in ``system/storage/cache.kamod/``.
For ``catalog/controller/common/footer.php`` changed by two modules it looks like this:

::

    opencart/catalog/controller/common/footer.2.kamod.php  - original class renamed to Footer_kamod
    opencart/catalog/controller/common/footer.1.kamod.php  - class of the 1st module, extends Footer_kamod
    opencart/catalog/controller/common/footer.0.kamod.php  - class of the 2nd module, extends the 1st one
    opencart/catalog/controller/common/footer.php          - class Footer extends the 2nd module class

The parent class written in a module file is replaced with the previous class of the chain, so the
methods of all modules are called when they call their parent methods.

Overriding Templates
--------------------

Twig templates are extended in the same way, for example
``extension/my_module/kamod/catalog/view/template/common/footer.twig``:

.. code-block:: twig

    {{ parent() }}
    <div class="my-module">{{ my_module_text }}</div>

The inheritance rules are described in :doc:`twig_templates`.

Patching Files
--------------

When a class cannot be extended (for example, a ``private`` property must be changed), use
:doc:`apply_kapatch`.
