Module Settings Page
====================

The settings page of a module is the ``admin/controller/extension.php`` controller of the module
(route ``extension/<module>/extension``). It extends
``\Opencart\Extension\KaExtensions\System\ControllerSettings``, which is also the installer of the module
(it extends ``ControllerInstaller``, see :doc:`how_to_make_module`):

.. code-block:: php

    <?php
    namespace Opencart\Admin\Controller\Extension\MyModule;

    class Extension extends \Opencart\Extension\KaExtensions\System\ControllerSettings {

        protected $ext_code = 'my_module';

        protected function onLoad() {
            $this->tables = array(); // database tables, see the Database page
            return parent::onLoad();
        }

        protected function getFields() {
            return array(
                'my_module_footer_text' => array(
                    'type'          => 'text',
                    'default_value' => 'Hello',
                ),
                'my_module_limit' => array(
                    'type'          => 'number',
                    'required'      => true,
                    'min_value'     => 1,
                    'default_value' => 10,
                ),
            );
        }
    }

Language file ``admin/language/en-gb/extension.php`` of the module:

.. code-block:: php

    <?php
    $_['txt_title_my_module_footer_text'] = 'Footer text';
    $_['txt_title_my_module_limit']       = 'Limit';
    $_['txt_tooltip_my_module_limit']     = 'Number of records';

The page shows the fields, validates them and saves them as store settings with the extension code
(``$this->config->get('my_module_limit')``). Default values are saved on installation, the settings are
deleted on uninstallation on the Ka Extensions page.

.. important::

    OpenCart 4 saves only settings whose keys start with the setting code. Field codes must start with
    the extension code, like ``my_module_limit`` above. Other fields are silently not saved.

Field Format
------------

``code``
    Setting key. When it is missing, the array key is used.
``type``
    Field template from ``extension/ka_extensions/admin/view/template/common/fields/``: ``text`` (default),
    ``textarea``, ``number``, ``select``, ``checkbox``, ``checkboxes``, ``radio``, ``date``, ``datetime``,
    ``time``, ``email``, ``tel``, ``password``, ``image``, ``file``, ``hidden``.
``default_value``
    Value saved on installation and shown when the setting is empty.
``options``
    Options of ``select``, ``radio``, ``checkboxes``.
``title``
    Field title. Without it the language variable ``txt_title_<code>`` is used.
``tooltip``
    Help text under the field. Without it the language variable ``txt_tooltip_<code>`` is used if it exists.
``placeholder``
    Placeholder of text fields.
``required``
    The value must not be empty (``0`` is treated as empty).
``min_value``, ``max_value``
    Limits for ``number`` fields.

Form Fields (``TraitControllerForm``)
-------------------------------------

The same field format is used by other forms through
``\Opencart\Extension\KaExtensions\System\TraitControllerForm`` (and the ``ControllerForm`` base class):

- ``getFieldsWithData($fields, $old_data, $new_data, $errors)`` - fills fields with values
  (posted data first, then old data, then ``default_value``);
- ``getFieldValues($fields)`` - returns ``code => value`` pairs for saving;
- ``validateFields($fields, $post)`` - checks the rules, errors are stored in ``$this->errors``.

Custom Template
---------------

To show your own template, override ``index()``:

.. code-block:: php

    public function index() {
        $this->disableRender();
        parent::index();
        $this->enableRender();

        $this->data['my_flag'] = 1;

        $this->showPage('extension/my_module/settings');
    }

``parent::index()`` prepares ``fields``, ``heading_title``, ``breadcrumbs``, ``action_save`` and
``action_cancel``. The template ``admin/view/template/settings.twig`` of the module extends the standard
settings page and replaces the ``settings_page_contents`` block. The form keeps the ``form-settings`` id,
it is used by the *Save* button:

.. code-block:: twig

    {% extends '@ka_common/pages/settings.twig' %}

    {% block settings_page_contents %}
        <form action="{{ action_save }}" method="post" id="form-settings" data-oc-toggle="ajax">
            {% for f in fields %}
                {% include '@ka_common/fields/' ~ (f.type ?: 'text') ~ '.twig' with {'field': f} %}
            {% endfor %}
            ...your html...
        </form>
    {% endblock %}

.. note::

    Blocks inside ``settings_page_contents`` (``contents``, ``settings_contents``) belong to an embedded
    card template and cannot be overridden from a child template.
