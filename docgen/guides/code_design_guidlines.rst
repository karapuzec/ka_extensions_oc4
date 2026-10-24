Code Design Guidelines
======================

1. Avoid Using ``final`` for Classes
------------------------------------

The ``final`` keyword prevents other developers from extending a class. Kamod and other modules
cannot change such a class by inheritance. Avoid ``final`` classes and methods.


2. Avoid Using ``private``
--------------------------

The same limitation applies to ``private`` properties and methods: child classes cannot use them.
Use ``protected`` instead if you need to hide internal implementation details while still allowing
inheritance.


3. Use QB (QueryBuilder) for Data Selection
-------------------------------------------

Direct SQL queries in modules may seem clear and simple, but they are difficult to extend.
If you build queries with ``QB``, other modules can change them (see :doc:`database`).

For example, a model returning a list of customers selected by your module:

.. code-block:: php

    use \Opencart\Extension\KaExtensions\System\QB;

    public function getCustomers(array $data): array {
        $qb = $this->getCustomersQB($data);
        return $qb->query()->rows;
    }

    protected function getCustomersQB(array $data): QB {
        $qb = new QB();
        $qb->select('c.*', 'customer', 'c');
        // ...
        return $qb;
    }

Other modules can change the result by extending ``getCustomersQB()`` and changing the ``QB`` object.


4. Avoid Direct File Inclusion
------------------------------

Do not include PHP files with ``require`` or ``include``. Let OpenCart and kamod load classes by their
namespaces (see :doc:`how_to_make_module`). Paths may change, and files included directly are not
processed by kamod, so other modules cannot extend them.


5. Always Call the Parent Method
--------------------------------

When overriding a method, call the parent method unless you are absolutely sure that the entire
behavior must be replaced.

Keep in mind that the same method may also be extended by other modules. Skipping the parent
call can lead to conflicts or broken functionality.


6. Apply Patches with Minimal Changes
-------------------------------------

In some cases, patching existing code is unavoidable. However, changes of original files should be
kept to a minimum.

Move all custom logic into separate methods or templates of your module, and insert only minimal
calls of them into the original code.

For twig templates, prefer extending them with kamod templates (see :doc:`twig_templates`) or
inserting an ``include`` of your template instead of changing large parts of the original template.

These practices help minimize conflicts with other modules and improve maintainability.


7. Use ``t()`` Only in Your Own Templates
-----------------------------------------

The ``t()`` twig function is defined by Ka Extensions. A standard template that calls it fails when
Ka Extensions is not installed. Put such code into a template of your module and include it into the
standard template under a condition (see :doc:`twig_templates`).


8. Prefix Settings with the Module Code
---------------------------------------

OpenCart 4 saves only settings whose keys start with the setting code. ``ControllerSettings`` uses the
extension code, so name settings fields ``<module>_...``, for example ``my_module_status``
(see :doc:`settings_page`).
