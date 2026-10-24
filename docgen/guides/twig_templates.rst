Twig Templates
==============

Base Page Templates
-------------------

Ka Extensions provides base templates for admin pages
(``extension/ka_extensions/admin/view/template/common/pages/``):

- ``page.twig`` - a page with the header, the left column, buttons, breadcrumbs and top messages;
- ``settings.twig`` - the settings page of a module (see :doc:`settings_page`);
- ``form.twig`` - a form built from a list of fields.

In the admin area they are available with the ``@ka_common`` twig namespace. ``page.twig`` defines the
blocks ``page_buttons``, ``page_breadcrumbs``, ``top_messages`` and ``page_contents``:

.. code-block:: twig

    {% extends '@ka_common/pages/page.twig' %}

    {% block page_buttons %}
        <a href="{{ action_cancel }}" class="btn btn-light"><i class="fas fa-reply"></i></a>
    {% endblock %}

    {% block page_contents %}
        <p>My page</p>
    {% endblock %}

Show such a template from a controller based on ``\Opencart\Extension\KaExtensions\System\Controller``
with ``showPage()``. It loads the header, the left column and the footer itself:

.. code-block:: php

    $this->data['heading_title'] = 'My page';
    $this->showPage('extension/my_module/my_page');

Other twig namespaces:

- ``@ka_admin`` - ``admin/view/template/`` of the store (the real admin directory name is used);
- ``@ka_catalog`` - ``catalog/view/template/``.

Template Inheritance in Kamod
-----------------------------

When a module overrides a template (see :doc:`overriding_core_files`), kamod builds a chain of
templates where every template extends the previous one.

A template **without blocks** is wrapped in the ``parent_template`` block automatically.
Standard OpenCart 4 templates have no blocks, so to add HTML to such a template, call ``parent()``:

.. code-block:: twig

    {{ parent() }}
    <div class="my-module">my new html</div>

It is better to put your HTML into your own blocks, then other modules can change them too.
In that case define the ``parent_template`` block explicitly:

.. code-block:: twig

    {% block parent_template %}
        {{ parent() }}
        {% block my_module_html %}
            <div class="my-module">my new html</div>
        {% endblock %}
    {% endblock %}

When the original template has blocks (for example, it extends another template), override
its blocks and call ``parent()`` inside them.

A module can override a template that does not exist in the store. Such a template is created
in the kamod cache and can be included by other templates.

Templates changed by the administrator in *Design > Theme Editor* are also taken into account:
kamod builds the chain on top of the changed template for the store where it was changed.

Twig Functions
--------------

Ka Extensions adds the following functions:

``t('text')``
    Returns a language variable (or the text itself if the variable is not defined).
``has_t('text')``
    Returns ``true`` if the language variable is defined.
``linka(route, params)``
    Returns a link to an admin or storefront route. In the admin area ``user_token`` is added automatically.
``dir(path)``
    Returns the directory of a path with a trailing slash, e.g. ``dir(_self)``.
``html_entity_decode(text)``, ``get_language_image(language)``
    Helpers.

Use ``t()`` and Other Functions Only in Your Templates
------------------------------------------------------

These functions are defined by Ka Extensions. A template that uses them fails when Ka Extensions is not
installed or the store is in :doc:`safe_mode`. Do not add them to standard templates. Include a
template of your module conditionally instead, for example with a kamod template:

.. code-block:: twig

    {{ parent() }}
    {% if my_module_status %}
        {{ include('extension/my_module/catalog/view/template/common/footer_block.twig', ignore_missing = true) }}
    {% endif %}

where ``my_module_status`` is set by your kamod controller. Paths in ``include`` and ``extends`` are
relative to the store root (or use ``dir(_self)`` for a path relative to the current template).

Rendering Errors
----------------

Ka Extensions catches twig errors: a template with an error is rendered as an empty string.
To see the error message, define ``KAMOD_DEBUG`` (see :doc:`useful_constants`).
